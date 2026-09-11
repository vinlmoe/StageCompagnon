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
 * Auto-évaluation libre d'un stage par l'étudiant (entry.php), quand la DEVE n'a défini aucune
 * question d'évaluation pour la thématique.
 *
 * Le formulaire ne porte que le commentaire d'auto-évaluation : les caractéristiques du stage
 * (thématique, structure, dates, durée) sont fixées par la DEVE et rappelées au-dessus du
 * formulaire par stage_render_entry_summary(). Elles y figuraient auparavant en champs statiques
 * doublés de champs cachés, que la page ne relisait pas à la soumission.
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

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'entryid');
        $mform->setType('entryid', PARAM_INT);

        $mform->addElement('editor', 'studentselfeval', get_string('studentselfeval', 'mod_stage'));
        $mform->setType('studentselfeval', PARAM_RAW);

        $this->add_action_buttons();
    }
}
