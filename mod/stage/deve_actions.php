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
 * Actions DEVE en attente : tous les stages de la promotion qui attendent une action de la DEVE
 * (convention à générer ou à marquer signée, invitation du maître de stage en échec, stage à
 * valider), réunis sur une seule page avec, pour chacun, le bouton qui mène à l'action.
 *
 * Page réservée à la DEVE (capacité de validation DEVE) ; les actions sur les conventions ne sont
 * proposées qu'avec le droit d'enregistrement des stages, exigé par les pages qu'elles ouvrent.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$id = required_param('id', PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$filterthemeid = optional_param('themeid', 0, PARAM_INT);
$filteraction = optional_param('action', '', PARAM_ALPHA);
$tsort = optional_param('tsort', 'modified', PARAM_ALPHA);
$tdir = optional_param('tdir', 'ASC', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:validatedeve', $context);
$canregister = has_capability('mod/stage:registerstages', $context);

$actiontypes = stage_deve_action_types();
if (!$canregister) {
    unset($actiontypes['conventionreview'], $actiontypes['conventionsign']);
}
if (!isset($actiontypes[$filteraction])) {
    $filteraction = '';
}

if ($canregister) {
    // Comme conventions.php : une demande qui n'attendait plus qu'un responsable de thématique
    // désinscrit passe à la DEVE, et doit donc figurer ici.
    stage_release_pending_conventions($stage);
}

$baseurl = new moodle_url('/mod/stage/deve_actions.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('deveactions', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('deveactions', 'mod_stage'));
echo stage_render_navlinks($cm, $context);
echo html_writer::div(get_string('deveactionsintro', 'mod_stage'), 'mb-3');

$listurl = new moodle_url($baseurl, [
    'search' => $search, 'themeid' => $filterthemeid, 'action' => $filteraction, 'tsort' => $tsort, 'tdir' => $tdir,
]);

$themes = stage_get_themes($stage->id);
$themeoptions = [0 => get_string('allthemes', 'mod_stage')];
foreach ($themes as $theme) {
    $themeoptions[$theme->id] = format_string($theme->name);
}
echo $OUTPUT->render(new \mod_stage\output\list_filters(
    $listurl,
    ['search', 'themeid', 'action', 'tsort', 'tdir'],
    $search,
    [
        'themeid' => ['options' => $themeoptions, 'selected' => $filterthemeid],
        'action' => ['options' => ['' => get_string('deveactionall', 'mod_stage')] + $actiontypes, 'selected' => $filteraction],
    ]
));

$entries = stage_get_deve_action_entries($stage, $search, $filterthemeid, $filteraction);
// Sans le droit d'enregistrement, les actions sur les conventions ne sont pas à la portée de
// l'utilisateur : elles sont retirées, et les stages qui n'attendaient qu'elles avec.
foreach ($entries as $entryid => $entry) {
    $entry->deveactions = array_values(array_filter($entry->deveactions, fn($key) => isset($actiontypes[$key])));
    if (empty($entry->deveactions)) {
        unset($entries[$entryid]);
    }
}

// Décompte par type d'action, avant pagination : la DEVE voit d'un coup d'œil ce qui l'attend.
$counts = array_fill_keys(array_keys($actiontypes), 0);
foreach ($entries as $entry) {
    foreach ($entry->deveactions as $key) {
        $counts[$key]++;
    }
}

if (empty($entries)) {
    echo $OUTPUT->notification(get_string('nodeveactions', 'mod_stage'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$summary = [];
foreach ($counts as $key => $count) {
    if ($count > 0) {
        $summary[] = html_writer::link(
            new moodle_url($listurl, ['action' => $key, 'page' => 0]),
            html_writer::span($actiontypes[$key] . ' : ' . $count, 'badge badge-warning mr-1')
        );
    }
}
echo html_writer::div(implode(' ', $summary), 'mb-3');

$students = stage_get_entry_users($entries);

// Tri en PHP : la liste est déjà filtrée en PHP (voir stage_get_deve_action_entries()), et ne
// compte que les stages en attente, quelques dizaines tout au plus.
$sortkeys = [
    'student' => fn($entry) => isset($students[$entry->userid])
        ? core_text::strtolower($students[$entry->userid]->lastname . ' ' . $students[$entry->userid]->firstname) : '',
    'theme' => fn($entry) => isset($themes[$entry->themeid]) ? core_text::strtolower($themes[$entry->themeid]->name) : '',
    'modified' => fn($entry) => (int) $entry->timemodified,
];
if (!isset($sortkeys[$tsort])) {
    $tsort = 'modified';
}
$tdir = strtoupper($tdir) === 'DESC' ? 'DESC' : 'ASC';
$sortkey = $sortkeys[$tsort];
uasort($entries, function ($a, $b) use ($sortkey, $tdir) {
    $cmp = $sortkey($a) <=> $sortkey($b) ?: (int) $a->id <=> (int) $b->id;
    return $tdir === 'DESC' ? -$cmp : $cmp;
});

$entrytotal = count($entries);
$page = max(0, min($page, (int) floor(($entrytotal - 1) / STAGE_LIST_PERPAGE)));
$pageentries = array_slice($entries, $page * STAGE_LIST_PERPAGE, STAGE_LIST_PERPAGE, true);
$rowreturnurl = (new moodle_url($listurl, ['page' => $page]))->out_as_local_url(false);

$table = new html_table();
$table->head = [
    stage_sort_header(get_string('student', 'mod_stage'), 'student', $listurl, $tsort, $tdir),
    stage_sort_header(get_string('theme', 'mod_stage'), 'theme', $listurl, $tsort, $tdir),
    get_string('deveactiondates', 'mod_stage'),
    get_string('status', 'mod_stage'),
    get_string('conventionstatus', 'mod_stage'),
    get_string('deveactionrequired', 'mod_stage'),
    stage_sort_header(get_string('lastmodified'), 'modified', $listurl, $tsort, $tdir),
];
foreach ($pageentries as $entry) {
    $student = $students[$entry->userid] ?? null;
    $studentcell = $student
        ? html_writer::link(
            new moodle_url('/mod/stage/dashboard.php', ['id' => $cm->id, 'studentid' => $student->id]),
            fullname($student)
        ) : '-';
    $themename = isset($themes[$entry->themeid]) ? format_string($themes[$entry->themeid]->name) : '-';

    $dates = [];
    foreach (['datestart', 'dateend'] as $field) {
        if (!empty($entry->$field)) {
            $dates[] = userdate($entry->$field, get_string('strftimedate'));
        }
    }

    $badge = html_writer::span(stage_status_label($entry->status), 'badge ' . stage_status_badgeclass($entry->status));
    $conventionbadge = html_writer::span(
        stage_convention_status_label($entry->conventionstatus),
        'badge ' . stage_convention_status_badgeclass($entry->conventionstatus)
    );

    // Un bouton par action attendue, vers la page qui la traite ; chacune y revient sur cette
    // liste telle qu'affichée (filtres, tri, page).
    $entryparams = ['id' => $cm->id, 'entryid' => $entry->id, 'returnurl' => $rowreturnurl];
    $targets = [
        'conventionreview' => [get_string('conventionreview', 'mod_stage'), '/mod/stage/convention_review.php'],
        'conventionsign' => [get_string('conventionmarksigned', 'mod_stage'), '/mod/stage/convention_sign.php'],
        'tutorrequestfailed' => [get_string('deveactionopen', 'mod_stage'), '/mod/stage/deve.php'],
        'validate' => [get_string('validate', 'mod_stage'), '/mod/stage/deve.php'],
    ];
    $labels = [];
    $links = [];
    foreach ($entry->deveactions as $key) {
        $labels[] = html_writer::div($actiontypes[$key], 'small');
        [$label, $path] = $targets[$key];
        $links[$label] = new moodle_url($path, $entryparams);
    }

    $table->data[] = [
        $studentcell,
        $themename,
        $dates ? implode(' – ', $dates) : '-',
        $badge,
        $conventionbadge,
        implode('', $labels) . stage_render_actions($links, 'btn btn-sm btn-primary mr-1 mb-1 mt-1'),
        userdate($entry->timemodified, get_string('strftimedatetimeshort')),
    ];
}
echo html_writer::table($table);
echo stage_paging_bar($entrytotal, $page, $listurl);

echo $OUTPUT->footer();
