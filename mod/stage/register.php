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
 * Enregistrement des stages par la DEVE : création unitaire ou en masse (pour plusieurs
 * étudiants à la fois sur une même thématique), et édition des saisies existantes.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/deve_entry_form.php');

use mod_stage\form\deve_entry_form;

$id = required_param('id', PARAM_INT);
$entryid = optional_param('entryid', 0, PARAM_INT);
$mode = optional_param('mode', 'list', PARAM_ALPHA);
$search = optional_param('search', '', PARAM_TEXT);
$filterthemeid = optional_param('themeid', 0, PARAM_INT);
$filterstatus = optional_param('status', '', PARAM_RAW);
$tsort = optional_param('tsort', 'timecreated', PARAM_ALPHA);
$tdir = optional_param('tdir', 'DESC', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:registerstages', $context);

$baseurl = new moodle_url('/mod/stage/register.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('registerstages', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$themes = stage_get_themes($stage->id, true);
$students = stage_get_enrolled_students($context);

if (empty($themes)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('nothemesyet', 'mod_stage'), 'warning');
    echo $OUTPUT->footer();
    exit;
}
if (empty($students)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('nostudents', 'mod_stage'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// Réinitialisation d'une saisie : redonne la main à l'étudiant et à l'enseignant référent.
if ($mode === 'reset' && $entryid && confirm_sesskey()) {
    $entry = $DB->get_record('stage_entry', ['id' => $entryid, 'stageid' => $stage->id], '*', MUST_EXIST);
    stage_reset_entry($entry);
    redirect($baseurl, get_string('entryreset', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Liste de toutes les saisies existantes, avec accès à l'édition.
if ($mode === 'list') {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('registerstages', 'mod_stage'));
    echo html_writer::link(new moodle_url('/mod/stage/view.php', ['id' => $cm->id]), get_string('back'));

    echo html_writer::div(
        html_writer::link(new moodle_url('/mod/stage/register.php', ['id' => $cm->id, 'mode' => 'single']),
            get_string('registerstage', 'mod_stage'), ['class' => 'btn btn-primary mr-2'])
        . html_writer::link(new moodle_url('/mod/stage/register.php', ['id' => $cm->id, 'mode' => 'bulk']),
            get_string('bulkregisterstages', 'mod_stage'), ['class' => 'btn btn-secondary mr-2'])
        . html_writer::link(new moodle_url('/mod/stage/import.php', ['id' => $cm->id]),
            get_string('importcsv', 'mod_stage'), ['class' => 'btn btn-secondary mr-2'])
        . html_writer::link(new moodle_url('/mod/stage/import_stagevet.php', ['id' => $cm->id]),
            get_string('importstagevetcsv', 'mod_stage'), ['class' => 'btn btn-secondary mr-2'])
        . (has_capability('mod/stage:viewall', $context)
            ? html_writer::link(new moodle_url('/mod/stage/export.php', ['id' => $cm->id]),
                get_string('exportexcel', 'mod_stage'), ['class' => 'btn btn-secondary'])
            : ''),
        'my-3'
    );

    // Pour l'affichage, on résout aussi les thématiques masquées, sur lesquelles des
    // stages ont pu être enregistrés avant leur masquage.
    $allthemes = stage_get_themes($stage->id);

    $listurl = new moodle_url($baseurl, [
        'search' => $search, 'themeid' => $filterthemeid, 'status' => $filterstatus, 'tsort' => $tsort, 'tdir' => $tdir,
    ]);
    echo stage_render_list_filters($listurl, $allthemes, $search, $filterthemeid, $filterstatus);

    $allentries = stage_get_filtered_entries($stage->id,
        ['search' => $search, 'themeid' => $filterthemeid, 'status' => $filterstatus], $tsort, $tdir);
    [$entries, $pagingbarhtml] = stage_paginate($allentries, $page, $listurl);
    $students = stage_get_entry_users($entries);

    $table = new html_table();
    $table->head = [
        stage_sort_header(get_string('student', 'mod_stage'), 'student', $listurl, $tsort, $tdir),
        stage_sort_header(get_string('theme', 'mod_stage'), 'theme', $listurl, $tsort, $tdir),
        stage_sort_header(get_string('declaredduration', 'mod_stage'), 'duration', $listurl, $tsort, $tdir),
        stage_sort_header(get_string('status', 'mod_stage'), 'status', $listurl, $tsort, $tdir),
        get_string('actions', 'mod_stage'),
    ];
    foreach ($entries as $entry) {
        $student = $students[$entry->userid] ?? null;
        $themename = isset($allthemes[$entry->themeid]) ? format_string($allthemes[$entry->themeid]->name) : '-';
        $badge = html_writer::span(stage_status_label($entry->status), 'badge ' . stage_status_badgeclass($entry->status));
        $editurl = new moodle_url('/mod/stage/register.php', ['id' => $cm->id, 'mode' => 'single', 'entryid' => $entry->id]);
        $actions = html_writer::link($editurl, get_string('edit'));
        $conventionstatus = (int) $entry->conventionstatus;
        if ($conventionstatus === STAGE_CONVENTION_REQUESTED) {
            $reviewurl = new moodle_url('/mod/stage/convention_review.php', ['id' => $cm->id, 'entryid' => $entry->id]);
            $actions .= ' | ' . html_writer::link($reviewurl, get_string('conventionreview', 'mod_stage'));
        } else if (in_array($conventionstatus, [STAGE_CONVENTION_EDITED, STAGE_CONVENTION_SIGNED], true)) {
            $conventionurl = new moodle_url('/mod/stage/convention.php', ['id' => $cm->id, 'entryid' => $entry->id]);
            $actions .= ' | ' . html_writer::link($conventionurl, get_string('generateconvention', 'mod_stage'));
        }
        if ($conventionstatus === STAGE_CONVENTION_EDITED) {
            $signurl = new moodle_url('/mod/stage/convention_sign.php', ['id' => $cm->id, 'entryid' => $entry->id]);
            $actions .= ' | ' . html_writer::link($signurl, get_string('conventionmarksigned', 'mod_stage'));
        } else if ($conventionstatus === STAGE_CONVENTION_SIGNED
                && stage_get_signed_convention_file($context, $entry->id)) {
            $signedurl = new moodle_url('/mod/stage/convention_signed.php', ['id' => $cm->id, 'entryid' => $entry->id]);
            $actions .= ' | ' . html_writer::link($signedurl, get_string('downloadsignedconvention', 'mod_stage'));
        }
        if ((int) $entry->status !== STAGE_STATUS_ENREGISTRE) {
            $reseturl = new moodle_url('/mod/stage/register.php',
                ['id' => $cm->id, 'mode' => 'reset', 'entryid' => $entry->id, 'sesskey' => sesskey()]);
            $actions .= ' | ' . html_writer::link($reseturl, get_string('resetentry', 'mod_stage'),
                ['onclick' => "return confirm('" . get_string('confirmresetentry', 'mod_stage') . "');"]);
        }
        if ((int) $entry->status !== STAGE_STATUS_ANNULE) {
            $cancelurl = new moodle_url('/mod/stage/cancel_entry.php', ['id' => $cm->id, 'entryid' => $entry->id]);
            $actions .= ' | ' . html_writer::link($cancelurl, get_string('cancelentry', 'mod_stage'));
        }
        $table->data[] = [
            $student ? fullname($student) : '-',
            $themename,
            $entry->declaredduration,
            $badge,
            $actions,
        ];
    }
    if (empty($allentries)) {
        echo $OUTPUT->notification(get_string('nostages', 'mod_stage'), 'info');
    } else {
        echo html_writer::table($table);
        echo $pagingbarhtml;
    }

    echo $OUTPUT->footer();
    exit;
}

// Création unitaire ou édition d'une saisie existante.
if ($mode === 'single') {
    $entry = null;
    if ($entryid) {
        $entry = $DB->get_record('stage_entry', ['id' => $entryid, 'stageid' => $stage->id], '*', MUST_EXIST);
    }

    $entrystudent = $entry ? $DB->get_record('user', ['id' => $entry->userid]) : null;
    $formurl = new moodle_url('/mod/stage/register.php', ['id' => $cm->id, 'mode' => 'single', 'entryid' => $entryid]);
    $mform = new deve_entry_form($formurl, [
        'themes' => $themes,
        'students' => $students,
        'lockstudent' => (bool) $entry,
        'studentname' => $entrystudent ? fullname($entrystudent) : '',
        'stageid' => $stage->id,
    ]);

    $toform = new stdClass();
    $toform->id = $cm->id;
    $toform->entryid = $entryid;
    if ($entry) {
        $toform->userid = $entry->userid;
        $toform->themeid = $entry->themeid;
        $toform->structure = $entry->structure;
        $toform->datestart = $entry->datestart;
        $toform->dateend = $entry->dateend;
        $toform->declaredduration = $entry->declaredduration;
    }
    $mform->set_data($toform);

    if ($mform->is_cancelled()) {
        redirect($baseurl);
    } else if ($data = $mform->get_data()) {
        if ($entry) {
            stage_update_entry_details($entry, $data->themeid, $data->structure, $data->datestart, $data->dateend,
                $data->declaredduration);
        } else {
            stage_register_entry($stage->id, $data->userid, $data->themeid, $data->structure, $data->datestart,
                $data->dateend, $data->declaredduration);
        }
        redirect($baseurl, get_string('stagesaved', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading($entry ? get_string('editstage', 'mod_stage') : get_string('registerstage', 'mod_stage'));
    echo html_writer::link($baseurl, get_string('back'));
    $mform->display();
    echo $OUTPUT->footer();
    exit;
}

// Création en masse : mêmes thématique/structure/dates/durée pour plusieurs étudiants sélectionnés.
if ($mode === 'bulk') {
    $bulkresults = null;

    if (data_submitted() && confirm_sesskey() && optional_param('bulkregister', 0, PARAM_INT)) {
        $themeid = required_param('themeid', PARAM_INT);
        $structure = optional_param('structure', '', PARAM_TEXT);
        $datestartraw = optional_param('datestart', '', PARAM_TEXT);
        $dateendraw = optional_param('dateend', '', PARAM_TEXT);
        $declaredduration = required_param('declaredduration', PARAM_INT);
        $studentids = optional_param_array('students', [], PARAM_INT);

        $start = $datestartraw ? strtotime($datestartraw) : null;
        $end = $dateendraw ? strtotime($dateendraw) : null;

        // Un étudiant ayant déjà un stage sur cette thématique et ces mêmes dates est écarté
        // et signalé, pour ne pas créer de doublon silencieux.
        $existing = stage_get_existing_theme_pairs($stage->id);
        $studentsbyid = [];
        foreach ($students as $student) {
            $studentsbyid[$student->id] = $student;
        }

        $bulkresults = (object) ['created' => 0, 'duplicates' => []];
        foreach ($studentids as $studentid) {
            $key = stage_duplicate_key($studentid, $themeid, $start, $end);
            if (isset($existing[$key])) {
                $bulkresults->duplicates[] = isset($studentsbyid[$studentid])
                    ? fullname($studentsbyid[$studentid]) : "#$studentid";
                continue;
            }
            // Les stages enregistrés en masse sont déjà signés sur SignVet au moment de leur
            // enregistrement : pas de gestion de convention à faire dans ce plugin pour eux.
            stage_register_entry($stage->id, $studentid, $themeid, $structure, $start, $end, $declaredduration,
                STAGE_CONVENTION_SIGNVET);
            $existing[$key] = true;
            $bulkresults->created++;
        }
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('bulkregisterstages', 'mod_stage'));
    echo html_writer::link($baseurl, get_string('back'));
    echo $OUTPUT->notification(get_string('bulkregistersignvethelp', 'mod_stage'), 'info');

    if ($bulkresults) {
        echo $OUTPUT->notification(get_string('bulkregistered', 'mod_stage', $bulkresults->created),
            \core\output\notification::NOTIFY_SUCCESS);
        if (!empty($bulkresults->duplicates)) {
            echo $OUTPUT->notification(
                get_string('bulkduplicatesskipped', 'mod_stage', implode(', ', $bulkresults->duplicates)),
                \core\output\notification::NOTIFY_WARNING
            );
        }
    }

    $bulkactionurl = new moodle_url('/mod/stage/register.php', ['id' => $cm->id, 'mode' => 'bulk']);
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $bulkactionurl]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    $themeoptions = [];
    foreach ($themes as $theme) {
        $themeoptions[$theme->id] = stage_theme_option_label($theme);
    }
    echo html_writer::tag('label', get_string('theme', 'mod_stage'), ['for' => 'themeid']);
    echo html_writer::select($themeoptions, 'themeid', '', false, ['id' => 'themeid', 'required' => 'required']);

    echo html_writer::tag('label', get_string('structure', 'mod_stage'), ['for' => 'structure']);
    echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'structure', 'id' => 'structure', 'class' => 'form-control']);

    echo html_writer::tag('label', get_string('datestart', 'mod_stage'));
    echo html_writer::empty_tag('input', ['type' => 'date', 'name' => 'datestart', 'class' => 'form-control']);

    echo html_writer::tag('label', get_string('dateend', 'mod_stage'));
    echo html_writer::empty_tag('input', ['type' => 'date', 'name' => 'dateend', 'class' => 'form-control']);

    echo html_writer::tag('label', get_string('declaredduration', 'mod_stage'), ['for' => 'declaredduration']);
    echo html_writer::empty_tag('input', [
        'type' => 'number', 'name' => 'declaredduration', 'id' => 'declaredduration', 'min' => 0, 'class' => 'form-control',
        'required' => 'required',
    ]);

    echo $OUTPUT->heading(get_string('selectstudents', 'mod_stage'), 4);
    foreach ($students as $student) {
        echo html_writer::start_tag('div', ['class' => 'form-check']);
        echo html_writer::checkbox('students[]', $student->id, false, fullname($student), ['class' => 'form-check-input']);
        echo html_writer::end_tag('div');
    }

    echo html_writer::tag('button', get_string('bulkregisterselected', 'mod_stage'),
        ['type' => 'submit', 'name' => 'bulkregister', 'value' => 1, 'class' => 'btn btn-primary mt-3']);
    echo html_writer::end_tag('form');

    echo $OUTPUT->footer();
    exit;
}
