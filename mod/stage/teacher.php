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

    $periods = stage_get_or_seed_entry_periods($entry);

    // Jours de stage effectifs sélectionnés par l'étudiant : visibles et modifiables ici par
    // l'enseignant référent (formulaire distinct de l'évaluation, avec son propre bouton).
    if ($editable && !empty($periods) && optional_param('saveworkdays', 0, PARAM_INT) && confirm_sesskey()) {
        $workdays = optional_param_array('workdays', [], PARAM_INT);
        stage_set_entry_workdays($entry->id, $workdays);
        redirect(new moodle_url('/mod/stage/teacher.php', ['id' => $cm->id, 'entryid' => $entryid]),
            get_string('workdayssaved', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

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

    // Rappel de la saisie évaluée, en tableau plutôt qu'en paragraphes épars, et complété des
    // informations qui manquaient ici (année d'étude, mobilité, plages de dates, convention).
    echo stage_render_entry_summary($entry, $theme);

    // Auto-évaluation de l'étudiant : réponses au formulaire défini par la DEVE si
    // des questions existent pour cette thématique, sinon commentaire libre.
    echo $OUTPUT->heading(get_string('studentselfeval', 'mod_stage'), 4);
    $studentquestions = stage_get_questions($entry->themeid, 'student');
    if (!empty($studentquestions)) {
        echo stage_render_answers_readonly($studentquestions, stage_get_answers($entry->id));
    } else {
        echo html_writer::div(format_text($entry->studentselfeval, FORMAT_HTML));
    }

    // Évaluation du maître de stage, si l'option est activée : mêmes modalités que
    // l'auto-évaluation de l'étudiant, affichée en lecture seule.
    if (!empty($stage->tutorevaluationenabled)) {
        echo $OUTPUT->heading(get_string('tutorevalheading', 'mod_stage'), 4);
        $tutorquestions = stage_get_questions($entry->themeid, 'tutor');
        if (!empty($tutorquestions) && $entry->tutortime) {
            echo stage_render_answers_readonly($tutorquestions, stage_get_answers($entry->id));
        } else if ($entry->tutoreval) {
            echo html_writer::div(format_text($entry->tutoreval, FORMAT_PLAIN));
        } else {
            echo $OUTPUT->notification(get_string('notutoreval', 'mod_stage'), 'info');
        }
    }

    if (!empty($periods)) {
        echo $OUTPUT->heading(get_string('workdays', 'mod_stage'), 4);
        if ($editable) {
            $workdaysformurl = new moodle_url('/mod/stage/teacher.php', ['id' => $cm->id, 'entryid' => $entry->id]);
            echo html_writer::start_tag('form', ['method' => 'post', 'action' => $workdaysformurl]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'saveworkdays', 'value' => 1]);
            echo stage_render_workday_picker($periods, stage_get_entry_workdays($entry->id), true);
            echo html_writer::empty_tag('input', [
                'type' => 'submit', 'value' => get_string('savechanges'), 'class' => 'btn btn-secondary mt-2',
            ]);
            echo html_writer::end_tag('form');
        } else {
            echo stage_render_workday_picker($periods, stage_get_entry_workdays($entry->id), false);
        }
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

    // Les deux issues possibles (valider ou refuser) sont présentées comme deux blocs distincts,
    // chacun sous son propre titre avec son champ et son bouton : présentés à la suite, le
    // commentaire de refus semblait se rapporter au bouton de validation qui le précédait.
    $formurl = new moodle_url('/mod/stage/teacher.php', ['id' => $cm->id, 'entryid' => $entry->id]);
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $formurl]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    echo $OUTPUT->heading(get_string('teachereval', 'mod_stage'), 4);
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
        'class' => 'btn btn-primary mt-2 mb-4',
    ]);

    echo $OUTPUT->heading(get_string('rejectstageheading', 'mod_stage'), 4);
    echo html_writer::tag('p', get_string('rejectstageheading_help', 'mod_stage'), ['class' => 'text-muted']);
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
                stage_render_actions([
                    get_string('conventionteachervalidate', 'mod_stage') =>
                        new moodle_url('/mod/stage/convention_teacher_validate.php',
                            ['id' => $cm->id, 'entryid' => $pendingentry->id]),
                ], 'btn btn-sm btn-primary mr-1 mb-1'),
            ];
        }
        echo html_writer::table($pendingtable);
    }

    // Titre distinct de celui de la page (« Validation enseignant »), qui était repris tel quel
    // et laissait croire à une répétition de la section précédente.
    echo $OUTPUT->heading(get_string('stagestoevaluate', 'mod_stage'), 4);

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
        $signedavailable = (int) $entry->conventionstatus === STAGE_CONVENTION_SIGNED
            && stage_get_signed_convention_file($context, $entry->id);
        $table->data[] = [
            $student ? fullname($student) : '-',
            $themename,
            $entry->declaredduration,
            $badge,
            stage_render_actions([
                get_string('evaluate', 'mod_stage') =>
                    new moodle_url('/mod/stage/teacher.php', ['id' => $cm->id, 'entryid' => $entry->id]),
                get_string('downloadsignedconvention', 'mod_stage') => $signedavailable
                    ? new moodle_url('/mod/stage/convention_signed.php', ['id' => $cm->id, 'entryid' => $entry->id])
                    : null,
            ]),
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
