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
 * Tests de la liste « Stages à évaluer » de l'enseignant référent (teacher.php) : par défaut
 * (aucun filtre de statut choisi), seuls les stages effectivement en attente d'évaluation
 * (STAGE_STATUS_EVAL_ETUDIANT) doivent apparaître, à l'exclusion de ceux déjà évalués, pas encore
 * auto-évalués par l'étudiant, ou rejetés/annulés.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_get_filtered_entries
 */
final class teacher_list_test extends \advanced_testcase {

    /**
     * Le filtre par défaut de teacher.php (status = STAGE_STATUS_EVAL_ETUDIANT) ne retient que les
     * saisies effectivement en attente d'évaluation, à l'exclusion des autres statuts.
     */
    public function test_default_filter_excludes_already_evaluated_entries(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $stage = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        /** @var \mod_stage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $theme = $generator->create_theme($stage);
        $student = $this->getDataGenerator()->create_user();

        $pending = $generator->create_entry($stage, $student->id, $theme);
        stage_apply_student_eval($pending, 'auto-évaluation');

        $notstarted = $generator->create_entry($stage, $student->id, $theme);

        $alreadyevaluated = $generator->create_entry($stage, $student->id, $theme);
        stage_apply_student_eval($alreadyevaluated, 'auto-évaluation');
        stage_apply_teacher_eval($alreadyevaluated, 2, 'ok');

        $rejected = $generator->create_entry($stage, $student->id, $theme);
        stage_apply_student_eval($rejected, 'auto-évaluation');
        stage_reject_by_teacher($rejected, 2, 'motif');

        $entries = stage_get_filtered_entries($stage->id, ['status' => STAGE_STATUS_EVAL_ETUDIANT]);
        $ids = array_map(function($e) {
            return (int) $e->id;
        }, $entries);

        $this->assertContains((int) $pending->id, $ids);
        $this->assertNotContains((int) $notstarted->id, $ids);
        $this->assertNotContains((int) $alreadyevaluated->id, $ids);
        $this->assertNotContains((int) $rejected->id, $ids);
    }
}
