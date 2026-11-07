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
 * Questionnaire de demande de stage, SIRET et validation de la demande de convention par le
 * référent et/ou le responsable de la thématique.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_convention_required_validators
 * @covers     ::stage_convention_missing_validations
 * @covers     ::stage_convention_user_validations
 * @covers     ::stage_validate_convention_request
 * @covers     ::stage_get_teacher_pending_convention_entries
 * @covers     ::stage_siret_valid
 * @covers     ::stage_normalize_siret
 * @covers     ::stage_validate_request_answers
 * @covers     ::stage_extract_request_answers
 * @covers     ::stage_save_request_answers
 * @covers     ::stage_render_request_answers
 * @covers     ::stage_render_convention_validation_status
 * @covers     ::stage_release_pending_conventions
 * @covers     ::stage_get_active_theme_teachers
 */
final class request_validation_test extends \advanced_testcase {
    /**
     * Activité dont les demandes de convention sont validées selon $validator, thématique avec un
     * responsable (teacher2), étudiant attribué au référent teacher1, et une demande en attente.
     *
     * @param string $validator
     * @param bool $withthemeteacher
     * @return array [stdClass $stage, \context_module $context, stdClass $student, stdClass $referent,
     *               stdClass $themeteacher, stdClass $theme, stdClass $entry, \mod_stage_generator $generator]
     */
    private function fixture(string $validator, bool $withthemeteacher = true): array {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $stage = $gen->create_module('stage', [
            'course' => $course, 'conventionrequireteachervalidation' => 1, 'conventionvalidator' => $validator,
        ]);
        $stage = $DB->get_record('stage', ['id' => $stage->id]);
        $context = \context_module::instance(get_coursemodule_from_instance('stage', $stage->id)->id);
        $student = $gen->create_user();
        $referent = $gen->create_user();
        $themeteacher = $gen->create_user();
        $gen->enrol_user($student->id, $course->id, 'student');
        $gen->enrol_user($referent->id, $course->id, 'teacher');
        $gen->enrol_user($themeteacher->id, $course->id, 'teacher');
        /** @var \mod_stage_generator $generator */
        $generator = $gen->get_plugin_generator('mod_stage');
        $theme = $generator->create_theme($stage, ['name' => 'Clinique']);
        if ($withthemeteacher) {
            stage_set_theme_teachers($theme->id, [$themeteacher->id]);
        }
        $generator->assign_teacher($stage, $student->id, $referent->id);
        $entry = $generator->create_entry($stage, $student->id, $theme);
        stage_request_convention($entry, $generator->create_convention_template($stage)->id, true);
        $entry = $DB->get_record('stage_entry', ['id' => $entry->id]);
        return [$stage, $context, $student, $referent, $themeteacher, $theme, $entry, $generator];
    }

    /**
     * Le paramètre de l'activité fixe les validations exigées ; sans responsable désigné, c'est le
     * référent qui valide.
     */
    public function test_required_validators(): void {
        [$stage, , , , , , $entry] = $this->fixture(STAGE_CONVENTION_VALIDATOR_BOTH);
        $this->assertSame(['referent', 'themeteacher'], stage_convention_required_validators($stage, $entry));
        $stage->conventionvalidator = STAGE_CONVENTION_VALIDATOR_THEMETEACHER;
        $this->assertSame(['themeteacher'], stage_convention_required_validators($stage, $entry));
        $stage->conventionvalidator = STAGE_CONVENTION_VALIDATOR_REFERENT;
        $this->assertSame(['referent'], stage_convention_required_validators($stage, $entry));
        unset($stage->conventionvalidator);
        $this->assertSame(['referent'], stage_convention_required_validators($stage, $entry));
    }

    /**
     * Thématique sans responsable : la demande ne reste pas bloquée, le référent la valide.
     */
    public function test_theme_without_teacher_falls_back_to_referent(): void {
        [$stage, $context, , $referent, $themeteacher, , $entry] = $this->fixture(
            STAGE_CONVENTION_VALIDATOR_THEMETEACHER,
            false
        );
        $this->assertSame(['referent'], stage_convention_required_validators($stage, $entry));
        $this->assertSame(['referent'], stage_convention_user_validations($stage, $entry, $context, $referent->id));
        $this->assertSame([], stage_convention_user_validations($stage, $entry, $context, $themeteacher->id));
    }

    /**
     * Le responsable de la thématique valide seul : la demande passe à la DEVE, et le référent n'a
     * rien à valider.
     */
    public function test_theme_teacher_validates_alone(): void {
        global $DB;
        [$stage, $context, , $referent, $themeteacher, , $entry] = $this->fixture(STAGE_CONVENTION_VALIDATOR_THEMETEACHER);

        $this->assertSame([], stage_convention_user_validations($stage, $entry, $context, $referent->id));
        $this->assertSame([], stage_get_teacher_pending_convention_entries($stage->id, $referent->id));
        $this->assertArrayHasKey($entry->id, stage_get_teacher_pending_convention_entries($stage->id, $themeteacher->id));

        $roles = stage_convention_user_validations($stage, $entry, $context, $themeteacher->id);
        $this->assertSame(['themeteacher'], $roles);
        $this->assertSame('requested', stage_validate_convention_request($stage, $entry, $themeteacher->id, $roles));

        $fresh = $DB->get_record('stage_entry', ['id' => $entry->id]);
        $this->assertEquals(STAGE_CONVENTION_REQUESTED, $fresh->conventionstatus);
        $this->assertEquals($themeteacher->id, $fresh->conventionthemevalidatedby);
        $this->assertNull($fresh->conventionteachervalidatedby);
    }

    /**
     * Les deux validations exigées : la première laisse la demande en attente, une seconde au même
     * titre est refusée, la seconde validation attendue la transmet à la DEVE.
     */
    public function test_both_validations_are_needed(): void {
        global $DB;
        [$stage, $context, , $referent, $themeteacher, , $entry] = $this->fixture(STAGE_CONVENTION_VALIDATOR_BOTH);
        $sink = $this->redirectEvents();

        $this->assertSame('pending', stage_validate_convention_request($stage, clone $entry, $referent->id, ['referent']));
        $this->assertEquals(
            STAGE_CONVENTION_TEACHERPENDING,
            $DB->get_field('stage_entry', 'conventionstatus', ['id' => $entry->id])
        );
        // Formulaire ouvert avant la première validation, envoyé ensuite au même titre.
        $this->assertFalse(stage_validate_convention_request($stage, clone $entry, $referent->id, ['referent']));
        $fresh = $DB->get_record('stage_entry', ['id' => $entry->id]);
        $this->assertSame([], stage_convention_user_validations($stage, $fresh, $context, $referent->id));
        $this->assertStringContainsString(
            get_string('conventionvalidationwaiting', 'mod_stage'),
            stage_render_convention_validation_status($stage, $fresh)
        );

        $this->assertSame('requested', stage_validate_convention_request($stage, $fresh, $themeteacher->id, ['themeteacher']));
        $this->assertEquals(STAGE_CONVENTION_REQUESTED, $DB->get_field('stage_entry', 'conventionstatus', ['id' => $entry->id]));

        $events = array_values(array_filter(
            $sink->get_events(),
            fn($event) => $event instanceof event\convention_teacher_validated
        ));
        $sink->close();
        $this->assertCount(2, $events);
        $this->assertStringContainsString('theme teacher', $events[1]->get_description());
    }

    /**
     * Un refus enregistré entre-temps n'est pas effacé par une validation, et un stage annulé
     * n'attend plus aucune validation.
     */
    public function test_no_validation_after_refusal_or_cancellation(): void {
        global $DB;
        [$stage, $context, , $referent, $themeteacher, , $entry] = $this->fixture(STAGE_CONVENTION_VALIDATOR_BOTH);
        stage_reject_convention(clone $entry, $referent->id, 'Incomplet', [STAGE_CONVENTION_TEACHERPENDING]);
        $this->assertFalse(stage_validate_convention_request($stage, clone $entry, $themeteacher->id, ['themeteacher']));
        $this->assertEquals(STAGE_CONVENTION_REJECTED, $DB->get_field('stage_entry', 'conventionstatus', ['id' => $entry->id]));

        $fresh = $DB->get_record('stage_entry', ['id' => $entry->id]);
        stage_request_convention($fresh, $fresh->conventiontemplateid, true);
        stage_cancel_entry($DB->get_record('stage_entry', ['id' => $entry->id]), get_admin()->id, 'Abandon');
        $cancelled = $DB->get_record('stage_entry', ['id' => $entry->id]);
        $this->assertSame([], stage_convention_user_validations($stage, $cancelled, $context, $themeteacher->id));
        $this->assertSame([], stage_get_teacher_pending_convention_entries($stage->id, $themeteacher->id));
    }

    /**
     * SIRET : 14 chiffres et clé de Luhn ; espaces et points acceptés à la saisie ; exception de
     * La Poste.
     */
    public function test_siret_validation(): void {
        $this->resetAfterTest();
        $this->assertSame('73282932000074', stage_normalize_siret(' 732 829 320.00074 '));
        $this->assertTrue(stage_siret_valid('73282932000074'));
        $this->assertFalse(stage_siret_valid('73282932000075'));
        $this->assertFalse(stage_siret_valid('7328293200007'));
        $this->assertFalse(stage_siret_valid('7328293200007A'));
        $this->assertTrue(stage_siret_valid('35600000000001'));
        // Siège de La Poste : clé de Luhn juste, somme des chiffres non multiple de 5.
        $this->assertTrue(stage_siret_valid('35600000000048'));
        $this->assertSame([], stage_validate_siret_field(['siret' => '']));
        $this->assertArrayHasKey('siret', stage_validate_siret_field(['siret' => '12345678901234']));
    }

    /**
     * Questionnaire de demande : réponse obligatoire contrôlée, note hors échelle refusée ; à
     * l'enregistrement, les réponses à l'ancien questionnaire (thématique changée) sont retirées et
     * les nouvelles s'affichent pour ceux qui valident.
     */
    public function test_request_questionnaire_answers(): void {
        global $DB;
        [$stage, , $student, , , $theme, $entry, $generator] = $this->fixture(STAGE_CONVENTION_VALIDATOR_REFERENT);
        $newq = function (string $name, string $qtype, int $required) use ($DB, $stage) {
            $id = $DB->insert_record('stage_question', (object) [
                'stageid' => $stage->id, 'themeid' => 0, 'evaltype' => 'request', 'qtype' => $qtype, 'name' => $name,
                'required' => $required, 'sortorder' => 0, 'timecreated' => time(), 'timemodified' => time(),
            ]);
            return $DB->get_record('stage_question', ['id' => $id]);
        };
        $why = $newq('Pourquoi cette structure ?', 'text', 1);
        $motivation = $newq('Motivation', 'rating', 0);
        $generator->create_evallist($stage, 'request', [$why->id, $motivation->id], [$theme]);
        $questions = stage_get_questions($theme->id, 'request');
        $this->assertCount(2, $questions);

        $whyfield = stage_request_question_field($theme->id, $why->id);
        $ratingfield = stage_request_question_field($theme->id, $motivation->id);
        $errors = stage_validate_request_answers([$ratingfield => '7'], $theme->id, $questions);
        $this->assertArrayHasKey($whyfield, $errors);
        $this->assertArrayHasKey($ratingfield, $errors);
        $valid = [$whyfield => 'Urgences', $ratingfield => '4'];
        $this->assertSame([], stage_validate_request_answers($valid, $theme->id, $questions));

        $other = $newq('Ancienne question', 'text', 0);
        stage_save_answers($entry->id, [$other->id => $other], [$other->id => 'Réponse périmée']);
        stage_save_request_answers(
            $entry->id,
            $questions,
            stage_extract_request_answers((object) [$whyfield => ' Urgences ', $ratingfield => '4'], $theme->id, $questions)
        );
        $answers = stage_get_answers($entry->id);
        $this->assertArrayNotHasKey($other->id, $answers);
        $this->assertSame('Urgences', $answers[$why->id]->answertext);

        $html = stage_render_request_answers($entry);
        $this->assertStringContainsString('Pourquoi cette structure ?', $html);
        $this->assertStringContainsString('Urgences', $html);
        $plaintheme = $generator->create_theme($stage, ['name' => 'Sans questionnaire']);
        $this->assertSame('', stage_render_request_answers($generator->create_entry($stage, $student->id, $plaintheme)));
    }

    /**
     * Une nouvelle demande efface la validation du responsable de la thématique, et la suppression
     * de son compte l'en dissocie.
     */
    public function test_theme_validation_reset_and_privacy(): void {
        global $DB;
        [$stage, $context, , , $themeteacher, , $entry] = $this->fixture(STAGE_CONVENTION_VALIDATOR_BOTH);
        stage_validate_convention_request($stage, $entry, $themeteacher->id, ['themeteacher']);

        $contexts = privacy\provider::get_contexts_for_userid($themeteacher->id)->get_contextids();
        $this->assertContains((int) $context->id, array_map('intval', $contexts));
        privacy\provider::delete_data_for_user(
            new \core_privacy\local\request\approved_contextlist($themeteacher, 'mod_stage', [$context->id])
        );
        $fresh = $DB->get_record('stage_entry', ['id' => $entry->id]);
        $this->assertNull($fresh->conventionthemevalidatedby);
        $this->assertNull($fresh->conventionthemevalidatetime);

        $DB->set_field('stage_entry', 'conventionthemevalidatedby', $themeteacher->id, ['id' => $entry->id]);
        $DB->set_field('stage_entry', 'conventionstatus', STAGE_CONVENTION_REJECTED, ['id' => $entry->id]);
        stage_request_convention($DB->get_record('stage_entry', ['id' => $entry->id]), $entry->conventiontemplateid, true);
        $this->assertNull($DB->get_field('stage_entry', 'conventionthemevalidatedby', ['id' => $entry->id]));
    }

    /**
     * Le paramètre passe de « les deux » à « référent » après la validation du référent : la
     * demande, qui n'attend plus rien, est transmise à la DEVE au lieu de rester bloquée.
     */
    public function test_setting_change_releases_pending_request(): void {
        global $DB;
        [$stage, , , $referent, , , $entry] = $this->fixture(STAGE_CONVENTION_VALIDATOR_BOTH);
        $this->assertSame('pending', stage_validate_convention_request($stage, $entry, $referent->id, ['referent']));

        stage_save_convention_teacher_validation_setting($stage->id, true, STAGE_CONVENTION_VALIDATOR_REFERENT);

        $this->assertEquals(STAGE_CONVENTION_REQUESTED, $DB->get_field('stage_entry', 'conventionstatus', ['id' => $entry->id]));
    }

    /**
     * Un responsable de thématique qui n'a plus la capacité (désinscrit) n'est plus attendu : le
     * référent valide à sa place, et une demande qui n'attendait que lui part à la DEVE.
     */
    public function test_inactive_theme_teacher_is_not_waited_for(): void {
        global $DB;
        [$stage, , , $referent, $themeteacher, $theme, $entry] = $this->fixture(STAGE_CONVENTION_VALIDATOR_BOTH);
        $this->assertSame('pending', stage_validate_convention_request($stage, $entry, $referent->id, ['referent']));

        $instance = $DB->get_record('enrol', ['courseid' => $stage->course, 'enrol' => 'manual'], '*', MUST_EXIST);
        enrol_get_plugin('manual')->unenrol_user($instance, $themeteacher->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->assertSame([], stage_get_active_theme_teachers($stage, $theme->id));
        $fresh = $DB->get_record('stage_entry', ['id' => $entry->id]);
        $this->assertSame(['referent'], stage_convention_required_validators($stage, $fresh));
        $this->assertSame(1, stage_release_pending_conventions($stage));
        $this->assertEquals(STAGE_CONVENTION_REQUESTED, $DB->get_field('stage_entry', 'conventionstatus', ['id' => $entry->id]));
    }
}
