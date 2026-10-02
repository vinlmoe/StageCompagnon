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
require_once($CFG->dirroot . '/mod/stage/classes/form/theme_edit_form.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/evallist_edit_form.php');

/**
 * Tests de la page unique de gestion d'une thématique (theme_edit.php) : années proposées pour
 * les durées par année, et pré-remplissage des lignes répétées du formulaire.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_theme_duration_years
 * @covers     \mod_stage\form\theme_edit_form
 * @covers     \mod_stage\form\evallist_edit_form
 */
final class theme_edit_test extends \advanced_testcase {
    /**
     * La plage d'années suit les mêmes règles que l'ancienne page des durées par année.
     */
    public function test_duration_years(): void {
        $this->assertSame([0], stage_theme_duration_years(0, 0));
        $this->assertSame([3], stage_theme_duration_years(0, 3));
        $this->assertSame([2], stage_theme_duration_years(2, 0));
        $this->assertSame([2, 3, 4], stage_theme_duration_years(2, 4));
        $this->assertSame([2, 3, 4], stage_theme_duration_years(4, 2));
    }

    /**
     * Les valeurs des éléments existants (passées « à plat », comme le fait theme_edit.php)
     * l'emportent sur les valeurs par défaut des lignes répétées, et les lignes vides gardent
     * ces valeurs par défaut.
     */
    public function test_repeated_rows_are_prefilled(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = new class (null, [
            'teachers' => [],
            'checklistcount' => 1,
            'evallists' => [],
            'tutorenabled' => false,
            'filemanageroptions' => [],
        ]) extends \mod_stage\form\theme_edit_form {
            /**
             * Accès au formulaire QuickForm sous-jacent.
             *
             * @return \MoodleQuickForm
             */
            public function get_mform(): \MoodleQuickForm {
                return $this->_form;
            }
        };
        $form->set_data([
            'checklistid[0]' => 12,
            'checklistsortorder[0]' => 5,
        ]);
        $mform = $form->get_mform();
        $this->assertEquals(12, $mform->getElement('checklistid[0]')->getValue());
        $this->assertEquals(5, $mform->getElement('checklistsortorder[0]')->getValue());
        $this->assertEquals(0, $mform->getElement('checklistsortorder[1]')->getValue());

        // Même mécanique pour les questions d'une liste d'évaluation (evallist_edit.php).
        $form = new class (null, [
            'editing' => true,
            'evaltype' => 'student',
            'questioncount' => 1,
            'questioninfo' => [0 => 'Info'],
            'reusable' => [],
        ]) extends \mod_stage\form\evallist_edit_form {
            /**
             * Accès au formulaire QuickForm sous-jacent.
             *
             * @return \MoodleQuickForm
             */
            public function get_mform(): \MoodleQuickForm {
                return $this->_form;
            }
        };
        $form->set_data([
            'questionid[0]' => 34,
            'questionqtype[0]' => 'choice',
            'questionrequired[0]' => 0,
            'questionsortorder[0]' => 7,
        ]);
        $mform = $form->get_mform();

        $this->assertEquals(34, $mform->getElement('questionid[0]')->getValue());
        $this->assertEquals(['choice'], $mform->getElement('questionqtype[0]')->getValue());
        $this->assertEmpty($mform->getElement('questionrequired[0]')->getValue());
        $this->assertEquals(7, $mform->getElement('questionsortorder[0]')->getValue());

        // Ligne vide : valeurs par défaut.
        $this->assertEquals(['text'], $mform->getElement('questionqtype[1]')->getValue());
        $this->assertEquals(1, $mform->getElement('questionrequired[1]')->getValue());
        // Liste non destinée au maître de stage : pas de version anglaise.
        $this->assertFalse($mform->elementExists('questionnameen[0]'));
    }
}
