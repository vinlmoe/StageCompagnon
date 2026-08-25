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
 * Export au format Excel (xlsx) de tous les stages d'une activité, pour la DEVE.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/excellib.class.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:viewall', $context);

$entries = $DB->get_records('stage_entry', ['stageid' => $stage->id], 'timecreated ASC');
$students = stage_get_entry_users($entries);
$themes = stage_get_themes($stage->id);

$teacherids = array_filter(array_unique(array_map(function($entry) {
    return $entry->teacherid;
}, $entries)));
$teachers = $teacherids ? $DB->get_records_list('user', 'id', $teacherids, '', 'id, ' . implode(', ',
    \core_user\fields::get_name_fields())) : [];

$filename = clean_filename(format_string($course->shortname) . '_' . format_string($stage->name) . '_stages.xlsx');

$workbook = new MoodleExcelWorkbook('-');
$workbook->send($filename);

$worksheet = $workbook->add_worksheet(get_string('modulename', 'mod_stage'));

$headerformat = new MoodleExcelFormat(['bold' => 1]);
$dateformat = new MoodleExcelFormat(['num_format' => 'dd/mm/yyyy']);

$headers = [
    get_string('student', 'mod_stage'),
    get_string('email'),
    get_string('theme', 'mod_stage'),
    get_string('mandatory', 'mod_stage'),
    get_string('structure', 'mod_stage'),
    get_string('datestart', 'mod_stage'),
    get_string('dateend', 'mod_stage'),
    get_string('declaredduration', 'mod_stage'),
    get_string('retainedduration', 'mod_stage'),
    get_string('status', 'mod_stage'),
    get_string('referentteachers', 'mod_stage'),
    get_string('teachereval', 'mod_stage'),
    get_string('devecomment', 'mod_stage'),
];
foreach ($headers as $col => $header) {
    $worksheet->write_string(0, $col, $header, $headerformat);
}

$row = 1;
foreach ($entries as $entry) {
    $student = $students[$entry->userid] ?? null;
    $theme = $themes[$entry->themeid] ?? null;
    $teacher = $entry->teacherid && isset($teachers[$entry->teacherid]) ? $teachers[$entry->teacherid] : null;

    $col = 0;
    $worksheet->write_string($row, $col++, $student ? fullname($student) : '');
    $worksheet->write_string($row, $col++, $student ? $student->email : '');
    $worksheet->write_string($row, $col++, $theme ? format_string($theme->name) : '');
    $worksheet->write_string($row, $col++, ($theme && $theme->mandatory) ? get_string('yes') : get_string('no'));
    $worksheet->write_string($row, $col++, (string) $entry->structure);

    if ($entry->datestart) {
        $worksheet->write_date($row, $col, $entry->datestart, $dateformat);
    } else {
        $worksheet->write_string($row, $col, '');
    }
    $col++;
    if ($entry->dateend) {
        $worksheet->write_date($row, $col, $entry->dateend, $dateformat);
    } else {
        $worksheet->write_string($row, $col, '');
    }
    $col++;

    $worksheet->write_number($row, $col++, (int) $entry->declaredduration);
    $worksheet->write_number($row, $col++, (int) $entry->retainedduration);
    $worksheet->write_string($row, $col++, stage_status_label($entry->status));
    $worksheet->write_string($row, $col++, $teacher ? fullname($teacher) : '');
    $worksheet->write_string($row, $col++, (string) $entry->teachereval);
    $worksheet->write_string($row, $col++, (string) $entry->devecomment);

    $row++;
}

$workbook->close();
