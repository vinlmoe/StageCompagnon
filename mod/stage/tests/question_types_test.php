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
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/evallist_edit_form.php');

/**
 * Types de question des listes d'évaluation ajoutés aux choix, notes et commentaires : cases à
 * cocher, oui/non, nombre, date et texte court. Saisie, contrôle, affichage et export.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_question_types
 * @covers     ::stage_question_type_has_options
 * @covers     ::stage_normalize_answer
 * @covers     ::stage_answer_is_valid
 * @covers     ::stage_validate_answers
 * @covers     ::stage_render_question_fields
 * @covers     ::stage_render_answers_readonly
 * @covers     ::stage_format_answer
 * @covers     ::stage_answer_export_values
 * @covers     ::stage_extract_request_answers
 * @covers     ::stage_request_question_form_data
 * @covers     ::stage_save_answers
 * @covers     ::stage_question_type_change_allowed
 * @covers     ::stage_question_param_type
 */
final class question_types_test extends \advanced_testcase {
    /**
     * Question d'un type donné.
     *
     * @param string $qtype
     * @param int $required
     * @param string|null $options
     * @return \stdClass
     */
    private function question(string $qtype, int $required = 1, ?string $options = null): \stdClass {
        return (object) [
            'id' => 21, 'qtype' => $qtype, 'name' => 'Question', 'nameen' => null,
            'options' => $options, 'optionsen' => null, 'required' => $required,
        ];
    }

    /**
     * Vérifie qu'une réponse est refusée.
     *
     * @param \stdClass $question
     * @param string $value
     */
    private function assert_refused(\stdClass $question, string $value): void {
        try {
            stage_validate_answers([$question], [$question->id => $value]);
            $this->fail("La réponse « $value » aurait dû être refusée ({$question->qtype}).");
        } catch (\moodle_exception $e) {
            $this->assertSame('errorinvalidanswer', $e->errorcode);
        }
    }

    /**
     * Les nouveaux types sont proposés dans le formulaire des listes, et tiennent dans la colonne
     * qtype ; seuls les choix et les cases à cocher demandent des options.
     */
    public function test_types_are_offered(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $types = array_keys(stage_question_types());
        $this->assertSame(['choice', 'checkbox', 'yesno', 'rating', 'number', 'date', 'shorttext', 'text'], $types);
        foreach ($types as $qtype) {
            $this->assertLessThanOrEqual(10, strlen($qtype));
            $this->assertNotSame('', get_string('qtype_' . $qtype, 'mod_stage'));
        }
        $this->assertTrue(stage_question_type_has_options('choice'));
        $this->assertTrue(stage_question_type_has_options('checkbox'));
        $this->assertFalse(stage_question_type_has_options('yesno'));

        $form = new \mod_stage\form\evallist_edit_form(null, ['editing' => true, 'evaltype' => 'student']);
        $mform = (fn() => $this->_form)->call($form);
        $select = $mform->getElement('questionqtype[0]');
        $values = array_map(fn($option) => $option['attr']['value'], $select->_options);
        $this->assertSame($types, $values);
    }

    /**
     * Des cases à cocher sans option sont refusées à l'enregistrement de la liste.
     */
    public function test_checkbox_needs_options(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = new \mod_stage\form\evallist_edit_form(null, ['editing' => true, 'evaltype' => 'student']);
        $data = [
            'name' => 'Liste',
            'questionid' => [0, 0],
            'questionqtype' => ['checkbox', 'yesno'],
            'questionname' => ['Activités', 'Satisfait ?'],
            'questionoptions' => ['', ''],
        ];
        $errors = $form->validation($data, []);
        $this->assertArrayHasKey('questionoptions[0]', $errors);
        $this->assertArrayNotHasKey('questionoptions[1]', $errors);
    }

    /**
     * Normalisation : cases cochées une par ligne, nombre à point décimal, textes rognés.
     */
    public function test_answers_are_normalized(): void {
        $this->assertSame("A\nC", stage_normalize_answer($this->question('checkbox'), ['A', ' C ', '', 'A']));
        $this->assertSame('12.5', stage_normalize_answer($this->question('number'), ' 12,5 '));
        $this->assertSame('1500', stage_normalize_answer($this->question('number'), '1 500'));
        $this->assertSame('douze', stage_normalize_answer($this->question('number'), 'douze'));
        $this->assertSame('Lyon', stage_normalize_answer($this->question('shorttext'), '  Lyon '));
        $this->assertSame('2026-03-01', stage_normalize_answer($this->question('date'), '2026-03-01 '));
    }

    /**
     * Contrôle : options proposées, oui/non, nombres, dates existantes, texte court borné.
     */
    public function test_answers_are_checked(): void {
        $checkbox = $this->question('checkbox', 1, "Chirurgie\nMédecine\nImagerie");
        stage_validate_answers([$checkbox], [$checkbox->id => "Chirurgie\nImagerie"]);
        $this->assert_refused($checkbox, "Chirurgie\nDentisterie");
        $this->assert_refused($checkbox, '');

        $yesno = $this->question('yesno');
        stage_validate_answers([$yesno], [$yesno->id => 'yes']);
        stage_validate_answers([$yesno], [$yesno->id => 'no']);
        $this->assert_refused($yesno, 'peut-être');

        $number = $this->question('number');
        foreach (['0', '-3', '12.5', '1e3'] as $value) {
            stage_validate_answers([$number], [$number->id => $value]);
        }
        $this->assert_refused($number, 'douze');
        $this->assert_refused($number, '1e999');

        $date = $this->question('date');
        stage_validate_answers([$date], [$date->id => '2024-02-29']);
        foreach (['2023-02-29', '01/03/2026', '2026-3-1'] as $value) {
            $this->assert_refused($date, $value);
        }

        $shorttext = $this->question('shorttext');
        stage_validate_answers([$shorttext], [$shorttext->id => str_repeat('é', 255)]);
        $this->assert_refused($shorttext, str_repeat('é', 256));
        $this->assert_refused($shorttext, "deux\nlignes");

        // Une réponse vide reste permise pour une question facultative.
        foreach (['checkbox', 'yesno', 'number', 'date', 'shorttext'] as $qtype) {
            stage_validate_answers([$this->question($qtype, 0, 'A')], [21 => '']);
        }
    }

    /**
     * Une valeur qui n'est pas une chaîne (tableau falsifié lu comme invalide) est refusée comme
     * réponse invalide, pas comme réponse manquante.
     */
    public function test_non_string_answer_is_invalid(): void {
        $question = $this->question('yesno', 0);
        try {
            stage_validate_answers([$question], [$question->id => null]);
            $this->fail('Une réponse non textuelle aurait dû être refusée.');
        } catch (\moodle_exception $e) {
            $this->assertSame('errorinvalidanswer', $e->errorcode);
        }
    }

    /**
     * Types fermés lus sans nettoyage (une option « <b>Urgences</b> » reste choisissable), textes
     * libres nettoyés ; changements de type permis seulement s'ils gardent les réponses lisibles.
     */
    public function test_param_types_and_type_changes(): void {
        foreach (['choice', 'checkbox', 'yesno', 'rating', 'number', 'date'] as $qtype) {
            $this->assertSame(PARAM_RAW, stage_question_param_type($qtype));
        }
        $this->assertSame(PARAM_TEXT, stage_question_param_type('text'));
        $this->assertSame(PARAM_TEXT, stage_question_param_type('shorttext'));
        $this->assertSame(PARAM_TEXT, stage_question_param_type('inconnu'));

        $checkbox = $this->question('checkbox', 1, "Chirurgie\n<b>Urgences</b>");
        stage_validate_answers([$checkbox], [$checkbox->id => '<b>Urgences</b>']);

        $this->assertTrue(stage_question_type_change_allowed('choice', 'checkbox'));
        $this->assertTrue(stage_question_type_change_allowed('shorttext', 'text'));
        $this->assertTrue(stage_question_type_change_allowed('rating', 'number'));
        $this->assertTrue(stage_question_type_change_allowed('date', 'date'));
        $this->assertFalse(stage_question_type_change_allowed('checkbox', 'choice'));
        $this->assertFalse(stage_question_type_change_allowed('text', 'shorttext'));
        $this->assertFalse(stage_question_type_change_allowed('text', 'yesno'));
    }

    /**
     * Saisie : cases à cocher (tableau), boutons oui/non, champs nombre, date et texte court,
     * pré-remplis avec la réponse enregistrée.
     */
    public function test_fields_are_rendered(): void {
        $checkbox = $this->question('checkbox', 1, "A\nB\nC");
        $html = stage_render_question_fields([$checkbox], [21 => (object) ['answertext' => "A\nC"]]);
        $this->assertSame(3, substr_count($html, 'type="checkbox"'));
        $this->assertStringContainsString('name="q_21[]"', $html);
        $this->assertMatchesRegularExpression('/value="C"[^>]*checked="checked"/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="B"[^>]*checked/', $html);
        $this->assertStringNotContainsString('required', $html);

        $html = stage_render_question_fields([$this->question('yesno')], [21 => (object) ['answertext' => 'no']]);
        $this->assertSame(2, substr_count($html, 'type="radio"'));
        $this->assertMatchesRegularExpression('/value="no"[^>]*checked="checked"/', $html);
        $this->assertStringContainsString(get_string('yes'), $html);

        $html = stage_render_question_fields([$this->question('number')], [21 => (object) ['answertext' => '12.5']]);
        $this->assertMatchesRegularExpression('/type="number"[^>]*value="12.5"/', $html);

        $html = stage_render_question_fields([$this->question('date')], [21 => (object) ['answertext' => '2026-03-01']]);
        $this->assertMatchesRegularExpression('/type="date"[^>]*value="2026-03-01"/', $html);

        $html = stage_render_question_fields([$this->question('shorttext')], [21 => (object) ['answertext' => 'a"b']]);
        $this->assertMatchesRegularExpression('/type="text"[^>]*value="a&quot;b"[^>]*maxlength="255"/', $html);
    }

    /**
     * Lecture seule et texte brut : libellés oui/non, cases en liste, date mise en forme.
     */
    public function test_answers_are_displayed(): void {
        $this->resetAfterTest();
        $checkbox = $this->question('checkbox', 1, "A\nB");

        $html = stage_render_answers_readonly([$checkbox], [21 => (object) ['answertext' => "A\nB"]]);
        $this->assertStringContainsString('<li>A</li>', $html);
        $this->assertStringContainsString('<li>B</li>', $html);
        $this->assertSame('A; B', stage_format_answer($checkbox, "A\nB"));

        $html = stage_render_answers_readonly([$this->question('yesno')], [21 => (object) ['answertext' => 'yes']]);
        $this->assertStringContainsString(get_string('yes'), $html);
        $this->assertSame(get_string('no'), stage_format_answer($this->question('yesno'), 'no'));

        $date = $this->question('date');
        $expected = userdate(gmmktime(12, 0, 0, 3, 1, 2026), get_string('strftimedate', 'langconfig'), 'UTC');
        $this->assertSame($expected, stage_format_answer($date, '2026-03-01'));
        $html = stage_render_answers_readonly([$date], [21 => (object) ['answertext' => '2026-03-01']]);
        $this->assertStringContainsString($expected, $html);

        $this->assertSame('4/5', stage_format_answer($this->question('rating'), '4'));
        $this->assertSame('', stage_format_answer($this->question('number'), ''));
    }

    /**
     * Export Excel : texte lisible, note sur 5 et valeur numérique (nombre, oui/non) à part.
     */
    public function test_export_values(): void {
        $this->resetAfterTest();
        $cases = [
            ['rating', '4', '4/5', 4.0, null],
            ['number', '12.5', '12.5', null, 12.5],
            ['number', 'douze', 'douze', null, null],
            ['yesno', 'yes', get_string('yes'), null, 1.0],
            ['yesno', 'no', get_string('no'), null, 0.0],
            ['checkbox', "A\nB", 'A; B', null, null],
            ['shorttext', 'Lyon', 'Lyon', null, null],
            ['text', "Deux\nlignes", "Deux\nlignes", null, null],
            ['choice', 'Oui', 'Oui', null, null],
        ];
        foreach ($cases as [$qtype, $stored, $answer, $score, $number]) {
            $values = stage_answer_export_values((object) ['qtype' => $qtype], $stored);
            $this->assertSame(['answer' => $answer, 'score' => $score, 'number' => $number], $values, $qtype);
        }
        $values = stage_answer_export_values((object) ['qtype' => 'date'], '2026-03-01');
        $this->assertSame(stage_format_answer_date('2026-03-01'), $values['answer']);
        $this->assertNull($values['number']);
    }

    /**
     * Questionnaire de demande : liste à sélection multiple et sélecteur de date convertis vers
     * et depuis la forme enregistrée.
     */
    public function test_request_form_values(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $checkbox = (object) ['id' => 31, 'qtype' => 'checkbox', 'name' => 'Q', 'options' => "A\nB", 'required' => 1];
        $date = (object) ['id' => 32, 'qtype' => 'date', 'name' => 'D', 'options' => null, 'required' => 0];
        $number = (object) ['id' => 33, 'qtype' => 'number', 'name' => 'N', 'options' => null, 'required' => 0];
        $questions = [31 => $checkbox, 32 => $date, 33 => $number];

        $timestamp = make_timestamp(2026, 3, 1);
        $data = [
            stage_request_question_field(5, 31) => ['B', 'A'],
            stage_request_question_field(5, 32) => $timestamp,
            stage_request_question_field(5, 33) => '3,5',
        ];
        $answers = stage_extract_request_answers($data, 5, $questions);
        $this->assertSame([31 => "B\nA", 32 => '2026-03-01', 33 => '3.5'], $answers);
        $this->assertSame([], stage_validate_request_answers($data, 5, $questions));

        $formdata = stage_request_question_form_data(5, $questions, [
            31 => (object) ['answertext' => "B\nA"],
            32 => (object) ['answertext' => '2026-03-01'],
        ]);
        $this->assertSame(['B', 'A'], $formdata[stage_request_question_field(5, 31)]);
        $this->assertSame($timestamp, $formdata[stage_request_question_field(5, 32)]);

        // Date non renseignée : réponse vide, refusée seulement si la question est obligatoire.
        $answers = stage_extract_request_answers([stage_request_question_field(5, 32) => 0], 5, $questions);
        $this->assertSame('', $answers[32]);
        $errors = stage_validate_request_answers([stage_request_question_field(5, 31) => []], 5, $questions);
        $this->assertArrayHasKey(stage_request_question_field(5, 31), $errors);
    }

    /**
     * Les cases cochées soumises en tableau sont enregistrées une par ligne.
     */
    public function test_checkbox_answers_are_saved(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $stage = $this->getDataGenerator()->create_module('stage', ['course' => $this->getDataGenerator()->create_course()]);
        $theme = $gen->create_theme($stage);
        $user = $this->getDataGenerator()->create_user();
        $entry = $gen->create_entry($stage, $user->id, $theme);
        $question = $this->question('checkbox', 1, "A\nB");
        $question->id = $DB->insert_record('stage_question', (object) [
            'stageid' => $stage->id, 'themeid' => 0, 'evaltype' => 'student', 'qtype' => 'checkbox',
            'name' => 'Q', 'options' => "A\nB", 'required' => 1, 'sortorder' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        stage_save_answers($entry->id, [$question], [$question->id => ['A', 'B']]);
        $this->assertSame("A\nB", $DB->get_field('stage_answer', 'answertext', ['entryid' => $entry->id]));
    }
}
