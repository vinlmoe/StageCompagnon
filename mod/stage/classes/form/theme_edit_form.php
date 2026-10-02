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
 * Formulaire unique de gestion d'une thématique (DEVE) : paramètres généraux, durées requises par
 * année, enseignants responsables, documents et check-list d'objectifs, questions d'évaluation.
 * Il remplace la navigation entre cinq pages distinctes par une seule page, enregistrée en une
 * fois (voir theme_edit.php).
 *
 * Données attendues dans customdata :
 * - themes : id => libellé, toutes les thématiques de l'activité (partage des questions) ;
 * - teachers : id => nom complet, enseignants pouvant être responsables ;
 * - checklistcount : nombre d'éléments de check-list existants ;
 * - questioncount : nombre de questions existantes ;
 * - questioninfo : index de ligne => texte informatif (thématiques partagées) ;
 * - reusable : id => libellé, questions d'autres thématiques pouvant être associées ;
 * - tutorenabled : l'évaluation par le maître de stage est-elle activée pour l'activité ;
 * - filemanageroptions : options du gestionnaire de fichiers des documents d'objectifs.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class theme_edit_form extends \moodleform {
    /** @var int Lignes vides proposées pour ajouter des éléments de check-list ou des questions. */
    const BLANK_ROWS = 2;

    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;
        $customdata = $this->_customdata;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'themeid');
        $mform->setType('themeid', PARAM_INT);

        $this->define_general($mform);
        $this->define_durations($mform);
        $this->define_teachers($mform, $customdata['teachers'] ?? []);
        $this->define_objectives($mform, $customdata);
        $this->define_questions($mform, $customdata);

        $buttons = [
            $mform->createElement('submit', 'submitbutton', get_string('savechanges')),
            $mform->createElement('submit', 'submitandreturn', get_string('savethemeandreturn', 'mod_stage')),
            $mform->createElement('cancel'),
        ];
        $mform->addGroup($buttons, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Paramètres généraux de la thématique (anciennement theme_form).
     *
     * @param \MoodleQuickForm $mform
     */
    protected function define_general(\MoodleQuickForm $mform) {
        $mform->addElement('header', 'generalhdr', get_string('themegeneral', 'mod_stage'));
        $mform->setExpanded('generalhdr', true);

        $mform->addElement('text', 'name', get_string('theme', 'mod_stage'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('textarea', 'description', get_string('description'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('description', PARAM_TEXT);

        $mform->addElement('advcheckbox', 'mandatory', get_string('mandatory', 'mod_stage'));

        $mform->addElement('select', 'minstudyyear', get_string('minstudyyear', 'mod_stage'), stage_studyyear_options());
        $mform->setDefault('minstudyyear', 0);

        $mform->addElement('select', 'maxstudyyear', get_string('maxstudyyear', 'mod_stage'), stage_studyyear_options());
        $mform->setDefault('maxstudyyear', 0);

        $mform->addElement('text', 'sortorder', get_string('sortorder', 'mod_stage'));
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);

        $mform->addElement('advcheckbox', 'visible', get_string('visible'));
        $mform->setDefault('visible', 1);

        $mform->addElement('advcheckbox', 'tutorevaluationenabled', get_string('tutorevaluationenabledtheme', 'mod_stage'));
        $mform->setDefault('tutorevaluationenabled', 1);
        $mform->addHelpButton('tutorevaluationenabled', 'tutorevaluationenabledtheme', 'mod_stage');

        $mform->addElement('select', 'reportmode', get_string('reportmode', 'mod_stage'), stage_report_mode_options());
        $mform->setDefault('reportmode', STAGE_REPORT_NONE);
        $mform->addHelpButton('reportmode', 'reportmode', 'mod_stage');
    }

    /**
     * Durée requise : soit une durée unique, soit une durée par année d'étude. Toutes les années
     * sont proposées, et pas seulement celles de la plage de la thématique : la durée requise
     * d'un stage est lue pour l'année d'étude du stage lui-même (voir stage_get_theme_duration()),
     * qui peut sortir de la plage (stage refait, thématique sans plage vérifiée chaque année). Un
     * champ laissé vide renvoie à la valeur « toutes années » ; un 0 est une valeur à part entière.
     *
     * @param \MoodleQuickForm $mform
     */
    protected function define_durations(\MoodleQuickForm $mform) {
        $mform->addElement('header', 'durationshdr', get_string('themedurationssection', 'mod_stage'));
        $mform->setExpanded('durationshdr', true);

        $mform->addElement('text', 'requiredduration', get_string('requiredduration', 'mod_stage'), ['size' => 6]);
        $mform->setType('requiredduration', PARAM_INT);
        $mform->setDefault('requiredduration', 0);
        $mform->addHelpButton('requiredduration', 'requiredduration', 'mod_stage');

        $mform->addElement('static', 'durationsintro', '', get_string('themedurationsintro', 'mod_stage'));

        foreach (array_keys(stage_studyyear_options()) as $year) {
            $mform->addElement(
                'text',
                'duration_' . $year,
                get_string('requireddurationforyear', 'mod_stage', stage_studyyear_label($year)),
                [
                    'size' => 6,
                    'data-stage-durationyear' => $year,
                    'placeholder' => $year ? get_string('durationusesdefault', 'mod_stage') : '',
                ]
            );
            $mform->setType('duration_' . $year, PARAM_RAW_TRIMMED);
            // La durée unique, si elle est renseignée, prime sur les durées par année.
            $mform->disabledIf('duration_' . $year, 'requiredduration', 'neq', 0);
        }
    }

    /**
     * Enseignants responsables de la thématique.
     *
     * @param \MoodleQuickForm $mform
     * @param array $teachers id => nom
     */
    protected function define_teachers(\MoodleQuickForm $mform, array $teachers) {
        $mform->addElement('header', 'teachershdr', get_string('themeteachers', 'mod_stage'));
        $mform->setExpanded('teachershdr', true);

        if (empty($teachers)) {
            $mform->addElement('static', 'noteachers', '', get_string('noteachers', 'mod_stage'));
            return;
        }
        $mform->addElement('autocomplete', 'teacherids', get_string('themeteachers', 'mod_stage'), $teachers, [
            'multiple' => true,
            'noselectionstring' => get_string('noselection', 'form'),
        ]);
        $mform->addHelpButton('teacherids', 'themeteachers', 'mod_stage');
    }

    /**
     * Objectifs de stage : documents téléchargeables et check-list éditable en ligne.
     *
     * @param \MoodleQuickForm $mform
     * @param array $customdata
     */
    protected function define_objectives(\MoodleQuickForm $mform, array $customdata) {
        $mform->addElement('header', 'objectiveshdr', get_string('themeobjectives', 'mod_stage'));
        $mform->setExpanded('objectiveshdr', true);

        $mform->addElement(
            'filemanager',
            'objectivefiles',
            get_string('themeobjectivefiles', 'mod_stage'),
            null,
            $customdata['filemanageroptions'] ?? []
        );
        $mform->addHelpButton('objectivefiles', 'themeobjectivefiles', 'mod_stage');

        $mform->addElement('header', 'checklisthdr', get_string('themechecklist', 'mod_stage'));
        $mform->setExpanded('checklisthdr', true);
        $mform->addElement('static', 'checklistintro', '', get_string('themechecklist_help', 'mod_stage'));

        $row = [
            $mform->createElement('static', 'checklistrowhdr', '', \html_writer::tag('hr', '')),
            $mform->createElement('hidden', 'checklistid', 0),
            $mform->createElement('text', 'checklistname', get_string('checklistitem', 'mod_stage'), ['size' => 64]),
            $mform->createElement(
                'textarea',
                'checklistdescription',
                get_string('checklistitemdescription', 'mod_stage'),
                ['rows' => 2, 'cols' => 60]
            ),
            $mform->createElement('text', 'checklistsortorder', get_string('sortorder', 'mod_stage'), ['size' => 4]),
            $mform->createElement('advcheckbox', 'checklistdelete', get_string('deletechecklistrow', 'mod_stage')),
        ];
        $options = [
            'checklistid' => ['type' => PARAM_INT],
            'checklistname' => ['type' => PARAM_TEXT],
            'checklistdescription' => ['type' => PARAM_TEXT],
            'checklistsortorder' => ['type' => PARAM_INT, 'default' => 0],
        ];
        $this->repeat_elements(
            $row,
            (int) ($customdata['checklistcount'] ?? 0) + self::BLANK_ROWS,
            $options,
            'checklistrepeats',
            'checklistaddrows',
            self::BLANK_ROWS,
            get_string('addchecklistrows', 'mod_stage'),
            true
        );
    }

    /**
     * Questions d'évaluation de la thématique, éditables en ligne, et association de questions
     * déjà définies pour d'autres thématiques.
     *
     * @param \MoodleQuickForm $mform
     * @param array $customdata
     */
    protected function define_questions(\MoodleQuickForm $mform, array $customdata) {
        $mform->addElement('header', 'questionshdr', get_string('evalquestions', 'mod_stage'));
        $mform->setExpanded('questionshdr', true);
        $mform->addElement('static', 'questionsintro', '', get_string('themequestionsintro', 'mod_stage'));

        if (!empty($customdata['reusable'])) {
            $mform->addElement(
                'autocomplete',
                'attachquestionids',
                get_string('attachquestions', 'mod_stage'),
                $customdata['reusable'],
                ['multiple' => true, 'noselectionstring' => get_string('noselection', 'form')]
            );
            $mform->addHelpButton('attachquestionids', 'attachquestions', 'mod_stage');
        }

        $evaltypeoptions = [
            'student' => get_string('evaltype_student', 'mod_stage'),
            'teacher' => get_string('evaltype_teacher', 'mod_stage'),
        ];
        if (!empty($customdata['tutorenabled'])) {
            $evaltypeoptions['tutor'] = get_string('evaltype_tutor', 'mod_stage');
        }
        $qtypeoptions = [
            'choice' => get_string('qtype_choice', 'mod_stage'),
            'text' => get_string('qtype_text', 'mod_stage'),
        ];

        $row = [
            $mform->createElement('static', 'questionrowhdr', '', \html_writer::tag('hr', '')),
            $mform->createElement('static', 'questioninfo', '', ''),
            $mform->createElement('hidden', 'questionid', 0),
            $mform->createElement('select', 'questionevaltype', get_string('evaltype', 'mod_stage'), $evaltypeoptions),
            $mform->createElement('select', 'questionqtype', get_string('qtype', 'mod_stage'), $qtypeoptions),
            $mform->createElement('text', 'questionname', get_string('questionlabel', 'mod_stage'), ['size' => 64]),
            $mform->createElement(
                'textarea',
                'questionoptions',
                get_string('choiceoptions', 'mod_stage'),
                ['rows' => 4, 'cols' => 50]
            ),
            $mform->createElement('text', 'questionnameen', get_string('questionlabelen', 'mod_stage'), ['size' => 64]),
            $mform->createElement(
                'textarea',
                'questionoptionsen',
                get_string('choiceoptionsen', 'mod_stage'),
                ['rows' => 4, 'cols' => 50]
            ),
            $mform->createElement('advcheckbox', 'questionrequired', get_string('questionrequired', 'mod_stage')),
            $mform->createElement('text', 'questionsortorder', get_string('sortorder', 'mod_stage'), ['size' => 4]),
            $mform->createElement('advcheckbox', 'questiondelete', get_string('unlinkquestionrow', 'mod_stage')),
        ];
        $options = [
            'questionid' => ['type' => PARAM_INT],
            'questionevaltype' => ['default' => 'student'],
            'questionqtype' => ['default' => 'text'],
            'questionname' => ['type' => PARAM_TEXT],
            'questionoptions' => ['type' => PARAM_TEXT, 'hideif' => ['questionqtype', 'eq', 'text']],
            'questionnameen' => ['type' => PARAM_TEXT, 'hideif' => ['questionevaltype', 'neq', 'tutor']],
            'questionoptionsen' => ['type' => PARAM_TEXT, 'hideif' => ['questionevaltype', 'neq', 'tutor']],
            'questionrequired' => ['default' => 1],
            'questionsortorder' => ['type' => PARAM_INT, 'default' => 0],
            'questiondelete' => ['helpbutton' => ['unlinkquestionrow', 'mod_stage']],
        ];
        $repeats = $this->repeat_elements(
            $row,
            (int) ($customdata['questioncount'] ?? 0) + self::BLANK_ROWS,
            $options,
            'questionrepeats',
            'questionaddrows',
            self::BLANK_ROWS,
            get_string('addquestionrows', 'mod_stage'),
            true
        );

        // Le second masquage des options anglaises (question à commentaire libre) n'est pas
        // exprimable dans les options de repeat_elements, qui n'acceptent qu'une condition.
        for ($i = 0; $i < $repeats; $i++) {
            $mform->hideIf("questionoptionsen[$i]", "questionqtype[$i]", 'eq', 'text');
            $info = $customdata['questioninfo'][$i] ?? '';
            $mform->getElement("questioninfo[$i]")->setText(
                $info !== '' ? $info : \html_writer::span(get_string('newquestionrow', 'mod_stage'), 'text-muted')
            );
        }
    }

    /**
     * Server-side validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        foreach (array_keys(stage_studyyear_options()) as $year) {
            $value = trim((string) ($data['duration_' . $year] ?? ''));
            if ($value !== '' && !ctype_digit($value)) {
                $errors['duration_' . $year] = get_string('durationinvalid', 'mod_stage');
            }
        }

        if (
            !empty($data['minstudyyear']) && !empty($data['maxstudyyear'])
                && $data['minstudyyear'] > $data['maxstudyyear']
        ) {
            $errors['maxstudyyear'] = get_string('studyyearrange_error', 'mod_stage');
        }

        // Un élément existant vidé de son intitulé est une erreur de saisie : pour le retirer, il
        // faut cocher sa case de suppression, ce qui supprime aussi les réponses des étudiants.
        foreach ($data['checklistid'] ?? [] as $i => $itemid) {
            if (!empty($itemid) && empty($data['checklistdelete'][$i]) && trim((string) $data['checklistname'][$i]) === '') {
                $errors["checklistname[$i]"] = get_string('required');
            }
        }

        foreach ($data['questionid'] ?? [] as $i => $questionid) {
            if (!empty($data['questiondelete'][$i])) {
                continue;
            }
            $name = trim((string) ($data['questionname'][$i] ?? ''));
            if ($name === '') {
                if (!empty($questionid)) {
                    $errors["questionname[$i]"] = get_string('required');
                }
                continue;
            }
            if (($data['questionqtype'][$i] ?? '') === 'choice' && trim((string) ($data['questionoptions'][$i] ?? '')) === '') {
                $errors["questionoptions[$i]"] = get_string('choiceoptionsrequired', 'mod_stage');
            }
        }

        return $errors;
    }
}
