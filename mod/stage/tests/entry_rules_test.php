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
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

/**
 * Règles du circuit d'un stage relevées par l'audit : qui peut encore demander une convention,
 * motifs de refus, création en masse, évaluation du maître de stage, changement de thématique ou
 * de liste d'évaluation, réinitialisation du cours et relances.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_convention_request_allowed
 * @covers     ::stage_confirm_onclick
 * @covers     ::stage_render_entry_management_actions
 * @covers     ::stage_reject_by_teacher
 * @covers     ::stage_reject_by_deve
 * @covers     ::stage_bulk_register_entries
 * @covers     ::stage_submit_tutor_eval
 * @covers     ::stage_update_entry_details
 * @covers     ::stage_get_entry_questions
 * @covers     ::stage_get_answered_questions
 * @covers     ::stage_max_upload_bytes
 * @covers     ::stage_reset_userdata
 * @covers     ::stage_get_entries_needing_convention_reminder
 * @covers     \mod_stage\task\send_convention_reminders
 * @covers     ::stage_update_entry_fields
 * @covers     ::stage_theme_name_taken
 * @covers     ::stage_can_edit_entry_checklist
 */
final class entry_rules_test extends \advanced_testcase {
    /**
     * Activité, étudiant inscrit et thématique.
     *
     * @return array [stdClass $course, stdClass $stage, \context_module $context, stdClass $student, stdClass $theme]
     */
    private function fixture(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $stage = $gen->create_module('stage', ['course' => $course, 'tutorevaluationenabled' => 1]);
        $student = $gen->create_user(['firstname' => 'Zoé', 'lastname' => 'Dupont']);
        $gen->enrol_user($student->id, $course->id, 'student');
        $theme = $gen->get_plugin_generator('mod_stage')->create_theme($stage, ['name' => 'Clinique']);
        return [$course, $stage, \context_module::instance($stage->cmid), $student, $theme];
    }

    /**
     * Crée une question d'évaluation.
     *
     * @param \stdClass $stage
     * @param string $name
     * @param string $evaltype
     * @param int $required
     * @return \stdClass
     */
    private function question(\stdClass $stage, string $name, string $evaltype = 'student', int $required = 0): \stdClass {
        global $DB;
        $id = $DB->insert_record('stage_question', (object) [
            'stageid' => $stage->id, 'themeid' => 0, 'evaltype' => $evaltype, 'qtype' => 'text', 'name' => $name,
            'required' => $required, 'sortorder' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        return $DB->get_record('stage_question', ['id' => $id]);
    }

    /**
     * Régression : l'étudiant pouvait rouvrir, par une demande de convention, un stage déjà validé
     * (import historique) ou annulé, et en changer thématique, année et durée.
     */
    public function test_convention_request_depends_on_entry_status(): void {
        global $DB, $USER;
        [, $stage, , $student, $theme] = $this->fixture();
        $entry = $this->getDataGenerator()->get_plugin_generator('mod_stage')->create_entry($stage, $student->id, $theme);

        $this->assertTrue(stage_convention_request_allowed($entry));
        $this->assertTrue(stage_convention_request_allowed($entry, true));

        // Validé directement par la DEVE, sans convention (cas de l'import historique).
        stage_apply_deve_validation($entry, $USER->id, 5);
        $entry = $DB->get_record('stage_entry', ['id' => $entry->id]);
        $this->assertEquals(STAGE_CONVENTION_NONE, $entry->conventionstatus);
        $this->assertFalse(stage_convention_request_allowed($entry));
        $this->assertTrue(stage_convention_request_allowed($entry, true));

        foreach ([STAGE_STATUS_EVAL_ETUDIANT, STAGE_STATUS_NON_VALIDE] as $status) {
            $entry->status = $status;
            $this->assertFalse(stage_convention_request_allowed($entry));
        }

        // Annulé : ni l'étudiant, ni la DEVE.
        $entry->status = STAGE_STATUS_ANNULE;
        $this->assertFalse(stage_convention_request_allowed($entry));
        $this->assertFalse(stage_convention_request_allowed($entry, true));

        // Convention déjà demandée : refus quel que soit le statut, comme avant.
        $entry->status = STAGE_STATUS_ENREGISTRE;
        $entry->conventionstatus = STAGE_CONVENTION_REQUESTED;
        $this->assertFalse(stage_convention_request_allowed($entry, true));
    }

    /**
     * Régression : l'apostrophe des messages français cassait le confirm() JavaScript, et le lien
     * (réinitialisation, contournement) était suivi sans confirmation.
     */
    public function test_confirmation_messages_are_escaped_for_javascript(): void {
        global $PAGE;
        [, $stage, $context, $student, $theme] = $this->fixture();
        $this->assertSame("return confirm('L\\'étudiant \\\"test\\\"');", stage_confirm_onclick('L\'étudiant "test"'));

        // Les messages français concernés contiennent bien une apostrophe.
        $string = [];
        include(__DIR__ . '/../lang/fr/stage.php');
        foreach (['confirmresetentry', 'confirmtutorevalbypass'] as $identifier) {
            $this->assertStringContainsString("'", $string[$identifier]);
            $this->assertStringContainsString("\\'", stage_confirm_onclick($string[$identifier]));
        }

        $entry = $this->getDataGenerator()->get_plugin_generator('mod_stage')->create_entry($stage, $student->id, $theme);
        stage_cancel_entry($entry, 2, 'Motif');
        $cm = get_coursemodule_from_instance('stage', $stage->id);
        $PAGE->set_url('/mod/stage/dashboard.php', ['id' => $cm->id]);
        $html = stage_render_entry_management_actions($entry, $cm, $context, (object) [
            'register' => true, 'validatedeve' => true, 'assignedteacher' => false, 'viewdetail' => true,
        ]);
        $this->assertStringContainsString(s(stage_confirm_onclick(get_string('confirmresetentry', 'mod_stage'))), $html);
        // Le stage annulé ne propose pas non plus de demande de convention à la DEVE.
        $this->assertStringNotContainsString('convention_request.php', $html);
    }

    /**
     * Un refus (enseignant ou DEVE) exige un motif, et la DEVE ne peut pas refuser un stage annulé.
     */
    public function test_rejection_requires_reason(): void {
        global $DB;
        [, $stage, , $student, $theme] = $this->fixture();
        $entry = $this->getDataGenerator()->get_plugin_generator('mod_stage')->create_entry($stage, $student->id, $theme);

        foreach (['stage_reject_by_teacher', 'stage_reject_by_deve'] as $function) {
            try {
                $function($entry, 2, '   ');
                $this->fail("$function ne doit pas accepter un motif vide.");
            } catch (\moodle_exception $e) {
                $this->assertSame('errorrejectreasonrequired', $e->errorcode);
            }
        }
        $this->assertEquals(STAGE_STATUS_ENREGISTRE, $DB->get_field('stage_entry', 'status', ['id' => $entry->id]));

        stage_cancel_entry($entry, 2, 'Abandon');
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('errorvalidatecancelled', 'mod_stage'));
        stage_reject_by_deve($entry, 2, 'Motif');
    }

    /**
     * Création en masse : thématique de l'activité, étudiants inscrits, valeurs valides ; un
     * identifiant forgé n'aboutit à rien.
     */
    public function test_bulk_register_validates_theme_students_and_values(): void {
        global $DB;
        [$course, $stage, $context, $student, $theme] = $this->fixture();
        $gen = $this->getDataGenerator();
        $outsider = $gen->create_user();
        $otherstage = $gen->create_module('stage', ['course' => $gen->create_course()]);
        $foreigntheme = $gen->get_plugin_generator('mod_stage')->create_theme($otherstage);
        $fields = (object) [
            'themeid' => $theme->id, 'studyyear' => 3, 'abroad' => 0, 'country' => 'Ignoré', 'structure' => 'Clinique A',
            'datestart' => make_timestamp(2026, 3, 1), 'dateend' => make_timestamp(2026, 3, 10), 'declaredduration' => 8,
        ];

        $bad = clone $fields;
        $bad->themeid = $foreigntheme->id;
        $this->assertSame(
            get_string('bulkregisterinvalidtheme', 'mod_stage'),
            stage_bulk_register_entries($stage, $context, [$student->id], $bad)->error
        );
        $bad = clone $fields;
        $bad->studyyear = 9;
        $this->assertNotNull(stage_bulk_register_entries($stage, $context, [$student->id], $bad)->error);
        $bad = clone $fields;
        $bad->dateend = make_timestamp(2026, 2, 1);
        $this->assertNotNull(stage_bulk_register_entries($stage, $context, [$student->id], $bad)->error);
        $this->assertEquals(0, $DB->count_records('stage_entry'));

        $result = stage_bulk_register_entries($stage, $context, [$student->id, $outsider->id, $student->id], $fields);
        $this->assertNull($result->error);
        $this->assertSame(1, $result->created);
        $this->assertSame([(int) $outsider->id], $result->ignored);
        $entry = $DB->get_record('stage_entry', ['stageid' => $stage->id], '*', MUST_EXIST);
        $this->assertEquals(STAGE_CONVENTION_SIGNVET, $entry->conventionstatus);
        $this->assertEquals(3, $entry->studyyear);
        $this->assertSame('', $entry->country);

        $again = stage_bulk_register_entries($stage, $context, [$student->id], $fields);
        $this->assertSame(0, $again->created);
        $this->assertSame([fullname($student)], $again->duplicates);
    }

    /**
     * Évaluation du maître de stage : une erreur est renvoyée sans rien enregistrer (la page
     * réaffiche alors la saisie), et un commentaire libre vide est refusé.
     */
    public function test_tutor_evaluation_submission(): void {
        global $DB;
        [, $stage, , $student, $theme] = $this->fixture();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $entry = $gen->create_entry($stage, $student->id, $theme);

        $this->assertNotNull(stage_submit_tutor_eval($entry, [], [], '  '));
        $this->assertEmpty($DB->get_field('stage_entry', 'tutortime', ['id' => $entry->id]));
        $this->assertNull(stage_submit_tutor_eval($entry, [], [], 'Très bon stagiaire'));
        $saved = $DB->get_record('stage_entry', ['id' => $entry->id]);
        $this->assertNotEmpty($saved->tutortime);
        $this->assertSame('Très bon stagiaire', $saved->tutoreval);

        $other = $gen->create_entry($stage, $student->id, $theme, [
            'datestart' => make_timestamp(2026, 5, 1), 'dateend' => make_timestamp(2026, 5, 5),
        ]);
        $required = $this->question($stage, 'Bilan', 'tutor', 1);
        $error = stage_submit_tutor_eval($other, [$required->id => $required], [$required->id => ''], null);
        $this->assertStringContainsString('Bilan', $error);
        $this->assertFalse($DB->record_exists('stage_answer', ['entryid' => $other->id]));
        $this->assertEmpty($DB->get_field('stage_entry', 'tutortime', ['id' => $other->id]));
        $this->assertNull(stage_submit_tutor_eval($other, [$required->id => $required], [$required->id => 'Bien'], null));
        $this->assertSame('Bien', $DB->get_field('stage_answer', 'answertext', ['entryid' => $other->id]));
    }

    /**
     * Régression : changer la thématique d'un stage laissait les réponses à la check-list de
     * l'ancienne thématique.
     */
    public function test_theme_change_drops_checklist_of_previous_theme(): void {
        global $DB;
        [, $stage, , $student, $theme] = $this->fixture();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $newtheme = $gen->create_theme($stage, ['name' => 'Ruminants']);
        $olditem = $gen->create_checklist_item($theme);
        $newitem = $gen->create_checklist_item($newtheme);
        $entry = $gen->create_entry($stage, $student->id, $theme);
        foreach ([$olditem, $newitem] as $item) {
            $DB->insert_record('stage_entry_checklist', (object) [
                'entryid' => $entry->id, 'itemid' => $item->id, 'checked' => 1, 'explanation' => '',
                'timecreated' => time(), 'timemodified' => time(),
            ]);
        }

        stage_update_entry_details($entry, $theme->id, 'A', $entry->datestart, $entry->dateend, 5);
        $this->assertEquals(2, $DB->count_records('stage_entry_checklist', ['entryid' => $entry->id]));

        stage_update_entry_details($entry, $newtheme->id, 'A', $entry->datestart, $entry->dateend, 5);
        $this->assertSame(
            [(int) $newitem->id],
            array_map('intval', $DB->get_fieldset_select('stage_entry_checklist', 'itemid', 'entryid = ?', [$entry->id]))
        );
        $this->assertEquals($newtheme->id, $DB->get_field('stage_entry', 'themeid', ['id' => $entry->id]));
    }

    /**
     * Régression : remplacer la liste d'évaluation d'une thématique rendait invisibles les
     * réponses déjà données. Elles restent affichées, après les questions de la liste actuelle.
     */
    public function test_answers_survive_evaluation_list_change(): void {
        [, $stage, , $student, $theme] = $this->fixture();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $old = $this->question($stage, 'Ancienne question');
        $new = $this->question($stage, 'Nouvelle question');
        $teacherq = $this->question($stage, 'Question enseignant', 'teacher');
        $gen->create_evallist($stage, 'student', [$old->id], [$theme], 'Ancienne liste');
        $gen->create_evallist($stage, 'teacher', [$teacherq->id], [$theme], 'Liste enseignant');
        $entry = $gen->create_entry($stage, $student->id, $theme);
        stage_save_answers($entry->id, [$old, $teacherq], [$old->id => 'Réponse', $teacherq->id => 'Avis']);

        // La thématique change de liste étudiant.
        $gen->create_evallist($stage, 'student', [$new->id], [$theme], 'Nouvelle liste');
        $this->assertSame([(int) $new->id], array_map('intval', array_keys(stage_get_questions($theme->id, 'student'))));
        $this->assertSame(
            [(int) $new->id, (int) $old->id],
            array_map('intval', array_keys(stage_get_entry_questions($entry, 'student')))
        );
        $this->assertSame(
            [(int) $teacherq->id],
            array_map('intval', array_keys(stage_get_entry_questions($entry, 'teacher')))
        );
        $html = stage_render_evaluation(stage_get_entry_questions($entry, 'student'), stage_get_answers($entry->id), '');
        $this->assertStringContainsString('Réponse', $html);
        $this->assertSame([], stage_get_answered_questions([], 'student'));
    }

    /**
     * La taille maximale des dépôts tient compte de la limite du cours.
     */
    public function test_max_upload_bytes_uses_course_limit(): void {
        global $DB;
        [$course, , $context, $student] = $this->fixture();
        // L'administrateur ignore les limites de taille : le test se fait en tant qu'étudiant.
        $this->setUser($student);
        set_config('maxbytes', 10 * 1024 * 1024);
        $DB->set_field('course', 'maxbytes', 1024 * 1024, ['id' => $course->id]);
        $this->assertSame(1024 * 1024, stage_max_upload_bytes($context));
    }

    /**
     * La réinitialisation du cours supprime les stages et les attributions de référents, en
     * conservant la configuration de l'activité.
     */
    public function test_course_reset_removes_internships_but_keeps_configuration(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        [$course, $stage, $context, $student, $theme] = $this->fixture();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $teacher = $this->getDataGenerator()->create_user();
        $gen->assign_teacher($stage, $student->id, $teacher->id);
        $entry = $gen->create_entry($stage, $student->id, $theme);
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => 'mod_stage', 'filearea' => STAGE_REPORT_FILEAREA,
            'itemid' => $entry->id, 'filepath' => '/', 'filename' => 'rapport.pdf',
        ], 'PDF');

        $this->assertSame(['reset_stage_entries' => 1, 'reset_stage_teachers' => 1], stage_reset_course_form_defaults($course));
        $status = reset_course_userdata((object) [
            'id' => $course->id, 'courseid' => $course->id, 'reset_stage_entries' => 1, 'reset_stage_teachers' => 1,
        ]);
        $this->assertNotEmpty(array_filter($status, fn($row) => $row['component'] === get_string('modulenameplural', 'mod_stage')));
        $this->assertFalse($DB->record_exists('stage_entry', ['stageid' => $stage->id]));
        $this->assertFalse($DB->record_exists('stage_entry_period', ['entryid' => $entry->id]));
        $this->assertFalse($DB->record_exists('stage_entry_teacher', ['stageid' => $stage->id]));
        $this->assertEmpty(stage_get_report_files($context, $entry->id));
        $this->assertTrue($DB->record_exists('stage_theme', ['id' => $theme->id]));
    }

    /**
     * Régression : les étapes du circuit réécrivaient la saisie entière, chargée en début de page.
     * Les dates recalculées juste avant à partir des nouvelles plages (revue de la convention par
     * la DEVE ou par l'enseignant référent) étaient ainsi remplacées par les anciennes.
     */
    public function test_workflow_steps_do_not_restore_stale_dates(): void {
        global $DB;
        [, $stage, , $student, $theme] = $this->fixture();
        $entry = $this->getDataGenerator()->get_plugin_generator('mod_stage')->create_entry($stage, $student->id, $theme);
        $DB->set_field('stage_entry', 'conventionstatus', STAGE_CONVENTION_TEACHERPENDING, ['id' => $entry->id]);
        $entry->conventionstatus = STAGE_CONVENTION_TEACHERPENDING;

        // La page a chargé $entry, puis enregistre les plages corrigées dans le formulaire.
        stage_save_entry_periods($entry->id, [
            ['datestart' => make_timestamp(2026, 4, 1), 'dateend' => make_timestamp(2026, 4, 20)],
        ]);
        stage_teacher_validate_convention($entry, 2);
        stage_convention_mark_edited($entry, 2);
        stage_convention_mark_signed($entry, 2);

        $saved = $DB->get_record('stage_entry', ['id' => $entry->id]);
        $this->assertEquals(make_timestamp(2026, 4, 1), $saved->datestart);
        $this->assertEquals(make_timestamp(2026, 4, 20), $saved->dateend);
        $this->assertEquals(STAGE_CONVENTION_SIGNED, $saved->conventionstatus);
        $this->assertEquals(2, $saved->conventionteachervalidatedby);
    }

    /**
     * Deux thématiques d'une même activité ne peuvent pas porter le même nom (sous forme
     * normalisée) : les rapprochements par nom des imports et du transfert seraient ambigus.
     */
    public function test_theme_names_must_be_unique(): void {
        [, $stage, , , $theme] = $this->fixture();
        $this->assertTrue(stage_theme_name_taken($stage->id, '  CLINIQUE '));
        $this->assertFalse(stage_theme_name_taken($stage->id, 'Clinique', $theme->id));
        $this->assertFalse(stage_theme_name_taken($stage->id, 'Clinique 2'));
    }

    /**
     * Modifier la check-list d'un stage demande la capacité de gestion des stages : la seule
     * capacité de consultation (viewall) ne suffit pas.
     */
    public function test_checklist_edition_requires_management_capability(): void {
        global $DB;
        [$course, $stage, $context, $student, $theme] = $this->fixture();
        $entry = $this->getDataGenerator()->get_plugin_generator('mod_stage')->create_entry($stage, $student->id, $theme);
        $observer = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('mod/stage:viewall', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $observer->id, $context->id);
        $this->assertTrue(has_capability('mod/stage:viewall', $context, $observer));
        $this->assertFalse(stage_can_edit_entry_checklist($stage, $entry, $context, $observer->id));

        $deve = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($deve->id, $course->id, 'editingteacher');
        $this->assertTrue(stage_can_edit_entry_checklist($stage, $entry, $context, $deve->id));
    }

    /**
     * Les relances de convention ignorent les activités en corbeille et les étudiants qui ne sont
     * plus inscrits ; ces derniers sont marqués traités pour ne pas être relancés à chaque cron.
     */
    public function test_convention_reminders_skip_deleted_activities_and_unenrolled_students(): void {
        global $DB;
        [$course, $stage, , $student, $theme] = $this->fixture();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $start = time() + 3 * DAYSECS;
        $entry = $gen->create_entry($stage, $student->id, $theme, ['datestart' => $start, 'dateend' => $start + DAYSECS]);
        $this->assertArrayHasKey($entry->id, stage_get_entries_needing_convention_reminder());

        $DB->set_field('course_modules', 'deletioninprogress', 1, ['id' => $stage->cmid]);
        $this->assertArrayNotHasKey($entry->id, stage_get_entries_needing_convention_reminder());
        $DB->set_field('course_modules', 'deletioninprogress', 0, ['id' => $stage->cmid]);

        $manual = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        enrol_get_plugin('manual')->unenrol_user($manual, $student->id);
        $sink = $this->redirectEmails();
        ob_start();
        (new \mod_stage\task\send_convention_reminders())->execute();
        ob_end_clean();
        $this->assertCount(0, $sink->get_messages());
        $this->assertNotEmpty($DB->get_field('stage_entry', 'conventionremindertime', ['id' => $entry->id]));
        $sink->close();
    }
}
