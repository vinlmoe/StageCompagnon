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
 * Formulaire de revue d'une demande de convention soumise par l'étudiant, utilisé par la DEVE
 * (convention_review.php) et par l'enseignant référent (convention_teacher_validate.php) : reprend
 * les champs de convention_request_form, éditables, et propose deux actions : valider ou refuser
 * avec un commentaire obligatoire renvoyé à l'étudiant.
 *
 * Les champs obligatoires ne le sont qu'en cas de validation. Le contrôle est fait dans
 * validation(), formslib ne permettant pas de conditionner une règle client au bouton cliqué.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class convention_review_form extends \moodleform {

    /** Champs requis uniquement en cas de validation (voir validation()). */
    const REQUIRED_FIELDS = [
        'referentteacherid', 'studentbirthdate', 'studentaddress', 'studentphone',
        'hostaddress', 'hostrepresentative', 'hostrepresentativetitle', 'hostservice', 'hostphone',
        'hostemail', 'tutorname', 'tutorfunction', 'tutorphone', 'tutoremail',
        'gratificationamount',
    ];

    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;
        $referentteachers = $this->_customdata['referentteachers'];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'entryid');
        $mform->setType('entryid', PARAM_INT);

        $referentoptions = [];
        foreach ($referentteachers as $teacher) {
            $referentoptions[$teacher->id] = fullname($teacher);
        }
        $mform->addElement('select', 'referentteacherid', get_string('conventionreferentteacher', 'mod_stage'),
            $referentoptions);

        $mform->addElement('select', 'yearsituation', get_string('conventionyearsituation', 'mod_stage'),
            stage_convention_yearsituation_options());
        $mform->addElement('select', 'stagetype', get_string('conventionstagetype', 'mod_stage'),
            stage_convention_stagetype_options());

        // Plages de dates du stage (plusieurs plages non contiguës possibles), consultables et
        // modifiables ici par la DEVE et l'enseignant référent (voir stage_add_period_fields()).
        stage_add_period_fields($this, $mform, count($this->_customdata['periods'] ?? []));

        $mform->addElement('header', 'studentheader', get_string('conventionstudent', 'mod_stage'));
        $mform->setExpanded('studentheader');
        $mform->addElement('date_selector', 'studentbirthdate', get_string('conventionbirthdate', 'mod_stage'));
        $mform->addElement('text', 'studentaddress', get_string('conventionstudentaddress', 'mod_stage'),
            ['size' => '64']);
        $mform->setType('studentaddress', PARAM_TEXT);
        $mform->addElement('text', 'studentphone', get_string('conventionstudentphone', 'mod_stage'));
        $mform->setType('studentphone', PARAM_TEXT);

        $mform->addElement('header', 'hostheader', get_string('conventionhoststructure', 'mod_stage'));
        $mform->setExpanded('hostheader');
        $mform->addElement('text', 'hostaddress', get_string('conventionhostaddress', 'mod_stage'), ['size' => '64']);
        $mform->setType('hostaddress', PARAM_TEXT);
        $mform->addElement('text', 'hostrepresentative', get_string('conventionhostrepresentative', 'mod_stage'),
            ['size' => '64']);
        $mform->setType('hostrepresentative', PARAM_TEXT);
        $mform->addElement('text', 'hostrepresentativetitle', get_string('conventionhostrepresentativetitle', 'mod_stage'),
            ['size' => '64']);
        $mform->setType('hostrepresentativetitle', PARAM_TEXT);
        $mform->addElement('text', 'hostservice', get_string('conventionhostservice', 'mod_stage'), ['size' => '64']);
        $mform->setType('hostservice', PARAM_TEXT);
        $mform->addElement('text', 'hostphone', get_string('conventionhostphone', 'mod_stage'));
        $mform->setType('hostphone', PARAM_TEXT);
        $mform->addElement('text', 'hostemail', get_string('conventionhostemail', 'mod_stage'), ['size' => '64']);
        $mform->setType('hostemail', PARAM_TEXT);
        $mform->addElement('text', 'hostlocation', get_string('conventionhostlocation', 'mod_stage'), ['size' => '64']);
        $mform->setType('hostlocation', PARAM_TEXT);
        $mform->addHelpButton('hostlocation', 'conventionhostlocation', 'mod_stage');

        $mform->addElement('header', 'tutorheader', get_string('conventiontutor', 'mod_stage'));
        $mform->setExpanded('tutorheader');
        $mform->addElement('text', 'tutorname', get_string('conventiontutorname', 'mod_stage'), ['size' => '64']);
        $mform->setType('tutorname', PARAM_TEXT);
        $mform->addElement('text', 'tutorfunction', get_string('conventiontutorfunction', 'mod_stage'), ['size' => '64']);
        $mform->setType('tutorfunction', PARAM_TEXT);
        $mform->addElement('text', 'tutorphone', get_string('conventiontutorphone', 'mod_stage'));
        $mform->setType('tutorphone', PARAM_TEXT);
        $mform->addElement('text', 'tutoremail', get_string('conventiontutoremail', 'mod_stage'), ['size' => '64']);
        $mform->setType('tutoremail', PARAM_TEXT);

        $mform->addElement('header', 'modalitiesheader', get_string('conventionmodalities', 'mod_stage'));
        $mform->setExpanded('modalitiesheader');
        $mform->addElement('advcheckbox', 'nightpresence', get_string('conventionnightpresence', 'mod_stage'));
        $mform->addElement('advcheckbox', 'sundaypresence', get_string('conventionsundaypresence', 'mod_stage'));
        $mform->addElement('advcheckbox', 'holidaypresence', get_string('conventionholidaypresence', 'mod_stage'));
        $mform->addElement('advcheckbox', 'homebased', get_string('conventionhomebased', 'mod_stage'));
        $mform->addElement('text', 'othermodality', get_string('conventionothermodality', 'mod_stage'), ['size' => '64']);
        $mform->setType('othermodality', PARAM_TEXT);

        $mform->addElement('text', 'gratificationamount', get_string('conventiongratification', 'mod_stage'));
        $mform->setType('gratificationamount', PARAM_TEXT);

        // Case propre à l'enseignant référent (convention_teacher_validate.php) : la DEVE, qui
        // réutilise ce même formulaire, ne la voit pas ici, mais voit son résultat combiné à celui
        // de l'étudiant plus bas (voir 'paperrequestedinfo' et le champ 'withsignatures').
        if (!empty($this->_customdata['showpaperrequest'])) {
            $mform->addElement('advcheckbox', 'paperrequestedbyteacher', get_string('conventionpaperrequest', 'mod_stage'));
            $mform->addHelpButton('paperrequestedbyteacher', 'conventionpaperrequest', 'mod_stage');
        }

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

        // Option de génération, proposée uniquement à la DEVE (convention_review.php), dont la
        // validation télécharge immédiatement le PDF : l'enseignant référent, lui, ne fait que
        // transmettre la demande, sans rien générer. La case est précochée si l'étudiant et/ou
        // l'enseignant référent a demandé une convention papier (voir 'paperrequestedinfo',
        // renseigné par le contrôleur à partir de leurs cases respectives), avec un rappel
        // au-dessus pour que la DEVE sache pourquoi sans avoir à rouvrir les demandes précédentes.
        if (!empty($this->_customdata['withsignatureoption'])) {
            $mform->addElement('header', 'generationheader', get_string('generateconvention', 'mod_stage'));
            $mform->setExpanded('generationheader');
            if (!empty($this->_customdata['paperrequestedinfo'])) {
                $mform->addElement('static', 'paperrequestedinfo', '', $this->_customdata['paperrequestedinfo']);
            }
            $mform->addElement('advcheckbox', 'withsignatures', get_string('includesignatureblock', 'mod_stage'));
        }

        $mform->addElement('header', 'rejectheader', get_string('conventionrejectcomment', 'mod_stage'));
        $mform->setExpanded('rejectheader');
        $mform->addElement('textarea', 'rejectcomment', get_string('conventionrejectcomment', 'mod_stage'),
            ['rows' => 3, 'cols' => 60]);
        $mform->setType('rejectcomment', PARAM_TEXT);

        $buttonarray = [];
        $buttonarray[] = $mform->createElement('submit', 'validateconvention', get_string('validateconvention', 'mod_stage'));
        $buttonarray[] = $mform->createElement('submit', 'rejectconvention', get_string('rejectconvention', 'mod_stage'));
        $buttonarray[] = $mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Validation serveur : les champs de la convention ne sont exigés qu'à la validation, un
     * refus restant possible sur une saisie incomplète. Le refus exige en revanche un motif.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['validateconvention'])) {
            // Les plages de dates conditionnent les dates du stage et sa convention : elles sont
            // vérifiées à la validation, au même titre que les champs obligatoires, mais pas au
            // refus, qui doit rester possible sur une saisie incohérente.
            $perioderror = stage_validate_periods(stage_extract_submitted_periods((object) $data));
            if ($perioderror !== null) {
                $errors['perioddatestart[0]'] = $perioderror;
            }
            foreach (self::REQUIRED_FIELDS as $field) {
                if ($data[$field] === '' || $data[$field] === null) {
                    $errors[$field] = get_string('required');
                }
            }
        } else if (!empty($data['rejectconvention'])) {
            if (trim((string) $data['rejectcomment']) === '') {
                $errors['rejectcomment'] = get_string('required');
            }
        }

        return $errors;
    }
}
