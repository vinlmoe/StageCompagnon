<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_stage;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/stage/locallib.php');

/**
 * Suppression en lot de stages par la DEVE (delete_entries.php).
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_bulk_delete_entries
 * @covers     ::stage_load_entries_by_id
 * @covers     ::stage_get_entry_creation_days
 * @covers     ::stage_get_filtered_entries
 * @covers     \mod_stage\event\entry_deleted
 */
final class bulk_delete_test extends \advanced_testcase {
    /**
     * Seuls les stages de l'activité sont supprimés, avec leurs plages et leur convention ; chacun
     * laisse un événement entry_deleted, et les autres stages restent intacts.
     */
    public function test_bulk_delete_removes_only_this_activity_entries(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $stage = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        $other = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        $cm = get_coursemodule_from_instance('stage', $stage->id);
        $context = \context_module::instance($cm->id);
        $student = $this->getDataGenerator()->create_user();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $theme = $generator->create_theme($stage);
        $othertheme = $generator->create_theme($other);

        $deleted = $generator->create_entry($stage, $student->id, $theme);
        $kept = $generator->create_entry($stage, $student->id, $theme, ['datestart' => make_timestamp(2026, 5, 1),
            'dateend' => make_timestamp(2026, 5, 10)]);
        $foreign = $generator->create_entry($other, $student->id, $othertheme);
        stage_set_entry_stagetype($deleted->id, 'complementaire');

        $sink = $this->redirectEvents();
        $count = stage_bulk_delete_entries($stage->id, $context, [$deleted->id, $foreign->id, $deleted->id]);
        $events = $sink->get_events();
        $sink->close();

        $this->assertSame(1, $count);
        $this->assertFalse($DB->record_exists('stage_entry', ['id' => $deleted->id]));
        $this->assertFalse($DB->record_exists('stage_entry_period', ['entryid' => $deleted->id]));
        $this->assertFalse($DB->record_exists('stage_convention_detail', ['entryid' => $deleted->id]));
        $this->assertTrue($DB->record_exists('stage_entry', ['id' => $kept->id]));
        $this->assertTrue($DB->record_exists('stage_entry_period', ['entryid' => $kept->id]));
        $this->assertTrue($DB->record_exists('stage_entry', ['id' => $foreign->id]));

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf(event\entry_deleted::class, $event);
        $this->assertSame('d', $event->crud);
        $this->assertSame((int) $deleted->id, (int) $event->objectid);
        $this->assertSame((int) $student->id, (int) $event->relateduserid);
        $this->assertSame((int) $theme->id, $event->other['themeid']);
        $this->assertStringContainsString('supprimé', $event->get_description());
        $this->assertSame(
            (new \moodle_url('/mod/stage/register.php', ['id' => $cm->id]))->out(false),
            $event->get_url()->out(false)
        );

        $this->assertSame(0, stage_bulk_delete_entries($stage->id, $context, []));
        $this->assertSame(0, stage_bulk_delete_entries($stage->id, $context, [$foreign->id]));
    }

    /**
     * Les stages d'un même jour d'enregistrement (un import) sont regroupés et retrouvés par le
     * filtre de date ; le chargement par identifiants écarte ceux d'une autre activité.
     */
    public function test_creation_day_filter_finds_one_import(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $stage = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        $other = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        $student = $this->getDataGenerator()->create_user();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $theme = $generator->create_theme($stage);
        $first = $generator->create_entry($stage, $student->id, $theme);
        $second = $generator->create_entry($stage, $student->id, $theme);
        $older = $generator->create_entry($stage, $student->id, $theme);
        $foreign = $generator->create_entry($other, $student->id, $generator->create_theme($other));
        $today = usergetmidnight(time());
        $DB->set_field('stage_entry', 'timecreated', $today - 3 * DAYSECS + HOURSECS, ['id' => $older->id]);

        $days = stage_get_entry_creation_days($stage->id);
        $this->assertSame(2, $days[$today]);
        $this->assertSame(1, $days[usergetmidnight($today - 3 * DAYSECS + HOURSECS)]);
        $this->assertSame($today, array_key_first($days));

        $filtered = stage_get_filtered_entries($stage->id, [
            'createdfrom' => $today,
            'createdto' => usergetmidnight($today + DAYSECS + HOURSECS),
        ]);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], array_keys($filtered));

        $loaded = stage_load_entries_by_id($stage->id, [$first->id, $foreign->id, 0]);
        $this->assertSame([(int) $first->id], array_map('intval', array_keys($loaded)));
    }
}
