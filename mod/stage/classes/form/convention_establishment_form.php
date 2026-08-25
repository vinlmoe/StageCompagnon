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
 * Formulaire des informations de l'établissement d'enseignement (VetAgro Sup), affichées sur la
 * page 1 de toutes les conventions de ce stage, éditables par la DEVE. Un seul jeu de valeurs
 * pour l'ensemble du stage, comme les logos (voir convention_logos_form.php).
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class convention_establishment_form extends \moodleform {

    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'establishmentname', get_string('conventionestablishmentname', 'mod_stage'),
            ['size' => '64']);
        $mform->setType('establishmentname', PARAM_TEXT);
        $mform->addElement('text', 'establishmentaddress', get_string('conventionestablishmentaddress', 'mod_stage'),
            ['size' => '64']);
        $mform->setType('establishmentaddress', PARAM_TEXT);
        $mform->addElement('text', 'establishmentrepresentative',
            get_string('conventionestablishmentrepresentative', 'mod_stage'), ['size' => '64']);
        $mform->setType('establishmentrepresentative', PARAM_TEXT);
        $mform->addElement('text', 'establishmentrepresentativetitle',
            get_string('conventionestablishmentrepresentativetitle', 'mod_stage'), ['size' => '64']);
        $mform->setType('establishmentrepresentativetitle', PARAM_TEXT);
        $mform->addElement('text', 'establishmentphone', get_string('conventionestablishmentphone', 'mod_stage'));
        $mform->setType('establishmentphone', PARAM_TEXT);
        $mform->addElement('text', 'establishmentemail', get_string('conventionestablishmentemail', 'mod_stage'),
            ['size' => '64']);
        $mform->setType('establishmentemail', PARAM_TEXT);

        $this->add_action_buttons();
    }
}
