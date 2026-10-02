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

namespace mod_stage\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

/**
 * Formulaire d'édition d'une liste d'évaluation (DEVE) : son nom, son type d'évaluation (fixé à la
 * création, une thématique choisissant une liste par type) et ses questions, éditables en ligne,
 * avec l'ajout de questions déjà définies dans d'autres listes du même type. Voir
 * evallist_edit.php.
 *
 * Données attendues dans customdata :
 * - editing : la liste existe déjà (son type n'est alors plus modifiable) ;
 * - evaltype : type de la liste existante ;
 * - questioncount : nombre de questions existantes ;
 * - questioninfo : index de ligne => texte informatif (autres listes qui partagent la question) ;
 * - reusable : id => libellé, questions d'autres listes pouvant être ajoutées.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class evallist_edit_form extends \moodleform {
    /** @var int Lignes vides proposées pour ajouter des questions. */
    const BLANK_ROWS = 2;

    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;
        $customdata = $this->_customdata;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'listid');
        $mform->setType('listid', PARAM_INT);
        $mform->addElement('hidden', 'returnurl');
        $mform->setType('returnurl', PARAM_LOCALURL);

        $mform->addElement('header', 'generalhdr', get_string('evallist', 'mod_stage'));
        $mform->setExpanded('generalhdr', true);
        $mform->addElement('text', 'name', get_string('evallistname', 'mod_stage'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', null, 'maxlength', 255, 'client');

        $evaltypes = [
            'student' => get_string('evaltype_student', 'mod_stage'),
            'teacher' => get_string('evaltype_teacher', 'mod_stage'),
            'tutor' => get_string('evaltype_tutor', 'mod_stage'),
        ];
        if (!empty($customdata['editing'])) {
            $mform->addElement(
                'static',
                'evaltypelabel',
                get_string('evaltype', 'mod_stage'),
                $evaltypes[$customdata['evaltype']] ?? ''
            );
        } else {
            $mform->addElement('select', 'evaltype', get_string('evaltype', 'mod_stage'), $evaltypes);
            $mform->addHelpButton('evaltype', 'evallisttype', 'mod_stage');
        }

        $this->define_questions($mform, $customdata);

        $buttons = [
            $mform->createElement('submit', 'submitbutton', get_string('savechanges')),
            $mform->createElement('submit', 'submitandreturn', get_string('evallistsaveandreturn', 'mod_stage')),
            $mform->createElement('cancel'),
        ];
        $mform->addGroup($buttons, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Questions de la liste, éditables en ligne, et ajout de questions d'autres listes.
     *
     * @param \MoodleQuickForm $mform
     * @param array $customdata
     */
    protected function define_questions(\MoodleQuickForm $mform, array $customdata) {
        $mform->addElement('header', 'questionshdr', get_string('evalquestions', 'mod_stage'));
        $mform->setExpanded('questionshdr', true);
        $mform->addElement('static', 'questionsintro', '', get_string('evallistquestionsintro', 'mod_stage'));

        if (!empty($customdata['reusable'])) {
            $mform->addElement(
                'autocomplete',
                'attachquestionids',
                get_string('attachquestions', 'mod_stage'),
                $customdata['reusable'],
                ['multiple' => true, 'noselectionstring' => get_string('noselection', 'form')]
            );
            $mform->addHelpButton('attachquestionids', 'attachquestions', 'mod_stage');
        }

        $qtypeoptions = [
            'choice' => get_string('qtype_choice', 'mod_stage'),
            'text' => get_string('qtype_text', 'mod_stage'),
        ];
        $row = [
            $mform->createElement('static', 'questionrowhdr', '', \html_writer::tag('hr', '')),
            $mform->createElement('static', 'questioninfo', '', ''),
            $mform->createElement('hidden', 'questionid', 0),
            $mform->createElement('select', 'questionqtype', get_string('qtype', 'mod_stage'), $qtypeoptions),
            $mform->createElement('text', 'questionname', get_string('questionlabel', 'mod_stage'), ['size' => 64]),
            $mform->createElement(
                'textarea',
                'questionoptions',
                get_string('choiceoptions', 'mod_stage'),
                ['rows' => 4, 'cols' => 50]
            ),
            $mform->createElement('text', 'questionnameen', get_string('questionlabelen', 'mod_stage'), ['size' => 64]),
            $mform->createElement(
                'textarea',
                'questionoptionsen',
                get_string('choiceoptionsen', 'mod_stage'),
                ['rows' => 4, 'cols' => 50]
            ),
            $mform->createElement('advcheckbox', 'questionrequired', get_string('questionrequired', 'mod_stage')),
            $mform->createElement('text', 'questionsortorder', get_string('sortorder', 'mod_stage'), ['size' => 4]),
            $mform->createElement('advcheckbox', 'questiondelete', get_string('removequestionrow', 'mod_stage')),
        ];
        $options = [
            'questionid' => ['type' => PARAM_INT],
            'questionqtype' => ['default' => 'text'],
            'questionname' => ['type' => PARAM_TEXT],
            'questionoptions' => ['type' => PARAM_TEXT, 'hideif' => ['questionqtype', 'eq', 'text']],
            'questionnameen' => ['type' => PARAM_TEXT],
            'questionoptionsen' => ['type' => PARAM_TEXT, 'hideif' => ['questionqtype', 'eq', 'text']],
            'questionrequired' => ['default' => 1],
            'questionsortorder' => ['type' => PARAM_INT, 'default' => 0],
            'questiondelete' => ['helpbutton' => ['removequestionrow', 'mod_stage']],
        ];
        $repeats = $this->repeat_elements(
            $row,
            (int) ($customdata['questioncount'] ?? 0) + self::BLANK_ROWS,
            $options,
            'questionrepeats',
            'questionaddrows',
            self::BLANK_ROWS,
            get_string('addquestionrows', 'mod_stage'),
            true
        );

        // Version anglaise : utile seulement aux questions du maître de stage, dont la convention
        // peut être rédigée en anglais (voir stage_get_entry_convention_lang()).
        $istutor = ($customdata['evaltype'] ?? '') === 'tutor';
        for ($i = 0; $i < $repeats; $i++) {
            if (!empty($customdata['editing']) && !$istutor) {
                $mform->removeElement("questionnameen[$i]");
                $mform->removeElement("questionoptionsen[$i]");
            } else if (empty($customdata['editing'])) {
                $mform->hideIf("questionnameen[$i]", 'evaltype', 'neq', 'tutor');
                $mform->hideIf("questionoptionsen[$i]", 'evaltype', 'neq', 'tutor');
            }
            $info = $customdata['questioninfo'][$i] ?? '';
            $mform->getElement("questioninfo[$i]")->setText(
                $info !== '' ? $info : \html_writer::span(get_string('newquestionrow', 'mod_stage'), 'text-muted')
            );
        }
    }

    /**
     * Server-side validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (trim((string) ($data['name'] ?? '')) === '') {
            $errors['name'] = get_string('required');
        }

        foreach ($data['questionid'] ?? [] as $i => $questionid) {
            if (!empty($data['questiondelete'][$i])) {
                continue;
            }
            $name = trim((string) ($data['questionname'][$i] ?? ''));
            if ($name === '') {
                if (!empty($questionid)) {
                    $errors["questionname[$i]"] = get_string('required');
                }
                continue;
            }
            if (($data['questionqtype'][$i] ?? '') === 'choice' && trim((string) ($data['questionoptions'][$i] ?? '')) === '') {
                $errors["questionoptions[$i]"] = get_string('choiceoptionsrequired', 'mod_stage');
            }
        }

        return $errors;
    }
}
