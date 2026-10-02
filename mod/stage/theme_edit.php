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
 * requises par année, enseignants responsables, documents et check-list d'objectifs, choix des
 * listes d'évaluation (éditées sur leur propre page, evallist_edit.php). Tout s'enregistre en
 * une fois ; un sélecteur permet de passer directement d'une
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

$filemanageroptions = ['subdirs' => 0, 'maxfiles' => 20, 'maxbytes' => stage_max_upload_bytes($context)];

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
// Listes d'évaluation disponibles pour chaque formulaire.
$evallists = [];
foreach (stage_get_evallists($stage->id) as $list) {
    $evallists[$list->evaltype][$list->id] = format_string($list->name);
}
$evallisturl = new moodle_url('/mod/stage/evallist_edit.php', [
    'id' => $cm->id,
    'returnurl' => (new moodle_url('/mod/stage/theme_edit.php', ['id' => $cm->id, 'themeid' => $themeid], 'id_questionshdr'))
        ->out_as_local_url(false),
]);

$mform = new theme_edit_form($baseurl, [
    'teachers' => $teacheroptions,
    'checklistcount' => count($checklist),
    'evallists' => $evallists,
    'evallisturl' => $evallisturl->out(false),
    'evallistsindexurl' => (new moodle_url('/mod/stage/evallists.php', ['id' => $cm->id]))->out(false),
    // Une liste déjà choisie pour le maître de stage reste visible même si l'évaluation par le
    // maître de stage a depuis été désactivée : sinon l'enregistrement la retirerait en silence.
    'tutorenabled' => !empty($stage->tutorevaluationenabled) || !empty($theme->tutorlistid),
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
            'visible', 'tutorevaluationenabled', 'reportmode', 'studentlistid', 'teacherlistid', 'tutorlistid',
        ] as $field
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
    // Liste d'évaluation de chaque formulaire : seule une liste de cette activité et de ce type
    // est acceptée. Un champ absent du formulaire (maître de stage non proposé) garde sa valeur.
    foreach (stage_evallist_fields() as $evaltype => $field) {
        if (isset($data->$field)) {
            $listid = (int) $data->$field;
            $record->$field = isset($evallists[$evaltype][$listid]) ? $listid : 0;
        } else {
            $record->$field = $theme ? (int) $theme->$field : 0;
        }
    }
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
    // « Nouvelle liste » : la thématique vient d'être enregistrée, la création de la liste s'ouvre
    // et la nouvelle liste sera choisie pour ce formulaire de la thématique.
    foreach (array_keys(stage_evallist_fields()) as $evaltype) {
        if (!empty($data->{'createlist_' . $evaltype})) {
            $back = new moodle_url('/mod/stage/theme_edit.php', ['id' => $cm->id, 'themeid' => $savedthemeid], 'id_questionshdr');
            $next = new moodle_url('/mod/stage/evallist_edit.php', [
                'id' => $cm->id,
                'themeid' => $savedthemeid,
                'evaltype' => $evaltype,
                'returnurl' => $back->out_as_local_url(false),
            ]);
        }
    }
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
    'id_questionshdr' => get_string('evallists', 'mod_stage'),
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

    // Le lien « Éditer la liste » suit la liste choisie dans le menu voisin.
    document.querySelectorAll('.stage-evallist-edit').forEach(function(link) {
        var select = document.getElementById(link.getAttribute('data-select'));
        if (!select) {
            return;
        }
        var refresh = function() {
            var listid = parseInt(select.value, 10) || 0;
            link.href = link.getAttribute('data-baseurl') + '&listid=' + listid;
            link.style.display = listid ? '' : 'none';
        };
        select.addEventListener('change', refresh);
        refresh();
    });
})();
JS;
echo html_writer::script($js);

echo $OUTPUT->footer();
