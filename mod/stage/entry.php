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

use mod_stage\form\entry_form;

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

// L'auto-évaluation n'est ouverte qu'une fois la convention de stage signée (voir
// convention_request.php / conventions.php), et n'est modifiable que tant qu'elle n'a pas
// encore été soumise : une fois soumise (ou la saisie rejetée), seule la DEVE peut réinitialiser
// la saisie pour la rouvrir.
$conventionsigned = stage_convention_is_signed($entry->conventionstatus);
$editable = $conventionsigned && ((int) $entry->status === STAGE_STATUS_ENREGISTRE);

// Traite la soumission du formulaire dynamique avant tout affichage, pour permettre la redirection.
if ($editable && !empty($questions) && data_submitted() && confirm_sesskey()) {
    stage_save_answers($entry->id, $questions, stage_get_submitted_answers($questions));
    stage_apply_student_eval($entry);
    stage_notify_teachers_selfeval($stage, $cm, $entry, $USER);

    redirect(new moodle_url('/mod/stage/view.php', ['id' => $cm->id]),
        get_string('stagesaved', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Formulaire de repli (commentaire libre) si aucune question n'est définie pour la thématique :
// construit et traité avant tout affichage, pour permettre la redirection après soumission.
$mform = null;
if ($editable && empty($questions)) {
    $entrytheme = $DB->get_record('stage_theme', ['id' => $entry->themeid]);
    $customdata = [
        'themes' => stage_get_themes($stage->id, true),
        'locked' => true,
        'themename' => $entrytheme ? format_string($entrytheme->name) : '',
        'structure' => $entry->structure,
        'datestart' => $entry->datestart,
        'dateend' => $entry->dateend,
        'declaredduration' => $entry->declaredduration,
    ];
    $mform = new entry_form(null, $customdata);

    $toform = new stdClass();
    $toform->id = $cm->id;
    $toform->entryid = $entryid;
    $toform->themeid = $entry->themeid;
    $toform->structure = $entry->structure;
    $toform->datestart = $entry->datestart;
    $toform->dateend = $entry->dateend;
    $toform->declaredduration = $entry->declaredduration;
    $toform->studentselfeval = ['text' => $entry->studentselfeval, 'format' => FORMAT_HTML];
    $mform->set_data($toform);

    if ($mform->is_cancelled()) {
        redirect(new moodle_url('/mod/stage/view.php', ['id' => $cm->id]));
    } else if ($data = $mform->get_data()) {
        $selfeval = is_array($data->studentselfeval) ? $data->studentselfeval['text'] : $data->studentselfeval;
        stage_apply_student_eval($entry, $selfeval);
        stage_notify_teachers_selfeval($stage, $cm, $entry, $USER);

        redirect(new moodle_url('/mod/stage/view.php', ['id' => $cm->id]),
            get_string('stagesaved', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('selfeval', 'mod_stage'));

if (!$editable) {
    $message = !$conventionsigned ? get_string('conventionnotsignedyet', 'mod_stage') : get_string('entrynoteditable', 'mod_stage');
    echo $OUTPUT->notification($message, 'info');
    $answers = stage_get_answers($entry->id);
    if (!empty($questions)) {
        echo stage_render_answers_readonly($questions, $answers);
    } else if ($entry->studentselfeval) {
        echo html_writer::div(format_text($entry->studentselfeval, FORMAT_HTML));
    }
    echo $OUTPUT->footer();
    exit;
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
