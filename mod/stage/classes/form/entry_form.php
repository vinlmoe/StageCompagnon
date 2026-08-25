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
 * Formulaire de saisie / auto-évaluation d'un stage par l'étudiant.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entry_form extends \moodleform {

    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;
        $themes = $this->_customdata['themes'];
        $locked = !empty($this->_customdata['locked']);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'entryid');
        $mform->setType('entryid', PARAM_INT);

        $options = [];
        foreach ($themes as $theme) {
            $options[$theme->id] = format_string($theme->name)
                . ($theme->mandatory ? ' (' . get_string('mandatory', 'mod_stage') . ')' : '');
        }

        // Informations fixées par la DEVE : l'étudiant les consulte sans pouvoir les modifier.
        // Affichage statique doublé d'un champ caché, et non freeze() : un élément gelé est
        // retiré du formulaire sans que sa règle "required" le soit, ce qui bloque la soumission.
        if ($locked) {
            $mform->addElement('static', 'themeidstatic', get_string('theme', 'mod_stage'),
                $this->_customdata['themename'] ?? '');
            $mform->addElement('hidden', 'themeid');
            $mform->setType('themeid', PARAM_INT);

            $mform->addElement('static', 'structurestatic', get_string('structure', 'mod_stage'),
                s($this->_customdata['structure'] ?? ''));
            $mform->addElement('hidden', 'structure');
            $mform->setType('structure', PARAM_TEXT);

            $mform->addElement('static', 'datestartstatic', get_string('datestart', 'mod_stage'),
                !empty($this->_customdata['datestart']) ? userdate($this->_customdata['datestart'],
                    get_string('strftimedate', 'langconfig')) : '-');
            $mform->addElement('hidden', 'datestart');
            $mform->setType('datestart', PARAM_INT);

            $mform->addElement('static', 'dateendstatic', get_string('dateend', 'mod_stage'),
                !empty($this->_customdata['dateend']) ? userdate($this->_customdata['dateend'],
                    get_string('strftimedate', 'langconfig')) : '-');
            $mform->addElement('hidden', 'dateend');
            $mform->setType('dateend', PARAM_INT);

            $mform->addElement('static', 'declareddurationstatic', get_string('declaredduration', 'mod_stage'),
                $this->_customdata['declaredduration'] ?? 0);
            $mform->addElement('hidden', 'declaredduration');
            $mform->setType('declaredduration', PARAM_INT);
        } else {
            $mform->addElement('select', 'themeid', get_string('theme', 'mod_stage'), $options);
            $mform->addRule('themeid', null, 'required', null, 'client');

            $mform->addElement('text', 'structure', get_string('structure', 'mod_stage'), ['size' => '64']);
            $mform->setType('structure', PARAM_TEXT);

            $mform->addElement('date_selector', 'datestart', get_string('datestart', 'mod_stage'));
            $mform->addElement('date_selector', 'dateend', get_string('dateend', 'mod_stage'));

            $mform->addElement('text', 'declaredduration', get_string('declaredduration', 'mod_stage'));
            $mform->setType('declaredduration', PARAM_INT);
            $mform->addRule('declaredduration', null, 'required', null, 'client');
        }

        $mform->addElement('editor', 'studentselfeval', get_string('studentselfeval', 'mod_stage'));
        $mform->setType('studentselfeval', PARAM_RAW);

        $this->add_action_buttons();
    }
}
