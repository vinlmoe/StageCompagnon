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
 * Formulaire de passage d'une convention au statut "signée" (DEVE) : le PDF de la convention
 * effectivement signée (scan du document papier) peut être téléversé, facultativement — s'il
 * l'est, il devient alors téléchargeable par l'étudiant, la DEVE et l'enseignant référent.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class convention_sign_form extends \moodleform {

    /**
     * Defines the form fields.
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'entryid');
        $mform->setType('entryid', PARAM_INT);

        $mform->addElement('static', 'studentname', get_string('student', 'mod_stage'),
            $this->_customdata['studentname'] ?? '');

        $mform->addElement('filemanager', 'signedfile', get_string('conventionsignedfile', 'mod_stage'), null, [
            'subdirs' => 0,
            'maxfiles' => 1,
            'maxbytes' => $CFG->maxbytes,
            'accepted_types' => ['.pdf'],
        ]);
        $mform->addHelpButton('signedfile', 'conventionsignedfile', 'mod_stage');

        $this->add_action_buttons(true, get_string('conventionmarksigned', 'mod_stage'));
    }
}
