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
 * Formulaire de dépôt, par la DEVE, des documents d'objectifs de stage d'une thématique : les
 * documents qui décrivent ce qui est attendu de l'étudiant, téléchargeables ensuite par
 * l'étudiant, les enseignants et le maître de stage.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class theme_objectives_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'themeid');
        $mform->setType('themeid', PARAM_INT);

        $mform->addElement(
            'filemanager',
            'objectivefiles',
            get_string('themeobjectivefiles', 'mod_stage'),
            null,
            $this->_customdata['filemanageroptions']
        );
        $mform->addHelpButton('objectivefiles', 'themeobjectivefiles', 'mod_stage');

        $this->add_action_buttons(false, get_string('savechanges'));
    }
}
