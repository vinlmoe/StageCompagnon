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
 * Formulaire d'upload des deux logos affichés sur la page 1 de toutes les conventions du stage
 * (haut gauche / haut droit), par la DEVE. Un seul jeu de logos pour l'ensemble du stage.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class convention_logos_form extends \moodleform {

    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $filemanageroptions = [
            'subdirs' => 0,
            'maxfiles' => 1,
            'maxbytes' => 2 * 1024 * 1024,
            'accepted_types' => ['.png'],
        ];

        $mform->addElement('filemanager', 'logoleft', get_string('conventionlogoleft', 'mod_stage'), null,
            $filemanageroptions);
        $mform->addElement('filemanager', 'logoright', get_string('conventionlogoright', 'mod_stage'), null,
            $filemanageroptions);

        $this->add_action_buttons();
    }
}
