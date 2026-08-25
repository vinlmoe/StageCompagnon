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
 * Formulaire de création / édition d'un gabarit de convention de stage (DEVE) : un nom et le
 * PDF des pages 2 à 4 (articles juridiques) associé.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class convention_template_form extends \moodleform {

    /**
     * Defines the form fields.
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'templateid');
        $mform->setType('templateid', PARAM_INT);

        $mform->addElement('text', 'name', get_string('conventiontemplatename', 'mod_stage'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('select', 'lang', get_string('conventionlang', 'mod_stage'), stage_convention_lang_options());
        $mform->setDefault('lang', 'fr');

        $mform->addElement('filemanager', 'templatefile', get_string('conventiontemplatefile', 'mod_stage'), null, [
            'subdirs' => 0,
            'maxfiles' => 1,
            'maxbytes' => $CFG->maxbytes,
            'accepted_types' => ['.pdf'],
        ]);

        $this->add_action_buttons();
    }

    /**
     * Validation serveur : un PDF est obligatoire à la création d'un gabarit. La vérification
     * est faite ici car une règle "required" côté client n'est pas fiable sur un filemanager.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $USER;

        $errors = parent::validation($data, $files);

        if (empty($this->_customdata['editing'])) {
            $draftitemid = $data['templatefile'] ?? 0;
            $fs = get_file_storage();
            $usercontext = \context_user::instance($USER->id);
            $hasfile = false;
            foreach ($fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid) as $file) {
                if (!$file->is_directory()) {
                    $hasfile = true;
                    break;
                }
            }
            if (!$hasfile) {
                $errors['templatefile'] = get_string('conventiontemplatefilerequired', 'mod_stage');
            }
        }

        return $errors;
    }
}
