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

/**
 * Événement de consultation de la synthèse.
 *
 * @package    mod_stagesynthesis
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_stagesynthesis\event\course_module_viewed
 */
final class events_test extends \advanced_testcase {
    /**
     * La consultation est l'événement standard de Moodle, rattaché à l'instance de synthèse.
     */
    public function test_course_module_viewed_event(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $synthesis = $this->getDataGenerator()->create_module('stagesynthesis', ['course' => $course]);
        $cm = get_coursemodule_from_instance('stagesynthesis', $synthesis->id);

        $sink = $this->redirectEvents();
        $event = event\course_module_viewed::create([
            'objectid' => $synthesis->id,
            'context' => \context_module::instance($cm->id),
        ]);
        $event->trigger();
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(\core\event\course_module_viewed::class, $events[0]);
        $this->assertSame('stagesynthesis', $events[0]->objecttable);
        $this->assertSame((int) $synthesis->id, (int) $events[0]->objectid);
        $this->assertSame((int) $cm->id, (int) $events[0]->contextinstanceid);
        $this->assertNotEmpty($events[0]->get_name());
    }
}
