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
 * Questions notées de 1 à 5 (type « rating ») des listes d'évaluation : proposées dans le
 * formulaire de la liste, saisies par boutons radio, contrôlées, affichées en étoiles.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_question_types
 * @covers     ::stage_validate_answers
 * @covers     ::stage_render_question_fields
 * @covers     ::stage_render_answers_readonly
 */
final class question_rating_test extends \advanced_testcase {
    /**
     * Question notée, obligatoire ou non.
     *
     * @param int $required
     * @return \stdClass
     */
    private function rating_question(int $required = 1): \stdClass {
        return (object) [
            'id' => 12, 'qtype' => 'rating', 'name' => 'Accueil', 'nameen' => 'Welcome',
            'options' => null, 'optionsen' => null, 'required' => $required,
        ];
    }

    /**
     * Le type est proposé, avec son libellé, dans le formulaire des listes d'évaluation.
     */
    public function test_rating_is_a_question_type(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertSame(['choice', 'rating', 'text'], array_keys(stage_question_types()));

        $form = new \mod_stage\form\evallist_edit_form(null, ['editing' => true, 'evaltype' => 'student']);
        $mform = (fn() => $this->_form)->call($form);
        $select = $mform->getElement('questionqtype[0]');
        $values = array_map(fn($option) => $option['attr']['value'], $select->_options);
        $this->assertContains('rating', $values);
    }

    /**
     * Seules les notes de 1 à 5 sont acceptées ; une note vide l'est si la question est facultative.
     */
    public function test_answers_are_checked(): void {
        $question = $this->rating_question();
        foreach (['1', '3', '5'] as $value) {
            stage_validate_answers([$question], [$question->id => $value]);
        }
        stage_validate_answers([$this->rating_question(0)], [$question->id => '']);

        foreach (['0', '6', '4.5', 'Bien'] as $value) {
            try {
                stage_validate_answers([$question], [$question->id => $value]);
                $this->fail("La note « $value » aurait dû être refusée.");
            } catch (\moodle_exception $e) {
                $this->assertSame('errorinvalidanswer', $e->errorcode);
            }
        }
    }

    /**
     * Saisie : cinq boutons radio, la note déjà donnée cochée.
     */
    public function test_fields_offer_five_radio_buttons(): void {
        $question = $this->rating_question();
        $html = stage_render_question_fields([$question], [$question->id => (object) ['answertext' => '4']]);

        $this->assertSame(5, substr_count($html, 'type="radio"'));
        $this->assertMatchesRegularExpression('/<input[^>]*value="4"[^>]*checked="checked"/', $html);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*value="3"[^>]*checked/', $html);
        $this->assertStringContainsString(get_string('qtype_rating_scale', 'mod_stage'), $html);
    }

    /**
     * Lecture seule : la note en étoiles, une réponse absente signalée comme telle.
     */
    public function test_readonly_answer_shows_stars(): void {
        $question = $this->rating_question();

        $html = stage_render_answers_readonly([$question], [$question->id => (object) ['answertext' => '4']]);
        $this->assertStringContainsString('stage-stars', $html);
        $this->assertSame(4, substr_count($html, 'stage-star-full'));
        $this->assertStringContainsString('4/5', $html);

        $html = stage_render_answers_readonly([$question], []);
        $this->assertStringNotContainsString('stage-stars', $html);
        $this->assertStringContainsString(get_string('noanswer', 'mod_stage'), $html);
    }
}
