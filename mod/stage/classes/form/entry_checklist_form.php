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
 * Formulaire de correction de la check-list d'objectifs d'une saisie de stage, réservé à la DEVE
 * et à l'enseignant référent de l'étudiant (voir stage_can_edit_entry_checklist()) : mêmes champs
 * que ceux renseignés par l'étudiant lors de sa demande de convention, la thématique étant ici
 * déjà fixée par la saisie.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entry_checklist_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'entryid');
        $mform->setType('entryid', PARAM_INT);
        $mform->addElement('hidden', 'returnurl');
        $mform->setType('returnurl', PARAM_LOCALURL);

        stage_add_checklist_fields($mform, [0 => $this->_customdata['checklistitems']]);

        $this->add_action_buttons();
    }

    /**
     * Validation serveur : un objectif laissé décoché doit être justifié, à la correction comme à
     * la saisie initiale.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        return $errors + stage_validate_checklist($data, $this->_customdata['checklistitems']);
    }
}
