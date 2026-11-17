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
 * Reprise d'organismes de stages précédents comme organismes proposés d'une thématique (DEVE).
 *
 * La DEVE choisit l'activité source (la promotion en cours ou une autre promotion qu'elle gère),
 * éventuellement une de ses thématiques, puis coche les organismes à proposer. Chaque organisme
 * est repris avec les coordonnées de son stage le plus récent ; ceux déjà proposés pour la
 * thématique ne sont pas ajoutés une seconde fois.
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
$sourceid = optional_param('source', 0, PARAM_INT);
$sourcethemeid = optional_param('sourcetheme', 0, PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:managethemes', $context);

$theme = $DB->get_record('stage_theme', ['id' => $themeid, 'stageid' => $stage->id], '*', MUST_EXIST);

// Activités sources : celle-ci, et celles où l'utilisateur gère aussi les thématiques. Une valeur
// hors de cette liste retombe sur l'activité courante.
$sources = [$stage->id => get_string('placementpickcurrent', 'mod_stage')] + stage_get_importable_stage_instances($stage->id);
if (!isset($sources[$sourceid])) {
    $sourceid = (int) $stage->id;
}
$sourcethemes = [];
foreach (stage_get_themes($sourceid) as $sourcetheme) {
    $sourcethemes[$sourcetheme->id] = format_string($sourcetheme->name);
}
if (!isset($sourcethemes[$sourcethemeid])) {
    $sourcethemeid = 0;
}

$params = ['id' => $cm->id, 'themeid' => $theme->id];
$listurl = new moodle_url('/mod/stage/placement_offers.php', $params);
$baseurl = new moodle_url('/mod/stage/placement_pick.php', $params + ['source' => $sourceid, 'sourcetheme' => $sourcethemeid]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('placementpick', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$organisations = stage_placement_get_past_organisations($sourceid, $sourcethemeid);

if (data_submitted() && confirm_sesskey()) {
    $selected = optional_param_array('pick', [], PARAM_ALPHANUM);
    $places = optional_param_array('places', [], PARAM_INT);
    $offers = [];
    foreach ($selected as $key) {
        if (isset($organisations[$key])) {
            $offer = clone $organisations[$key];
            $offer->places = max(1, (int) ($places[$key] ?? 1));
            $offers[] = $offer;
        }
    }
    $added = stage_placement_add_offers($theme->id, $offers);
    redirect(
        $listurl,
        get_string('placementpickresult', 'mod_stage', (object) ['added' => $added, 'skipped' => count($offers) - $added]),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Organismes déjà proposés : ils restent listés, sans case à cocher.
$known = [];
foreach (stage_placement_get_offers($theme->id) as $offer) {
    $known[md5(stage_placement_offer_key($offer->name, $offer->siret))] = true;
    $known[md5(stage_placement_offer_key($offer->name))] = true;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('placementpick', 'mod_stage') . ' : ' . format_string($theme->name));
echo html_writer::link($listurl, get_string('back'));
echo $OUTPUT->box(get_string('placementpick_help', 'mod_stage'), 'generalbox my-3');

// Choix de la source : formulaire GET, pour que la sélection reste dans l'adresse de la page.
echo html_writer::start_tag('form', [
    'method' => 'get', 'action' => new moodle_url('/mod/stage/placement_pick.php'), 'class' => 'form-inline mb-3',
]);
foreach ($params as $name => $value) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
}
echo html_writer::label(get_string('placementpicksource', 'mod_stage'), 'placementpicksource', false, ['class' => 'mr-2']);
echo html_writer::select($sources, 'source', $sourceid, false, ['id' => 'placementpicksource', 'class' => 'custom-select mr-3']);
echo html_writer::label(get_string('theme', 'mod_stage'), 'placementpicktheme', false, ['class' => 'mr-2']);
echo html_writer::select(
    $sourcethemes,
    'sourcetheme',
    $sourcethemeid,
    [0 => get_string('placementpickallthemes', 'mod_stage')],
    ['id' => 'placementpicktheme', 'class' => 'custom-select mr-3']
);
echo html_writer::tag('button', get_string('placementpickshow', 'mod_stage'), ['type' => 'submit', 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

if (empty($organisations)) {
    echo $OUTPUT->notification(get_string('placementpicknone', 'mod_stage'), 'info');
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

$table = new html_table();
$table->attributes['class'] = 'generaltable stage-placement-pick';
$table->head = [
    get_string('placementpickselect', 'mod_stage'),
    get_string('structure', 'mod_stage'),
    get_string('placementofferplace', 'mod_stage'),
    get_string('placementcolsiret', 'mod_stage'),
    get_string('placementpickcount', 'mod_stage'),
    get_string('placementpicklast', 'mod_stage'),
    get_string('placementplaces', 'mod_stage'),
];
foreach ($organisations as $key => $organisation) {
    $alreadyoffered = isset($known[$key]) || isset($known[md5(stage_placement_offer_key($organisation->name))]);
    $label = get_string('placementpickselectname', 'mod_stage', stage_plain_string($organisation->name));
    $table->data[] = [
        $alreadyoffered
            ? html_writer::span(get_string('placementpickalready', 'mod_stage'), 'badge badge-secondary')
            : html_writer::checkbox('pick[]', $key, false, '', ['aria-label' => $label, 'id' => 'pick_' . $key]),
        html_writer::label(format_string($organisation->name), $alreadyoffered ? null : 'pick_' . $key),
        s(stage_placement_offer_place($organisation)),
        s($organisation->siret),
        $organisation->count,
        $organisation->lastdate ? userdate($organisation->lastdate, get_string('strftimedatefullshort', 'langconfig')) : '-',
        $alreadyoffered ? '-' : html_writer::empty_tag('input', [
            'type' => 'number', 'name' => 'places[' . $key . ']', 'value' => 1, 'min' => 1, 'class' => 'form-control',
            'style' => 'width:5em', 'aria-label' => get_string('placementplaces', 'mod_stage'),
        ]),
    ];
}
echo html_writer::table($table);
echo html_writer::tag('button', get_string('placementpickadd', 'mod_stage'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
