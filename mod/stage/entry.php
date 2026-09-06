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

/**
 * Auto-évaluation par l'étudiant d'un stage préalablement enregistré par la DEVE.
 * La création des stages est réservée à la DEVE (voir register.php).
 *
 * Si la DEVE a défini des questions d'évaluation (choix multiples ou commentaire libre)
 * pour la thématique du stage, elles sont affichées à la place du commentaire libre
 * générique. Sinon, un simple champ de commentaire libre est proposé.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/entry_form.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/report_form.php');

use mod_stage\form\entry_form;
use mod_stage\form\report_form;

$id = required_param('id', PARAM_INT);
$entryid = required_param('entryid', PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:submit', $context);

$entry = $DB->get_record('stage_entry', ['id' => $entryid, 'stageid' => $stage->id], '*', MUST_EXIST);
if ($entry->userid != $USER->id) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('selfeval', 'mod_stage'));
}

$PAGE->set_url('/mod/stage/entry.php', ['id' => $cm->id, 'entryid' => $entryid]);
$PAGE->set_title(format_string($stage->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$questions = stage_get_questions($entry->themeid, 'student');
$theme = $DB->get_record('stage_theme', ['id' => $entry->themeid]);
$reportmode = stage_theme_report_mode($theme);

// L'auto-évaluation n'est ouverte qu'une fois la convention de stage signée (voir
// convention_request.php / conventions.php), et n'est modifiable que tant qu'elle n'a pas
// encore été soumise : une fois soumise (ou la saisie rejetée), seule la DEVE peut réinitialiser
// la saisie pour la rouvrir.
$conventionsigned = stage_convention_is_signed($entry->conventionstatus);

$periods = stage_get_or_seed_entry_periods($entry);

// Un stage ne s'auto-évalue pas avant d'avoir commencé : tant que la première plage n'a pas
// débuté, l'étudiant n'a rien à évaluer et une saisie soumise d'avance passerait à l'évaluation
// enseignant alors que le stage reste à faire. Le jour même du début est ouvert.
$notstartedyet = stage_entry_not_started_yet($entry);

$editable = $conventionsigned && !$notstartedyet && ((int) $entry->status === STAGE_STATUS_ENREGISTRE);

// Sélection des jours de stage effectifs parmi les plages de la saisie : formulaire distinct de
// l'auto-évaluation, avec son propre bouton, pour rester simple à intégrer aux deux formulaires
// d'évaluation possibles (dynamique ou commentaire libre) ci-dessous.
if ($editable && !empty($periods) && optional_param('saveworkdays', 0, PARAM_INT) && confirm_sesskey()) {
    $workdays = optional_param_array('workdays', [], PARAM_INT);
    stage_set_entry_workdays($entry->id, $workdays);
    redirect(new moodle_url('/mod/stage/entry.php', ['id' => $cm->id, 'entryid' => $entryid]),
        get_string('workdayssaved', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Dépôt du rapport de stage, si la thématique en demande un : formulaire distinct de
// l'auto-évaluation (comme les jours de stage effectifs), pour que l'étudiant puisse déposer ses
// documents en plusieurs fois avant de soumettre définitivement son auto-évaluation.
$reportform = null;
if ($editable && $reportmode != STAGE_REPORT_NONE) {
    $filemanageroptions = [
        'subdirs' => 0,
        'maxfiles' => 20,
        'maxbytes' => $CFG->maxbytes,
    ];
    $reportform = new report_form(new moodle_url('/mod/stage/entry.php', ['id' => $cm->id, 'entryid' => $entryid]),
        ['filemanageroptions' => $filemanageroptions]);

    $draftitemid = file_get_submitted_draft_itemid('reportfiles');
    file_prepare_draft_area($draftitemid, $context->id, 'mod_stage', STAGE_REPORT_FILEAREA, $entry->id,
        $filemanageroptions);
    $reportform->set_data([
        'id' => $cm->id, 'entryid' => $entryid, 'reportfiles' => $draftitemid,
    ]);

    if ($reportdata = $reportform->get_data()) {
        file_save_draft_area_files($reportdata->reportfiles, $context->id, 'mod_stage', STAGE_REPORT_FILEAREA,
            $entry->id, $filemanageroptions);
        redirect(new moodle_url('/mod/stage/entry.php', ['id' => $cm->id, 'entryid' => $entryid]),
            get_string('reportfilessaved', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// Le dépôt du rapport, quand la thématique l'exige, conditionne la soumission de
// l'auto-évaluation : sans document déposé, le formulaire est réaffiché avec un message plutôt
// que soumis, la saisie passant sinon hors de portée de l'étudiant sans son rapport.
$reportmissing = $reportmode == STAGE_REPORT_REQUIRED && empty(stage_get_report_files($context, $entry->id));
$reportblocked = false;

// Traite la soumission du formulaire dynamique avant tout affichage, pour permettre la redirection.
if ($editable && !empty($questions) && !optional_param('savereport', 0, PARAM_INT)
        && data_submitted() && confirm_sesskey()) {
    // Les réponses sont enregistrées dans tous les cas : seul le passage au statut « évalué par
    // l'étudiant » est retenu faute de rapport, et l'étudiant retrouve sa saisie telle quelle
    // après avoir déposé ses documents.
    stage_save_answers($entry->id, $questions, stage_get_submitted_answers($questions));
    if ($reportmissing) {
        $reportblocked = true;
    } else {
        stage_apply_student_eval($entry);
        stage_notify_teachers_selfeval($stage, $cm, $entry, $USER);

        redirect(new moodle_url('/mod/stage/view.php', ['id' => $cm->id]),
            get_string('stagesaved', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// Formulaire de repli (commentaire libre) si aucune question n'est définie pour la thématique :
// construit et traité avant tout affichage, pour permettre la redirection après soumission.
$mform = null;
if ($editable && empty($questions)) {
    // Le formulaire ne porte que le commentaire : les caractéristiques du stage sont fixées par la
    // DEVE et rappelées au-dessus par stage_render_entry_summary().
    $mform = new entry_form(null, []);

    $toform = new stdClass();
    $toform->id = $cm->id;
    $toform->entryid = $entryid;
    $toform->studentselfeval = ['text' => $entry->studentselfeval, 'format' => FORMAT_HTML];
    $mform->set_data($toform);

    if ($mform->is_cancelled()) {
        redirect(new moodle_url('/mod/stage/view.php', ['id' => $cm->id]));
    } else if ($data = $mform->get_data()) {
        if ($reportmissing) {
            $reportblocked = true;
        } else {
            $selfeval = is_array($data->studentselfeval) ? $data->studentselfeval['text'] : $data->studentselfeval;
            stage_apply_student_eval($entry, $selfeval);
            stage_notify_teachers_selfeval($stage, $cm, $entry, $USER);

            redirect(new moodle_url('/mod/stage/view.php', ['id' => $cm->id]),
                get_string('stagesaved', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('selfeval', 'mod_stage'));
echo html_writer::link(new moodle_url('/mod/stage/view.php', ['id' => $cm->id]), get_string('back'));

// Rappel de la saisie concernée : la page ne disait pas de quel stage il s'agissait, alors qu'un
// étudiant peut en avoir plusieurs en cours d'auto-évaluation.
echo stage_render_entry_summary($entry, $theme);

if (!$editable) {
    if (!$conventionsigned) {
        $message = get_string('conventionnotsignedyet', 'mod_stage');
    } else if ($notstartedyet) {
        $message = get_string('selfevalnotstartedyet', 'mod_stage');
    } else {
        $message = get_string('entrynoteditable', 'mod_stage');
    }
    echo $OUTPUT->notification($message, 'info');
    if (!empty($periods)) {
        echo $OUTPUT->heading(get_string('workdays', 'mod_stage'), 4);
        echo stage_render_workday_picker($periods, stage_get_entry_workdays($entry->id), false);
    }
    echo stage_render_report_section($cm, $context, $entry, $theme);
    $answers = stage_get_answers($entry->id);
    if (!empty($questions)) {
        echo stage_render_answers_readonly($questions, $answers);
    } else if ($entry->studentselfeval) {
        echo html_writer::div(format_text($entry->studentselfeval, FORMAT_HTML));
    }
    echo $OUTPUT->footer();
    exit;
}

if ($reportform) {
    echo $OUTPUT->heading(get_string('reportfiles', 'mod_stage'), 4);
    if ($reportblocked) {
        echo $OUTPUT->notification(get_string('reportrequiredmissing', 'mod_stage'), 'error');
    } else if ($reportmode == STAGE_REPORT_REQUIRED) {
        echo $OUTPUT->notification(get_string('reportrequirednotice', 'mod_stage'), 'info');
    }
    $reportform->display();
}

if (!empty($periods)) {
    echo $OUTPUT->heading(get_string('workdays', 'mod_stage'), 4);
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/mod/stage/entry.php', ['id' => $cm->id, 'entryid' => $entry->id]),
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'saveworkdays', 'value' => 1]);
    echo stage_render_workday_picker($periods, stage_get_entry_workdays($entry->id), true);
    echo html_writer::empty_tag('input', [
        'type' => 'submit', 'value' => get_string('savechanges'), 'class' => 'btn btn-primary mt-2',
    ]);
    echo html_writer::end_tag('form');
}

if (!empty($questions)) {
    // Formulaire dynamique défini par la DEVE pour cette thématique.
    $answers = stage_get_answers($entry->id);

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/mod/stage/entry.php', ['id' => $cm->id, 'entryid' => $entry->id]),
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    echo stage_render_question_fields($questions, $answers);

    echo html_writer::empty_tag('input', [
        'type' => 'submit', 'value' => get_string('savechanges'), 'class' => 'btn btn-primary',
    ]);
    echo html_writer::end_tag('form');
} else {
    // Aucune question définie pour cette thématique : commentaire libre générique.
    $mform->display();
}

echo $OUTPUT->footer();
