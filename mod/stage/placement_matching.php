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
 * Vœux et appariement d'une thématique à organismes proposés (DEVE).
 *
 * La page montre où en est la campagne (vœux ouverts, clos, appariement fait), les vœux de chaque
 * étudiant et l'organisme auquel il est affecté. La DEVE peut lancer l'appariement sans attendre
 * la tâche planifiée, le rejouer en tour complémentaire pour les étudiants restés sans affectation,
 * affecter ou réaffecter un étudiant à la main, et exporter le tableau.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$id = required_param('id', PARAM_INT);
$themeid = required_param('themeid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$dataformat = optional_param('dataformat', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:managethemes', $context);

$theme = $DB->get_record('stage_theme', ['id' => $themeid, 'stageid' => $stage->id], '*', MUST_EXIST);

$params = ['id' => $cm->id, 'themeid' => $theme->id];
$baseurl = new moodle_url('/mod/stage/placement_matching.php', $params);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('placementmatching', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$students = stage_get_enrolled_students($context);

if ($dataformat !== '') {
    $export = stage_placement_get_export($theme, $students);
    // Formats HTML et PDF : les cellules y sont écrites sans échappement par Moodle.
    $escape = \core\dataformat::get_format_instance($dataformat)->supports_html() ? 's' : fn($value) => $value;
    \core\dataformat::download_data(
        clean_filename(get_string('placementexportfilename', 'mod_stage', stage_plain_string($theme->name))),
        $dataformat,
        array_map($escape, $export['columns']),
        array_map(fn($row) => array_map($escape, $row), $export['rows'])
    );
    exit;
}

if ($action === 'match' && data_submitted()) {
    require_sesskey();
    $result = stage_placement_run_matching($theme);
    if (optional_param('notify', 0, PARAM_BOOL)) {
        stage_placement_notify_results($stage, $cm, $theme, $result);
    }
    redirect(
        $baseurl,
        get_string('placementmatchingdone', 'mod_stage', (object) [
            'assigned' => count($result->assigned), 'unassigned' => count($result->unassigned),
        ]),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

if ($action === 'assign' && data_submitted()) {
    require_sesskey();
    $submitted = optional_param_array('offer', [], PARAM_INT);
    $current = stage_placement_get_assignments($theme->id);
    $changed = 0;
    $errors = [];
    // Les retraits d'abord : ils libèrent les places que les affectations de la même saisie
    // peuvent reprendre (échange de deux étudiants, par exemple).
    asort($submitted);
    foreach ($submitted as $userid => $offerid) {
        if (!isset($students[$userid]) || (int) ($current[$userid]->offerid ?? 0) === (int) $offerid) {
            continue;
        }
        $error = stage_placement_set_assignment($theme, $userid, $offerid, $USER->id);
        if ($error === null) {
            $changed++;
        } else {
            $errors[] = fullname($students[$userid]) . ' : ' . get_string($error, 'mod_stage');
        }
    }
    if ($errors) {
        redirect($baseurl, implode(' ; ', $errors), null, \core\output\notification::NOTIFY_ERROR);
    }
    redirect(
        $baseurl,
        get_string('placementassignmentssaved', 'mod_stage', $changed),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$offers = stage_placement_get_offers($theme->id);
$wishes = stage_placement_get_all_wishes($theme->id);
$assignments = stage_placement_get_assignments($theme->id);
$remaining = stage_placement_remaining_places($offers, $assignments);
$state = stage_placement_state($theme);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('placementmatching', 'mod_stage') . ' : ' . format_string($theme->name));
echo html_writer::div(stage_render_actions([
    get_string('placementoffers', 'mod_stage') => new moodle_url('/mod/stage/placement_offers.php', $params),
    get_string('backtotheme', 'mod_stage') => new moodle_url('/mod/stage/theme_edit.php', $params, 'id_placementhdr'),
], 'btn btn-secondary mr-1 mb-1'), 'mb-3');

if (empty($theme->placementenabled)) {
    echo $OUTPUT->notification(get_string('placementdisabledwarning', 'mod_stage'), \core\output\notification::NOTIFY_WARNING);
}

// État de la campagne.
$statelines = [];
if ($state === STAGE_PLACEMENT_MATCHED) {
    $statelines[] = get_string('placementstatematched', 'mod_stage', userdate($theme->placementmatchedtime));
} else if ($state === STAGE_PLACEMENT_CLOSED) {
    $statelines[] = get_string('placementstateclosed', 'mod_stage', userdate($theme->placementdeadline));
} else if (!empty($theme->placementdeadline)) {
    $statelines[] = get_string('placementstateopenuntil', 'mod_stage', userdate($theme->placementdeadline));
} else {
    $statelines[] = get_string('placementstateopen', 'mod_stage');
}
$withwishes = count(array_intersect_key($wishes, $students));
$statelines[] = get_string('placementmatchingsummary', 'mod_stage', (object) [
    'students' => count($students),
    'withwishes' => $withwishes,
    'assigned' => count(array_intersect_key($assignments, $students)),
    'places' => array_sum(array_map(fn($offer) => (int) $offer->places, $offers)),
    'free' => array_sum($remaining),
]);
echo $OUTPUT->box(implode(html_writer::empty_tag('br'), $statelines), 'generalbox mb-3');

// Lancement de l'appariement : immédiat, sans attendre la date de clôture ni la tâche planifiée.
$waiting = count(array_diff_key(array_intersect_key($wishes, $students), $assignments));
if ($waiting > 0 && !empty($offers)) {
    $button = html_writer::tag('label', html_writer::checkbox('notify', 1, true) . ' '
        . get_string('placementmatchnotify', 'mod_stage'), ['class' => 'mr-3'])
        . html_writer::tag(
            'button',
            get_string($state === STAGE_PLACEMENT_MATCHED ? 'placementmatchagain' : 'placementmatchnow', 'mod_stage'),
            ['type' => 'submit', 'class' => 'btn btn-primary']
        );
    echo html_writer::div(
        html_writer::tag('p', get_string('placementmatchexplain', 'mod_stage', $waiting))
        . stage_post_form(
            new moodle_url($baseurl, ['action' => 'match']),
            null,
            get_string('placementmatchconfirm', 'mod_stage'),
            $button
        ),
        'border rounded p-3 mb-3'
    );
}

if (empty($students)) {
    echo $OUTPUT->notification(get_string('nostudents', 'mod_stage'), 'info');
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->download_dataformat_selector(
    get_string('placementexport', 'mod_stage'),
    $baseurl->out_omit_querystring(),
    'dataformat',
    $params
);

$offeroptions = [];
foreach ($offers as $offer) {
    $offeroptions[$offer->id] = format_string($offer->name) . ' ('
        . get_string('placementfreeplaces', 'mod_stage', (object) ['free' => $remaining[$offer->id], 'places' => $offer->places])
        . ')';
}

echo html_writer::start_tag('form', ['method' => 'post', 'action' => new moodle_url($baseurl, ['action' => 'assign'])]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

$table = new html_table();
$table->attributes['class'] = 'generaltable stage-placement-matching';
$table->head = [
    get_string('student', 'mod_stage'),
    get_string('placementwishes', 'mod_stage'),
    get_string('placementassignment', 'mod_stage'),
    get_string('placementsatisfiedwish', 'mod_stage'),
    get_string('conventions', 'mod_stage'),
];
foreach ($students as $student) {
    $wishitems = [];
    foreach ($wishes[$student->id] ?? [] as $offerid) {
        $wishitems[] = html_writer::tag('li', isset($offers[$offerid]) ? format_string($offers[$offerid]->name) : '-');
    }
    $assignment = $assignments[$student->id] ?? null;
    $entrycell = '-';
    if ($assignment && !empty($assignment->entryid)) {
        $entrycell = html_writer::link(
            new moodle_url('/mod/stage/entrydetail.php', ['id' => $cm->id, 'entryid' => $assignment->entryid]),
            get_string('placementrequestdone', 'mod_stage')
        );
    }
    $table->data[] = [
        fullname($student),
        $wishitems ? html_writer::tag('ol', implode('', $wishitems), ['class' => 'mb-0 pl-3'])
            : html_writer::span(get_string('placementnowishesyet', 'mod_stage'), 'text-muted'),
        html_writer::select(
            $offeroptions,
            'offer[' . $student->id . ']',
            $assignment ? $assignment->offerid : 0,
            [0 => get_string('placementnoassignment', 'mod_stage')],
            [
                'class' => 'custom-select',
                'aria-label' => get_string('placementassignmentof', 'mod_stage', fullname($student)),
            ]
        ),
        $assignment ? stage_placement_rank_label($assignment->wishrank) : '-',
        $entrycell,
    ];
}
echo html_writer::table($table);
echo html_writer::tag('button', get_string('placementsaveassignments', 'mod_stage'), [
    'type' => 'submit', 'class' => 'btn btn-primary',
]);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
