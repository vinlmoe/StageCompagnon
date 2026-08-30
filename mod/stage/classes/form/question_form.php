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

/**
 * Formulaire de création / édition d'une question d'évaluation (DEVE), rattachée à une
 * thématique et à un type d'évaluation (étudiant ou enseignant).
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_form extends \moodleform {

    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;
        $themes = $this->_customdata['themes'] ?? [];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'themeid');
        $mform->setType('themeid', PARAM_INT);
        $mform->addElement('hidden', 'questionid');
        $mform->setType('questionid', PARAM_INT);

        $mform->addElement('select', 'themeids', get_string('assignedthemes', 'mod_stage'), $themes,
            ['multiple' => 'multiple', 'size' => min(8, max(3, count($themes)))]);
        $mform->addRule('themeids', null, 'required', null, 'client');
        $mform->addHelpButton('themeids', 'assignedthemes', 'mod_stage');

        $evaltypeoptions = [
            'student' => get_string('evaltype_student', 'mod_stage'),
            'teacher' => get_string('evaltype_teacher', 'mod_stage'),
        ];
        if (!empty($this->_customdata['tutorenabled'])) {
            $evaltypeoptions['tutor'] = get_string('evaltype_tutor', 'mod_stage');
        }
        $mform->addElement('select', 'evaltype', get_string('evaltype', 'mod_stage'), $evaltypeoptions);

        $mform->addElement('select', 'qtype', get_string('qtype', 'mod_stage'), [
            'choice' => get_string('qtype_choice', 'mod_stage'),
            'text' => get_string('qtype_text', 'mod_stage'),
        ]);

        $mform->addElement('text', 'name', get_string('questionlabel', 'mod_stage'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('textarea', 'options', get_string('choiceoptions', 'mod_stage'),
            ['rows' => 5, 'cols' => 50]);
        $mform->setType('options', PARAM_TEXT);
        $mform->hideIf('options', 'qtype', 'eq', 'text');

        $mform->addElement('advcheckbox', 'required', get_string('questionrequired', 'mod_stage'));
        $mform->setDefault('required', 1);

        $mform->addElement('text', 'sortorder', get_string('sortorder', 'mod_stage'));
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);

        $this->add_action_buttons();
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
        if ($data['qtype'] === 'choice' && trim((string) $data['options']) === '') {
            $errors['options'] = get_string('choiceoptionsrequired', 'mod_stage');
        }
        if (empty($data['themeids'])) {
            $errors['themeids'] = get_string('themesrequired', 'mod_stage');
        }
        return $errors;
    }
}
