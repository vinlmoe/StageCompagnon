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
 * Tests de stage_convention_paper_requested_info() : le message rappelé à la DEVE lors de sa
 * revue (convention_review.php) dépend de qui, entre l'étudiant (case cochée lors de la demande,
 * convention_request.php) et l'enseignant référent (case cochée lors de sa validation,
 * convention_teacher_validate.php), a demandé une convention papier.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_convention_paper_requested_info
 */
final class convention_paper_request_test extends \advanced_testcase {

    public function test_no_request_returns_null(): void {
        $detail = (object) ['paperrequestedbystudent' => 0, 'paperrequestedbyteacher' => 0];
        $this->assertNull(stage_convention_paper_requested_info($detail));
        $this->assertNull(stage_convention_paper_requested_info(false));
    }

    public function test_student_only(): void {
        $detail = (object) ['paperrequestedbystudent' => 1, 'paperrequestedbyteacher' => 0];
        $this->assertSame(get_string('conventionpaperrequestedbystudentonly', 'mod_stage'),
            stage_convention_paper_requested_info($detail));
    }

    public function test_teacher_only(): void {
        $detail = (object) ['paperrequestedbystudent' => 0, 'paperrequestedbyteacher' => 1];
        $this->assertSame(get_string('conventionpaperrequestedbyteacheronly', 'mod_stage'),
            stage_convention_paper_requested_info($detail));
    }

    public function test_both(): void {
        $detail = (object) ['paperrequestedbystudent' => 1, 'paperrequestedbyteacher' => 1];
        $this->assertSame(get_string('conventionpaperrequestedbyboth', 'mod_stage'),
            stage_convention_paper_requested_info($detail));
    }
}
