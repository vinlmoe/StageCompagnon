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
require_once($CFG->dirroot . '/mod/stage/locallib.php');

/**
 * Périmètre du suivi multi-promotions : une activité liée n'y entre que si l'enseignant peut
 * toujours y accéder à la source, pas seulement s'il en garde la capacité.
 *
 * @package    mod_stagesynthesis
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stagesynthesis_get_active_links
 */
final class active_links_test extends \advanced_testcase {
    /**
     * Une inscription suspendue garde ses rôles, donc la capacité : l'activité sort pourtant du
     * périmètre, comme elle serait refusée à la source.
     */
    public function test_suspended_enrolment_leaves_the_scope(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $synthesiscourse = $gen->create_course();
        $stage = $gen->create_module('stage', ['course' => $course]);
        $synthesis = $gen->create_module('stagesynthesis', ['course' => $synthesiscourse]);
        $teacher = $gen->create_user();
        $student = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'teacher');
        $gen->enrol_user($student->id, $course->id, 'student');
        $gen->get_plugin_generator('mod_stage')->assign_teacher($stage, $student->id, $teacher->id);
        stagesynthesis_set_links($synthesis->id, [$stage->cmid]);

        $this->assertArrayHasKey((int) $stage->cmid, stagesynthesis_get_active_links($synthesis->id, $teacher->id));

        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        enrol_get_plugin('manual')->update_user_enrol($instance, $teacher->id, ENROL_USER_SUSPENDED);
        \course_modinfo::clear_instance_cache($course);

        $this->assertArrayNotHasKey((int) $stage->cmid, stagesynthesis_get_active_links($synthesis->id, $teacher->id));
    }

    /**
     * Une activité visible mais fermée à l'enseignant par une restriction d'accès (date à venir,
     * sans le droit d'ignorer les restrictions) sort de son périmètre : c'est la visibilité pour
     * l'utilisateur qui compte, comme à la source, pas seulement celle de l'activité.
     */
    public function test_activity_restricted_for_user_leaves_the_scope(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enableavailability', 1);
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $stage = $gen->create_module('stage', ['course' => $course]);
        $synthesis = $gen->create_module('stagesynthesis', ['course' => $gen->create_course()]);
        $teacher = $gen->create_user();
        $student = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'teacher');
        $gen->enrol_user($student->id, $course->id, 'student');
        $gen->get_plugin_generator('mod_stage')->assign_teacher($stage, $student->id, $teacher->id);
        stagesynthesis_set_links($synthesis->id, [$stage->cmid]);
        $this->assertArrayHasKey((int) $stage->cmid, stagesynthesis_get_active_links($synthesis->id, $teacher->id));

        $teacherrole = $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        $coursecontext = \context_course::instance($course->id);
        assign_capability('moodle/course:ignoreavailabilityrestrictions', CAP_PROHIBIT, $teacherrole, $coursecontext->id);
        assign_capability('moodle/course:viewhiddenactivities', CAP_PROHIBIT, $teacherrole, $coursecontext->id);
        $DB->set_field('course_modules', 'availability', json_encode([
            'op' => '&', 'c' => [['type' => 'date', 'd' => '>=', 't' => time() + WEEKSECS]], 'showc' => [true],
        ]), ['id' => $stage->cmid]);
        rebuild_course_cache($course->id, true);
        accesslib_clear_all_caches_for_unit_testing();

        $this->assertSame([], stagesynthesis_get_active_links($synthesis->id, $teacher->id));
    }
}
