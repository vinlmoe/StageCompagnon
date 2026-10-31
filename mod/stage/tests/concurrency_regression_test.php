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

use mod_stage\local\global_export_importer;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

/**
 * Régressions relevées par le test de concurrence (docs/audit/test-de-concurrence.tex) : une page
 * lit le stage, un autre intervenant le modifie, puis la page écrit. Chaque test rejoue cet
 * entrelacement avec une copie périmée de la saisie, comme la page la détient.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_update_entry_if
 * @covers     ::stage_update_entry_if_convention
 * @covers     ::stage_run_entry_transition
 * @covers     ::stage_apply_student_eval
 * @covers     ::stage_apply_teacher_eval
 * @covers     ::stage_reject_by_teacher
 * @covers     ::stage_cancel_entry
 * @covers     ::stage_apply_tutor_eval
 * @covers     ::stage_submit_tutor_eval
 * @covers     ::stage_teacher_validate_convention
 * @covers     ::stage_reject_convention
 * @covers     ::stage_convention_mark_edited
 * @covers     ::stage_convention_mark_signed
 * @covers     ::stage_get_teacher_pending_convention_entries
 * @covers     ::stage_convention_entries_where
 * @covers     ::stage_set_entry_workdays
 * @covers     \mod_stage\local\global_export_importer::restore
 * @covers     \mod_stage\local\csv_importer::apply_evaluations
 */
final class concurrency_regression_test extends \advanced_testcase {
    /**
     * Activité, étudiant, enseignant référent, thématique et stage commencé.
     *
     * @return array [stdClass $stage, stdClass $student, stdClass $teacher, stdClass $theme, stdClass $entry]
     */
    private function fixture(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $stage = $gen->create_module('stage', ['course' => $course, 'tutorevaluationenabled' => 1]);
        $student = $gen->create_user();
        $teacher = $gen->create_user();
        $gen->enrol_user($student->id, $course->id, 'student');
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        $sg = $gen->get_plugin_generator('mod_stage');
        $theme = $sg->create_theme($stage, ['name' => 'Clinique', 'tutorevaluationenabled' => 1]);
        $sg->assign_teacher($stage, $student->id, $teacher->id);
        $entry = $sg->create_entry($stage, $student->id, $theme, ['conventionstatus' => STAGE_CONVENTION_SIGNVET]);
        return [$stage, $student, $teacher, $theme, $entry];
    }

    /**
     * Statut enregistré d'une saisie.
     *
     * @param \stdClass $entry
     * @return int
     */
    private function status(\stdClass $entry): int {
        global $DB;
        return (int) $DB->get_field('stage_entry', 'status', ['id' => $entry->id], MUST_EXIST);
    }

    /**
     * L'avis de l'enseignant, préparé sur un stage « évalué par l'étudiant », n'efface pas la
     * validation que la DEVE a enregistrée entre-temps (stage 142343 du test).
     */
    public function test_teacher_eval_does_not_undo_deve_validation(): void {
        [, , $teacher, , $entry] = $this->fixture();
        stage_apply_student_eval($entry, 'Bilan');
        $stale = clone $entry;

        stage_apply_deve_validation(clone $entry, get_admin()->id, 8, 'Validé');
        $this->assertFalse(stage_apply_teacher_eval($stale, $teacher->id, 'Avis', [STAGE_STATUS_EVAL_ETUDIANT]));
        $this->assertSame(STAGE_STATUS_VALIDE_DEVE, $this->status($entry));

        $this->assertFalse(stage_reject_by_teacher(clone $stale, $teacher->id, 'Non', [STAGE_STATUS_EVAL_ETUDIANT]));
        $this->assertSame(STAGE_STATUS_VALIDE_DEVE, $this->status($entry));
    }

    /**
     * L'auto-évaluation, préparée sur un stage « enregistré », ne fait pas sortir de l'annulation un
     * stage annulé entre-temps (stage 142064 du test) ; sur un stage inchangé, elle s'applique.
     */
    public function test_student_eval_does_not_resurrect_cancelled_entry(): void {
        global $DB;
        [, , , , $entry] = $this->fixture();
        $stale = clone $entry;

        $this->assertTrue(stage_cancel_entry(clone $entry, get_admin()->id, 'Abandon'));
        $this->assertFalse(stage_apply_student_eval($stale, 'Bilan', [STAGE_STATUS_ENREGISTRE]));
        $this->assertSame(STAGE_STATUS_ANNULE, $this->status($entry));
        $this->assertEmpty($DB->get_field('stage_entry', 'studentselfeval', ['id' => $entry->id]));

        stage_reset_entry($DB->get_record('stage_entry', ['id' => $entry->id]));
        $fresh = $DB->get_record('stage_entry', ['id' => $entry->id]);
        $this->assertTrue(stage_apply_student_eval($fresh, 'Bilan', [STAGE_STATUS_ENREGISTRE]));
        $this->assertSame(STAGE_STATUS_EVAL_ETUDIANT, $this->status($entry));
        $this->assertSame('Bilan', $DB->get_field('stage_entry', 'studentselfeval', ['id' => $entry->id]));
    }

    /**
     * Deux annulations simultanées : la seconde n'écrase ni l'auteur ni le motif de la première.
     */
    public function test_second_cancellation_keeps_first(): void {
        global $DB;
        [, , $teacher, , $entry] = $this->fixture();
        $stale = clone $entry;

        $this->assertTrue(stage_cancel_entry(clone $entry, get_admin()->id, 'Premier motif'));
        $this->assertFalse(stage_cancel_entry($stale, $teacher->id, 'Second motif'));
        $saved = $DB->get_record('stage_entry', ['id' => $entry->id]);
        $this->assertEquals(get_admin()->id, $saved->cancelledby);
        $this->assertSame('Premier motif', $saved->cancelcomment);
    }

    /**
     * Le maître de stage ne peut plus évaluer un stage annulé depuis l'ouverture du formulaire, ni
     * évaluer deux fois (stages 142092, 142305 et 142438 du test) ; ses réponses ne sont pas
     * enregistrées non plus.
     */
    public function test_tutor_eval_refused_on_cancelled_or_already_evaluated(): void {
        global $DB;
        [$stage, , , $theme, $entry] = $this->fixture();

        $this->assertTrue(stage_apply_tutor_eval($entry, 'Premier avis'));
        $this->assertFalse(stage_apply_tutor_eval($entry, 'Second avis'));
        $this->assertSame('Premier avis', $DB->get_field('stage_entry', 'tutoreval', ['id' => $entry->id]));

        $other = $this->getDataGenerator()->get_plugin_generator('mod_stage')->create_entry($stage, $entry->userid, $theme, [
            'datestart' => make_timestamp(2026, 6, 1), 'dateend' => make_timestamp(2026, 6, 5),
        ]);
        $questionid = $DB->insert_record('stage_question', (object) [
            'stageid' => $stage->id, 'themeid' => 0, 'evaltype' => 'tutor', 'qtype' => 'text', 'name' => 'Bilan',
            'required' => 1, 'sortorder' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $question = $DB->get_record('stage_question', ['id' => $questionid]);
        $stale = clone $other;
        stage_cancel_entry(clone $other, get_admin()->id, 'Abandon');

        $error = stage_submit_tutor_eval($stale, [$questionid => $question], [$questionid => 'Bien'], null, 'fr');
        $this->assertSame(get_string_manager()->get_string('tutorevalinvalidtoken', 'mod_stage', null, 'fr'), $error);
        $this->assertFalse($DB->record_exists('stage_answer', ['entryid' => $other->id]));
        $this->assertEmpty($DB->get_field('stage_entry', 'tutortime', ['id' => $other->id]));
    }

    /**
     * Une étape refusée n'écrit rien de ce qui l'accompagne (réponses au questionnaire), y compris
     * dans une transaction englobante, qui peut ensuite être validée ; une étape appliquée écrit le
     * tout.
     */
    public function test_refused_step_writes_nothing_else(): void {
        global $DB;
        [$stage, , , , $entry] = $this->fixture();
        $questionid = $DB->insert_record('stage_question', (object) [
            'stageid' => $stage->id, 'themeid' => 0, 'evaltype' => 'student', 'qtype' => 'text', 'name' => 'Bilan',
            'required' => 1, 'sortorder' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $questions = [$questionid => $DB->get_record('stage_question', ['id' => $questionid])];
        $write = function (\stdClass $copy) use ($questions, $questionid) {
            return function () use ($copy, $questions, $questionid) {
                if (!stage_apply_student_eval($copy, null, [STAGE_STATUS_ENREGISTRE])) {
                    return false;
                }
                stage_save_answers($copy->id, $questions, [$questionid => 'Bien']);
                return true;
            };
        };
        $stale = clone $entry;
        stage_cancel_entry(clone $entry, get_admin()->id, 'Abandon');

        $outer = $DB->start_delegated_transaction();
        $this->assertFalse(stage_run_entry_transition($write($stale)));
        $outer->allow_commit();
        $this->assertFalse($DB->record_exists('stage_answer', ['entryid' => $entry->id]));
        $this->assertSame(STAGE_STATUS_ANNULE, $this->status($entry));

        stage_reset_entry($DB->get_record('stage_entry', ['id' => $entry->id]));
        $this->assertTrue(stage_run_entry_transition($write($DB->get_record('stage_entry', ['id' => $entry->id]))));
        $this->assertSame('Bien', $DB->get_field('stage_answer', 'answertext', ['entryid' => $entry->id]));
        $this->assertSame(STAGE_STATUS_EVAL_ETUDIANT, $this->status($entry));
    }

    /**
     * Deux enseignants référents décident en même temps de la même demande de convention : la
     * seconde décision n'est pas appliquée (21 demandes sur 148 dans le test).
     */
    public function test_two_referents_cannot_both_decide(): void {
        global $DB;
        [$stage, $student, $teacher, $theme] = $this->fixture();
        $other = $this->getDataGenerator()->create_user();
        $template = $this->getDataGenerator()->get_plugin_generator('mod_stage')->create_convention_template($stage);
        $entry = $this->getDataGenerator()->get_plugin_generator('mod_stage')->create_entry($stage, $student->id, $theme, [
            'datestart' => make_timestamp(2026, 12, 1), 'dateend' => make_timestamp(2026, 12, 10),
        ]);
        stage_request_convention($entry, $template->id, true);
        $first = clone $entry;
        $second = clone $entry;
        $from = [STAGE_CONVENTION_TEACHERPENDING];

        $this->assertTrue(stage_reject_convention($first, $teacher->id, 'Incomplet', $from));
        $this->assertFalse(stage_teacher_validate_convention($second, $other->id, $from));
        $saved = $DB->get_record('stage_entry', ['id' => $entry->id]);
        $this->assertEquals(STAGE_CONVENTION_REJECTED, $saved->conventionstatus);
        $this->assertEmpty($saved->conventionteachervalidatedby);
    }

    /**
     * Un stage annulé n'avance plus dans le circuit de sa convention et sort des files d'attente
     * de l'enseignant et de la DEVE (stage 142602 du test).
     */
    public function test_cancelled_entry_leaves_convention_circuit(): void {
        [$stage, $student, $teacher, $theme] = $this->fixture();
        $sg = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $template = $sg->create_convention_template($stage);
        $pending = $sg->create_entry($stage, $student->id, $theme, [
            'datestart' => make_timestamp(2026, 12, 1), 'dateend' => make_timestamp(2026, 12, 10),
        ]);
        stage_request_convention($pending, $template->id, true);
        $requested = $sg->create_entry($stage, $student->id, $theme, [
            'datestart' => make_timestamp(2027, 1, 4), 'dateend' => make_timestamp(2027, 1, 8),
        ]);
        stage_request_convention($requested, $template->id, false);
        $this->assertArrayHasKey($pending->id, stage_get_teacher_pending_convention_entries($stage->id, $teacher->id));
        $this->assertArrayHasKey($requested->id, stage_get_convention_entries($stage->id, '', 'student', 'ASC', 0, 0));

        stage_cancel_entry(clone $pending, get_admin()->id, 'Abandon');
        stage_cancel_entry(clone $requested, get_admin()->id, 'Abandon');
        $this->assertFalse(stage_teacher_validate_convention($pending, $teacher->id, [STAGE_CONVENTION_TEACHERPENDING]));
        $this->assertFalse(stage_convention_mark_edited($requested, get_admin()->id, [STAGE_CONVENTION_REQUESTED]));
        $this->assertFalse(stage_convention_mark_signed($requested, get_admin()->id, [STAGE_CONVENTION_REQUESTED]));
        $this->assertArrayNotHasKey($pending->id, stage_get_teacher_pending_convention_entries($stage->id, $teacher->id));
        $this->assertArrayNotHasKey($requested->id, stage_get_convention_entries($stage->id, '', 'student', 'ASC', 0, 0));
    }

    /**
     * Jours effectifs enregistrés deux fois de suite pour la même saisie : la seconde liste remplace
     * la première, sans doublon.
     */
    public function test_workdays_replaced_without_duplicates(): void {
        global $DB;
        [, , , , $entry] = $this->fixture();
        $day = make_timestamp(2026, 3, 2);
        stage_set_entry_workdays($entry->id, [$day, $day + DAYSECS]);
        stage_set_entry_workdays($entry->id, [$day + DAYSECS, $day + 2 * DAYSECS, $day + 2 * DAYSECS]);
        $saved = $DB->get_fieldset_select('stage_entry_workday', 'workdate', 'entryid = ? ORDER BY workdate', [$entry->id]);
        $this->assertEquals([$day + DAYSECS, $day + 2 * DAYSECS], array_map('intval', $saved));
    }

    /**
     * Deux confirmations de la même restauration d'export global : la seconde ne recrée pas les
     * stages, devenus des doublons depuis sa prévisualisation (300 doublons dans le test). Un stage
     * signalé comme doublon probable et coché par la DEVE est, lui, toujours restauré.
     */
    public function test_global_restore_twice_does_not_duplicate(): void {
        global $DB;
        [$stage, $student, , $theme] = $this->fixture();
        $entry = (array) $DB->get_record('stage_entry', ['stageid' => $stage->id]);
        $DB->delete_records('stage_entry_period', ['entryid' => $entry['id']]);
        $DB->delete_records('stage_entry', ['id' => $entry['id']]);
        unset($entry['id']);
        $records = [['entry' => $entry, 'detail' => [], 'periods' => [], 'probableduplicates' => []]];

        $skipped = null;
        $this->assertSame(1, global_export_importer::restore($stage->id, $records, $skipped));
        $this->assertSame(0, $skipped);
        $this->assertSame(0, global_export_importer::restore($stage->id, $records, $skipped));
        $this->assertSame(1, $skipped);
        $this->assertSame(1, $DB->count_records('stage_entry', ['stageid' => $stage->id]));

        $forced = [['entry' => $entry, 'detail' => [], 'periods' => [], 'probableduplicates' => ['Clinique']]];
        $this->assertSame(1, global_export_importer::restore($stage->id, $forced, $skipped));
        $this->assertSame(2, $DB->count_records('stage_entry', ['stageid' => $stage->id]));
    }

    /**
     * Un import StageVet qui reporte l'auto-évaluation sur un stage lu « enregistré » ne le fait pas
     * repasser à « évalué par l'étudiant » si la DEVE l'a validé entre-temps ; l'évaluation, elle,
     * est enregistrée.
     */
    public function test_stagevet_evaluation_keeps_concurrent_validation(): void {
        global $DB;
        [, , , , $entry] = $this->fixture();
        $stale = $DB->get_record('stage_entry', ['id' => $entry->id]);
        stage_apply_deve_validation(clone $entry, get_admin()->id, 8, 'Validé');

        $result = \mod_stage\local\csv_importer::apply_evaluations($stale, 'Bilan StageVet', '');
        $this->assertSame(1, $result['changed']);
        $saved = $DB->get_record('stage_entry', ['id' => $entry->id]);
        $this->assertEquals(STAGE_STATUS_VALIDE_DEVE, $saved->status);
        $this->assertSame('Bilan StageVet', $saved->studentselfeval);
    }
}
