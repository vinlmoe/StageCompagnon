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
 * Formulaire d'un organisme proposé pour une thématique (DEVE) : nombre de places, précisions
 * montrées aux étudiants, et coordonnées qui préremplissent la demande de convention de l'étudiant
 * affecté. Seul le nom est obligatoire : ce qui manque sera saisi par l'étudiant dans sa demande.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class placement_offer_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'themeid');
        $mform->setType('themeid', PARAM_INT);
        $mform->addElement('hidden', 'offerid');
        $mform->setType('offerid', PARAM_INT);

        $mform->addElement('header', 'offerhdr', get_string('placementoffer', 'mod_stage'));
        $mform->setExpanded('offerhdr', true);
        $this->add_text('name', 'structure', 64);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'places', get_string('placementplaces', 'mod_stage'), ['size' => 4]);
        $mform->setType('places', PARAM_INT);
        $mform->setDefault('places', 1);
        $mform->addHelpButton('places', 'placementplaces', 'mod_stage');

        $mform->addElement(
            'textarea',
            'description',
            get_string('placementofferdescription', 'mod_stage'),
            ['rows' => 3, 'cols' => 60]
        );
        $mform->setType('description', PARAM_TEXT);
        $mform->addHelpButton('description', 'placementofferdescription', 'mod_stage');

        $mform->addElement('header', 'hosthdr', get_string('conventionhoststructure', 'mod_stage'));
        $mform->setExpanded('hosthdr', true);
        $mform->addElement('static', 'prefillintro', '', get_string('placementofferprefill', 'mod_stage'));
        $this->add_text('address', 'conventionhostaddress', 64);
        $this->add_text('representative', 'conventionhostrepresentative', 64);
        $this->add_text('representativetitle', 'conventionhostrepresentativetitle', 64);
        $this->add_text('service', 'conventionhostservice', 64);
        $this->add_text('phone', 'conventionhostphone');
        $this->add_text('email', 'conventionhostemail', 64);
        $this->add_text('siret', 'conventionsiret', 20);
        $this->add_text('location', 'conventionhostlocation', 64);
        $this->add_text('country', 'placementoffercountry', 32);
        $mform->addHelpButton('country', 'placementoffercountry', 'mod_stage');

        $mform->addElement('header', 'tutorhdr', get_string('conventiontutor', 'mod_stage'));
        $mform->setExpanded('tutorhdr', true);
        $this->add_text('tutorname', 'conventiontutorname', 64);
        $this->add_text('tutorfunction', 'conventiontutorfunction', 64);
        $this->add_text('tutorphone', 'conventiontutorphone');
        $this->add_text('tutoremail', 'conventiontutoremail', 64);

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Ajoute un champ texte court, limité à la longueur de sa colonne.
     *
     * @param string $name Champ de stage_placement_offer.
     * @param string $label Identifiant de la chaîne du libellé.
     * @param int $size Largeur du champ, 0 pour la largeur par défaut.
     */
    protected function add_text($name, $label, $size = 0) {
        static $lengths = null;
        $lengths = $lengths ?? stage_placement_offer_max_lengths();

        $mform = $this->_form;
        $mform->addElement('text', $name, get_string($label, 'mod_stage'), $size ? ['size' => $size] : []);
        $mform->setType($name, PARAM_TEXT);
        // Le SIRET se saisit souvent avec des espaces : sa longueur est contrôlée une fois normalisé.
        if ($name !== 'siret' && isset($lengths[$name])) {
            $mform->addRule($name, get_string('maximumchars', '', $lengths[$name]), 'maxlength', $lengths[$name], 'client');
        }
    }

    /**
     * Validation serveur : nom non vide, au moins autant de places que d'étudiants affectés,
     * SIRET plausible, adresse du tuteur valide (c'est à
     * elle que partira l'invitation à évaluer le stage).
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        global $DB;

        // La règle « obligatoire » accepte un nom fait d'espaces, que l'enregistrement réduirait à vide.
        if (trim((string) ($data['name'] ?? '')) === '') {
            $errors['name'] = get_string('required');
        }
        $places = (int) ($data['places'] ?? 0);
        $assigned = empty($data['offerid']) ? 0 : $DB->count_records('stage_placement_assign', ['offerid' => $data['offerid']]);
        if ($places < 1) {
            $errors['places'] = get_string('placementplacesinvalid', 'mod_stage');
        } else if ($places < $assigned) {
            // Moins de places que d'étudiants déjà affectés : l'organisme serait en dépassement.
            $errors['places'] = get_string('placementplacesbelowassigned', 'mod_stage', $assigned);
        }
        $errors += stage_validate_siret_field($data);
        $tutoremail = trim((string) ($data['tutoremail'] ?? ''));
        if ($tutoremail !== '' && !validate_email($tutoremail)) {
            $errors['tutoremail'] = get_string('invalidemail');
        }

        return $errors;
    }
}
