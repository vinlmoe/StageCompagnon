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
 * Formulaire d'annulation d'un stage par la DEVE, à tout moment quel que soit son statut actuel :
 * la saisie est conservée telle quelle, seul un commentaire obligatoire expliquant le motif est
 * demandé.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cancel_entry_form extends \moodleform {

    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'entryid');
        $mform->setType('entryid', PARAM_INT);

        $mform->addElement('static', 'studentname', get_string('student', 'mod_stage'),
            $this->_customdata['studentname'] ?? '');

        $mform->addElement('textarea', 'cancelcomment', get_string('cancelcomment', 'mod_stage'),
            ['rows' => 4, 'cols' => 60]);
        $mform->setType('cancelcomment', PARAM_TEXT);
        $mform->addRule('cancelcomment', null, 'required', null, 'client');

        $this->add_action_buttons(true, get_string('cancelentry', 'mod_stage'));
    }

    /**
     * Server-side validation : le commentaire est obligatoire.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (trim((string) $data['cancelcomment']) === '') {
            $errors['cancelcomment'] = get_string('required');
        }

        return $errors;
    }
}
