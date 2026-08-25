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
 * Validation des stages par l'enseignant référent, pour les étudiants qui lui sont attribués.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$id = required_param('id', PARAM_INT);
$entryid = optional_param('entryid', 0, PARAM_INT);
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
require_capability('mod/stage:evaluateteacher', $context);

$baseurl = new moodle_url('/mod/stage/teacher.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('teachervalidation', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$assignedids = array_keys(stage_get_assigned_students($stage->id, $USER->id));

// Traitement de l'évaluation d'une saisie.
if ($entryid) {
    $entry = $DB->get_record('stage_entry', ['id' => $entryid, 'stageid' => $stage->id], '*', MUST_EXIST);
    if (!in_array($entry->userid, $assignedids)) {
        throw new moodle_exception('nopermissions', 'error', '', get_string('teachervalidation', 'mod_stage'));
    }

    $questions = stage_get_questions($entry->themeid, 'teacher');
    // L'évaluation n'est modifiable que tant qu'elle n'a pas encore été soumise : une fois
    // évaluée (ou rejetée), seule la DEVE peut réinitialiser la saisie pour la rouvrir.
    $editable = ((int) $entry->status === STAGE_STATUS_EVAL_ETUDIANT);

    if ($editable && data_submitted() && confirm_sesskey()) {
        if (optional_param('rejectstage', '', PARAM_RAW) !== '') {
            $rejectcomment = optional_param('rejectcomment', '', PARAM_RAW);
            stage_reject_by_teacher($entry, $USER->id, $rejectcomment);
        } else if (!empty($questions)) {
            stage_save_answers($entry->id, $questions, stage_get_submitted_answers($questions));
            stage_apply_teacher_eval($entry, $USER->id);
        } else {
            $comment = optional_param('teachereval', '', PARAM_RAW);
            stage_apply_teacher_eval($entry, $USER->id, $comment);
        }
        redirect($baseurl, get_string('evalsaved', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    $student = $DB->get_record('user', ['id' => $entry->userid]);
    $theme = $DB->get_record('stage_theme', ['id' => $entry->themeid]);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('evaluatestage', 'mod_stage', fullname($student)));
    echo html_writer::link($baseurl, get_string('back'));

    echo html_writer::tag('p', get_string('theme', 'mod_stage') . ' : ' . format_string($theme->name));
    echo html_writer::tag('p', get_string('structure', 'mod_stage') . ' : ' . s($entry->structure));
    echo html_writer::tag('p', get_string('declaredduration', 'mod_stage') . ' : ' . $entry->declaredduration);
    echo html_writer::tag('p', get_string('status', 'mod_stage') . ' : '
        . html_writer::span(stage_status_label($entry->status), 'badge ' . stage_status_badgeclass($entry->status)));
    // Auto-évaluation de l'étudiant : réponses au formulaire défini par la DEVE si
    // des questions existent pour cette thématique, sinon commentaire libre.
    echo $OUTPUT->heading(get_string('studentselfeval', 'mod_stage'), 4);
    $studentquestions = stage_get_questions($entry->themeid, 'student');
    if (!empty($studentquestions)) {
        echo stage_render_answers_readonly($studentquestions, stage_get_answers($entry->id));
    } else {
        echo html_writer::div(format_text($entry->studentselfeval, FORMAT_HTML));
    }

    if (!$editable) {
        echo $OUTPUT->notification(get_string('entrynoteditable', 'mod_stage'), 'info');
        if (!empty($questions)) {
            echo stage_render_answers_readonly($questions, stage_get_answers($entry->id));
        } else if ($entry->teachereval) {
            echo $OUTPUT->heading(get_string('teachereval', 'mod_stage'), 4);
            echo html_writer::div(format_text($entry->teachereval, FORMAT_PLAIN));
        }
        echo $OUTPUT->footer();
        exit;
    }

    $formurl = new moodle_url('/mod/stage/teacher.php', ['id' => $cm->id, 'entryid' => $entry->id]);
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $formurl]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    if (!empty($questions)) {
        // Formulaire dynamique défini par la DEVE pour cette thématique.
        echo stage_render_question_fields($questions, stage_get_answers($entry->id));
    } else {
        echo html_writer::tag('label', get_string('teachereval', 'mod_stage'), ['for' => 'teachereval']);
        echo html_writer::tag('textarea', s($entry->teachereval),
            ['name' => 'teachereval', 'id' => 'teachereval', 'rows' => 5, 'class' => 'form-control']);
    }

    echo html_writer::empty_tag('input', [
        'type' => 'submit', 'name' => 'validatestage', 'value' => get_string('validate', 'mod_stage'),
        'class' => 'btn btn-primary mt-2 mr-2',
    ]);

    echo html_writer::tag('label', get_string('rejectcomment', 'mod_stage'), ['for' => 'rejectcomment']);
    echo html_writer::tag('textarea', '',
        ['name' => 'rejectcomment', 'id' => 'rejectcomment', 'rows' => 3, 'class' => 'form-control']);
    echo html_writer::empty_tag('input', [
        'type' => 'submit', 'name' => 'rejectstage', 'value' => get_string('markinvalid', 'mod_stage'),
        'class' => 'btn btn-danger mt-2',
    ]);
    echo html_writer::end_tag('form');
    echo $OUTPUT->footer();
    exit;
}

// Liste des saisies des étudiants attribués.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('teachervalidation', 'mod_stage'));
echo html_writer::link(new moodle_url('/mod/stage/view.php', ['id' => $cm->id]), get_string('back'));

if (empty($assignedids)) {
    echo $OUTPUT->notification(get_string('noassignedstudents', 'mod_stage'), 'info');
} else {
    // Demandes de convention en attente de validation (voir
    // stage_convention_requires_teacher_validation()), avant transmission à la DEVE.
    $pendingconventions = stage_get_teacher_pending_convention_entries($stage->id, $USER->id);
    echo $OUTPUT->heading(get_string('conventionteachervalidation', 'mod_stage'), 4);
    if (empty($pendingconventions)) {
        echo $OUTPUT->notification(get_string('noconventionteachervalidations', 'mod_stage'), 'info');
    } else {
        $pendingstudents = stage_get_entry_users($pendingconventions);
        $pendingthemes = stage_get_themes($stage->id);
        $pendingtable = new html_table();
        $pendingtable->head = [
            get_string('student', 'mod_stage'),
            get_string('theme', 'mod_stage'),
            get_string('conventionrequestdate', 'mod_stage'),
            get_string('actions', 'mod_stage'),
        ];
        foreach ($pendingconventions as $pendingentry) {
            $pendingstudent = $pendingstudents[$pendingentry->userid] ?? null;
            $pendingthemename = isset($pendingthemes[$pendingentry->themeid])
                ? format_string($pendingthemes[$pendingentry->themeid]->name) : '-';
            $pendingtable->data[] = [
                $pendingstudent ? fullname($pendingstudent) : '-',
                $pendingthemename,
                $pendingentry->conventionrequesttime
                    ? userdate($pendingentry->conventionrequesttime, get_string('strftimedatetimeshort')) : '-',
                html_writer::link(
                    new moodle_url('/mod/stage/convention_teacher_validate.php',
                        ['id' => $cm->id, 'entryid' => $pendingentry->id]),
                    get_string('conventionteachervalidate', 'mod_stage')
                ),
            ];
        }
        echo html_writer::table($pendingtable);
    }

    echo $OUTPUT->heading(get_string('teachervalidation', 'mod_stage'), 4);

    $themes = stage_get_themes($stage->id);

    $listurl = new moodle_url($baseurl, [
        'search' => $search, 'themeid' => $filterthemeid, 'status' => $filterstatus, 'tsort' => $tsort, 'tdir' => $tdir,
    ]);
    echo stage_render_list_filters($listurl, $themes, $search, $filterthemeid, $filterstatus);

    $allentries = stage_get_filtered_entries($stage->id,
        ['search' => $search, 'themeid' => $filterthemeid, 'status' => $filterstatus], $tsort, $tdir, $assignedids);
    [$entries, $pagingbarhtml] = stage_paginate($allentries, $page, $listurl);

    $table = new html_table();
    $table->head = [
        stage_sort_header(get_string('student', 'mod_stage'), 'student', $listurl, $tsort, $tdir),
        stage_sort_header(get_string('theme', 'mod_stage'), 'theme', $listurl, $tsort, $tdir),
        stage_sort_header(get_string('declaredduration', 'mod_stage'), 'duration', $listurl, $tsort, $tdir),
        stage_sort_header(get_string('status', 'mod_stage'), 'status', $listurl, $tsort, $tdir),
        get_string('actions', 'mod_stage'),
    ];
    $students = stage_get_entry_users($entries);
    foreach ($entries as $entry) {
        $student = $students[$entry->userid] ?? null;
        $themename = isset($themes[$entry->themeid]) ? format_string($themes[$entry->themeid]->name) : '-';
        $badge = html_writer::span(stage_status_label($entry->status), 'badge ' . stage_status_badgeclass($entry->status));
        $action = html_writer::link(new moodle_url('/mod/stage/teacher.php', ['id' => $cm->id, 'entryid' => $entry->id]),
            get_string('evaluate', 'mod_stage'));
        if ((int) $entry->conventionstatus === STAGE_CONVENTION_SIGNED
                && stage_get_signed_convention_file($context, $entry->id)) {
            $action .= ' | ' . html_writer::link(
                new moodle_url('/mod/stage/convention_signed.php', ['id' => $cm->id, 'entryid' => $entry->id]),
                get_string('downloadsignedconvention', 'mod_stage')
            );
        }
        $table->data[] = [
            $student ? fullname($student) : '-',
            $themename,
            $entry->declaredduration,
            $badge,
            $action,
        ];
    }

    if (empty($allentries)) {
        echo $OUTPUT->notification(get_string('nostages', 'mod_stage'), 'info');
    } else {
        echo html_writer::table($table);
        echo $pagingbarhtml;
    }
}

echo $OUTPUT->footer();
