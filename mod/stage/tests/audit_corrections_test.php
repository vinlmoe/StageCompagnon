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

use mod_stage\local\csv_importer;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/stage/locallib.php');

/**
 * Tests des corrections issues de l'audit du code (docs/audit/audit-du-module.tex) : un test par
 * constat corrigé, nommé d'après lui.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_clean_entry_text
 * @covers     ::stage_reset_entry
 * @covers     ::stage_bulk_validate_entries
 * @covers     ::stage_execute_student_transfer
 * @covers     ::stage_normalize_day
 * @covers     ::stage_set_entry_workdays
 * @covers     ::stage_get_theme_required_duration
 * @covers     ::stage_bypass_tutor_eval
 * @covers     ::stage_request_convention
 * @covers     ::stage_set_entry_convention_exempt
 * @covers     ::stage_tutor_email_warning
 * @covers     ::stage_convention_template_readable
 * @covers     ::stage_convention_logo_valid
 * @covers     ::stage_post_button
 * @covers     ::stage_plain_string
 * @covers     \mod_stage\external\get_my_stages
 * @covers     \mod_stage\privacy\provider
 * @covers     \mod_stage\local\csv_importer::entries
 * @covers     \mod_stage\local\csv_importer::apply_evaluations
 */
final class audit_corrections_test extends \advanced_testcase {
    /**
     * Cours, activité, étudiant inscrit, enseignant référent et thématique.
     *
     * @return array [stdClass $stage, \context_module $context, stdClass $student, stdClass $teacher,
     *               stdClass $theme, \mod_stage_generator $generator]
     */
    private function fixture(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $stage = $gen->create_module('stage', ['course' => $course]);
        $context = \context_module::instance($stage->cmid);
        $student = $gen->create_user(['email' => 'student@example.com']);
        $teacher = $gen->create_user(['email' => 'teacher@example.com']);
        $gen->enrol_user($student->id, $course->id, 'student');
        $gen->enrol_user($teacher->id, $course->id, 'teacher');
        /** @var \mod_stage_generator $generator */
        $generator = $gen->get_plugin_generator('mod_stage');
        $theme = $generator->create_theme($stage, ['name' => 'Clinique']);
        $generator->assign_teacher($stage, $student->id, $teacher->id);
        return [$stage, $context, $student, $teacher, $theme, $generator];
    }

    /**
     * Crée une question d'évaluation du type donné, rattachée à la thématique.
     *
     * @param stdClass $stage
     * @param stdClass $theme
     * @param string $evaltype
     * @return stdClass
     */
    private function create_question(\stdClass $stage, \stdClass $theme, string $evaltype): \stdClass {
        global $DB;
        $id = $DB->insert_record('stage_question', (object) [
            'stageid' => $stage->id, 'themeid' => $theme->id, 'evaltype' => $evaltype, 'qtype' => 'text',
            'name' => 'Question ' . $evaltype, 'required' => 0, 'sortorder' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        return $DB->get_record('stage_question', ['id' => $id]);
    }

    /**
     * B1, C6 : une structure ou un pays importés ou saisis perdent leurs balises et sont ramenés à
     * la taille de leur colonne, quel que soit le chemin de création.
     */
    public function test_entry_text_is_cleaned_and_truncated(): void {
        [$stage, , $student, , $theme, $generator] = $this->fixture();
        $entry = $generator->create_entry($stage, $student->id, $theme, [
            'structure' => '<img src=x onerror=alert(1)>Clinique ' . str_repeat('A', 300),
            'abroad' => 1, 'country' => '<b>Belgique</b>',
        ]);
        $this->assertStringNotContainsString('<', $entry->structure);
        $this->assertSame(255, \core_text::strlen($entry->structure));
        $this->assertSame('Belgique', $entry->country);
    }

    /**
     * B1 : le tableau « Mes stages » échappe la structure, même si une ancienne valeur en base
     * contient du HTML.
     */
    public function test_student_dashboard_escapes_structure(): void {
        global $DB, $PAGE;
        [$stage, $context, $student, , $theme, $generator] = $this->fixture();
        $entry = $generator->create_entry($stage, $student->id, $theme);
        $DB->set_field('stage_entry', 'structure', '<script>alert(1)</script>', ['id' => $entry->id]);
        $PAGE->set_url(new \moodle_url('/mod/stage/view.php', ['id' => $stage->cmid]));
        $PAGE->set_context($context);
        $this->setUser($student);

        $cm = get_coursemodule_from_id('stage', $stage->cmid);
        ob_start();
        stage_print_student_dashboard_content($stage, $student->id, $cm, true);
        $html = ob_get_clean();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * D2 : sans en-tête, la première ligne est importée ; une ligne incomplète et une durée non
     * entière sont signalées au lieu d'être écartées en silence.
     */
    public function test_csv_without_header_and_incomplete_lines(): void {
        global $DB;
        [$stage, $context] = $this->fixture();
        $csv = "student@example.com;Clinique;A;2026-03-02;2026-03-06;5\n"
            . "student@example.com;;B;2026-04-06;2026-04-10;5\n"
            . "student@example.com;Clinique;C;2026-05-04;2026-05-08;12,5\n";

        $result = csv_importer::entries($stage, $context, $csv);

        $this->assertSame(1, $result['results']->created);
        $this->assertSame('A', $DB->get_field('stage_entry', 'structure', ['stageid' => $stage->id]));
        $this->assertSame([
            get_string('importerrormissing', 'mod_stage', 2),
            get_string('importerrorduration', 'mod_stage', (object) ['line' => 3, 'value' => '12,5']),
        ], $result['results']->errors);
    }

    /**
     * C1 : la réinitialisation efface l'avis de l'enseignant (évaluation ou motif de refus) et ses
     * réponses au questionnaire, mais garde celles de l'étudiant.
     */
    public function test_reset_clears_teacher_evaluation(): void {
        global $DB;
        [$stage, , $student, $teacher, $theme, $generator] = $this->fixture();
        $entry = $generator->create_entry($stage, $student->id, $theme);
        $studentq = $this->create_question($stage, $theme, 'student');
        $teacherq = $this->create_question($stage, $theme, 'teacher');
        stage_save_answers($entry->id, [$studentq->id => $studentq, $teacherq->id => $teacherq], [
            $studentq->id => 'Mon bilan', $teacherq->id => 'Rapport manquant',
        ]);
        stage_apply_student_eval($entry, 'Bilan');
        stage_reject_by_teacher($entry, $teacher->id, 'Rapport manquant');

        stage_reset_entry($DB->get_record('stage_entry', ['id' => $entry->id]));

        $reset = $DB->get_record('stage_entry', ['id' => $entry->id]);
        $this->assertEquals(STAGE_STATUS_ENREGISTRE, $reset->status);
        $this->assertNull($reset->teacherid);
        $this->assertNull($reset->teachereval);
        $this->assertNull($reset->teachertime);
        $this->assertFalse($DB->record_exists('stage_answer', ['entryid' => $entry->id, 'questionid' => $teacherq->id]));
        $this->assertTrue($DB->record_exists('stage_answer', ['entryid' => $entry->id, 'questionid' => $studentq->id]));
    }

    /**
     * C2 : la validation en masse écarte les stages refusés ou annulés et retient les jours
     * effectivement cochés.
     */
    public function test_bulk_validation_skips_refused_and_uses_workdays(): void {
        global $DB;
        [$stage, , $student, $teacher, $theme, $generator] = $this->fixture();
        $open = $generator->create_entry($stage, $student->id, $theme, ['declaredduration' => 10]);
        $days = stage_get_period_days((object) ['datestart' => $open->datestart, 'dateend' => $open->dateend]);
        stage_set_entry_workdays($open->id, array_slice($days, 0, 4));
        $refused = $generator->create_entry($stage, $student->id, $theme, [
            'datestart' => make_timestamp(2026, 6, 1), 'dateend' => make_timestamp(2026, 6, 5),
        ]);
        stage_apply_student_eval($refused, 'Bilan');
        stage_reject_by_teacher($refused, $teacher->id, 'Insuffisant');
        $cancelled = $generator->create_entry($stage, $student->id, $theme, [
            'datestart' => make_timestamp(2026, 7, 1), 'dateend' => make_timestamp(2026, 7, 5),
        ]);
        stage_cancel_entry($cancelled, get_admin()->id, 'Abandon');

        $count = stage_bulk_validate_entries($stage, [$open->id, $refused->id, $cancelled->id], get_admin()->id);

        $this->assertSame(1, $count);
        $this->assertEquals(STAGE_STATUS_VALIDE_DEVE, $DB->get_field('stage_entry', 'status', ['id' => $open->id]));
        $this->assertEquals(4, $DB->get_field('stage_entry', 'retainedduration', ['id' => $open->id]));
        $this->assertEquals(STAGE_STATUS_NON_VALIDE, $DB->get_field('stage_entry', 'status', ['id' => $refused->id]));
        $this->assertEquals(STAGE_STATUS_ANNULE, $DB->get_field('stage_entry', 'status', ['id' => $cancelled->id]));
    }

    /**
     * C5 : la mobilité d'études suit l'étudiant transféré.
     */
    public function test_transfer_moves_study_mobility(): void {
        global $DB;
        [$stage, $context, $student, , $theme, $generator] = $this->fixture();
        $targetcourse = $this->getDataGenerator()->create_course();
        $target = $this->getDataGenerator()->create_module('stage', ['course' => $targetcourse]);
        $this->getDataGenerator()->enrol_user($student->id, $targetcourse->id, 'student');
        $generator->create_theme($target, ['name' => 'Clinique']);
        $generator->create_entry($stage, $student->id, $theme);
        stage_set_student_study_mobility($stage->id, $student->id, true, 'Liège', get_admin()->id);

        $plan = stage_plan_student_transfer($stage, $target, $student->id);
        $this->assertEmpty($plan->blockers);
        stage_execute_student_transfer($stage, $context, $target, \context_module::instance($target->cmid), $student->id, $plan);

        $this->assertFalse($DB->record_exists('stage_study_mobility', ['stageid' => $stage->id, 'userid' => $student->id]));
        $this->assertSame('Liège', $DB->get_field('stage_study_mobility', 'location', [
            'stageid' => $target->id, 'userid' => $student->id,
        ]));
    }

    /**
     * C3 : des jours cochés par un étudiant d'un autre fuseau ne disparaissent plus quand la DEVE
     * modifie les plages du stage.
     */
    public function test_workdays_survive_edit_from_other_timezone(): void {
        [$stage, , $student, , $theme, $generator] = $this->fixture();
        $this->setTimezone('Europe/Paris', 'Europe/Paris');
        $student->timezone = 'America/Montreal';
        $this->setUser($student);
        $entry = $generator->create_entry($stage, $student->id, $theme, [
            'datestart' => make_timestamp(2026, 3, 2, 0, 0, 0, 'America/Montreal'),
            'dateend' => make_timestamp(2026, 3, 6, 0, 0, 0, 'America/Montreal'),
        ]);
        // Minuits de Montréal, comme les aurait produits l'ancien calcul dans le fuseau de l'étudiant.
        $montreal = [];
        for ($day = 2; $day <= 6; $day++) {
            $montreal[] = make_timestamp(2026, 3, $day, 0, 0, 0, 'America/Montreal');
        }
        stage_set_entry_workdays($entry->id, $montreal);
        $this->assertCount(5, stage_get_entry_workdays($entry->id));

        $this->setAdminUser();
        stage_save_entry_periods($entry->id, [['datestart' => $entry->datestart, 'dateend' => $entry->dateend]]);

        $saved = stage_get_entry_workdays($entry->id);
        $this->assertCount(5, $saved);
        $this->assertSame(stage_normalize_day($montreal[0]), $saved[0]);
        $this->assertSame('02/03/2026', userdate($saved[0], '%d/%m/%Y', 'Europe/Paris'));
    }

    /**
     * C4 : pour une thématique bornée, le bilan par année et le tableau de bord exigent la même
     * durée, celle des années où l'étudiant a fait ses stages.
     */
    public function test_ranged_theme_requirement_matches_dashboard(): void {
        [$stage, , $student, , , $generator] = $this->fixture();
        $theme = $generator->create_theme($stage, [
            'name' => 'Pluriannuelle', 'mandatory' => 1, 'minstudyyear' => 2, 'maxstudyyear' => 4,
        ]);
        stage_set_theme_duration($theme->id, 2, 10);
        stage_set_theme_duration($theme->id, 3, 10);
        stage_set_theme_duration($theme->id, 4, 10);
        $entry = $generator->create_entry($stage, $student->id, $theme, ['studyyear' => 2, 'declaredduration' => 10]);
        stage_apply_deve_validation($entry, get_admin()->id, 10);
        $entry4 = $generator->create_entry($stage, $student->id, $theme, [
            'studyyear' => 4, 'declaredduration' => 5,
            'datestart' => make_timestamp(2026, 9, 1), 'dateend' => make_timestamp(2026, 9, 5),
        ]);
        stage_apply_deve_validation($entry4, get_admin()->id, 5);

        $byyear = stage_get_student_year_progress($stage->id, $student->id);
        $row = array_values(array_filter($byyear[4]->themes, fn($r) => (int) $r->theme->id === (int) $theme->id))[0];
        $dashboard = stage_get_student_progress($stage->id, $student->id)->themes[$theme->id];

        $this->assertSame(20, $row->required);
        $this->assertSame($dashboard->requiredduration, $row->required);
        $this->assertFalse($row->done);
    }

    /**
     * C4 : une plage dont seule la dernière année a une durée exige toujours cette durée, que
     * l'étudiant n'ait aucun stage sur la thématique ou seulement un stage d'une autre année.
     */
    public function test_ranged_theme_never_requires_zero(): void {
        [$stage, , $student, , , $generator] = $this->fixture();
        $theme = $generator->create_theme($stage, [
            'name' => 'Pluriannuelle', 'mandatory' => 1, 'minstudyyear' => 2, 'maxstudyyear' => 4,
        ]);
        stage_set_theme_duration($theme->id, 4, 30);
        $this->assertSame(30, stage_get_theme_required_duration($theme, []));

        $entry = $generator->create_entry($stage, $student->id, $theme, ['studyyear' => 2, 'declaredduration' => 5]);
        stage_apply_deve_validation($entry, get_admin()->id, 5);
        $byyear = stage_get_student_year_progress($stage->id, $student->id);
        $row = array_values(array_filter($byyear[4]->themes, fn($r) => (int) $r->theme->id === (int) $theme->id))[0];
        $this->assertSame(30, $row->required);
        $this->assertFalse($row->done);
    }

    /**
     * C7 : le contournement du maître de stage est refusé sur un stage annulé, et tracé sinon.
     */
    public function test_tutor_bypass_guarded_and_logged(): void {
        global $DB;
        [$stage, , $student, , $theme, $generator] = $this->fixture();
        $entry = $generator->create_entry($stage, $student->id, $theme);
        $sink = $this->redirectEvents();
        $this->assertTrue(stage_bypass_tutor_eval($DB->get_record('stage_entry', ['id' => $entry->id])));
        $events = $sink->get_events();
        $sink->close();
        $this->assertInstanceOf(event\entry_tutor_bypassed::class, $events[0]);
        $this->assertStringContainsString('bypassed', $events[0]->get_description());

        $cancelled = $generator->create_entry($stage, $student->id, $theme, [
            'datestart' => make_timestamp(2026, 6, 1), 'dateend' => make_timestamp(2026, 6, 5),
        ]);
        stage_cancel_entry($cancelled, get_admin()->id, 'Abandon');
        $this->assertFalse(stage_bypass_tutor_eval($DB->get_record('stage_entry', ['id' => $cancelled->id])));
        $this->assertEquals(0, $DB->get_field('stage_entry', 'tutorbypassed', ['id' => $cancelled->id]));
    }

    /**
     * C8 : une nouvelle demande de convention efface les décisions de la précédente.
     */
    public function test_new_convention_request_clears_previous_decisions(): void {
        global $DB;
        [$stage, , $student, $teacher, $theme, $generator] = $this->fixture();
        $entry = $generator->create_entry($stage, $student->id, $theme);
        $template = $generator->create_convention_template($stage);
        stage_request_convention($entry, $template->id, true);
        stage_teacher_validate_convention($entry, $teacher->id);
        stage_reject_convention($DB->get_record('stage_entry', ['id' => $entry->id]), get_admin()->id, 'Incomplet');

        stage_request_convention($DB->get_record('stage_entry', ['id' => $entry->id]), $template->id, true);

        $fresh = $DB->get_record('stage_entry', ['id' => $entry->id]);
        $this->assertEquals(STAGE_CONVENTION_TEACHERPENDING, $fresh->conventionstatus);
        $this->assertNull($fresh->conventionteachervalidatedby);
        $this->assertNull($fresh->conventionteachervalidatetime);
        $this->assertNull($fresh->conventionrejectedby);
        $this->assertNull($fresh->conventionrejectcomment);
    }

    /**
     * C14 : la dispense de convention d'un stage déjà évalué n'est pas levée.
     */
    public function test_exemption_kept_on_evaluated_entry(): void {
        global $DB;
        [$stage, , $student, , $theme, $generator] = $this->fixture();
        $entry = $generator->create_entry($stage, $student->id, $theme, ['conventionstatus' => STAGE_CONVENTION_EXEMPT]);
        stage_apply_student_eval($entry, 'Bilan');

        stage_set_entry_convention_exempt($DB->get_record('stage_entry', ['id' => $entry->id]), false);

        $this->assertEquals(STAGE_CONVENTION_EXEMPT, $DB->get_field('stage_entry', 'conventionstatus', ['id' => $entry->id]));
    }

    /**
     * D3 : un réimport StageVet ne remplace pas l'évaluation d'un stage déjà évalué par
     * l'enseignant, ni celle d'un maître de stage qui a reçu son lien.
     */
    public function test_stagevet_reimport_keeps_moodle_evaluations(): void {
        $entry = (object) [
            'id' => 0, 'status' => STAGE_STATUS_EVAL_ENSEIGNANT, 'conventionstatus' => STAGE_CONVENTION_SIGNVET,
            'studentselfeval' => 'Saisie dans Moodle', 'tutoreval' => '', 'tutortime' => null, 'tutortoken' => null,
        ];
        $result = csv_importer::apply_evaluations($entry, 'Version StageVet', '');
        $this->assertSame(['student'], $result['kept']);
        $this->assertSame('Saisie dans Moodle', $entry->studentselfeval);

        $entry = (object) [
            'id' => 0, 'status' => STAGE_STATUS_EVAL_ETUDIANT, 'conventionstatus' => STAGE_CONVENTION_SIGNVET,
            'studentselfeval' => 'Bilan', 'tutoreval' => 'Réponse par le lien', 'tutortime' => time(),
            'tutortoken' => str_repeat('a', 64),
        ];
        $result = csv_importer::apply_evaluations($entry, 'Bilan', 'Version StageVet');
        $this->assertSame(['tutor'], $result['kept']);
        $this->assertSame('Réponse par le lien', $entry->tutoreval);
    }

    /**
     * A2 : l'adresse du maître de stage égale à celle de l'étudiant, ou d'un inscrit, est signalée.
     */
    public function test_tutor_email_warning(): void {
        [, $context, $student, $teacher] = $this->fixture();
        $this->assertNotNull(stage_tutor_email_warning($context, $student, ' Student@Example.com '));
        $this->assertStringContainsString(fullname($teacher), stage_tutor_email_warning($context, $student, 'teacher@example.com'));
        $this->assertNull(stage_tutor_email_warning($context, $student, 'maitre@clinique.fr'));
        $this->assertNull(stage_tutor_email_warning($context, $student, ''));
    }

    /**
     * D4, D10 : un gabarit que FPDI ne sait pas lire et un logo qui n'est pas un PNG sont reconnus.
     */
    public function test_template_and_logo_checks(): void {
        [, $context] = $this->fixture();
        $fs = get_file_storage();
        $record = ['contextid' => $context->id, 'component' => 'mod_stage', 'filearea' => 'test', 'itemid' => 0,
            'filepath' => '/'];
        $notpdf = $fs->create_file_from_string($record + ['filename' => 'gabarit.pdf'], 'Ceci n\'est pas un PDF');
        $this->assertFalse(stage_convention_template_readable($notpdf));

        // Après l'appel ci-dessus, qui doit lui-même charger TCPDF : un gabarit valide est accepté.
        $pdf = new \pdf();
        $pdf->AddPage();
        $pdf->Write(5, 'Articles');
        $realpdf = $fs->create_file_from_string($record + ['filename' => 'vrai.pdf'], $pdf->Output('', 'S'));
        $this->assertTrue(stage_convention_template_readable($realpdf));

        $png = $fs->create_file_from_string($record + ['filename' => 'logo.png'], base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        ));
        $this->assertTrue(stage_convention_logo_valid($png));
        $fake = $fs->create_file_from_string($record + ['filename' => 'faux.png'], 'GIF89a');
        $this->assertFalse(stage_convention_logo_valid($fake));
    }

    /**
     * B3 : les noms destinés au texte brut ne contiennent pas d'entités HTML.
     */
    public function test_plain_string_has_no_entities(): void {
        $this->resetAfterTest();
        $this->assertSame("Chirurgie & soins d'urgence", stage_plain_string("Chirurgie & soins d'urgence"));
    }

    /**
     * A6 : le bouton d'action envoie en POST ses paramètres et la clé de session.
     */
    public function test_post_button(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $html = stage_post_button(new \moodle_url('/mod/stage/themes.php', ['id' => 3, 'action' => 'delete']), 'Supprimer');
        $this->assertStringContainsString('method="post"', $html);
        $this->assertStringContainsString('name="action" value="delete"', $html);
        $this->assertStringContainsString('name="sesskey" value="' . sesskey() . '"', $html);
        $this->assertStringNotContainsString('sesskey=', $html);
    }

    /**
     * F2 : le service web ne renvoie que les stages de l'utilisateur et refuse qui n'a pas la
     * capacité d'étudiant.
     */
    public function test_get_my_stages_returns_own_entries_only(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/stage/classes/external/get_my_stages.php');
        [$stage, , $student, $teacher, $theme, $generator] = $this->fixture();
        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($other->id, $stage->course, 'student');
        $mine = $generator->create_entry($stage, $student->id, $theme);
        $generator->create_entry($stage, $other->id, $theme);

        $this->setUser($student);
        $result = \external_api::clean_returnvalue(
            external\get_my_stages::execute_returns(),
            external\get_my_stages::execute($stage->cmid)
        );
        $this->assertCount(1, $result);
        $this->assertEquals($mine->id, $result[0]['id']);

        $this->setUser($teacher);
        $this->expectException(\required_capability_exception::class);
        external\get_my_stages::execute($stage->cmid);
    }

    /**
     * E2, E3 : l'export d'un étudiant contient ses jours effectifs et ses référents ; la
     * suppression d'un enseignant emporte ses réponses au questionnaire de l'enseignant.
     */
    public function test_privacy_exports_workdays_and_deletes_teacher_answers(): void {
        global $DB;
        [$stage, $context, $student, $teacher, $theme, $generator] = $this->fixture();
        $entry = $generator->create_entry($stage, $student->id, $theme);
        $days = stage_get_period_days((object) ['datestart' => $entry->datestart, 'dateend' => $entry->dateend]);
        stage_set_entry_workdays($entry->id, array_slice($days, 0, 3));
        $teacherq = $this->create_question($stage, $theme, 'teacher');
        stage_apply_student_eval($entry, 'Bilan');
        stage_save_answers($entry->id, [$teacherq->id => $teacherq], [$teacherq->id => 'Très bon stage']);
        stage_apply_teacher_eval($entry, $teacher->id);

        $approved = new \core_privacy\local\request\approved_contextlist($student, 'mod_stage', [$context->id]);
        privacy\provider::export_user_data($approved);
        $writer = \core_privacy\local\request\writer::with_context($context);
        $subcontext = [get_string('mystages', 'mod_stage'), (string) $entry->id];
        $this->assertCount(3, $writer->get_data(array_merge($subcontext, [get_string('workdays', 'mod_stage')]))->workdays);
        $this->assertSame([fullname($teacher)], $writer->get_data([get_string('referentteachers', 'mod_stage')])->teachers);

        privacy\provider::delete_data_for_user(
            new \core_privacy\local\request\approved_contextlist($teacher, 'mod_stage', [$context->id])
        );
        $this->assertTrue($DB->record_exists('stage_entry', ['id' => $entry->id]));
        $this->assertNull($DB->get_field('stage_entry', 'teacherid', ['id' => $entry->id]));
        $this->assertFalse($DB->record_exists('stage_answer', ['entryid' => $entry->id, 'questionid' => $teacherq->id]));
    }
}
