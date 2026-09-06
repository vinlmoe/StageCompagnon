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
 * Dépôt par l'étudiant des documents de son rapport de stage, lors de son auto-évaluation.
 *
 * Formulaire distinct de l'auto-évaluation elle-même, avec son propre bouton, comme la sélection
 * des jours de stage effectifs : le dépôt doit fonctionner à l'identique que la thématique
 * définisse un questionnaire ou un simple commentaire libre, et l'étudiant doit pouvoir déposer
 * ses documents en plusieurs fois avant de soumettre son auto-évaluation.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_form extends \moodleform {

    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'entryid');
        $mform->setType('entryid', PARAM_INT);
        // Marqueur repris par entry.php pour distinguer ce formulaire de celui de
        // l'auto-évaluation, les deux étant postés sur la même page.
        $mform->addElement('hidden', 'savereport', 1);
        $mform->setType('savereport', PARAM_INT);

        $mform->addElement('filemanager', 'reportfiles', get_string('reportfiles', 'mod_stage'), null,
            $this->_customdata['filemanageroptions']);
        $mform->addHelpButton('reportfiles', 'reportfiles', 'mod_stage');

        $this->add_action_buttons(false, get_string('savereportfiles', 'mod_stage'));
    }
}
