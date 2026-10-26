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
 * Import Excel, par la DEVE, des mobilités d'études de plusieurs étudiants à la fois (voir
 * \mod_stage\local\study_mobility_importer pour le format du classeur). Un modèle prêt à remplir,
 * listant les étudiants inscrits, est proposé au téléchargement.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_stage\local\study_mobility_importer;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/excellib.class.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$id = required_param('id', PARAM_INT);
$template = optional_param('template', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:validatedeve', $context);

$baseurl = new moodle_url('/mod/stage/study_mobility_import.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('studymobilityimport', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Modèle : un étudiant inscrit par ligne, avec sa mobilité d'études déjà validée le cas échéant,
// pour que la DEVE n'ait qu'à compléter les colonnes « Mobilité d'études » et « Lieu ».
if ($template) {
    $workbook = new MoodleExcelWorkbook('-');
    $workbook->send(clean_filename(get_string('studymobilityimporttemplatefile', 'mod_stage') . '.xlsx'));
    $sheet = $workbook->add_worksheet(get_string('studymobility', 'mod_stage'));
    $headerformat = new MoodleExcelFormat(['bold' => 1]);
    $headers = [
        get_string('lastname'), get_string('firstname'), get_string('email'),
        get_string('studymobility', 'mod_stage'), get_string('studymobilityimportlocationheader', 'mod_stage'),
    ];
    foreach ($headers as $col => $header) {
        $sheet->write_string(0, $col, $header, $headerformat);
    }
    $row = 1;
    foreach (stage_get_enrolled_students($context) as $student) {
        $mobility = stage_get_student_study_mobility($stage->id, $student->id);
        $sheet->write_string($row, 0, $student->lastname);
        $sheet->write_string($row, 1, $student->firstname);
        $sheet->write_string($row, 2, $student->email);
        $sheet->write_string($row, 3, $mobility ? get_string('yes') : '');
        $sheet->write_string($row, 4, $mobility ? $mobility->location : '');
        $row++;
    }
    $workbook->close();
    exit;
}

$results = null;
$uploaderror = null;

if (data_submitted() && confirm_sesskey()) {
    $upload = $_FILES['xlsxfile'] ?? null;
    if (empty($upload) || $upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
        $uploaderror = get_string('importerrorupload', 'mod_stage');
    } else {
        try {
            $records = study_mobility_importer::read($upload['tmp_name']);
            $results = study_mobility_importer::import($stage, $context, $records, $USER->id);
        } catch (moodle_exception $e) {
            $uploaderror = $e->getMessage();
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('studymobilityimport', 'mod_stage'));
echo html_writer::link(new moodle_url('/mod/stage/administration.php', ['id' => $cm->id]), get_string('back'));

if ($uploaderror !== null) {
    echo $OUTPUT->notification($uploaderror, \core\output\notification::NOTIFY_ERROR);
}
if ($results) {
    echo $OUTPUT->notification(
        get_string('studymobilityimportresult', 'mod_stage', $results),
        \core\output\notification::NOTIFY_SUCCESS
    );
    if (!empty($results->errors)) {
        echo $OUTPUT->notification(
            implode(html_writer::empty_tag('br'), array_map('s', $results->errors)),
            \core\output\notification::NOTIFY_WARNING
        );
    }
}

echo $OUTPUT->box(get_string('studymobilityimport_help', 'mod_stage'), 'generalbox mb-3');
echo html_writer::div(html_writer::link(
    new moodle_url($baseurl, ['template' => 1]),
    get_string('studymobilityimporttemplate', 'mod_stage'),
    ['class' => 'btn btn-outline-secondary']
), 'mb-3');

echo html_writer::start_tag('form', [
    'method' => 'post', 'action' => $baseurl, 'enctype' => 'multipart/form-data',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', [
    'type' => 'file', 'name' => 'xlsxfile', 'required' => 'required',
    'accept' => '.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
]);
echo html_writer::tag(
    'button',
    get_string('import', 'mod_stage'),
    ['type' => 'submit', 'class' => 'btn btn-primary ml-2']
);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
