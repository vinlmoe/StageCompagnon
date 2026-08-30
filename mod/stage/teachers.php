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
 * Attribution des enseignants référents aux étudiants (DEVE).
 *
 * Conçue pour un grand nombre d'étudiants et d'enseignants (recherche par nom, affichage
 * paginé, filtre "sans référent") : afficher les 80 enseignants en cases à cocher pour
 * chacun des 1000 étudiants sur une seule page n'est pas praticable. Pour une attribution en
 * masse, voir aussi teachers_import.php (import CSV/Excel).
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$id = required_param('id', PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$onlyunassigned = optional_param('onlyunassigned', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:manageteachers', $context);

$baseurl = new moodle_url('/mod/stage/teachers.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('manageteachers', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$allstudents = stage_get_enrolled_students($context);
$teachers = stage_get_potential_teachers($context);

// Toutes les affectations de l'activité en une requête, regroupées par étudiant.
$assignments = [];
foreach ($DB->get_records('stage_entry_teacher', ['stageid' => $stage->id], '', 'id, studentid, teacherid')
        as $assignment) {
    $assignments[$assignment->studentid][$assignment->teacherid] = true;
}

// Filtre (recherche par nom, étudiants sans référent) appliqué avant la pagination.
$students = $allstudents;
if ($search !== '') {
    $needle = core_text::strtolower($search);
    $students = array_filter($students, function($student) use ($needle) {
        return core_text::strpos(core_text::strtolower(fullname($student)), $needle) !== false;
    });
}
if ($onlyunassigned) {
    $students = array_filter($students, function($student) use ($assignments) {
        return empty($assignments[$student->id]);
    });
}
$listurl = new moodle_url($baseurl, ['search' => $search, 'onlyunassigned' => $onlyunassigned]);
$returnurl = new moodle_url($listurl, ['page' => $page]);

[$pagestudents, $pagingbarhtml] = stage_paginate($students, $page, $listurl);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manageteachers', 'mod_stage'));
echo html_writer::link(new moodle_url('/mod/stage/administration.php', ['id' => $cm->id]), get_string('back'));

echo html_writer::link(new moodle_url('/mod/stage/teachers_import.php', ['id' => $cm->id]),
    get_string('importteacherscsv', 'mod_stage'), ['class' => 'btn btn-secondary d-block mt-2 mb-3', 'style' => 'width:fit-content']);

if (empty($allstudents)) {
    echo $OUTPUT->notification(get_string('nostudents', 'mod_stage'), 'info');
} else if (empty($teachers)) {
    echo $OUTPUT->notification(get_string('noteachers', 'mod_stage'), 'info');
} else {
    // Recherche par nom d'étudiant + filtre "sans référent".
    echo html_writer::start_tag('form', ['method' => 'get', 'action' => $baseurl, 'class' => 'form-inline stage-filters mb-3']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
    echo html_writer::empty_tag('input', [
        'type' => 'text', 'name' => 'search', 'value' => s($search),
        'placeholder' => get_string('searchstudent', 'mod_stage'), 'class' => 'form-control mr-2',
    ]);
    echo html_writer::start_tag('label', ['class' => 'mr-2']);
    echo html_writer::checkbox('onlyunassigned', 1, (bool) $onlyunassigned, ' ' . get_string('onlyunassigned', 'mod_stage'));
    echo html_writer::end_tag('label');
    echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('search'), 'class' => 'btn btn-secondary mr-2']);
    echo html_writer::link($baseurl, get_string('resetfilters', 'mod_stage'), ['class' => 'btn btn-link']);
    echo html_writer::end_tag('form');

    if (empty($students)) {
        echo $OUTPUT->notification(get_string('nostudents', 'mod_stage'), 'info');
    } else {
        $teachersbyid = [];
        foreach ($teachers as $teacher) {
            $teachersbyid[$teacher->id] = $teacher;
        }

        $table = new html_table();
        $table->head = [
            get_string('student', 'mod_stage'),
            get_string('currentreferentteachers', 'mod_stage'),
            get_string('actions', 'mod_stage'),
        ];
        foreach ($pagestudents as $student) {
            $currentids = array_keys($assignments[$student->id] ?? []);
            if (empty($currentids)) {
                $currentlabel = html_writer::span(get_string('noreferentteacher', 'mod_stage'), 'text-muted');
            } else {
                $names = array_map(function($teacherid) use ($teachersbyid) {
                    return isset($teachersbyid[$teacherid]) ? fullname($teachersbyid[$teacherid]) : '?';
                }, $currentids);
                $currentlabel = implode(', ', $names);
            }
            $editurl = new moodle_url('/mod/stage/teacher_assign.php',
                ['id' => $cm->id, 'studentid' => $student->id, 'returnurl' => $returnurl->out_as_local_url(false)]);
            $table->data[] = [
                fullname($student),
                $currentlabel,
                html_writer::link($editurl, get_string('edit'), ['class' => 'btn btn-secondary btn-sm']),
            ];
        }
        echo html_writer::table($table);

        echo $pagingbarhtml;
    }
}

echo $OUTPUT->footer();
