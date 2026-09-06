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
 * Reprend l'écran de validation enseignant de mod_stage (teacher.php) -- conventions en attente,
 * liste filtrable/triable des saisies en attente d'évaluation -- combiné sur toutes les activités
 * "Gestion des stages" liées où l'utilisateur connecté est enseignant référent. Complète
 * dashboard.php (pilotage par étudiant, page d'atterrissage de l'activité) : voir ce fichier pour
 * la navigation entre les deux.
 *
 * @package   mod_stagesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stagesynthesis/locallib.php');

$id = required_param('id', PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$themekey = optional_param('themekey', '', PARAM_RAW);
$filterstatus = optional_param('status', '', PARAM_RAW);
$tsort = optional_param('tsort', 'timecreated', PARAM_ALPHA);
$tdir = optional_param('tdir', 'DESC', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);

$cm = get_coursemodule_from_id('stagesynthesis', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stagesynthesis = $DB->get_record('stagesynthesis', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stagesynthesis:view', $context);

$baseurl = new moodle_url('/mod/stagesynthesis/entries.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stagesynthesis->name) . ' - ' . get_string('teachervalidation', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($stagesynthesis->name));
echo html_writer::link(new moodle_url('/mod/stagesynthesis/dashboard.php', ['id' => $cm->id]),
    get_string('pilotage', 'mod_stage'));

echo stagesynthesis_render_managelinks_notice($stagesynthesis, $cm, $context);

$activelinks = stagesynthesis_get_active_links($stagesynthesis->id, $USER->id);

if (empty($activelinks)) {
    echo $OUTPUT->notification(get_string('nostudents', 'mod_stagesynthesis'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// Demandes de convention en attente de validation enseignant, avant transmission à la DEVE (voir
// stage_get_teacher_pending_convention_entries()), agrégées sur toutes les activités actives.
$pendingconventions = stagesynthesis_get_pending_convention_entries($activelinks, $USER->id);
echo $OUTPUT->heading(get_string('conventionteachervalidation', 'mod_stage'), 4);
if (empty($pendingconventions)) {
    echo $OUTPUT->notification(get_string('noconventionteachervalidations', 'mod_stage'), 'info');
} else {
    $pendingtable = new html_table();
    $pendingtable->head = [
        get_string('course'),
        get_string('student', 'mod_stage'),
        get_string('theme', 'mod_stage'),
        get_string('conventionrequestdate', 'mod_stage'),
        get_string('actions', 'mod_stage'),
    ];
    foreach ($pendingconventions as $pendingentry) {
        $pendingtable->data[] = [
            format_string($pendingentry->coursename),
            $pendingentry->studentfullname,
            $pendingentry->themename,
            $pendingentry->conventionrequesttime
                ? userdate($pendingentry->conventionrequesttime, get_string('strftimedatetimeshort')) : '-',
            stage_render_actions([
                get_string('conventionteachervalidate', 'mod_stage') =>
                    new moodle_url('/mod/stage/convention_teacher_validate.php',
                        ['id' => $pendingentry->cmid, 'entryid' => $pendingentry->id,
                            'returnurl' => $baseurl->out_as_local_url(false)]),
            ], 'btn btn-sm btn-primary mr-1 mb-1'),
        ];
    }
    echo html_writer::table($pendingtable);
}

echo $OUTPUT->heading(get_string('stagestoevaluate', 'mod_stage'), 4);

$themeoptions = stagesynthesis_get_theme_options($activelinks);
$themefilter = stagesynthesis_parse_theme_filter($themekey, $themeoptions);

$listurl = new moodle_url($baseurl, [
    'search' => $search, 'themekey' => $themekey, 'status' => $filterstatus, 'tsort' => $tsort, 'tdir' => $tdir,
]);
echo stagesynthesis_render_list_filters($listurl, $themeoptions, $search, $themekey, $filterstatus);

// Par défaut (aucun statut choisi dans le filtre), ne montre que les stages effectivement en
// attente d'évaluation, comme sur l'écran d'origine (teacher.php) : le filtre de statut reste
// disponible pour retrouver explicitement un stage par son statut, y compris déjà évalué.
$liststatus = $filterstatus !== '' ? $filterstatus : STAGE_STATUS_EVAL_ETUDIANT;

$allentries = stagesynthesis_get_filtered_entries($activelinks,
    ['search' => $search, 'themefilter' => $themefilter, 'status' => $liststatus], $tsort, $tdir);
[$entries, $pagingbarhtml] = stage_paginate($allentries, $page, $listurl);

if (empty($allentries)) {
    echo $OUTPUT->notification(get_string('nostages', 'mod_stage'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    stage_sort_header(get_string('course'), 'course', $listurl, $tsort, $tdir),
    stage_sort_header(get_string('student', 'mod_stage'), 'student', $listurl, $tsort, $tdir),
    get_string('currentstudyyear', 'mod_stage'),
    stage_sort_header(get_string('theme', 'mod_stage'), 'theme', $listurl, $tsort, $tdir),
    stage_sort_header(get_string('declaredduration', 'mod_stage'), 'duration', $listurl, $tsort, $tdir),
    stage_sort_header(get_string('status', 'mod_stage'), 'status', $listurl, $tsort, $tdir),
    get_string('actions', 'mod_stage'),
];

foreach ($entries as $entry) {
    $badge = html_writer::span(stage_status_label($entry->status), 'badge ' . stage_status_badgeclass($entry->status));
    $entrycontext = context_module::instance($entry->cmid);
    $signedavailable = (int) $entry->conventionstatus === STAGE_CONVENTION_SIGNED
        && stage_get_signed_convention_file($entrycontext, $entry->id);
    $actionlabel = (int) $entry->status === STAGE_STATUS_EVAL_ETUDIANT
        ? get_string('evaluate', 'mod_stage')
        : get_string('viewevaluation', 'mod_stage');

    $table->data[] = [
        format_string($entry->coursename),
        $entry->studentfullname,
        stage_studyyear_label($entry->currentstudyyear),
        $entry->themename,
        $entry->declaredduration,
        $badge,
        stage_render_actions([
            $actionlabel => new moodle_url('/mod/stage/teacher.php',
                ['id' => $entry->cmid, 'entryid' => $entry->id, 'returnurl' => $listurl->out_as_local_url(false)]),
            get_string('downloadsignedconvention', 'mod_stage') => $signedavailable
                ? new moodle_url('/mod/stage/convention_signed.php', ['id' => $entry->cmid, 'entryid' => $entry->id])
                : null,
        ]),
    ];
}

echo html_writer::table($table);
echo $pagingbarhtml;

echo $OUTPUT->footer();
