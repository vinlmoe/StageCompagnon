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
 * année, enseignants responsables, documents et check-list d'objectifs, choix des listes
 * d'évaluation (éditées à part, voir evallists.php).
 * Il remplace la navigation entre cinq pages distinctes par une seule page, enregistrée en une
 * fois (voir theme_edit.php).
 *
 * Données attendues dans customdata :
 * - teachers : id => nom complet, enseignants pouvant être responsables ;
 * - checklistcount : nombre d'éléments de check-list existants ;
 * - evallists : evaltype => [id => nom], listes d'évaluation disponibles par type ;
 * - evallisturl : URL de base de l'édition d'une liste (evallist_edit.php), complétée de listid ;
 * - evallistsindexurl : URL de la page qui gère toutes les listes (evallists.php) ;
 * - tutorenabled : la liste du maître de stage est-elle proposée ;
 * - filemanageroptions : options du gestionnaire de fichiers des documents d'objectifs.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class theme_edit_form extends \moodleform {
    /** @var int Lignes vides proposées pour ajouter des éléments de check-list. */
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
        $this->define_evallists($mform, $customdata);

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
     * Choix, pour chaque formulaire d'évaluation, de la liste de questions utilisée par la
     * thématique. Les listes elles-mêmes s'éditent sur leur propre page : un lien y mène
     * (« Éditer la liste », mis à jour par le script de theme_edit.php selon la liste choisie).
     *
     * @param \MoodleQuickForm $mform
     * @param array $customdata
     */
    protected function define_evallists(\MoodleQuickForm $mform, array $customdata) {
        $mform->addElement('header', 'questionshdr', get_string('evallists', 'mod_stage'));
        $mform->setExpanded('questionshdr', true);
        $mform->addElement('static', 'evallistsintro', '', get_string('themeevallistsintro', 'mod_stage'));

        $evaltypes = ['student', 'teacher'];
        if (!empty($customdata['tutorenabled'])) {
            $evaltypes[] = 'tutor';
        }
        $baseurl = (string) ($customdata['evallisturl'] ?? '');
        foreach ($evaltypes as $evaltype) {
            $field = stage_evallist_fields()[$evaltype];
            $options = [0 => get_string('evallistnone', 'mod_stage')] + ($customdata['evallists'][$evaltype] ?? []);
            $link = \html_writer::link($baseurl, get_string('evallistedit', 'mod_stage'), [
                'class' => 'btn btn-sm btn-outline-secondary ml-2 stage-evallist-edit',
                'data-select' => 'id_' . $field,
                'data-baseurl' => $baseurl,
            ]);
            // « Nouvelle liste » enregistre d'abord la thématique (rien de la saisie en cours n'est
            // perdu), puis ouvre la création d'une liste de ce type, choisie automatiquement pour la
            // thématique à son enregistrement (voir theme_edit.php et evallist_edit.php).
            $mform->addGroup([
                $mform->createElement('select', $field, '', $options),
                $mform->createElement('static', $field . 'link', '', $link),
                $mform->createElement('submit', 'createlist_' . $evaltype, get_string('evallistnew', 'mod_stage'), [
                    'class' => 'btn-sm',
                ], false),
            ], $field . 'group', stage_evaltype_label($evaltype), ' ', false);
        }

        $mform->addElement('static', 'evallistslinks', '', \html_writer::link(
            (string) ($customdata['evallistsindexurl'] ?? ''),
            get_string('evallistsmanage', 'mod_stage'),
            ['class' => 'btn btn-sm btn-link']
        ));
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

        if (
            !empty($this->_customdata['stageid'])
                && stage_theme_name_taken($this->_customdata['stageid'], $data['name'], $this->_customdata['themeid'] ?? 0)
        ) {
            $errors['name'] = get_string('themenametaken', 'mod_stage');
        }
        if ((int) ($data['requiredduration'] ?? 0) < 0) {
            $errors['requiredduration'] = get_string('durationinvalid', 'mod_stage');
        }

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

        return $errors;
    }
}
