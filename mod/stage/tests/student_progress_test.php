<?php
// This file is part of Moodle - http://moodle.org/
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_stage;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/stage/locallib.php');

/**
 * Tests du bilan global d'un étudiant (stage_get_student_progress()), en particulier de la
 * durée requise affichée pour une thématique définissant une durée globale unique
 * (stage_theme.requiredduration) plutôt qu'une durée par année.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_get_student_progress
 */
final class student_progress_test extends \advanced_testcase {

    /**
     * Une thématique bornée sur plusieurs années avec une durée globale (ex : 30 jours, quelle que
     * soit l'année) ne doit pas voir cette durée sommée une fois par année sur laquelle l'étudiant a
     * des saisies : le total requis doit rester 30, pas 30 * nombre d'années.
     */
    public function test_flat_duration_is_not_multiplied_by_number_of_years(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $stage = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        /** @var \mod_stage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $theme = $generator->create_theme($stage, [
            'name' => 'Thématique pluriannuelle', 'mandatory' => 1,
            'minstudyyear' => 2, 'maxstudyyear' => 4, 'requiredduration' => 30,
        ]);
        $student = $this->getDataGenerator()->create_user();

        foreach ([2, 3, 4] as $year) {
            $entry = $generator->create_entry($stage, $student->id, $theme,
                ['studyyear' => $year, 'declaredduration' => 10]);
            stage_apply_deve_validation($entry, 2, 10);
        }

        $progress = stage_get_student_progress($stage->id, $student->id);
        $themerow = $progress->themes[$theme->id];

        $this->assertSame(30, $themerow->retained);
        $this->assertSame(30, $themerow->requiredduration);
        $this->assertTrue($themerow->done);
    }
}
