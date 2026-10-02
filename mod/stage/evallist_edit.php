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
 * Édition d'une liste d'évaluation par la DEVE : son nom, son type (fixé à la création) et ses
 * questions, modifiables en ligne et enregistrées en une fois. Une liste est choisie par une ou
 * plusieurs thématiques, pour l'un de leurs formulaires d'évaluation (voir theme_edit.php).
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/evallist_edit_form.php');

use mod_stage\form\evallist_edit_form;

$id = required_param('id', PARAM_INT);
$listid = optional_param('listid', 0, PARAM_INT);
$returnurlparam = optional_param('returnurl', '', PARAM_LOCALURL);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);
$list = $listid ? $DB->get_record('stage_evallist', ['id' => $listid, 'stageid' => $stage->id], '*', MUST_EXIST) : null;

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:managethemes', $context);

$indexurl = new moodle_url('/mod/stage/evallists.php', ['id' => $cm->id]);
$returnurl = $returnurlparam !== '' ? new moodle_url($returnurlparam) : $indexurl;
$baseurl = new moodle_url('/mod/stage/evallist_edit.php', ['id' => $cm->id, 'listid' => $listid, 'returnurl' => $returnurlparam]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('evallist', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Questions existantes, dans l'ordre où elles sont présentées : les lignes soumises y sont
// rapportées par leur id, jamais crues sur parole.
$questions = $list ? array_values(stage_get_evallist_questions($list->id)) : [];
$questioninfo = [];
foreach ($questions as $i => $question) {
    $others = stage_get_question_other_evallists($question->id, $list->id);
    if ($others) {
        $questioninfo[$i] = html_writer::span(
            get_string('questionsharedwithlists', 'mod_stage', implode(', ', array_map('format_string', $others))),
            'text-warning'
        );
    }
}
$reusable = [];
if ($list) {
    foreach (stage_get_reusable_evallist_questions($list) as $question) {
        $typelabel = $question->qtype === 'choice'
            ? get_string('qtype_choice', 'mod_stage') : get_string('qtype_text', 'mod_stage');
        $reusable[$question->id] = format_string($question->name) . ' (' . $typelabel . ')';
    }
}

$mform = new evallist_edit_form($baseurl, [
    'editing' => (bool) $list,
    'evaltype' => $list->evaltype ?? null,
    'questioncount' => count($questions),
    'questioninfo' => $questioninfo,
    'reusable' => $reusable,
]);

if ($mform->is_cancelled()) {
    redirect($returnurl);
}

// Clés « à plat » (champ[i]) : les valeurs par défaut des lignes répétées sont enregistrées sous
// cette forme par repeat_elements() et l'emporteraient sur un tableau imbriqué.
$formdata = ['id' => $cm->id, 'listid' => $listid, 'returnurl' => $returnurlparam, 'name' => $list->name ?? ''];
foreach ($questions as $i => $question) {
    $formdata["questionid[$i]"] = $question->id;
    $formdata["questionqtype[$i]"] = $question->qtype;
    $formdata["questionname[$i]"] = $question->name;
    $formdata["questionoptions[$i]"] = $question->options;
    $formdata["questionnameen[$i]"] = $question->nameen;
    $formdata["questionoptionsen[$i]"] = $question->optionsen;
    $formdata["questionrequired[$i]"] = $question->required;
    $formdata["questionsortorder[$i]"] = $question->sortorder;
}
$mform->set_data($formdata);

if ($data = $mform->get_data()) {
    $now = time();
    $transaction = $DB->start_delegated_transaction();

    if ($list) {
        $list->name = $data->name;
        $list->timemodified = $now;
        $DB->update_record('stage_evallist', $list);
    } else {
        $evaltype = array_key_exists($data->evaltype ?? '', stage_evallist_fields()) ? $data->evaltype : 'student';
        $list = (object) [
            'stageid' => $stage->id,
            'evaltype' => $evaltype,
            'name' => $data->name,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $list->id = $DB->insert_record('stage_evallist', $list);
    }

    $existing = [];
    foreach ($questions as $question) {
        $existing[(int) $question->id] = $question;
    }
    foreach ($data->questionid ?? [] as $i => $questionid) {
        $questionid = (int) $questionid;
        $name = trim((string) ($data->questionname[$i] ?? ''));
        $delete = !empty($data->questiondelete[$i]);
        $qtype = ($data->questionqtype[$i] ?? 'text') === 'choice' ? 'choice' : 'text';
        $question = (object) [
            'stageid' => $stage->id,
            'evaltype' => $list->evaltype,
            'qtype' => $qtype,
            'name' => $name,
            'nameen' => $data->questionnameen[$i] ?? null,
            'options' => $qtype === 'choice' ? ($data->questionoptions[$i] ?? null) : null,
            'optionsen' => $qtype === 'choice' ? ($data->questionoptionsen[$i] ?? null) : null,
            'required' => !empty($data->questionrequired[$i]) ? 1 : 0,
            'sortorder' => (int) ($data->questionsortorder[$i] ?? 0),
            'timemodified' => $now,
        ];
        if ($questionid && isset($existing[$questionid])) {
            if ($delete) {
                // Retirée de cette liste seulement : partagée, elle survit dans les autres ;
                // sinon elle disparaît avec ses réponses.
                stage_remove_evallist_question($list->id, $questionid);
            } else {
                $question->id = $questionid;
                $DB->update_record('stage_question', $question);
            }
        } else if (!$questionid && !$delete && $name !== '') {
            $question->themeid = 0;
            $question->timecreated = $now;
            stage_add_evallist_question($list->id, $DB->insert_record('stage_question', $question));
        }
    }

    foreach ((array) ($data->attachquestionids ?? []) as $questionid) {
        if (isset($reusable[(int) $questionid])) {
            stage_add_evallist_question($list->id, (int) $questionid);
        }
    }

    $transaction->allow_commit();

    $next = !empty($data->submitandreturn)
        ? $returnurl
        : new moodle_url('/mod/stage/evallist_edit.php', [
            'id' => $cm->id, 'listid' => $list->id, 'returnurl' => $returnurlparam,
        ]);
    redirect($next, get_string('evallistsaved', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($list
    ? get_string('evallist', 'mod_stage') . ' : ' . format_string($list->name)
    : get_string('evallistadd', 'mod_stage'));

echo html_writer::start_div('mb-3');
echo html_writer::link($returnurl, get_string('back'), ['class' => 'btn btn-secondary mr-2']);
if ($returnurlparam !== '') {
    echo html_writer::link($indexurl, get_string('evallistsmanage', 'mod_stage'), ['class' => 'btn btn-link']);
}
echo html_writer::end_div();

if ($list) {
    $themes = stage_get_evallist_themes($list);
    echo $OUTPUT->notification(
        $themes
            ? get_string('evallistusedby', 'mod_stage', implode(', ', array_map(
                fn($theme) => format_string($theme->name),
                $themes
            )))
            : get_string('evallistunused', 'mod_stage'),
        'info'
    );
}

$mform->display();

echo $OUTPUT->footer();
