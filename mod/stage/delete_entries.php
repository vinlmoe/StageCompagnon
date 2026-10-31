<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Suppression en lot de stages par la DEVE, typiquement pour défaire un import erroné.
 *
 * Trois étapes : la DEVE choisit les stages (cases à cocher, ou tous ceux qui répondent aux
 * filtres), relit un écran d'avertissement qui les récapitule, puis confirme deux fois : une case
 * « je comprends » et le nombre exact de stages à saisir. La suppression est définitive.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$search = optional_param('search', '', PARAM_TEXT);
$filterthemeid = optional_param('themeid', 0, PARAM_INT);
$filterstatus = stage_optional_status_param('status');
$createdon = optional_param('createdon', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
// Mêmes droits que les imports en masse, que cette page sert surtout à défaire.
require_capability('mod/stage:registerstages', $context);
require_capability('mod/stage:validatedeve', $context);

$filterparams = ['search' => $search, 'themeid' => $filterthemeid, 'status' => $filterstatus, 'createdon' => $createdon];
$url = new moodle_url('/mod/stage/delete_entries.php', ['id' => $cm->id] + $filterparams);
$backurl = new moodle_url('/mod/stage/register.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('deleteentries', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));

$allthemes = stage_get_themes($stage->id);
$filters = ['search' => $search, 'themeid' => $filterthemeid, 'status' => $filterstatus];
if ($createdon) {
    // Fin du jour : le minuit suivant, même un jour de changement d'heure (23 ou 25 heures).
    $filters['createdfrom'] = $createdon;
    $filters['createdto'] = usergetmidnight($createdon + DAYSECS + HOURSECS);
}

$error = null;
$toconfirm = null;

// Étape 3 : suppression, après la double confirmation.
if ($action === 'delete' && confirm_sesskey()) {
    $entries = stage_load_entries_by_id($stage->id, explode(',', required_param('entryids', PARAM_SEQUENCE)));
    $understood = optional_param('understood', 0, PARAM_BOOL);
    $typedcount = trim(optional_param('confirmcount', '', PARAM_RAW_TRIMMED));
    if (!$entries) {
        redirect($url, get_string('deleteentriesnone', 'mod_stage'), null, \core\output\notification::NOTIFY_WARNING);
    } else if (!$understood || $typedcount !== (string) count($entries)) {
        $error = get_string('deleteentriesconfirmmismatch', 'mod_stage', count($entries));
        $toconfirm = $entries;
    } else {
        $deleted = stage_bulk_delete_entries($stage->id, $context, array_keys($entries));
        redirect(
            $backurl,
            get_string('deleteentriesdone', 'mod_stage', $deleted),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Étape 2 : écran d'avertissement, sur la sélection ou sur tous les stages filtrés.
if ($action === 'confirm' && confirm_sesskey()) {
    if (optional_param('scope', 'selected', PARAM_ALPHA) === 'all') {
        $ids = array_keys(stage_get_filtered_entries($stage->id, $filters));
    } else {
        $ids = optional_param_array('selected', [], PARAM_INT);
    }
    $toconfirm = stage_load_entries_by_id($stage->id, $ids);
    if (!$toconfirm) {
        redirect($url, get_string('deleteentriesnone', 'mod_stage'), null, \core\output\notification::NOTIFY_WARNING);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('deleteentries', 'mod_stage'));
echo html_writer::link($backurl, get_string('back'));

if ($toconfirm !== null) {
    $count = count($toconfirm);
    $validated = count(array_filter($toconfirm, fn($entry) => (int) $entry->status === STAGE_STATUS_VALIDE_DEVE));
    if ($error) {
        echo $OUTPUT->notification($error, \core\output\notification::NOTIFY_ERROR);
    }
    $warning = html_writer::tag('strong', get_string('deleteentrieswarning', 'mod_stage', $count))
        . html_writer::tag('p', get_string('deleteentrieswarning_details', 'mod_stage'), ['class' => 'mb-1 mt-2']);
    if ($validated) {
        $warning .= html_writer::tag(
            'p',
            get_string('deleteentrieswarning_validated', 'mod_stage', $validated),
            ['class' => 'mb-1']
        );
    }
    $warning .= html_writer::tag('p', get_string('deleteentrieswarning_export', 'mod_stage', (object) [
        'url' => (new moodle_url('/mod/stage/export.php', ['id' => $cm->id]))->out(false),
    ]), ['class' => 'mb-0']);
    echo $OUTPUT->notification($warning, \core\output\notification::NOTIFY_ERROR, false);

    // Récapitulatif par statut, puis détail : la DEVE relit ce qu'elle s'apprête à perdre.
    $bystatus = [];
    foreach ($toconfirm as $entry) {
        $label = stage_status_label($entry->status);
        $bystatus[$label] = ($bystatus[$label] ?? 0) + 1;
    }
    $summary = new html_table();
    $summary->head = [get_string('status', 'mod_stage'), get_string('deleteentriescount', 'mod_stage')];
    foreach ($bystatus as $label => $number) {
        $summary->data[] = [s($label), $number];
    }
    echo html_writer::table($summary);
    echo html_writer::tag(
        'details',
        html_writer::tag('summary', get_string('deleteentrieslist', 'mod_stage', $count))
            . stage_render_deletion_table($toconfirm, $allthemes, false),
        ['class' => 'mb-3']
    );

    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url, 'class' => 'border border-danger rounded p-3']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'delete']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'entryids',
        'value' => implode(',', array_keys($toconfirm))]);
    echo html_writer::div(
        html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'understood', 'value' => 1,
            'id' => 'stage-delete-understood', 'class' => 'form-check-input'])
            . html_writer::tag(
                'label',
                get_string('deleteentriesunderstood', 'mod_stage'),
                ['for' => 'stage-delete-understood', 'class' => 'form-check-label']
            ),
        'form-check mb-3'
    );
    echo html_writer::tag(
        'label',
        get_string('deleteentriestypecount', 'mod_stage', $count),
        ['for' => 'stage-delete-count', 'class' => 'd-block']
    );
    echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'confirmcount', 'id' => 'stage-delete-count',
        'inputmode' => 'numeric', 'autocomplete' => 'off', 'required' => 'required',
        'class' => 'form-control mb-3', 'style' => 'width:8em']);
    echo html_writer::tag(
        'button',
        get_string('deleteentriesfinal', 'mod_stage', $count),
        ['type' => 'submit', 'class' => 'btn btn-danger mr-2']
    );
    echo html_writer::link($url, get_string('cancel'), ['class' => 'btn btn-secondary']);
    echo html_writer::end_tag('form');
    echo $OUTPUT->footer();
    exit;
}

// Étape 1 : choix des stages.
echo $OUTPUT->box(get_string('deleteentries_help', 'mod_stage'), 'generalbox my-3');

$themeoptions = [0 => get_string('allthemes', 'mod_stage')];
foreach ($allthemes as $theme) {
    $themeoptions[$theme->id] = format_string($theme->name);
}
$dayoptions = [0 => get_string('deleteentriesalldays', 'mod_stage')];
foreach (stage_get_entry_creation_days($stage->id) as $day => $number) {
    $dayoptions[$day] = get_string('deleteentriesday', 'mod_stage', (object) [
        'date' => userdate($day, get_string('strftimedatefullshort', 'langconfig')), 'count' => $number,
    ]);
}
echo $OUTPUT->render(new \mod_stage\output\list_filters($url, ['search', 'themeid', 'status', 'createdon', 'page'], $search, [
    'themeid' => ['options' => $themeoptions, 'selected' => $filterthemeid],
    'status' => ['options' => stage_status_filter_options(), 'selected' => $filterstatus],
    'createdon' => ['options' => $dayoptions, 'selected' => $createdon],
]));

[$entries, $pagingbarhtml, $entrytotal] = stage_get_filtered_entries_page(
    $stage->id,
    $filters,
    'student',
    'ASC',
    null,
    $page,
    $url
);
if ($entrytotal === 0) {
    echo $OUTPUT->notification(get_string('nostages', 'mod_stage'), 'info');
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'confirm']);
echo stage_render_deletion_table($entries, $allthemes, true);
echo $pagingbarhtml;
echo html_writer::div(
    html_writer::tag(
        'button',
        get_string('deleteentriesselected', 'mod_stage'),
        ['type' => 'submit', 'name' => 'scope', 'value' => 'selected', 'class' => 'btn btn-outline-danger mr-2']
    )
    . html_writer::tag(
        'button',
        get_string('deleteentriesallfiltered', 'mod_stage', $entrytotal),
        ['type' => 'submit', 'name' => 'scope', 'value' => 'all', 'class' => 'btn btn-outline-danger']
    ),
    'my-3'
);
echo html_writer::end_tag('form');
echo $OUTPUT->footer();
