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
 * Formulaire unique des notifications (DEVE) : activation de l'évaluation par le maître de stage
 * et personnalisation du sujet et du corps de chacun des e-mails définis dans
 * stage_get_email_definitions(), tout enregistré en une fois. Laisser les deux champs d'un
 * e-mail vides revient au texte par défaut (voir stage_resolve_email_text()).
 *
 * Données attendues dans customdata :
 * - definitions : clé => définition (stage_get_email_definitions()) ;
 * - customized : clés des e-mails déjà personnalisés, dont la section est ouverte et signalée.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notifications_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;
        $customized = $this->_customdata['customized'] ?? [];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'settingshdr', get_string('generalsettings', 'mod_stage'));
        $mform->setExpanded('settingshdr', true);
        $mform->addElement('advcheckbox', 'tutorevaluationenabled', get_string('tutorevaluationenabled', 'mod_stage'));
        $mform->addHelpButton('tutorevaluationenabled', 'tutorevaluationenabled', 'mod_stage');

        $mform->addElement('header', 'emailshdr', get_string('notificationssettings', 'mod_stage'));
        $mform->setExpanded('emailshdr', true);
        $mform->addElement('static', 'emailsintro', '', get_string('notificationssettings_help', 'mod_stage'));

        foreach ($this->_customdata['definitions'] ?? [] as $key => $definition) {
            $iscustom = in_array($key, $customized, true);
            $label = $definition['label'];
            if ($iscustom) {
                $label .= ' (' . get_string('emailcustomized', 'mod_stage') . ')';
            }
            $mform->addElement('header', 'email_' . $key . '_hdr', $label);
            $mform->setExpanded('email_' . $key . '_hdr', $iscustom);

            $mform->addElement('text', 'subject_' . $key, get_string('emailsubject', 'mod_stage'), ['size' => '80']);
            $mform->setType('subject_' . $key, PARAM_TEXT);

            $mform->addElement('textarea', 'body_' . $key, get_string('emailbody', 'mod_stage'), ['rows' => 6, 'cols' => 80]);
            $mform->setType('body_' . $key, PARAM_RAW);

            $varlist = implode(', ', array_map(fn($var) => '{{' . $var . '}}', $definition['vars']));
            $mform->addElement('static', 'vars_' . $key, '', get_string('emailavailablevars', 'mod_stage', $varlist));
            $mform->addElement('static', 'reset_' . $key, '', get_string('emailresettodefault', 'mod_stage'));
        }

        $this->add_action_buttons(false, get_string('savechanges'));
    }
}
