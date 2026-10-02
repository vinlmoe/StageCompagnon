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

namespace mod_stagesynthesis;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/stagesynthesis/locallib.php');

/**
 * Liens vers les activités « Gestion des stages » : une activité en cours de suppression (corbeille)
 * n'est plus ni listée ni proposée.
 *
 * @package    mod_stagesynthesis
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stagesynthesis_get_links
 * @covers     ::stagesynthesis_get_available_stage_activities
 */
final class links_test extends \advanced_testcase {
    /**
     * Une activité liée puis mise à la corbeille disparaît des liens et des activités proposées.
     */
    public function test_activity_being_deleted_is_ignored(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $synthesis = $this->getDataGenerator()->create_module('stagesynthesis', ['course' => $course]);
        $kept = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        $deleted = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        stagesynthesis_set_links($synthesis->id, [$kept->cmid, $deleted->cmid]);
        $this->assertCount(2, stagesynthesis_get_links($synthesis->id));

        $DB->set_field('course_modules', 'deletioninprogress', 1, ['id' => $deleted->cmid]);

        $this->assertSame([(int) $kept->cmid], array_keys(stagesynthesis_get_links($synthesis->id)));
        $available = stagesynthesis_get_available_stage_activities($USER->id);
        $this->assertArrayHasKey((int) $kept->cmid, $available);
        $this->assertArrayNotHasKey((int) $deleted->cmid, $available);
    }
}
