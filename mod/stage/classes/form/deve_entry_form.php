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
 * Formulaire d'enregistrement / édition unitaire d'un stage par la DEVE, pour un étudiant donné.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class deve_entry_form extends \moodleform {

    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;
        $themes = $this->_customdata['themes'];
        $students = $this->_customdata['students'];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'entryid');
        $mform->setType('entryid', PARAM_INT);

        $studentoptions = [];
        foreach ($students as $student) {
            $studentoptions[$student->id] = fullname($student);
        }

        // Édition d'une saisie existante : l'étudiant n'est plus modifiable. Affichage statique
        // doublé d'un champ caché, et non d'un select gelé : freeze() retire l'élément du
        // formulaire sans lever sa règle "required", ce qui bloque la soumission.
        if (!empty($this->_customdata['lockstudent'])) {
            $mform->addElement('static', 'useridstatic', get_string('student', 'mod_stage'),
                $this->_customdata['studentname'] ?? '');
            $mform->addElement('hidden', 'userid');
            $mform->setType('userid', PARAM_INT);
        } else {
            $mform->addElement('select', 'userid', get_string('student', 'mod_stage'), $studentoptions);
            $mform->addRule('userid', null, 'required', null, 'client');
        }

        $themeoptions = [];
        foreach ($themes as $theme) {
            $themeoptions[$theme->id] = stage_theme_option_label($theme);
        }
        $mform->addElement('select', 'themeid', get_string('theme', 'mod_stage'), $themeoptions);
        $mform->addRule('themeid', null, 'required', null, 'client');

        // La DEVE peut rattacher un stage à n'importe quelle année d'étude.
        $mform->addElement('select', 'studyyear', get_string('studyyear', 'mod_stage'), stage_studyyear_options());
        $mform->addRule('studyyear', null, 'required', null, 'client');

        $mform->addElement('text', 'structure', get_string('structure', 'mod_stage'), ['size' => '64']);
        $mform->setType('structure', PARAM_TEXT);

        // Un stage complémentaire (EP) ne compte pas dans le décompte des stages obligatoires de
        // l'année (voir stage_get_student_year_progress()), mais est affiché à part.
        $mform->addElement('select', 'stagetype', get_string('conventionstagetype', 'mod_stage'),
            stage_convention_stagetype_options());
        $mform->setDefault('stagetype', 'obligatoire');

        $mform->addElement('advcheckbox', 'abroad', get_string('abroad', 'mod_stage'));

        $mform->addElement('text', 'country', get_string('country', 'mod_stage'), ['size' => '32']);
        $mform->setType('country', PARAM_TEXT);
        $mform->hideIf('country', 'abroad', 'notchecked');

        $mform->addElement('text', 'declaredduration', get_string('declaredduration', 'mod_stage'));
        $mform->setType('declaredduration', PARAM_INT);
        $mform->addRule('declaredduration', null, 'required', null, 'client');

        // Dispense de convention : ouvre directement le droit à l'auto-évaluation, sans passer
        // par le circuit de demande/signature de convention (voir STAGE_CONVENTION_EXEMPT).
        $mform->addElement('advcheckbox', 'exemptfromconvention', get_string('exemptfromconvention', 'mod_stage'));
        $mform->addHelpButton('exemptfromconvention', 'exemptfromconvention', 'mod_stage');

        // Les dates du stage se saisissent uniquement sous forme de plages : celles de la saisie
        // en sont déduites (première et dernière date couvertes, voir stage_save_entry_periods()).
        stage_add_period_fields($this, $mform, count($this->_customdata['periods'] ?? []));

        $this->add_action_buttons();
    }

    /**
     * Server-side validation : vérifie la cohérence des plages de dates (au moins une, sans
     * chevauchement) et empêche la création d'un doublon (même étudiant, même thématique, mêmes
     * dates).
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Les dates du stage étant déduites des plages, leur cohérence conditionne le contrôle de
        // doublon ci-dessous, qui s'appuie sur elles.
        $periods = stage_extract_submitted_periods((object) $data);
        $perioderror = stage_validate_periods($periods);
        if ($perioderror !== null) {
            $errors['perioddatestart[0]'] = $perioderror;
            return $errors;
        }

        // L'entryid à exclure vient du customdata (connu côté serveur avant même la construction
        // du formulaire, voir register.php), pas du champ caché soumis par le client : pour une
        // édition, c'est ce qui garantit que la saisie ne se compare jamais à elle-même, quel que
        // soit l'aléa d'un champ caché mal réhydraté.
        $duplicate = stage_entry_is_duplicate(
            $this->_customdata['stageid'],
            $data['userid'],
            $data['themeid'],
            min(array_column($periods, 'datestart')),
            max(array_column($periods, 'dateend')),
            $this->_customdata['entryid'] ?? 0
        );
        if ($duplicate) {
            $errors['themeid'] = get_string('errorduplicateentry', 'mod_stage');
        }

        return $errors;
    }
}
