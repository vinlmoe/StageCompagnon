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
 * Vue des stages faits sur une thématique, pour l'enseignant qui en est responsable (voir
 * theme_teachers.php) : la liste de tous les stages de la thématique, quels que soient les
 * enseignants référents des étudiants, et les rapports de stage déposés, téléchargeables un par
 * un ou en une archive ZIP.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$id = required_param('id', PARAM_INT);
$themeid = optional_param('themeid', 0, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$filterstatus = optional_param('status', '', PARAM_RAW);
$tsort = optional_param('tsort', 'student', PARAM_ALPHA);
$tdir = optional_param('tdir', 'ASC', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:view', $context);

// Thématiques ouvertes à l'utilisateur : celles dont il est responsable ; la DEVE, qui voit déjà
// tous les stages, dispose ici de la même vue par thématique pour toutes les thématiques.
$themes = has_capability('mod/stage:viewall', $context)
    ? stage_get_themes($stage->id)
    : stage_get_teacher_themes($stage->id, $USER->id);
if (empty($themes)) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('mythemestages', 'mod_stage'));
}

// Sans thématique choisie, la première de la liste : la page n'a de sens que thématique par
// thématique (les rapports se téléchargent par thématique).
if (!$themeid || !isset($themes[$themeid])) {
    $themeid = (int) reset($themes)->id;
}
$theme = $themes[$themeid];

$baseurl = new moodle_url('/mod/stage/theme_stages.php', ['id' => $cm->id, 'themeid' => $themeid]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('mythemestages', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('mythemestages', 'mod_stage'));
echo html_writer::link(new moodle_url('/mod/stage/view.php', ['id' => $cm->id]), get_string('back'));

echo stage_render_navlinks($cm, $context);

// Choix de la thématique : des onglets plutôt qu'une liste déroulante, un enseignant n'étant en
// général responsable que de quelques thématiques.
$tabs = [];
foreach ($themes as $availabletheme) {
    $taburl = new moodle_url('/mod/stage/theme_stages.php', ['id' => $cm->id, 'themeid' => $availabletheme->id]);
    $tabs[] = new tabobject('stagetheme' . $availabletheme->id, $taburl, format_string($availabletheme->name));
}
if (count($tabs) > 1) {
    echo $OUTPUT->tabtree($tabs, 'stagetheme' . $themeid);
}

$listurl = new moodle_url($baseurl, ['search' => $search, 'status' => $filterstatus, 'tsort' => $tsort, 'tdir' => $tdir]);

// Filtres propres à cette page plutôt que le bandeau commun (stage_render_list_filters) : ici la
// thématique est choisie par les onglets ci-dessus et doit être conservée à chaque recherche,
// alors que le bandeau commun la propose lui-même en liste déroulante.
$filterurl = new moodle_url('/mod/stage/theme_stages.php');
echo html_writer::start_tag('form',
    ['method' => 'get', 'action' => $filterurl, 'class' => 'form-inline stage-filters mb-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'themeid', 'value' => $themeid]);
echo html_writer::empty_tag('input', [
    'type' => 'text', 'name' => 'search', 'value' => s($search),
    'placeholder' => get_string('searchstudent', 'mod_stage'), 'class' => 'form-control mr-2',
]);
$statusoptions = ['' => get_string('allstatuses', 'mod_stage')];
foreach ([STAGE_STATUS_ANNULE, STAGE_STATUS_NON_VALIDE, STAGE_STATUS_ENREGISTRE, STAGE_STATUS_EVAL_ETUDIANT,
        STAGE_STATUS_EVAL_ENSEIGNANT, STAGE_STATUS_VALIDE_DEVE] as $statuscode) {
    $statusoptions[$statuscode] = stage_status_label($statuscode);
}
echo html_writer::select($statusoptions, 'status', $filterstatus, false, ['class' => 'form-control mr-2']);
echo html_writer::empty_tag('input', [
    'type' => 'submit', 'value' => get_string('search'), 'class' => 'btn btn-secondary mr-2',
]);
echo html_writer::link(new moodle_url($filterurl, ['id' => $cm->id, 'themeid' => $themeid]),
    get_string('resetfilters', 'mod_stage'), ['class' => 'btn btn-link']);
echo html_writer::end_tag('form');

$allentries = stage_get_filtered_entries($stage->id,
    ['search' => $search, 'themeid' => $themeid, 'status' => $filterstatus], $tsort, $tdir);
[$entries, $pagingbarhtml] = stage_paginate($allentries, $page, $listurl);

if (empty($allentries)) {
    echo $OUTPUT->notification(get_string('nostagesfortheme', 'mod_stage'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$students = stage_get_entry_users($allentries);

// Téléchargement groupé : proposé seulement si au moins un rapport a été déposé, un ZIP vide
// n'ayant aucun intérêt (et stage_send_reports_zip() refusant de le construire).
$hasreports = false;
foreach ($allentries as $entry) {
    if (stage_get_report_files($context, $entry->id)) {
        $hasreports = true;
        break;
    }
}
if ($hasreports) {
    $zipurl = new moodle_url('/mod/stage/reports_zip.php', [
        'id' => $cm->id, 'themeid' => $themeid, 'search' => $search, 'status' => $filterstatus,
        'sesskey' => sesskey(),
    ]);
    echo html_writer::link($zipurl, get_string('downloadallreports', 'mod_stage'),
        ['class' => 'btn btn-primary mb-3']);
}

$table = new html_table();
$table->head = [
    stage_sort_header(get_string('student', 'mod_stage'), 'student', $listurl, $tsort, $tdir),
    stage_sort_header(get_string('declaredduration', 'mod_stage'), 'duration', $listurl, $tsort, $tdir),
    stage_sort_header(get_string('status', 'mod_stage'), 'status', $listurl, $tsort, $tdir),
    get_string('reportfiles', 'mod_stage'),
    get_string('actions', 'mod_stage'),
];
foreach ($entries as $entry) {
    $student = $students[$entry->userid] ?? null;
    $badge = html_writer::span(stage_status_label($entry->status), 'badge ' . stage_status_badgeclass($entry->status));
    $reportlinks = stage_render_report_links($cm, $context, $entry);
    // Le retour ramène sur cette liste telle qu'affichée (recherche, statut, tri), pas sur la
    // vue de pilotage à laquelle l'enseignant responsable de thématique n'a pas forcément accès.
    $action = html_writer::link(
        new moodle_url('/mod/stage/entrydetail.php',
            ['id' => $cm->id, 'entryid' => $entry->id, 'returnurl' => $listurl->out_as_local_url(false)]),
        get_string('viewdetails', 'mod_stage'), ['class' => 'btn btn-sm btn-secondary']);

    $table->data[] = [
        $student ? fullname($student) : '-',
        $entry->declaredduration,
        $badge,
        $reportlinks !== '' ? $reportlinks : html_writer::span(get_string('noreportfiles', 'mod_stage'), 'text-muted'),
        $action,
    ];
}
echo html_writer::table($table);

echo $pagingbarhtml;

echo $OUTPUT->footer();
