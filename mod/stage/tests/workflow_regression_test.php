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
 * Régressions des invitations, questionnaires et dérogations DEVE.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_maybe_request_tutor_evaluation
 * @covers     ::stage_get_entries_needing_tutor_request
 * @covers     ::stage_resend_tutor_evaluation_request
 * @covers     ::stage_validate_answers
 * @covers     ::stage_apply_deve_validation
 */
final class workflow_regression_test extends \advanced_testcase {
    /**
     * Prépare une saisie dont l'évaluation du maître de stage est activée.
     *
     * @return array Activité, module et saisie.
     */
    private function prepare(): array {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $stage = $this->getDataGenerator()->create_module('stage', [
            'course' => $course, 'tutorevaluationenabled' => 1,
        ]);
        $cm = get_coursemodule_from_instance('stage', $stage->id);
        $student = $this->getDataGenerator()->create_user();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $theme = $generator->create_theme($stage, ['tutorevaluationenabled' => 1]);
        $entry = $generator->create_entry($stage, $student->id, $theme, [
            'datestart' => time() - DAYSECS, 'dateend' => time() + DAYSECS,
        ]);
        stage_save_convention_detail($entry->id, (object) [
            'tutorname' => 'Tutor Test', 'tutoremail' => 'tutor@example.com',
        ]);
        return [$stage, $cm, $entry];
    }

    /**
     * Un lien consulté par la DEVE ne supprime pas l'invitation automatique.
     */
    public function test_viewing_link_does_not_suppress_invitation(): void {
        [$stage, $cm, $entry] = $this->prepare();
        stage_get_tutor_eval_url($stage, $entry);
        $token = $entry->tutortoken;
        $this->assertArrayHasKey($entry->id, stage_get_entries_needing_tutor_request());
        $sink = $this->redirectEmails();
        $this->assertTrue(stage_maybe_request_tutor_evaluation($stage, $cm, $entry));
        $this->assertSame($token, $entry->tutortoken);
        $this->assertFalse(stage_maybe_request_tutor_evaluation($stage, $cm, $entry));
        $this->assertCount(1, $sink->get_messages());
        $this->assertArrayNotHasKey($entry->id, stage_get_entries_needing_tutor_request());
        $sink->close();
    }

    /**
     * Un échec laisse la saisie éligible, et une relance échouée ne rapporte pas de succès.
     */
    public function test_failed_dispatch_can_be_retried(): void {
        global $DB;
        [$stage, $cm, $entry] = $this->prepare();
        $studentid = $entry->userid;
        $entry->userid = 0;
        $DB->set_field('stage_entry', 'userid', 0, ['id' => $entry->id]);
        $this->assertFalse(stage_maybe_request_tutor_evaluation($stage, $cm, $entry));
        $this->assertFalse(stage_resend_tutor_evaluation_request($stage, $cm, $entry));
        $this->assertEquals(0, $DB->get_field('stage_entry', 'tutorrequesttime', ['id' => $entry->id]));
        $this->assertArrayHasKey($entry->id, stage_get_entries_needing_tutor_request());
        $token = $entry->tutortoken;
        $entry->userid = $studentid;
        $DB->set_field('stage_entry', 'userid', $studentid, ['id' => $entry->id]);
        $sink = $this->redirectEmails();
        $this->assertTrue(stage_maybe_request_tutor_evaluation($stage, $cm, $entry));
        $this->assertSame($token, $entry->tutortoken);
        $this->assertCount(1, $sink->get_messages());
        $sink->close();
    }

    /**
     * Un refus d'email_to_user ne marque pas l'invitation comme envoyée.
     */
    public function test_email_failure_is_reported_and_retried(): void {
        global $DB;
        [$stage, $cm, $entry] = $this->prepare();
        stage_save_convention_detail($entry->id, (object) ['tutoremail' => 'not-an-email']);
        $sink = $this->redirectEmails();
        $this->assertFalse(stage_maybe_request_tutor_evaluation($stage, $cm, $entry));
        $this->assertDebuggingCalled();
        $this->assertFalse(stage_resend_tutor_evaluation_request($stage, $cm, $entry));
        $this->assertDebuggingCalled();
        $this->assertEquals(0, $DB->get_field('stage_entry', 'tutorrequesttime', ['id' => $entry->id]));
        $this->assertCount(0, $sink->get_messages());
        stage_save_convention_detail($entry->id, (object) ['tutoremail' => 'tutor@example.com']);
        $this->assertTrue(stage_maybe_request_tutor_evaluation($stage, $cm, $entry));
        $this->assertCount(1, $sink->get_messages());
        $sink->close();
    }

    /**
     * Une évaluation déjà soumise sans invitation (ou restaurée) ne déclenche pas d'envoi.
     */
    public function test_completed_evaluation_is_not_invited(): void {
        global $DB;
        [$stage, $cm, $entry] = $this->prepare();
        $entry->tutortime = time();
        $DB->set_field('stage_entry', 'tutortime', $entry->tutortime, ['id' => $entry->id]);
        $this->assertFalse(stage_maybe_request_tutor_evaluation($stage, $cm, $entry));
        $this->assertArrayNotHasKey($entry->id, stage_get_entries_needing_tutor_request());
    }

    /**
     * La DEVE garde le droit de valider sans enseignant, maître de stage ou dispense.
     */
    public function test_deve_can_validate_without_either_evaluator(): void {
        global $DB;
        [$stage, $cm, $entry] = $this->prepare();
        $deve = $this->getDataGenerator()->create_user();
        stage_apply_deve_validation($entry, $deve->id, 7);
        $saved = $DB->get_record('stage_entry', ['id' => $entry->id]);
        $this->assertEquals(STAGE_STATUS_VALIDE_DEVE, $saved->status);
        $this->assertEquals(7, $saved->retainedduration);
        $this->assertEmpty($saved->teachertime);
        $this->assertEmpty($saved->tutortime);
        $this->assertEmpty($saved->tutorbypassed);
    }

    /**
     * Une ancienne page ouverte ne permet pas de valider un stage annulé entretemps.
     */
    public function test_stale_entry_cannot_validate_cancelled_internship(): void {
        global $DB;
        [$stage, $cm, $entry] = $this->prepare();
        $DB->set_field('stage_entry', 'status', STAGE_STATUS_ANNULE, ['id' => $entry->id]);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('errorvalidatecancelled', 'mod_stage'));
        stage_apply_deve_validation($entry, 2, 7);
    }

    /**
     * Les champs absents d'une requête POST ne sont pas des réponses valides.
     */
    public function test_required_answer_cannot_be_omitted(): void {
        $question = (object) ['id' => 1, 'name' => 'Bilan', 'required' => 1, 'qtype' => 'text'];
        $this->expectException(\moodle_exception::class);
        stage_validate_answers([$question], []);
    }

    /**
     * Les valeurs de QCM sont vérifiées dans la langue du formulaire.
     */
    public function test_translated_choices_are_validated(): void {
        $question = (object) [
            'id' => 1, 'name' => 'Bilan', 'required' => 1, 'qtype' => 'choice',
            'options' => "Oui\nNon", 'optionsen' => "Yes\nNo",
        ];
        stage_validate_answers([$question], [1 => 'Yes'], 'en');
        $this->expectException(\moodle_exception::class);
        stage_validate_answers([$question], [1 => 'Forged answer'], 'en');
    }
}
