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
 * Choix de l'étudiant à transférer et de l'instance de destination (voir transfer.php). Le
 * formulaire ne fait que ce choix : ce qui sera effectivement transféré est ensuite présenté pour
 * confirmation, le transfert n'étant pas réversible.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class transfer_form extends \moodleform {

    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('select', 'userid', get_string('student', 'mod_stage'),
            $this->_customdata['students']);
        $mform->addRule('userid', null, 'required', null, 'client');

        $mform->addElement('select', 'targetstageid', get_string('transfertarget', 'mod_stage'),
            $this->_customdata['targets']);
        $mform->addRule('targetstageid', null, 'required', null, 'client');
        $mform->addHelpButton('targetstageid', 'transfertarget', 'mod_stage');

        $this->add_action_buttons(true, get_string('transferpreview', 'mod_stage'));
    }
}
