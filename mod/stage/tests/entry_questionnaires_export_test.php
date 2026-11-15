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
require_once($CFG->dirroot . '/mod/stage/classes/pdf/questionnaires_pdf.php');

/**
 * Tests de l'export des questionnaires d'un stage en Excel ou en PDF (entry_questionnaires_export.php).
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_get_entry_questionnaires
 * @covers     ::stage_get_entry_questionnaires_table
 * @covers     ::stage_get_entry_questionnaires_identity
 * @covers     ::stage_can_export_entry_questionnaires
 * @covers     \mod_stage\pdf\questionnaires_pdf
 */
final class entry_questionnaires_export_test extends \advanced_testcase {
    /** @var \stdClass */
    private $course;

    /** @var \stdClass */
    private $stage;

    /** @var \stdClass */
    private $theme;

    /** @var \mod_stage_generator */
    private $generator;

    /**
     * Prépare une activité et une thématique.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->stage = $this->getDataGenerator()->create_module('stage', ['course' => $this->course]);
        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $this->theme = $this->generator->create_theme($this->stage);
    }

    /**
     * Crée une question, la range dans une liste du type donné choisie par la thématique.
     *
     * @param string $evaltype
     * @param string $qtype
     * @param string $name
     * @return \stdClass
     */
    private function question(string $evaltype, string $qtype, string $name): \stdClass {
        global $DB;

        $id = $DB->insert_record('stage_question', (object) [
            'stageid' => $this->stage->id, 'themeid' => $this->theme->id, 'evaltype' => $evaltype, 'qtype' => $qtype,
            'name' => $name, 'options' => '', 'required' => 0, 'sortorder' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $this->generator->create_evallist($this->stage, $evaltype, [$id], [$this->theme], 'Liste ' . $evaltype);
        return $DB->get_record('stage_question', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Enregistre une réponse.
     *
     * @param \stdClass $entry
     * @param \stdClass $question
     * @param string $text
     */
    private function answer(\stdClass $entry, \stdClass $question, string $text): void {
        global $DB;

        $DB->insert_record('stage_answer', (object) [
            'entryid' => $entry->id, 'questionid' => $question->id, 'answertext' => $text,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Tous les questionnaires remplis figurent, dans l'ordre du circuit, avec leurs réponses en
     * clair, leurs valeurs numériques et le commentaire libre de l'enseignant.
     */
    public function test_collects_every_questionnaire_in_order(): void {
        global $DB;

        $student = $this->getDataGenerator()->create_user(['firstname' => 'Alice', 'lastname' => 'Martin']);
        $teacher = $this->getDataGenerator()->create_user(['firstname' => 'Paul', 'lastname' => 'Durand']);
        $entry = $this->generator->create_entry($this->stage, $student->id, $this->theme);

        $request = $this->question('request', 'yesno', 'Avez-vous un logement ?');
        $self = $this->question('student', 'rating', 'Autonomie');
        $teacherq = $this->question('teacher', 'number', 'Nombre de gardes');
        $tutor = $this->question('tutor', 'text', 'Appréciation');

        $this->answer($entry, $request, 'yes');
        $this->answer($entry, $self, '4');
        $this->answer($entry, $teacherq, '3');
        $DB->update_record('stage_entry', (object) [
            'id' => $entry->id, 'teacherid' => $teacher->id, 'teachertime' => time(),
            'teachereval' => 'Très bon stage.',
        ]);
        $entry = $DB->get_record('stage_entry', ['id' => $entry->id], '*', MUST_EXIST);

        // Évaluation du maître de stage non sollicitée par l'activité : absente.
        $sections = stage_get_entry_questionnaires($this->stage, $entry, $this->theme);
        $this->assertSame(['request', 'student', 'teacher'], array_column($sections, 'type'));

        $DB->set_field('stage', 'tutorevaluationenabled', 1, ['id' => $this->stage->id]);
        $stage = $DB->get_record('stage', ['id' => $this->stage->id], '*', MUST_EXIST);
        $theme = $DB->get_record('stage_theme', ['id' => $this->theme->id], '*', MUST_EXIST);
        $sections = stage_get_entry_questionnaires($stage, $entry, $theme);
        $this->assertSame(['request', 'student', 'teacher', 'tutor'], array_column($sections, 'type'));

        [$requestsection, $selfsection, $teachersection, $tutorsection] = $sections;
        $this->assertSame(stage_question_yesno_values()['yes'], $requestsection['items'][0]['answer']);
        $this->assertSame(1.0, $requestsection['items'][0]['number']);
        $this->assertSame(4.0, $selfsection['items'][0]['score']);
        $this->assertSame('Alice Martin', $selfsection['respondent']);
        $this->assertSame(3.0, $teachersection['items'][0]['number']);
        $this->assertSame('Paul Durand', $teachersection['respondent']);
        $this->assertSame('Très bon stage.', $teachersection['comment']);
        // Question sans réponse : listée, réponse vide.
        $this->assertSame('', $tutorsection['items'][0]['answer']);

        // Tableau Excel : une ligne par question, plus une pour le commentaire libre.
        $table = stage_get_entry_questionnaires_table($sections);
        $this->assertCount(8, $table['columns']);
        $this->assertCount(5, $table['rows']);
        $commentrow = $table['rows'][3];
        $this->assertSame(get_string('entryquestionnairecomment', 'mod_stage'), $commentrow[3]);
        $this->assertSame('Très bon stage.', $commentrow[4]);
        $this->assertSame(4.0, $table['rows'][1][5]);

        $identity = stage_get_entry_questionnaires_identity($entry, $theme);
        $this->assertSame('Alice Martin', $identity[get_string('student', 'mod_stage')]);
    }

    /**
     * Une question retirée de la liste après coup reste dans l'export avec sa réponse.
     */
    public function test_keeps_answers_to_removed_questions(): void {
        global $DB;

        $student = $this->getDataGenerator()->create_user();
        $entry = $this->generator->create_entry($this->stage, $student->id, $this->theme);
        $question = $this->question('student', 'shorttext', 'Lieu');
        $this->answer($entry, $question, 'Clinique');
        $DB->set_field('stage_theme', 'studentlistid', 0, ['id' => $this->theme->id]);

        $sections = stage_get_entry_questionnaires($this->stage, $entry, $this->theme);
        $this->assertCount(1, $sections);
        $this->assertSame('Clinique', $sections[0]['items'][0]['answer']);
    }

    /**
     * Le PDF se génère, y compris pour un stage sans aucun questionnaire.
     */
    public function test_pdf_is_generated(): void {
        $student = $this->getDataGenerator()->create_user();
        $entry = $this->generator->create_entry($this->stage, $student->id, $this->theme);
        $question = $this->question('student', 'text', 'Bilan <b>du</b> stage & suite');
        $this->answer($entry, $question, "Ligne 1\nLigne 2");

        $labels = [
            'question' => 'Question', 'answer' => 'Réponse', 'comment' => 'Commentaire', 'respondent' => 'Répondant',
            'noanswer' => 'Non renseigné', 'empty' => 'Aucun questionnaire',
        ];
        foreach ([stage_get_entry_questionnaires($this->stage, $entry, $this->theme), []] as $sections) {
            $pdf = new \mod_stage\pdf\questionnaires_pdf('P', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->generate([
                'title' => 'Questionnaires', 'generatedon' => 'Aujourd\'hui',
                'identity' => stage_get_entry_questionnaires_identity($entry, $this->theme),
                'sections' => $sections, 'labels' => $labels,
            ]);
            $this->assertStringStartsWith('%PDF-', $pdf->Output('', 'S'));
        }
    }

    /**
     * L'export est ouvert à la DEVE, à l'enseignant référent de l'étudiant et au responsable de la
     * thématique ; ni à un autre enseignant, ni à l'étudiant.
     */
    public function test_access_rules(): void {
        $cm = get_coursemodule_from_instance('stage', $this->stage->id);
        $context = \context_module::instance($cm->id);
        $dg = $this->getDataGenerator();

        $student = $dg->create_and_enrol($this->course, 'student');
        $deve = $dg->create_and_enrol($this->course, 'editingteacher');
        $referent = $dg->create_and_enrol($this->course, 'teacher');
        $responsable = $dg->create_and_enrol($this->course, 'teacher');
        $other = $dg->create_and_enrol($this->course, 'teacher');
        $this->generator->assign_teacher($this->stage, $student->id, $referent->id);
        stage_set_theme_teachers($this->theme->id, [$responsable->id]);
        $entry = $this->generator->create_entry($this->stage, $student->id, $this->theme);

        $this->assertTrue(stage_can_export_entry_questionnaires($this->stage, $entry, $context, $deve->id));
        $this->assertTrue(stage_can_export_entry_questionnaires($this->stage, $entry, $context, $referent->id));
        $this->assertTrue(stage_can_export_entry_questionnaires($this->stage, $entry, $context, $responsable->id));
        $this->assertFalse(stage_can_export_entry_questionnaires($this->stage, $entry, $context, $other->id));
        $this->assertFalse(stage_can_export_entry_questionnaires($this->stage, $entry, $context, $student->id));
    }
}
