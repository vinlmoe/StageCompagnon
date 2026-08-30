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
 * Tableau de pilotage de la DEVE : avancement des validations pour l'ensemble des étudiants,
 * avec accès à la situation détaillée de chacun.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$id = required_param('id', PARAM_INT);
$studentid = optional_param('studentid', 0, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$tsort = optional_param('tsort', 'student', PARAM_ALPHA);
$tdir = optional_param('tdir', 'ASC', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

// Accessible à la DEVE (vision de tous les étudiants) et aux enseignants référents (vision
// restreinte aux étudiants qui leur sont attribués).
$isdeve = has_capability('mod/stage:viewall', $context);
$isteacher = has_capability('mod/stage:evaluateteacher', $context);
if (!$isdeve && !$isteacher) {
    require_capability('mod/stage:viewall', $context);
}
$restrictuserids = $isdeve ? null : array_keys(stage_get_assigned_students($stage->id, $USER->id));

$baseurl = new moodle_url('/mod/stage/dashboard.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('pilotage', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Situation détaillée d'un étudiant donné.
if ($studentid) {
    $student = $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST);
    if (!is_enrolled($context, $student) || ($restrictuserids !== null && !in_array($studentid, $restrictuserids))) {
        throw new moodle_exception('nopermissions', 'error', '', get_string('pilotage', 'mod_stage'));
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('pilotage', 'mod_stage') . ' - ' . fullname($student));
    echo html_writer::link($baseurl, get_string('back'));

    stage_print_student_dashboard($stage, $student->id, $cm, false, true);

    echo $OUTPUT->footer();
    exit;
}

// Vue d'ensemble : un étudiant par ligne, avec recherche par nom et tri. Page d'atterrissage de
// la DEVE et de l'enseignant référent (voir view.php, qui les redirige ici) : pas de lien
// "retour" vers view.php, remplacé par la barre de navigation entre les pages de gestion.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pilotage', 'mod_stage'));
echo stage_render_navlinks($cm, $context);

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

$rows = stage_get_pilotage_overview($stage->id, $context, $restrictuserids);

if ($search !== '') {
    $needle = core_text::strtolower($search);
    $rows = array_filter($rows, function($row) use ($needle) {
        return core_text::strpos(core_text::strtolower(fullname($row->user)), $needle) !== false;
    });
}

$sortmap = [
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
    $va = $sortfn($a);
    $vb = $sortfn($b);
    return $va <=> $vb;
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
        stage_sort_header(get_string('student', 'mod_stage'), 'student', $listurl, $sortkey, $tdir),
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
            // Plutôt qu'un simple "à compléter", indique quelles années sont déjà validées
            // (voir stage_get_student_year_progress()), pour situer l'avancement en un coup d'œil.
            // Le badge ne porte que le statut ; la liste des années le suit en clair, un badge
            // devenant illisible dès qu'il contient une énumération.
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
        // Une saisie en attente est ce sur quoi la DEVE a une action à faire : on la met en avant
        // plutôt que de la noyer dans une colonne de zéros.
        $pendingcell = $row->pendingcount > 0
            ? html_writer::span($row->pendingcount, 'badge badge-info')
            : html_writer::span('0', 'text-muted');

        $table->data[] = [
            fullname($row->user),
            $progresslabel,
            $pendingcell,
            get_string('retaineddaysonly', 'mod_stage', $row->progress->totalretained),
            $globalstatus,
            stage_render_actions([
                get_string('viewdetails', 'mod_stage') =>
                    new moodle_url('/mod/stage/dashboard.php', ['id' => $cm->id, 'studentid' => $row->user->id]),
            ]),
        ];
    }
    echo html_writer::table($table);
    echo $pagingbarhtml;
}

echo $OUTPUT->footer();
