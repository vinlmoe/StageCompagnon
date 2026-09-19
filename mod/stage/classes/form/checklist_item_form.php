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
 * Formulaire de création / édition d'un élément de la check-list d'objectifs d'une thématique
 * (DEVE) : l'intitulé que l'étudiant devra cocher lors de sa demande de convention, et la
 * précision facultative affichée sous cet intitulé.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class checklist_item_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'themeid');
        $mform->setType('themeid', PARAM_INT);
        $mform->addElement('hidden', 'itemid');
        $mform->setType('itemid', PARAM_INT);

        $mform->addElement('text', 'name', get_string('checklistitem', 'mod_stage'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement(
            'textarea',
            'description',
            get_string('checklistitemdescription', 'mod_stage'),
            ['rows' => 3, 'cols' => 60]
        );
        $mform->setType('description', PARAM_TEXT);
        $mform->addHelpButton('description', 'checklistitemdescription', 'mod_stage');

        $mform->addElement('text', 'sortorder', get_string('sortorder', 'mod_stage'));
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);

        $this->add_action_buttons();
    }
}
