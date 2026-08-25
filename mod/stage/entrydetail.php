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
 * Détail en lecture seule d'une saisie de stage, pour la DEVE ou l'enseignant référent de
 * l'étudiant concerné (accessible notamment depuis le tableau de pilotage).
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$id = required_param('id', PARAM_INT);
$entryid = required_param('entryid', PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

$entry = $DB->get_record('stage_entry', ['id' => $entryid, 'stageid' => $stage->id], '*', MUST_EXIST);

$isdeve = has_capability('mod/stage:viewall', $context);
$isassignedteacher = has_capability('mod/stage:evaluateteacher', $context)
    && in_array($entry->userid, array_keys(stage_get_assigned_students($stage->id, $USER->id)));
if (!$isdeve && !$isassignedteacher) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('viewdetails', 'mod_stage'));
}

$student = $DB->get_record('user', ['id' => $entry->userid], '*', MUST_EXIST);
$theme = $DB->get_record('stage_theme', ['id' => $entry->themeid]);

$baseurl = new moodle_url('/mod/stage/entrydetail.php', ['id' => $cm->id, 'entryid' => $entry->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('viewdetails', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('viewdetails', 'mod_stage') . ' - ' . fullname($student));

$backurl = new moodle_url('/mod/stage/dashboard.php', ['id' => $cm->id, 'studentid' => $student->id]);
echo html_writer::link($backurl, get_string('back'));

echo html_writer::tag('p', get_string('theme', 'mod_stage') . ' : '
    . ($theme ? format_string($theme->name) : '-'));
if ($theme) {
    echo html_writer::tag('p', get_string('studyyear', 'mod_stage') . ' : ' . stage_studyyear_label($theme->studyyear));
}
echo html_writer::tag('p', get_string('structure', 'mod_stage') . ' : ' . s($entry->structure));
$dateformat = get_string('strftimedate', 'langconfig');
if ($entry->datestart) {
    echo html_writer::tag('p', get_string('datestart', 'mod_stage') . ' : ' . userdate($entry->datestart, $dateformat));
}
if ($entry->dateend) {
    echo html_writer::tag('p', get_string('dateend', 'mod_stage') . ' : ' . userdate($entry->dateend, $dateformat));
}
echo html_writer::tag('p', get_string('declaredduration', 'mod_stage') . ' : ' . $entry->declaredduration);
echo html_writer::tag('p', get_string('retainedduration', 'mod_stage') . ' : ' . $entry->retainedduration);
echo html_writer::tag('p', get_string('status', 'mod_stage') . ' : '
    . html_writer::span(stage_status_label($entry->status), 'badge ' . stage_status_badgeclass($entry->status)));

$answers = stage_get_answers($entry->id);

$studentquestions = stage_get_questions($entry->themeid, 'student');
if (!empty($studentquestions) || $entry->studentselfeval) {
    echo $OUTPUT->heading(get_string('studentselfeval', 'mod_stage'), 4);
    echo !empty($studentquestions)
        ? stage_render_answers_readonly($studentquestions, $answers)
        : html_writer::div(format_text($entry->studentselfeval, FORMAT_HTML));
}

$teacherquestions = stage_get_questions($entry->themeid, 'teacher');
if (!empty($teacherquestions) || $entry->teachereval) {
    echo $OUTPUT->heading(get_string('teachereval', 'mod_stage'), 4);
    echo !empty($teacherquestions)
        ? stage_render_answers_readonly($teacherquestions, $answers)
        : html_writer::div(format_text($entry->teachereval, FORMAT_PLAIN));
}

if ($entry->devecomment) {
    echo $OUTPUT->heading(get_string('devecomment', 'mod_stage'), 4);
    echo html_writer::div(format_text($entry->devecomment, FORMAT_PLAIN));
}

echo $OUTPUT->footer();
