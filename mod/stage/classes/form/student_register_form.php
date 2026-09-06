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
 * Formulaire combiné permettant à l'étudiant d'enregistrer lui-même un stage et d'en demander la
 * convention en une seule saisie. Réunit les champs d'enregistrement d'un stage (thématique,
 * structure, dates, durée) et ceux de convention_request_form (langue, gabarit, enseignant
 * référent, coordonnées de l'étudiant, organisme d'accueil, tuteur, modalités, gratification,
 * congés).
 *
 * Également réutilisé en mode édition par student_register.php (customdata 'editing' et
 * 'excludeentryid') pour une saisie déjà existante sans convention (enregistrée par la DEVE, ou
 * demande refusée à corriger) : mêmes champs, mais la saisie existante est mise à jour plutôt que
 * dupliquée.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_register_form extends \moodleform {

    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;
        $themes = $this->_customdata['themes'];
        $templates = $this->_customdata['templates'];
        $referentteachers = $this->_customdata['referentteachers'];
        $stage = $this->_customdata['stage'];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Enregistrement du stage.
        $mform->addElement('header', 'stageheader', get_string('registerstage', 'mod_stage'));
        $mform->setExpanded('stageheader');

        $themeoptions = [];
        foreach ($themes as $theme) {
            $themeoptions[$theme->id] = stage_theme_option_label($theme);
        }
        $mform->addElement('select', 'themeid', get_string('theme', 'mod_stage'), $themeoptions);
        $mform->addRule('themeid', null, 'required', null, 'client');

        // L'étudiant ne peut positionner son stage que sur l'année N (normale), N-1 (dette) ou
        // N+1 (anticipation), par rapport à l'année d'étude courante définie pour ce cours.
        $mform->addElement('select', 'studyyear', get_string('studyyear', 'mod_stage'),
            stage_studyyear_selectable_options($stage));
        $mform->addRule('studyyear', null, 'required', null, 'client');
        $mform->setDefault('studyyear', $stage->currentstudyyear);

        $mform->addElement('text', 'structure', get_string('structure', 'mod_stage'), ['size' => '64']);
        $mform->setType('structure', PARAM_TEXT);
        $mform->addRule('structure', null, 'required', null, 'client');

        $mform->addElement('advcheckbox', 'abroad', get_string('abroad', 'mod_stage'));

        $mform->addElement('text', 'country', get_string('country', 'mod_stage'), ['size' => '32']);
        $mform->setType('country', PARAM_TEXT);
        $mform->hideIf('country', 'abroad', 'notchecked');

        $mform->addElement('text', 'declaredduration', get_string('declaredduration', 'mod_stage'));
        $mform->setType('declaredduration', PARAM_INT);
        $mform->addRule('declaredduration', null, 'required', null, 'client');

        // Langue et gabarit de la convention.
        $mform->addElement('select', 'conventionlang', get_string('conventionlang', 'mod_stage'),
            stage_convention_lang_options(), ['id' => 'id_conventionlang']);
        $mform->setDefault('conventionlang', 'fr');

        $templateoptions = [];
        foreach ($templates as $template) {
            $templateoptions[$template->id] = format_string($template->name)
                . ' (' . stage_convention_lang_label($template->lang) . ')';
        }
        $mform->addElement('select', 'conventiontemplateid', get_string('conventiontemplatename', 'mod_stage'),
            $templateoptions);
        $mform->addRule('conventiontemplateid', null, 'required', null, 'client');

        // Signalée dès la demande, cette case informe la DEVE qu'un exemplaire imprimé (avec cadre
        // de signatures) sera nécessaire, en plus (ou à la place) de la convention signée
        // électroniquement : voir stage_convention_paper_requested_info() pour son report jusqu'à
        // la revue DEVE.
        $mform->addElement('advcheckbox', 'paperrequestedbystudent', get_string('conventionpaperrequest', 'mod_stage'));
        $mform->addHelpButton('paperrequestedbystudent', 'conventionpaperrequest', 'mod_stage');

        // Un seul enseignant référent par convention, choisi parmi ceux que la DEVE a attribués
        // à l'étudiant. Son courriel est lu sur son compte à la génération et la convention ne
        // demande pas de téléphone pour ce rôle : aucun des deux n'est saisi ici.
        $referentoptions = [];
        foreach ($referentteachers as $teacher) {
            $referentoptions[$teacher->id] = fullname($teacher);
        }
        $mform->addElement('select', 'referentteacherid', get_string('conventionreferentteacher', 'mod_stage'),
            $referentoptions);
        $mform->addRule('referentteacherid', null, 'required', null, 'client');

        // Situation de l'étudiant, type de stage.
        $mform->addElement('select', 'yearsituation', get_string('conventionyearsituation', 'mod_stage'),
            stage_convention_yearsituation_options());
        $mform->addElement('select', 'stagetype', get_string('conventionstagetype', 'mod_stage'),
            stage_convention_stagetype_options());

        // Les plages de dates sont le seul endroit où se saisissent les dates du stage : ses dates
        // de début et de fin en sont déduites (première et dernière date couvertes, voir
        // stage_save_entry_periods()). Des champs de début/fin distincts n'auraient rien pu
        // apporter de plus, et auraient pu les contredire.
        stage_add_period_fields($this, $mform, count($this->_customdata['periods'] ?? []));

        // Coordonnées de l'étudiant.
        $mform->addElement('header', 'studentheader', get_string('conventionstudent', 'mod_stage'));
        $mform->setExpanded('studentheader');
        $mform->addElement('date_selector', 'studentbirthdate', get_string('conventionbirthdate', 'mod_stage'));
        $mform->addElement('text', 'studentaddress', get_string('conventionstudentaddress', 'mod_stage'),
            ['size' => '64']);
        $mform->setType('studentaddress', PARAM_TEXT);
        $mform->addRule('studentaddress', null, 'required', null, 'client');
        $mform->addElement('text', 'studentphone', get_string('conventionstudentphone', 'mod_stage'));
        $mform->setType('studentphone', PARAM_TEXT);
        $mform->addRule('studentphone', null, 'required', null, 'client');

        // Organisme d'accueil.
        $mform->addElement('header', 'hostheader', get_string('conventionhoststructure', 'mod_stage'));
        $mform->setExpanded('hostheader');
        $mform->addElement('text', 'hostaddress', get_string('conventionhostaddress', 'mod_stage'), ['size' => '64']);
        $mform->setType('hostaddress', PARAM_TEXT);
        $mform->addRule('hostaddress', null, 'required', null, 'client');
        $mform->addElement('text', 'hostrepresentative', get_string('conventionhostrepresentative', 'mod_stage'),
            ['size' => '64']);
        $mform->setType('hostrepresentative', PARAM_TEXT);
        $mform->addRule('hostrepresentative', null, 'required', null, 'client');
        $mform->addElement('text', 'hostrepresentativetitle', get_string('conventionhostrepresentativetitle', 'mod_stage'),
            ['size' => '64']);
        $mform->setType('hostrepresentativetitle', PARAM_TEXT);
        $mform->addRule('hostrepresentativetitle', null, 'required', null, 'client');
        $mform->addElement('text', 'hostservice', get_string('conventionhostservice', 'mod_stage'), ['size' => '64']);
        $mform->setType('hostservice', PARAM_TEXT);
        $mform->addRule('hostservice', null, 'required', null, 'client');
        $mform->addElement('text', 'hostphone', get_string('conventionhostphone', 'mod_stage'));
        $mform->setType('hostphone', PARAM_TEXT);
        $mform->addRule('hostphone', null, 'required', null, 'client');
        $mform->addElement('text', 'hostemail', get_string('conventionhostemail', 'mod_stage'), ['size' => '64']);
        $mform->setType('hostemail', PARAM_TEXT);
        $mform->addRule('hostemail', null, 'required', null, 'client');
        $mform->addElement('text', 'hostlocation', get_string('conventionhostlocation', 'mod_stage'), ['size' => '64']);
        $mform->setType('hostlocation', PARAM_TEXT);
        $mform->addHelpButton('hostlocation', 'conventionhostlocation', 'mod_stage');

        // Tuteur / tutrice de stage.
        $mform->addElement('header', 'tutorheader', get_string('conventiontutor', 'mod_stage'));
        $mform->setExpanded('tutorheader');
        $mform->addElement('text', 'tutorname', get_string('conventiontutorname', 'mod_stage'), ['size' => '64']);
        $mform->setType('tutorname', PARAM_TEXT);
        $mform->addRule('tutorname', null, 'required', null, 'client');
        $mform->addElement('text', 'tutorfunction', get_string('conventiontutorfunction', 'mod_stage'), ['size' => '64']);
        $mform->setType('tutorfunction', PARAM_TEXT);
        $mform->addRule('tutorfunction', null, 'required', null, 'client');
        $mform->addElement('text', 'tutorphone', get_string('conventiontutorphone', 'mod_stage'));
        $mform->setType('tutorphone', PARAM_TEXT);
        $mform->addRule('tutorphone', null, 'required', null, 'client');
        $mform->addElement('text', 'tutoremail', get_string('conventiontutoremail', 'mod_stage'), ['size' => '64']);
        $mform->setType('tutoremail', PARAM_TEXT);
        $mform->addRule('tutoremail', null, 'required', null, 'client');

        // Modalités particulières (art. 3.2) : cases à cocher, valeur par défaut (décochée)
        // toujours valide, donc pas de règle "required" ici. "Autre" reste libre : sans objet
        // si aucune modalité particulière ne s'applique.
        $mform->addElement('header', 'modalitiesheader', get_string('conventionmodalities', 'mod_stage'));
        $mform->setExpanded('modalitiesheader');
        $mform->addElement('advcheckbox', 'nightpresence', get_string('conventionnightpresence', 'mod_stage'));
        $mform->addElement('advcheckbox', 'sundaypresence', get_string('conventionsundaypresence', 'mod_stage'));
        $mform->addElement('advcheckbox', 'holidaypresence', get_string('conventionholidaypresence', 'mod_stage'));
        $mform->addElement('advcheckbox', 'homebased', get_string('conventionhomebased', 'mod_stage'));
        $mform->addElement('text', 'othermodality', get_string('conventionothermodality', 'mod_stage'), ['size' => '64']);
        $mform->setType('othermodality', PARAM_TEXT);

        // Gratification (art. 5.13).
        $mform->addElement('text', 'gratificationamount', get_string('conventiongratification', 'mod_stage'));
        $mform->setType('gratificationamount', PARAM_TEXT);
        $mform->addRule('gratificationamount', null, 'required', null, 'client');

        // Congés et autorisations d'absence (art. 10.1) : nombre de jours et modalités
        // obligatoires uniquement si la case "congés prévus" est cochée (voir validation()).
        $mform->addElement('header', 'leaveheader', get_string('conventionleave', 'mod_stage'));
        $mform->setExpanded('leaveheader');
        $mform->addElement('advcheckbox', 'hasleave', get_string('conventionhasleave', 'mod_stage'));
        $mform->addElement('text', 'leavedays', get_string('conventionleavedays', 'mod_stage'));
        $mform->setType('leavedays', PARAM_INT);
        $mform->hideIf('leavedays', 'hasleave', 'notchecked');
        $mform->addElement('textarea', 'leavemodalities', get_string('conventionleavemodalities', 'mod_stage'),
            ['rows' => 3, 'cols' => 60]);
        $mform->setType('leavemodalities', PARAM_TEXT);
        $mform->hideIf('leavemodalities', 'hasleave', 'notchecked');

        $submitlabel = !empty($this->_customdata['editing'])
            ? get_string('requestconvention', 'mod_stage') : get_string('registerstageandconvention', 'mod_stage');
        $this->add_action_buttons(true, $submitlabel);
    }

    /**
     * Validation serveur : vérifie la cohérence des plages de dates, refuse un doublon (même
     * thématique et mêmes dates pour l'étudiant connecté) et vérifie que le gabarit sélectionné
     * est dans la langue choisie.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Les dates du stage étant déduites des plages, leur cohérence conditionne tout le reste :
        // le contrôle de doublon ci-dessous s'appuie sur elles.
        $periods = stage_extract_submitted_periods((object) $data);
        // L'étudiant enregistre ici son stage et demande sa convention dans la foulée : une
        // convention ne pouvant pas couvrir un stage déjà commencé, les dates passées sont
        // refusées (contrairement aux formulaires de la DEVE, voir stage_validate_periods()).
        $perioderror = stage_validate_periods($periods, true);
        if ($perioderror !== null) {
            $errors['perioddatestart[0]'] = $perioderror;
            return $errors;
        }

        $duplicate = stage_entry_is_duplicate(
            $this->_customdata['stageid'],
            $this->_customdata['userid'],
            $data['themeid'],
            min(array_column($periods, 'datestart')),
            max(array_column($periods, 'dateend')),
            $this->_customdata['excludeentryid'] ?? 0
        );
        if ($duplicate) {
            $errors['themeid'] = get_string('errorduplicateentry', 'mod_stage');
        }

        $templates = $this->_customdata['templates'];
        $template = $templates[$data['conventiontemplateid']] ?? null;
        if (!$template || $template->lang !== $data['conventionlang']) {
            $errors['conventiontemplateid'] = get_string('conventiontemplatelangmismatch', 'mod_stage');
        }

        return $errors;
    }
}
