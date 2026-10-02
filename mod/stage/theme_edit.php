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
 * Gestion d'une thématique de stage par la DEVE, en une seule page : paramètres généraux, durées
 * requises par année, enseignants responsables, documents et check-list d'objectifs, questions
 * d'évaluation. Tout s'enregistre en une fois ; un sélecteur permet de passer directement d'une
 * thématique à l'autre sans repasser par la liste.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/theme_edit_form.php');

use mod_stage\form\theme_edit_form;

$id = required_param('id', PARAM_INT);
$themeid = optional_param('themeid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);
$theme = $themeid ? $DB->get_record('stage_theme', ['id' => $themeid, 'stageid' => $stage->id], '*', MUST_EXIST) : null;

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:managethemes', $context);

$listurl = new moodle_url('/mod/stage/themes.php', ['id' => $cm->id]);
$baseurl = new moodle_url('/mod/stage/theme_edit.php', ['id' => $cm->id, 'themeid' => $themeid]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('managetheme', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$filemanageroptions = ['subdirs' => 0, 'maxfiles' => 20, 'maxbytes' => $CFG->maxbytes];
$evaltypes = ['student', 'teacher', 'tutor'];

$allthemes = stage_get_themes($stage->id);
$themelabels = [];
foreach ($allthemes as $stagetheme) {
    $themelabels[$stagetheme->id] = stage_theme_option_label($stagetheme);
}

$teacheroptions = [];
foreach (stage_get_potential_teachers($context) as $teacher) {
    $teacheroptions[$teacher->id] = fullname($teacher);
}

// Éléments existants de la thématique, dans l'ordre où ils sont présentés dans le formulaire :
// les lignes soumises y sont rapportées par leur id, jamais crues sur parole.
$checklist = $theme ? array_values(stage_get_theme_checklist($theme->id)) : [];
$questions = [];
if ($theme) {
    foreach ($evaltypes as $evaltype) {
        $questions = array_merge($questions, array_values(stage_get_questions($theme->id, $evaltype)));
    }
}

$questioninfo = [];
$hastutorquestion = false;
foreach ($questions as $i => $question) {
    $hastutorquestion = $hastutorquestion || $question->evaltype === 'tutor';
    $info = html_writer::tag('strong', stage_evaltype_label($question->evaltype));
    $shared = array_diff(stage_get_question_themeids($question->id), [$theme->id]);
    $sharedlabels = array_intersect_key($themelabels, array_flip($shared));
    if (!empty($sharedlabels)) {
        $info .= ' ' . html_writer::span(
            get_string('questionsharedwith', 'mod_stage', implode(', ', $sharedlabels)),
            'text-warning'
        );
    }
    $questioninfo[$i] = $info;
}

$reusable = [];
if ($theme) {
    foreach (stage_get_reusable_questions($stage->id, $theme->id) as $question) {
        $typelabel = $question->qtype === 'choice'
            ? get_string('qtype_choice', 'mod_stage') : get_string('qtype_text', 'mod_stage');
        $reusable[$question->id] = format_string($question->name)
            . ' (' . stage_evaltype_label($question->evaltype) . ', ' . $typelabel . ')';
    }
}

$mform = new theme_edit_form($baseurl, [
    'teachers' => $teacheroptions,
    'checklistcount' => count($checklist),
    'questioncount' => count($questions),
    'questioninfo' => $questioninfo,
    'reusable' => $reusable,
    // Une question existante destinée au maître de stage reste éditable même si l'évaluation par
    // le maître de stage a depuis été désactivée : sinon son formulaire la basculerait en silence.
    'tutorenabled' => !empty($stage->tutorevaluationenabled) || $hastutorquestion,
    'filemanageroptions' => $filemanageroptions,
]);

if ($mform->is_cancelled()) {
    redirect($listurl);
}

// Valeurs initiales.
$draftitemid = file_get_submitted_draft_itemid('objectivefiles');
file_prepare_draft_area(
    $draftitemid,
    $context->id,
    'mod_stage',
    STAGE_THEME_OBJECTIVE_FILEAREA,
    $theme ? $theme->id : null,
    $filemanageroptions
);
$formdata = ['id' => $cm->id, 'themeid' => $themeid, 'objectivefiles' => $draftitemid];
if ($theme) {
    foreach (
        ['name', 'description', 'mandatory', 'requiredduration', 'minstudyyear', 'maxstudyyear', 'sortorder',
            'visible', 'tutorevaluationenabled', 'reportmode'] as $field
    ) {
        $formdata[$field] = $theme->$field;
    }
    foreach (stage_get_theme_durations($theme->id) as $year => $duration) {
        $formdata['duration_' . $year] = $duration;
    }
    $formdata['teacherids'] = array_keys(stage_get_theme_teachers($theme->id));
}
// Clés « à plat » (champ[i]) : les valeurs par défaut des lignes répétées sont enregistrées sous
// cette forme par repeat_elements() et l'emporteraient sur un tableau imbriqué.
foreach ($checklist as $i => $item) {
    $formdata["checklistid[$i]"] = $item->id;
    $formdata["checklistname[$i]"] = $item->name;
    $formdata["checklistdescription[$i]"] = $item->description;
    $formdata["checklistsortorder[$i]"] = $item->sortorder;
}
foreach ($questions as $i => $question) {
    $formdata["questionid[$i]"] = $question->id;
    $formdata["questionevaltype[$i]"] = $question->evaltype;
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

    // Paramètres généraux.
    $record = (object) [
        'stageid' => $stage->id,
        'name' => $data->name,
        'description' => $data->description,
        'mandatory' => !empty($data->mandatory) ? 1 : 0,
        'requiredduration' => (int) $data->requiredduration,
        'minstudyyear' => (int) $data->minstudyyear,
        'maxstudyyear' => (int) $data->maxstudyyear,
        'sortorder' => (int) $data->sortorder,
        'visible' => !empty($data->visible) ? 1 : 0,
        'tutorevaluationenabled' => !empty($data->tutorevaluationenabled) ? 1 : 0,
        'reportmode' => array_key_exists((int) $data->reportmode, stage_report_mode_options())
            ? (int) $data->reportmode : STAGE_REPORT_NONE,
        'timemodified' => $now,
    ];
    if ($theme) {
        $record->id = $theme->id;
        $DB->update_record('stage_theme', $record);
    } else {
        $record->timecreated = $now;
        $record->id = $DB->insert_record('stage_theme', $record);
    }
    $savedthemeid = (int) $record->id;

    // Durées par année, en l'absence de durée unique (les champs sont alors désactivés et non
    // soumis). Un champ vide supprime la valeur propre à l'année, qui reprend alors la valeur
    // « toutes années » ; enregistrer 0 la masquerait.
    if (empty($record->requiredduration)) {
        foreach (array_keys(stage_studyyear_options()) as $year) {
            $value = trim((string) ($data->{'duration_' . $year} ?? ''));
            if ($value === '') {
                stage_delete_theme_duration($savedthemeid, $year);
            } else {
                stage_set_theme_duration($savedthemeid, $year, (int) $value);
            }
        }
    }

    // Enseignants responsables : choisis parmi les enseignants du cours uniquement, sans quoi un
    // id arbitraire soumis à la main donnerait accès aux rapports de la thématique.
    if (!empty($teacheroptions)) {
        $teacherids = array_intersect((array) ($data->teacherids ?? []), array_keys($teacheroptions));
        stage_set_theme_teachers($savedthemeid, $teacherids);
    }

    // Check-list d'objectifs.
    $existingitems = [];
    foreach ($checklist as $item) {
        $existingitems[(int) $item->id] = $item;
    }
    foreach ($data->checklistid ?? [] as $i => $itemid) {
        $itemid = (int) $itemid;
        $name = trim((string) ($data->checklistname[$i] ?? ''));
        $delete = !empty($data->checklistdelete[$i]);
        $item = (object) [
            'themeid' => $savedthemeid,
            'name' => $name,
            'description' => $data->checklistdescription[$i] ?? '',
            'sortorder' => (int) ($data->checklistsortorder[$i] ?? 0),
            'timemodified' => $now,
        ];
        if ($itemid && isset($existingitems[$itemid])) {
            if ($delete) {
                stage_delete_theme_checklist_item($itemid);
            } else {
                $item->id = $itemid;
                $DB->update_record('stage_theme_checklist', $item);
            }
        } else if (!$itemid && !$delete && $name !== '') {
            $item->timecreated = $now;
            $DB->insert_record('stage_theme_checklist', $item);
        }
    }

    // Questions d'évaluation.
    $existingquestions = [];
    foreach ($questions as $question) {
        $existingquestions[(int) $question->id] = $question;
    }
    foreach ($data->questionid ?? [] as $i => $questionid) {
        $questionid = (int) $questionid;
        $name = trim((string) ($data->questionname[$i] ?? ''));
        $delete = !empty($data->questiondelete[$i]);
        $qtype = ($data->questionqtype[$i] ?? 'text') === 'choice' ? 'choice' : 'text';
        $evaltype = in_array($data->questionevaltype[$i] ?? '', $evaltypes, true) ? $data->questionevaltype[$i] : 'student';
        $question = (object) [
            'stageid' => $stage->id,
            'evaltype' => $evaltype,
            'qtype' => $qtype,
            'name' => $name,
            'nameen' => $data->questionnameen[$i] ?? null,
            'options' => $qtype === 'choice' ? ($data->questionoptions[$i] ?? null) : null,
            'optionsen' => $qtype === 'choice' ? ($data->questionoptionsen[$i] ?? null) : null,
            'required' => !empty($data->questionrequired[$i]) ? 1 : 0,
            'sortorder' => (int) ($data->questionsortorder[$i] ?? 0),
            'timemodified' => $now,
        ];
        if ($questionid && isset($existingquestions[$questionid])) {
            if ($delete) {
                // Retire la question de cette thématique seulement : partagée, elle survit
                // ailleurs ; sinon elle disparaît avec ses réponses.
                stage_unlink_question_theme($questionid, $savedthemeid);
            } else {
                $question->id = $questionid;
                $DB->update_record('stage_question', $question);
            }
        } else if (!$questionid && !$delete && $name !== '') {
            $question->themeid = $savedthemeid;
            $question->timecreated = $now;
            $newid = $DB->insert_record('stage_question', $question);
            stage_set_question_themes($newid, [$savedthemeid]);
        }
    }

    // Questions d'autres thématiques associées à celle-ci.
    foreach ((array) ($data->attachquestionids ?? []) as $questionid) {
        $questionid = (int) $questionid;
        if (!isset($reusable[$questionid])) {
            continue;
        }
        $themeids = stage_get_question_themeids($questionid);
        $themeids[] = $savedthemeid;
        stage_set_question_themes($questionid, $themeids);
    }

    $transaction->allow_commit();

    file_save_draft_area_files(
        $data->objectivefiles,
        $context->id,
        'mod_stage',
        STAGE_THEME_OBJECTIVE_FILEAREA,
        $savedthemeid,
        $filemanageroptions
    );

    $next = !empty($data->submitandreturn)
        ? $listurl
        : new moodle_url('/mod/stage/theme_edit.php', ['id' => $cm->id, 'themeid' => $savedthemeid]);
    redirect($next, get_string('themesaved', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($theme
    ? get_string('managetheme', 'mod_stage') . ' : ' . format_string($theme->name)
    : get_string('addtheme', 'mod_stage'));

echo html_writer::start_div('d-flex flex-wrap align-items-center mb-3');
echo html_writer::link($listurl, get_string('backtothemelist', 'mod_stage'), ['class' => 'btn btn-secondary mr-2 mb-1']);
if (count($themelabels) > 1 || ($theme === null && !empty($themelabels))) {
    $select = new single_select(
        new moodle_url('/mod/stage/theme_edit.php', ['id' => $cm->id]),
        'themeid',
        $themelabels,
        $themeid,
        ['' => get_string('switchtheme', 'mod_stage')]
    );
    $select->set_label(get_string('switchtheme', 'mod_stage'), ['class' => 'sr-only']);
    $select->class = 'mr-2 mb-1';
    echo $OUTPUT->render($select);
}
if ($theme) {
    echo html_writer::link(
        new moodle_url('/mod/stage/theme_stages.php', ['id' => $cm->id, 'themeid' => $theme->id]),
        get_string('viewthemestages', 'mod_stage'),
        ['class' => 'btn btn-outline-secondary mr-2 mb-1']
    );
}
echo html_writer::end_div();

// Sommaire : chaque volet du formulaire reste à un clic, sans quitter la page.
$sections = [
    'id_generalhdr' => get_string('themegeneral', 'mod_stage'),
    'id_durationshdr' => get_string('themedurationssection', 'mod_stage'),
    'id_teachershdr' => get_string('themeteachers', 'mod_stage'),
    'id_objectiveshdr' => get_string('themeobjectives', 'mod_stage'),
    'id_checklisthdr' => get_string('themechecklist', 'mod_stage'),
    'id_questionshdr' => get_string('evalquestions', 'mod_stage'),
];
$toc = [];
foreach ($sections as $anchor => $label) {
    $toc[] = html_writer::link('#' . $anchor, $label, ['class' => 'badge badge-light border mr-1 mb-1 p-2']);
}
echo html_writer::div(implode('', $toc), 'stage-theme-toc mb-3');

$mform->display();

// Toutes les années restent modifiables ; celles de la plage choisie sont mises en avant à mesure
// que la plage change (même règle que stage_theme_duration_years()).
$js = <<<'JS'
(function() {
    var min = document.getElementById('id_minstudyyear');
    var max = document.getElementById('id_maxstudyyear');
    if (!min || !max) {
        return;
    }
    function update() {
        var a = parseInt(min.value, 10) || 0;
        var b = parseInt(max.value, 10) || 0;
        var lo = 0;
        var hi = 0;
        if (a || b) {
            a = a || b;
            b = b || a;
            lo = Math.min(a, b);
            hi = Math.max(a, b);
        }
        document.querySelectorAll('[data-stage-durationyear]').forEach(function(input) {
            var year = parseInt(input.getAttribute('data-stage-durationyear'), 10);
            var row = input.closest('.fitem') || input.closest('.form-group');
            if (row) {
                var inrange = year === 0 || (lo > 0 && year >= lo && year <= hi);
                row.style.opacity = inrange ? '' : '0.6';
            }
        });
    }
    min.addEventListener('change', update);
    max.addEventListener('change', update);
    update();
})();
JS;
echo html_writer::script($js);

echo $OUTPUT->footer();
