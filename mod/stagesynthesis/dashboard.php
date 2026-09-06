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
 * Tableau de pilotage : reprend mod_stage/dashboard.php (avancement des validations, avec accès à
 * la situation détaillée de chaque étudiant), combiné sur toutes les activités "Gestion des
 * stages" liées où l'utilisateur connecté est enseignant référent. Page d'atterrissage de
 * l'activité (voir view.php, qui y redirige) : mêmes conventions que sur l'écran d'origine.
 *
 * @package   mod_stagesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stagesynthesis/locallib.php');

$id = required_param('id', PARAM_INT);
$studentid = optional_param('studentid', 0, PARAM_INT);
$studentcmid = optional_param('cmid', 0, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$tsort = optional_param('tsort', 'student', PARAM_ALPHA);
$tdir = optional_param('tdir', 'ASC', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);

$cm = get_coursemodule_from_id('stagesynthesis', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stagesynthesis = $DB->get_record('stagesynthesis', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stagesynthesis:view', $context);

$baseurl = new moodle_url('/mod/stagesynthesis/dashboard.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stagesynthesis->name) . ' - ' . get_string('pilotage', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$activelinks = stagesynthesis_get_active_links($stagesynthesis->id, $USER->id);

// Situation détaillée d'un étudiant donné : délègue entièrement à stage_print_student_dashboard(),
// qui affiche déjà tout (synthèse, thématiques, mobilité...) pour une activité mod_stage donnée --
// seul le repérage de l'activité et de l'étudiant dans le périmètre change ici.
if ($studentid) {
    if (!isset($activelinks[$studentcmid])) {
        throw new moodle_exception('nopermissions', 'error', '', get_string('pilotage', 'mod_stage'));
    }
    $link = $activelinks[$studentcmid];
    if (!in_array($studentid, $link->assignedids, true)) {
        throw new moodle_exception('nopermissions', 'error', '', get_string('pilotage', 'mod_stage'));
    }
    $student = $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('pilotage', 'mod_stage') . ' - ' . fullname($student));
    echo html_writer::link($baseurl, get_string('back'));

    stage_print_student_dashboard($link->stage, $student->id, $link->cm, false, true);

    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($stagesynthesis->name));
echo html_writer::link(new moodle_url('/mod/stagesynthesis/entries.php', ['id' => $cm->id]),
    get_string('teachervalidation', 'mod_stage'));

if ($stagesynthesis->intro) {
    echo $OUTPUT->box(format_module_intro('stagesynthesis', $stagesynthesis, $cm->id), 'generalbox mod_introbox');
}

echo stagesynthesis_render_managelinks_notice($stagesynthesis, $cm, $context);

if (empty($activelinks)) {
    echo $OUTPUT->notification(get_string('nostudents', 'mod_stagesynthesis'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$listurl = new moodle_url($baseurl, ['search' => $search, 'tsort' => $tsort, 'tdir' => $tdir]);

$searchformurl = new moodle_url($baseurl);
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $searchformurl, 'class' => 'form-inline stage-filters mb-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
echo html_writer::empty_tag('input', [
    'type' => 'text', 'name' => 'search', 'value' => s($search),
    'placeholder' => get_string('searchstudent', 'mod_stage'), 'class' => 'form-control mr-2',
]);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('search'), 'class' => 'btn btn-secondary mr-2']);
echo html_writer::link($searchformurl, get_string('resetfilters', 'mod_stage'), ['class' => 'btn btn-link']);
echo html_writer::end_tag('form');

$rows = stagesynthesis_get_pilotage_rows($activelinks);

if ($search !== '') {
    $needle = core_text::strtolower($search);
    $rows = array_filter($rows, function($row) use ($needle) {
        return core_text::strpos(core_text::strtolower(fullname($row->user)), $needle) !== false;
    });
}

$sortmap = [
    'course' => function($row) {
        return core_text::strtolower($row->coursename);
    },
    'student' => function($row) {
        return core_text::strtolower(fullname($row->user));
    },
    'progress' => function($row) {
        return $row->mandatorytotal > 0 ? ($row->mandatorydone / $row->mandatorytotal) : -1;
    },
    'pending' => function($row) {
        return $row->pendingcount;
    },
    'retained' => function($row) {
        return $row->progress->totalretained;
    },
];
$sortkey = array_key_exists($tsort, $sortmap) ? $tsort : 'student';
$sortfn = $sortmap[$sortkey];
usort($rows, function($a, $b) use ($sortfn) {
    return $sortfn($a) <=> $sortfn($b);
});
if (strtoupper($tdir) === 'DESC') {
    $rows = array_reverse($rows);
}

if (empty($rows)) {
    echo $OUTPUT->notification(get_string('nostudents', 'mod_stage'), 'info');
} else {
    [$rows, $pagingbarhtml] = stage_paginate($rows, $page, $listurl);

    $table = new html_table();
    $table->head = [
        stage_sort_header(get_string('course'), 'course', $listurl, $sortkey, $tdir),
        stage_sort_header(get_string('student', 'mod_stage'), 'student', $listurl, $sortkey, $tdir),
        get_string('currentstudyyear', 'mod_stage'),
        stage_sort_header(get_string('mandatorythemes', 'mod_stage'), 'progress', $listurl, $sortkey, $tdir),
        stage_sort_header(get_string('pendingstages', 'mod_stage'), 'pending', $listurl, $sortkey, $tdir),
        stage_sort_header(get_string('totalretainedshort', 'mod_stage'), 'retained', $listurl, $sortkey, $tdir),
        get_string('status', 'mod_stage'),
        get_string('actions', 'mod_stage'),
    ];
    foreach ($rows as $row) {
        $progresslabel = $row->mandatorytotal > 0
            ? "{$row->mandatorydone} / {$row->mandatorytotal}"
            : get_string('nomandatorythemes', 'mod_stage');
        if ($row->complete) {
            $globalstatus = html_writer::span(get_string('themedone', 'mod_stage'), 'badge badge-success');
        } else {
            $validatedyears = array_filter($row->yearprogress, function($yearrow) {
                return $yearrow->done;
            });
            if (!empty($validatedyears)) {
                $labels = array_map(function($yearrow) {
                    return stage_studyyear_label($yearrow->studyyear);
                }, $validatedyears);
                $globalstatus = html_writer::span(get_string('themetodo', 'mod_stage'), 'badge badge-warning')
                    . html_writer::div(get_string('validatedyears', 'mod_stage', implode(', ', $labels)),
                        'text-muted small');
            } else {
                $globalstatus = html_writer::span(get_string('themetodo', 'mod_stage'), 'badge badge-warning');
            }
        }
        $pendingcell = $row->pendingcount > 0
            ? html_writer::span($row->pendingcount, 'badge badge-info')
            : html_writer::span('0', 'text-muted');

        $table->data[] = [
            format_string($row->coursename),
            fullname($row->user),
            stage_studyyear_label($row->currentstudyyear),
            $progresslabel,
            $pendingcell,
            get_string('retaineddaysonly', 'mod_stage', $row->progress->totalretained),
            $globalstatus,
            stage_render_actions([
                get_string('viewdetails', 'mod_stage') =>
                    new moodle_url('/mod/stagesynthesis/dashboard.php',
                        ['id' => $cm->id, 'studentid' => $row->user->id, 'cmid' => $row->cmid]),
            ]),
        ];
    }
    echo html_writer::table($table);
    echo $pagingbarhtml;
}

echo $OUTPUT->footer();
