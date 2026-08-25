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
 * Formulaire d'import des thématiques, gabarits de convention, logos et informations
 * d'établissement depuis une autre instance de mod_stage (généralement dans un autre cours).
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class administration_import_form extends \moodleform {

    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;
        $sourceoptions = $this->_customdata['sourceoptions'];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('select', 'sourcestageid', get_string('importsource', 'mod_stage'), $sourceoptions);
        $mform->addRule('sourcestageid', null, 'required', null, 'client');

        $mform->addElement('advcheckbox', 'importthemes', get_string('importthemes', 'mod_stage'));
        $mform->setDefault('importthemes', 1);
        $mform->addElement('advcheckbox', 'importtemplates', get_string('importtemplates', 'mod_stage'));
        $mform->setDefault('importtemplates', 1);
        $mform->addElement('advcheckbox', 'importlogos', get_string('importlogos', 'mod_stage'));
        $mform->setDefault('importlogos', 1);
        $mform->addElement('advcheckbox', 'importestablishment', get_string('importestablishment', 'mod_stage'));
        $mform->setDefault('importestablishment', 1);

        $this->add_action_buttons(true, get_string('import', 'mod_stage'));
    }

    /**
     * Server-side validation : au moins une catégorie doit être cochée, et la source doit être
     * l'une des instances proposées (l'appelant a déjà vérifié que l'utilisateur peut les gérer).
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!isset($this->_customdata['sourceoptions'][$data['sourcestageid']])) {
            $errors['sourcestageid'] = get_string('required');
        }

        if (empty($data['importthemes']) && empty($data['importtemplates']) && empty($data['importlogos'])
                && empty($data['importestablishment'])) {
            $errors['importthemes'] = get_string('importnothingselected', 'mod_stage');
        }

        return $errors;
    }
}
