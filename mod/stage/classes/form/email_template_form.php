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
 * Personnalisation du sujet et du corps d'un des e-mails envoyés par l'activité (DEVE), un
 * formulaire par e-mail défini dans stage_get_email_definitions(). Laisser les deux champs vides
 * revient au texte par défaut (voir stage_resolve_email_text()).
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class email_template_form extends \moodleform {

    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;
        $definition = $this->_customdata['definition'];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'emailkey');
        $mform->setType('emailkey', PARAM_ALPHANUMEXT);

        $mform->addElement('header', 'emailheader', $definition['label']);
        $mform->setExpanded('emailheader', false);

        $mform->addElement('text', 'subject', get_string('emailsubject', 'mod_stage'), ['size' => '80']);
        $mform->setType('subject', PARAM_TEXT);

        $mform->addElement('textarea', 'body', get_string('emailbody', 'mod_stage'), ['rows' => 6, 'cols' => 80]);
        $mform->setType('body', PARAM_RAW);

        $varlist = implode(', ', array_map(fn($var) => '{{' . $var . '}}', $definition['vars']));
        $mform->addElement('static', 'emailvars', '', get_string('emailavailablevars', 'mod_stage', $varlist));
        $mform->addElement('static', 'emailreset', '', get_string('emailresettodefault', 'mod_stage'));

        $this->add_action_buttons();
    }
}
