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
 * Évaluation du stage par le maître de stage (encadrant en entreprise, sans compte Moodle),
 * accessible uniquement via le jeton unique envoyé par courriel (voir
 * stage_maybe_request_tutor_evaluation()). Aucune authentification Moodle : la validité du
 * jeton, à lui seul, fait foi.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$token = required_param('token', PARAM_ALPHANUM);

$entry = stage_get_entry_by_tutor_token($token);
if (!$entry) {
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(new moodle_url('/mod/stage/tutor_eval.php', ['token' => $token]));
    $PAGE->set_pagelayout('embedded');
    $PAGE->set_title(get_string('tutorevalpagetitle', 'mod_stage'));
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('tutorevalinvalidtoken', 'mod_stage'), 'error');
    echo $OUTPUT->footer();
    exit;
}

$stage = $DB->get_record('stage', ['id' => $entry->stageid], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('stage', $stage->id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$context = context_module::instance($cm->id);
$student = $DB->get_record('user', ['id' => $entry->userid], '*', MUST_EXIST);
$theme = $DB->get_record('stage_theme', ['id' => $entry->themeid]);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/mod/stage/tutor_eval.php', ['token' => $token]));
$PAGE->set_pagelayout('embedded');
$PAGE->set_title(get_string('tutorevalpagetitle', 'mod_stage'));
$PAGE->set_heading(format_string($stage->name));

$questions = stage_get_questions($entry->themeid, 'tutor');

if (empty($entry->tutortime) && data_submitted() && confirm_sesskey()) {
    if (!empty($questions)) {
        stage_save_answers($entry->id, $questions, stage_get_submitted_answers($questions));
        stage_apply_tutor_eval($entry);
    } else {
        $comment = optional_param('tutoreval', '', PARAM_RAW);
        stage_apply_tutor_eval($entry, $comment);
    }
    redirect(new moodle_url('/mod/stage/tutor_eval.php', ['token' => $token]));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('tutorevalpagetitle', 'mod_stage'));

if (!empty($entry->tutortime)) {
    echo $OUTPUT->notification(get_string('tutorevalalreadysubmitted', 'mod_stage'), 'success');
    if (!empty($questions)) {
        echo stage_render_answers_readonly($questions, stage_get_answers($entry->id));
    } else if ($entry->tutoreval) {
        echo html_writer::div(format_text($entry->tutoreval, FORMAT_PLAIN));
    }
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::tag('p', get_string('tutorevalintro', 'mod_stage', (object) [
    'student' => fullname($student),
    'stage' => format_string($stage->name) . ($theme ? ' - ' . format_string($theme->name) : ''),
]));

$formurl = new moodle_url('/mod/stage/tutor_eval.php', ['token' => $token]);
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $formurl]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

if (!empty($questions)) {
    echo stage_render_question_fields($questions, []);
} else {
    echo html_writer::tag('label', get_string('tutorevalheading', 'mod_stage'), ['for' => 'tutoreval']);
    echo html_writer::tag('textarea', '',
        ['name' => 'tutoreval', 'id' => 'tutoreval', 'rows' => 6, 'class' => 'form-control']);
}

echo html_writer::empty_tag('input', [
    'type' => 'submit', 'value' => get_string('tutorevalsubmit', 'mod_stage'), 'class' => 'btn btn-primary mt-2',
]);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
