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
 * Validation finale des stages par la DEVE, en masse ou unitaire.
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
$tdir = optional_param('tdir', 'ASC', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:validatedeve', $context);

$baseurl = new moodle_url('/mod/stage/deve.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('devevalidation', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Réinitialisation d'une saisie (DEVE uniquement) : redonne la main à l'étudiant et à
// l'enseignant référent pour une nouvelle auto-évaluation / évaluation.
if ($entryid && optional_param('resetentry', 0, PARAM_INT) && confirm_sesskey()) {
    $entry = $DB->get_record('stage_entry', ['id' => $entryid, 'stageid' => $stage->id], '*', MUST_EXIST);
    stage_reset_entry($entry);
    redirect($baseurl, get_string('entryreset', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Validation unitaire (formulaire dédié à une saisie).
if ($entryid) {
    $entry = $DB->get_record('stage_entry', ['id' => $entryid, 'stageid' => $stage->id], '*', MUST_EXIST);
    $periods = stage_get_or_seed_entry_periods($entry);

    // Jours de stage effectifs sélectionnés par l'étudiant : visibles et modifiables ici par la
    // DEVE (formulaire distinct de la validation, avec son propre bouton).
    if (!empty($periods) && optional_param('saveworkdays', 0, PARAM_INT) && confirm_sesskey()) {
        $workdays = optional_param_array('workdays', [], PARAM_INT);
        stage_set_entry_workdays($entry->id, $workdays);
        redirect(new moodle_url('/mod/stage/deve.php', ['id' => $cm->id, 'entryid' => $entryid]),
            get_string('workdayssaved', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    if (data_submitted() && confirm_sesskey()) {
        if (optional_param('rejectstage', '', PARAM_RAW) !== '') {
            $comment = optional_param('devecomment', '', PARAM_RAW);
            stage_reject_by_deve($entry, $USER->id, $comment);
        } else {
            $retained = optional_param('retainedduration', 0, PARAM_INT);
            $comment = optional_param('devecomment', '', PARAM_RAW);
            stage_apply_deve_validation($entry, $USER->id, $retained, $comment);
        }
        redirect($baseurl, get_string('evalsaved', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    $student = $DB->get_record('user', ['id' => $entry->userid]);
    $theme = $DB->get_record('stage_theme', ['id' => $entry->themeid]);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('validatestage', 'mod_stage', fullname($student)));
    echo html_writer::link($baseurl, get_string('back'));

    // Rappel de la saisie validée, en tableau plutôt qu'en paragraphes épars, et complété des
    // informations qui manquaient ici (année d'étude, structure, mobilité, plages, convention).
    echo stage_render_entry_summary($entry, $theme);

    // Les deux évaluations amont, telles qu'elles ont été saisies (questions ou commentaire libre).
    $answers = stage_get_answers($entry->id);

    $studentquestions = stage_get_questions($entry->themeid, 'student');
    if (!empty($studentquestions) || $entry->studentselfeval) {
        echo $OUTPUT->heading(get_string('studentselfeval', 'mod_stage'), 4);
        echo !empty($studentquestions)
            ? stage_render_answers_readonly($studentquestions, $answers)
            : html_writer::div(format_text($entry->studentselfeval, FORMAT_HTML));
    }

    $teacherquestions = stage_get_questions($entry->themeid, 'teacher');
    if (!empty($teacherquestions) || $entry->teachereval) {
        echo $OUTPUT->heading(get_string('teachereval', 'mod_stage'), 4);
        echo !empty($teacherquestions)
            ? stage_render_answers_readonly($teacherquestions, $answers)
            : html_writer::div(format_text($entry->teachereval, FORMAT_PLAIN));
    }

    if (!empty($stage->tutorevaluationenabled)) {
        $tutorquestions = stage_get_questions($entry->themeid, 'tutor');
        echo $OUTPUT->heading(get_string('tutorevalheading', 'mod_stage'), 4);
        if (!empty($tutorquestions) && $entry->tutortime) {
            echo stage_render_answers_readonly($tutorquestions, $answers);
        } else if ($entry->tutoreval) {
            echo html_writer::div(format_text($entry->tutoreval, FORMAT_PLAIN));
        } else {
            echo $OUTPUT->notification(get_string('notutoreval', 'mod_stage'), 'info');
        }
    }

    if (!empty($periods)) {
        echo $OUTPUT->heading(get_string('workdays', 'mod_stage'), 4);
        $workdaysformurl = new moodle_url('/mod/stage/deve.php', ['id' => $cm->id, 'entryid' => $entry->id]);
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $workdaysformurl]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'saveworkdays', 'value' => 1]);
        echo stage_render_workday_picker($periods, stage_get_entry_workdays($entry->id), true);
        echo html_writer::empty_tag('input', [
            'type' => 'submit', 'value' => get_string('savechanges'), 'class' => 'btn btn-secondary mt-2',
        ]);
        echo html_writer::end_tag('form');
    }

    $formurl = new moodle_url('/mod/stage/deve.php', ['id' => $cm->id, 'entryid' => $entry->id]);
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $formurl]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    // Le nombre de jours proposé par défaut est celui coché par l'étudiant lors de son
    // auto-évaluation (jours de stage effectifs), s'il en a sélectionné ; sinon la durée déjà
    // retenue ou, à défaut, déclarée. Reste modifiable par la DEVE avant validation.
    $workdaycount = count(stage_get_entry_workdays($entry->id));
    $proposedduration = $workdaycount > 0 ? $workdaycount : ($entry->retainedduration ?: $entry->declaredduration);
    echo html_writer::tag('label', get_string('retainedduration', 'mod_stage'), ['for' => 'retainedduration']);
    echo html_writer::empty_tag('input', [
        'type' => 'number', 'name' => 'retainedduration', 'id' => 'retainedduration',
        'value' => $proposedduration, 'class' => 'form-control', 'min' => 0,
    ]);
    echo html_writer::tag('label', get_string('devecomment', 'mod_stage'), ['for' => 'devecomment']);
    echo html_writer::tag('textarea', s($entry->devecomment),
        ['name' => 'devecomment', 'id' => 'devecomment', 'rows' => 4, 'class' => 'form-control']);
    echo html_writer::empty_tag('input', [
        'type' => 'submit', 'name' => 'validatestage', 'value' => get_string('validate', 'mod_stage'),
        'class' => 'btn btn-primary mt-2 mr-2',
    ]);
    echo html_writer::empty_tag('input', [
        'type' => 'submit', 'name' => 'rejectstage', 'value' => get_string('markinvalid', 'mod_stage'),
        'class' => 'btn btn-danger mt-2',
    ]);
    echo html_writer::end_tag('form');

    $reseturl = new moodle_url('/mod/stage/deve.php',
        ['id' => $cm->id, 'entryid' => $entry->id, 'resetentry' => 1, 'sesskey' => sesskey()]);
    echo html_writer::div(
        html_writer::link($reseturl, get_string('resetentry', 'mod_stage'),
            ['class' => 'btn btn-outline-secondary mt-3',
                'onclick' => "return confirm('" . get_string('confirmresetentry', 'mod_stage') . "');"]),
    );

    echo $OUTPUT->footer();
    exit;
}

// Validation en masse : sélection de plusieurs saisies puis validation d'un coup.
if (optional_param('bulkvalidate', 0, PARAM_INT) && confirm_sesskey()) {
    $ids = optional_param_array('selected', [], PARAM_INT);
    foreach ($ids as $sid) {
        $entry = $DB->get_record('stage_entry', ['id' => $sid, 'stageid' => $stage->id]);
        if ($entry) {
            stage_apply_deve_validation($entry, $USER->id, $entry->declaredduration, '');
        }
    }
    redirect($baseurl, get_string('bulkvalidated', 'mod_stage', count($ids)), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// Liste de toutes les saisies non encore validées DEVE.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('devevalidation', 'mod_stage'));
echo html_writer::link(new moodle_url('/mod/stage/view.php', ['id' => $cm->id]), get_string('back'));

$themes = stage_get_themes($stage->id);

$listurl = new moodle_url($baseurl, [
    'search' => $search, 'themeid' => $filterthemeid, 'status' => $filterstatus, 'tsort' => $tsort, 'tdir' => $tdir,
]);
echo stage_render_list_filters($listurl, $themes, $search, $filterthemeid, $filterstatus);

$allentries = stage_get_filtered_entries($stage->id,
    ['search' => $search, 'themeid' => $filterthemeid, 'status' => $filterstatus, 'statuslt' => STAGE_STATUS_VALIDE_DEVE],
    $tsort, $tdir);
[$entries, $pagingbarhtml] = stage_paginate($allentries, $page, $listurl);

if (empty($allentries)) {
    echo $OUTPUT->notification(get_string('nopendingstages', 'mod_stage'), 'info');
} else {
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    $table = new html_table();
    $table->head = [
        html_writer::checkbox('selectall', 1, false, get_string('selectall', 'mod_stage'),
            ['onclick' => 'this.form.querySelectorAll(".stageselect").forEach(c=>c.checked=this.checked)']),
        stage_sort_header(get_string('student', 'mod_stage'), 'student', $listurl, $tsort, $tdir),
        stage_sort_header(get_string('theme', 'mod_stage'), 'theme', $listurl, $tsort, $tdir),
        stage_sort_header(get_string('declaredduration', 'mod_stage'), 'duration', $listurl, $tsort, $tdir),
        get_string('status', 'mod_stage'),
        get_string('actions', 'mod_stage'),
    ];
    $students = stage_get_entry_users($entries);
    foreach ($entries as $entry) {
        $student = $students[$entry->userid] ?? null;
        $themename = isset($themes[$entry->themeid]) ? format_string($themes[$entry->themeid]->name) : '-';
        $badge = html_writer::span(stage_status_label($entry->status), 'badge ' . stage_status_badgeclass($entry->status));
        $checkbox = html_writer::checkbox('selected[]', $entry->id, false, '', ['class' => 'stageselect']);
        $action = html_writer::link(new moodle_url('/mod/stage/deve.php', ['id' => $cm->id, 'entryid' => $entry->id]),
            get_string('validate', 'mod_stage'));
        $table->data[] = [
            $checkbox,
            $student ? fullname($student) : '-',
            $themename,
            $entry->declaredduration,
            $badge,
            $action,
        ];
    }
    echo html_writer::table($table);

    echo html_writer::tag('button', get_string('bulkvalidateselected', 'mod_stage'),
        ['type' => 'submit', 'name' => 'bulkvalidate', 'value' => 1, 'class' => 'btn btn-success mt-2']);
    echo html_writer::end_tag('form');

    echo $pagingbarhtml;
}

echo $OUTPUT->footer();
