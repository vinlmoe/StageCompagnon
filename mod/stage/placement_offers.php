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
 * Organismes proposés d'une thématique (DEVE) : liste des organismes avec leurs places et le nombre
 * de vœux reçus, ajout à la main, import d'un classeur Excel, reprise des organismes de stages
 * précédents, et accès aux vœux et à l'appariement.
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
$offerid = optional_param('offerid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:managethemes', $context);

$theme = $DB->get_record('stage_theme', ['id' => $themeid, 'stageid' => $stage->id], '*', MUST_EXIST);

$params = ['id' => $cm->id, 'themeid' => $theme->id];
$baseurl = new moodle_url('/mod/stage/placement_offers.php', $params);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('placementoffers', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if ($action === 'delete' && $offerid && data_submitted()) {
    require_sesskey();
    if (stage_placement_delete_offer($theme->id, $offerid)) {
        redirect($baseurl, get_string('placementofferdeleted', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    redirect($baseurl, get_string('placementofferinuse', 'mod_stage'), null, \core\output\notification::NOTIFY_ERROR);
}

$offers = stage_placement_get_offers($theme->id);
$assignments = stage_placement_get_assignments($theme->id);
$wishes = stage_placement_get_all_wishes($theme->id);

// Vœux reçus par organisme : tous rangs confondus, et en premier vœu.
$wishcounts = [];
$firstcounts = [];
foreach ($wishes as $userwishes) {
    foreach ($userwishes as $index => $wishofferid) {
        $wishcounts[$wishofferid] = ($wishcounts[$wishofferid] ?? 0) + 1;
        if ($index === 0) {
            $firstcounts[$wishofferid] = ($firstcounts[$wishofferid] ?? 0) + 1;
        }
    }
}
$assignedcounts = [];
foreach ($assignments as $assignment) {
    $assignedcounts[$assignment->offerid] = ($assignedcounts[$assignment->offerid] ?? 0) + 1;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('placementoffers', 'mod_stage') . ' : ' . format_string($theme->name));

if (empty($theme->placementenabled)) {
    echo $OUTPUT->notification(get_string('placementdisabledwarning', 'mod_stage'), \core\output\notification::NOTIFY_WARNING);
}

echo html_writer::div(stage_render_actions([
    get_string('backtotheme', 'mod_stage') => new moodle_url('/mod/stage/theme_edit.php', $params, 'id_placementhdr'),
    get_string('placementaddoffer', 'mod_stage') => new moodle_url('/mod/stage/placement_offer.php', $params),
    get_string('placementimport', 'mod_stage') => new moodle_url('/mod/stage/placement_import.php', $params),
    get_string('placementpick', 'mod_stage') => new moodle_url('/mod/stage/placement_pick.php', $params),
    get_string('placementmatching', 'mod_stage') => new moodle_url('/mod/stage/placement_matching.php', $params),
], 'btn btn-secondary mr-1 mb-1'), 'mb-3');

$places = array_sum(array_map(fn($offer) => (int) $offer->places, $offers));
echo html_writer::tag('p', get_string('placementofferssummary', 'mod_stage', (object) [
    'offers' => count($offers),
    'places' => $places,
    'students' => count($wishes),
    'choices' => stage_placement_required_wishes($theme, count($offers)),
]));

if (empty($offers)) {
    echo $OUTPUT->notification(get_string('placementnooffers', 'mod_stage'), 'info');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable stage-placement-offers';
    $table->head = [
        get_string('structure', 'mod_stage'),
        get_string('placementofferplace', 'mod_stage'),
        get_string('placementcolsiret', 'mod_stage'),
        get_string('placementplaces', 'mod_stage'),
        get_string('placementwishcount', 'mod_stage'),
        get_string('placementfirstwishcount', 'mod_stage'),
        get_string('placementassignedcount', 'mod_stage'),
        get_string('actions', 'mod_stage'),
    ];
    foreach ($offers as $offer) {
        $offerparams = $params + ['offerid' => $offer->id];
        $name = html_writer::tag('strong', format_string($offer->name));
        if (trim((string) $offer->description) !== '') {
            $name .= html_writer::div(format_text($offer->description, FORMAT_PLAIN), 'small text-muted');
        }
        $actions = html_writer::link(
            new moodle_url('/mod/stage/placement_offer.php', $offerparams),
            get_string('edit'),
            ['class' => 'btn btn-sm btn-secondary mr-1 mb-1']
        );
        $actions .= stage_post_button(
            new moodle_url($baseurl, ['action' => 'delete', 'offerid' => $offer->id]),
            get_string('delete'),
            'btn btn-sm btn-outline-danger mr-1 mb-1',
            get_string('placementofferdeleteconfirm', 'mod_stage', stage_plain_string($offer->name))
        );
        $table->data[] = [
            $name,
            s(stage_placement_offer_place($offer)),
            s((string) $offer->siret),
            (int) $offer->places,
            $wishcounts[$offer->id] ?? 0,
            $firstcounts[$offer->id] ?? 0,
            $assignedcounts[$offer->id] ?? 0,
            $actions,
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
