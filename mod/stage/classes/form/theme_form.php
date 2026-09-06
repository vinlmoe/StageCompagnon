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
 * Formulaire de création / édition d'une thématique de stage (DEVE).
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class theme_form extends \moodleform {

    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'themeid');
        $mform->setType('themeid', PARAM_INT);

        $mform->addElement('text', 'name', get_string('theme', 'mod_stage'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('textarea', 'description', get_string('description'));
        $mform->setType('description', PARAM_TEXT);

        $mform->addElement('advcheckbox', 'mandatory', get_string('mandatory', 'mod_stage'));

        $mform->addElement('text', 'requiredduration', get_string('requiredduration', 'mod_stage'));
        $mform->setType('requiredduration', PARAM_INT);
        $mform->setDefault('requiredduration', 0);
        $mform->addHelpButton('requiredduration', 'requiredduration', 'mod_stage');

        $mform->addElement('select', 'minstudyyear', get_string('minstudyyear', 'mod_stage'), stage_studyyear_options());
        $mform->setDefault('minstudyyear', 0);

        $mform->addElement('select', 'maxstudyyear', get_string('maxstudyyear', 'mod_stage'), stage_studyyear_options());
        $mform->setDefault('maxstudyyear', 0);

        $mform->addElement('text', 'sortorder', get_string('sortorder', 'mod_stage'));
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);

        $mform->addElement('advcheckbox', 'visible', get_string('visible'));
        $mform->setDefault('visible', 1);

        $mform->addElement('advcheckbox', 'tutorevaluationenabled', get_string('tutorevaluationenabledtheme', 'mod_stage'));
        $mform->setDefault('tutorevaluationenabled', 1);
        $mform->addHelpButton('tutorevaluationenabled', 'tutorevaluationenabledtheme', 'mod_stage');

        $mform->addElement('select', 'reportmode', get_string('reportmode', 'mod_stage'),
            stage_report_mode_options());
        $mform->setDefault('reportmode', STAGE_REPORT_NONE);
        $mform->addHelpButton('reportmode', 'reportmode', 'mod_stage');

        $this->add_action_buttons();
    }

    /**
     * Validates that the minimum study year does not exceed the maximum one, unless either
     * bound is left unspecified (0 = toutes années).
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!empty($data['minstudyyear']) && !empty($data['maxstudyyear'])
                && $data['minstudyyear'] > $data['maxstudyyear']) {
            $errors['maxstudyyear'] = get_string('studyyearrange_error', 'mod_stage');
        }
        return $errors;
    }
}
