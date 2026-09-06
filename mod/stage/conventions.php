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
 * Suivi par la DEVE des demandes de convention de stage : validation d'une demande étudiante
 * (passage au statut "éditée"), puis passage au statut "signée" une fois le PDF de la convention
 * effectivement signée (scan du document papier) téléversé (voir convention_sign.php), ce qui
 * ouvre le droit à l'auto-évaluation de l'étudiant et à l'évaluation de l'enseignant référent, et
 * rend ce PDF téléchargeable par l'étudiant. Liste triable et cherchable par nom d'étudiant, avec
 * les demandes les plus récentes en avant par défaut (stage_get_convention_entries()). Les
 * demandes en attente de validation par l'enseignant référent (voir
 * stage_convention_requires_teacher_validation()) n'apparaissent ici qu'une fois validées.
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
$tsort = optional_param('tsort', 'requested', PARAM_ALPHA);
$tdir = optional_param('tdir', 'DESC', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:registerstages', $context);

$baseurl = new moodle_url('/mod/stage/conventions.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('conventions', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('conventions', 'mod_stage'));
echo html_writer::link(new moodle_url('/mod/stage/view.php', ['id' => $cm->id]), get_string('back'));
echo html_writer::link(new moodle_url('/mod/stage/convention_templates.php', ['id' => $cm->id]),
    get_string('conventiontemplates', 'mod_stage'), ['class' => 'btn btn-secondary d-block mt-2 mb-3', 'style' => 'width:fit-content']);

$listurl = new moodle_url($baseurl, ['search' => $search, 'tsort' => $tsort, 'tdir' => $tdir]);
$searchformurl = new moodle_url($listurl);
$searchformurl->remove_params('search', 'tsort', 'tdir');
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $searchformurl, 'class' => 'form-inline stage-filters mb-3']);
foreach ($searchformurl->params() as $key => $value) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $key, 'value' => $value]);
}
echo html_writer::empty_tag('input', [
    'type' => 'text', 'name' => 'search', 'value' => s($search),
    'placeholder' => get_string('searchstudent', 'mod_stage'), 'class' => 'form-control mr-2',
]);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('search'), 'class' => 'btn btn-secondary mr-2']);
echo html_writer::link($searchformurl, get_string('resetfilters', 'mod_stage'), ['class' => 'btn btn-link']);
echo html_writer::end_tag('form');

$allentries = stage_get_convention_entries($stage->id, $search, $tsort, $tdir);
[$entries, $pagingbarhtml] = stage_paginate($allentries, $page, $listurl);

if (empty($allentries)) {
    echo $OUTPUT->notification(get_string('noconventionrequests', 'mod_stage'), 'info');
} else {
    $students = stage_get_entry_users($entries);
    $themes = stage_get_themes($stage->id);
    $templates = stage_get_convention_templates($stage->id);

    $table = new html_table();
    $table->head = [
        stage_sort_header(get_string('student', 'mod_stage'), 'student', $listurl, $tsort, $tdir),
        stage_sort_header(get_string('theme', 'mod_stage'), 'theme', $listurl, $tsort, $tdir),
        get_string('conventiontemplatename', 'mod_stage'),
        stage_sort_header(get_string('conventionstatus', 'mod_stage'), 'status', $listurl, $tsort, $tdir),
        stage_sort_header(get_string('conventionrequestdate', 'mod_stage'), 'requested', $listurl, $tsort, $tdir),
        get_string('actions', 'mod_stage'),
    ];
    foreach ($entries as $entry) {
        $student = $students[$entry->userid] ?? null;
        $themename = isset($themes[$entry->themeid]) ? format_string($themes[$entry->themeid]->name) : '-';
        $templatename = isset($templates[$entry->conventiontemplateid])
            ? format_string($templates[$entry->conventiontemplateid]->name) : '-';
        $badge = html_writer::span(stage_convention_status_label($entry->conventionstatus),
            'badge ' . stage_convention_status_badgeclass($entry->conventionstatus));
        $requestdate = $entry->conventionrequesttime ? userdate($entry->conventionrequesttime, get_string('strftimedatetimeshort')) : '-';

        // Chaque statut n'ouvre qu'une action « suivante » dans le circuit (relire, marquer
        // signée...) : elle est mise en avant en bouton principal, les actions toujours
        // disponibles (regénérer le PDF, télécharger la convention signée) restant discrètes.
        // Le retour ramène sur cette liste telle qu'affichée (recherche, tri, page).
        $status = (int) $entry->conventionstatus;
        $rowreturnurl = (new moodle_url($listurl, ['page' => $page]))->out_as_local_url(false);
        $nextaction = stage_render_actions([
            get_string('conventionreview', 'mod_stage') => $status === STAGE_CONVENTION_REQUESTED
                ? new moodle_url('/mod/stage/convention_review.php',
                    ['id' => $cm->id, 'entryid' => $entry->id, 'returnurl' => $rowreturnurl]) : null,
            get_string('conventionmarksigned', 'mod_stage') => $status === STAGE_CONVENTION_EDITED
                ? new moodle_url('/mod/stage/convention_sign.php',
                    ['id' => $cm->id, 'entryid' => $entry->id, 'returnurl' => $rowreturnurl]) : null,
        ], 'btn btn-sm btn-primary mr-1 mb-1');
        $otheractions = stage_render_actions([
            get_string('downloadsignedconvention', 'mod_stage') =>
                $status === STAGE_CONVENTION_SIGNED && stage_get_signed_convention_file($context, $entry->id)
                    ? new moodle_url('/mod/stage/convention_signed.php', ['id' => $cm->id, 'entryid' => $entry->id])
                    : null,
            get_string('generateconvention', 'mod_stage') => $status >= STAGE_CONVENTION_EDITED
                ? new moodle_url('/mod/stage/convention.php',
                    ['id' => $cm->id, 'entryid' => $entry->id, 'returnurl' => $rowreturnurl]) : null,
        ]);
        $actions = trim(($nextaction !== '-' ? $nextaction : '') . ($otheractions !== '-' ? $otheractions : ''));
        // Le motif de refus explique pourquoi il n'y a rien à faire ici : il suit les actions,
        // en clair sous celles-ci plutôt qu'aligné avec elles comme s'il en était une.
        if ($status === STAGE_CONVENTION_REJECTED && !empty($entry->conventionrejectcomment)) {
            $actions .= html_writer::div(
                get_string('conventionrejectedwithcomment', 'mod_stage', format_string($entry->conventionrejectcomment)),
                'text-muted small'
            );
        }

        $table->data[] = [
            $student ? fullname($student) : '-',
            $themename,
            $templatename,
            $badge,
            $requestdate,
            $actions !== '' ? $actions : '-',
        ];
    }
    echo html_writer::table($table);
    echo $pagingbarhtml;
}

echo $OUTPUT->footer();
