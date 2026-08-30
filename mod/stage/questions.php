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
 * Gestion par la DEVE des questions d'évaluation (choix multiples ou commentaire libre),
 * définies par thématique, pour le formulaire d'auto-évaluation de l'étudiant et pour le
 * formulaire d'évaluation de l'enseignant référent.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/question_form.php');

use mod_stage\form\question_form;

$id = required_param('id', PARAM_INT);
$themeid = required_param('themeid', PARAM_INT);
$questionid = optional_param('questionid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);
$theme = $DB->get_record('stage_theme', ['id' => $themeid, 'stageid' => $stage->id], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:managethemes', $context);

$baseurl = new moodle_url('/mod/stage/questions.php', ['id' => $cm->id, 'themeid' => $theme->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('evalquestions', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Suppression d'une question (uniquement son association à cette thématique : la question
// n'est effacée pour de bon que si elle n'est plus utilisée par aucune autre thématique).
if ($action === 'delete' && $questionid) {
    require_sesskey();
    $DB->get_record('stage_question', ['id' => $questionid, 'stageid' => $stage->id], '*', MUST_EXIST);
    stage_unlink_question_theme($questionid, $theme->id);
    redirect($baseurl, get_string('questiondeleted', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Association d'une question déjà définie pour une autre thématique du même stage (réutilisation).
if ($action === 'attach' && $questionid) {
    require_sesskey();
    $question = $DB->get_record('stage_question', ['id' => $questionid, 'stageid' => $stage->id], '*', MUST_EXIST);
    $themeids = stage_get_question_themeids($question->id);
    $themeids[] = $theme->id;
    stage_set_question_themes($question->id, $themeids);
    redirect($baseurl, get_string('questionattached', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Ajout / édition d'une question.
if ($action === 'edit') {
    $themeoptions = [];
    foreach (stage_get_themes($stage->id) as $stagetheme) {
        $themeoptions[$stagetheme->id] = stage_theme_option_label($stagetheme);
    }

    $formurl = new moodle_url('/mod/stage/questions.php',
        ['id' => $cm->id, 'themeid' => $theme->id, 'action' => 'edit', 'questionid' => $questionid]);
    $mform = new question_form($formurl, ['themes' => $themeoptions, 'tutorenabled' => !empty($stage->tutorevaluationenabled)]);
    $question = null;
    if ($questionid) {
        $question = $DB->get_record('stage_question', ['id' => $questionid, 'stageid' => $stage->id], '*', MUST_EXIST);
        $question->questionid = $question->id;
        $question->id = $cm->id;
        $question->themeid = $theme->id;
        $question->themeids = stage_get_question_themeids($question->id);
        $mform->set_data($question);
    } else {
        $mform->set_data([
            'id' => $cm->id, 'themeid' => $theme->id, 'questionid' => 0, 'qtype' => 'text',
            'themeids' => [$theme->id],
        ]);
    }

    if ($mform->is_cancelled()) {
        redirect($baseurl);
    } else if ($data = $mform->get_data()) {
        $record = new stdClass();
        $record->stageid = $stage->id;
        $record->themeid = in_array($theme->id, $data->themeids) ? $theme->id : reset($data->themeids);
        $record->evaltype = $data->evaltype;
        $record->qtype = $data->qtype;
        $record->name = $data->name;
        $record->options = $data->qtype === 'choice' ? $data->options : null;
        $record->required = !empty($data->required) ? 1 : 0;
        $record->sortorder = $data->sortorder;
        $record->timemodified = time();

        if (!empty($data->questionid)) {
            $record->id = $data->questionid;
            $DB->update_record('stage_question', $record);
            $newquestionid = $record->id;
        } else {
            $record->timecreated = time();
            $newquestionid = $DB->insert_record('stage_question', $record);
        }
        stage_set_question_themes($newquestionid, $data->themeids);
        redirect($baseurl, get_string('questionsaved', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('evalquestions', 'mod_stage') . ' - ' . format_string($theme->name));
    $mform->display();
    echo $OUTPUT->footer();
    exit;
}

// Liste des questions, groupées par type d'évaluation.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('evalquestions', 'mod_stage') . ' - ' . format_string($theme->name));
echo html_writer::link(new moodle_url('/mod/stage/themes.php', ['id' => $cm->id]), get_string('back'));

echo html_writer::link(new moodle_url('/mod/stage/questions.php', ['id' => $cm->id, 'themeid' => $theme->id, 'action' => 'edit']),
    get_string('addquestion', 'mod_stage'), ['class' => 'btn btn-primary d-block mt-2 mb-3', 'style' => 'width:fit-content']);

// Réutilisation d'une question déjà définie pour une autre thématique de ce stage.
$reusable = stage_get_reusable_questions($stage->id, $theme->id);
if (!empty($reusable)) {
    $options = [0 => get_string('selectexistingquestion', 'mod_stage')];
    foreach ($reusable as $reusablequestion) {
        $typelabel = $reusablequestion->qtype === 'choice'
            ? get_string('qtype_choice', 'mod_stage') : get_string('qtype_text', 'mod_stage');
        $evallabel = stage_evaltype_label($reusablequestion->evaltype);
        $options[$reusablequestion->id] = format_string($reusablequestion->name) . ' (' . $evallabel . ', ' . $typelabel . ')';
    }

    echo html_writer::start_tag('form', [
        'method' => 'post', 'action' => $baseurl, 'class' => 'form-inline mb-3',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'attach']);
    echo html_writer::select($options, 'questionid', 0, null, ['class' => 'form-control mr-2']);
    echo html_writer::empty_tag('input', [
        'type' => 'submit', 'value' => get_string('reusequestion', 'mod_stage'), 'class' => 'btn btn-secondary',
    ]);
    echo html_writer::end_tag('form');
}

$evaltypes = ['student', 'teacher'];
if (!empty($stage->tutorevaluationenabled)) {
    $evaltypes[] = 'tutor';
}
foreach ($evaltypes as $evaltype) {
    echo $OUTPUT->heading(stage_evaltype_label($evaltype), 4);
    $questions = stage_get_questions($theme->id, $evaltype);

    if (empty($questions)) {
        echo $OUTPUT->notification(get_string('noquestionsyet', 'mod_stage'), 'info');
        continue;
    }

    $table = new html_table();
    $table->head = [
        get_string('questionlabel', 'mod_stage'),
        get_string('qtype', 'mod_stage'),
        get_string('questionrequired', 'mod_stage'),
        get_string('actions', 'mod_stage'),
    ];
    foreach ($questions as $question) {
        $qtypelabel = $question->qtype === 'choice'
            ? get_string('qtype_choice', 'mod_stage') : get_string('qtype_text', 'mod_stage');
        $editurl = new moodle_url('/mod/stage/questions.php',
            ['id' => $cm->id, 'themeid' => $theme->id, 'action' => 'edit', 'questionid' => $question->id]);
        $deleteurl = new moodle_url('/mod/stage/questions.php',
            ['id' => $cm->id, 'themeid' => $theme->id, 'action' => 'delete', 'questionid' => $question->id,
                'sesskey' => sesskey()]);
        $actions = stage_render_actions([get_string('edit') => $editurl])
            . html_writer::link($deleteurl, get_string('delete'), [
                'class' => 'btn btn-sm btn-outline-danger mr-1 mb-1',
                'onclick' => "return confirm('" . get_string('confirmdeletequestion', 'mod_stage') . "');",
            ]);
        $table->data[] = [
            format_string($question->name),
            $qtypelabel,
            $question->required ? get_string('yes') : get_string('no'),
            $actions,
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
