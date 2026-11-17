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
 * Organismes proposés d'une thématique, côté étudiant : la liste des organismes et de leurs
 * places, le classement de ses vœux tant qu'ils sont ouverts, puis le résultat de l'appariement et
 * l'accès à la demande de convention préremplie avec les coordonnées de l'organisme attribué.
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

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:submit', $context);

$theme = $DB->get_record('stage_theme', ['id' => $themeid, 'stageid' => $stage->id], '*', MUST_EXIST);
$viewurl = new moodle_url('/mod/stage/view.php', ['id' => $cm->id]);
if (empty($theme->placementenabled) || empty($theme->visible)) {
    redirect($viewurl, get_string('placementnotavailable', 'mod_stage'), null, \core\output\notification::NOTIFY_INFO);
}

$baseurl = new moodle_url('/mod/stage/placement.php', ['id' => $cm->id, 'themeid' => $theme->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('placementoffers', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if (data_submitted() && confirm_sesskey()) {
    $withdraw = optional_param('withdraw', 0, PARAM_BOOL);
    // Les vœux sont lus dans l'ordre des rangs ; un rang laissé vide est signalé comme incomplet.
    $submitted = optional_param_array('wish', [], PARAM_INT);
    ksort($submitted);
    $choices = array_values(array_filter($submitted));
    if (!$withdraw && !$choices) {
        redirect($baseurl, get_string('placementwishesincomplete', 'mod_stage'), null, \core\output\notification::NOTIFY_ERROR);
    }
    $error = stage_placement_save_wishes($theme, $USER->id, $withdraw ? [] : $choices);
    if ($error !== null) {
        redirect($baseurl, get_string($error, 'mod_stage'), null, \core\output\notification::NOTIFY_ERROR);
    }
    redirect(
        $baseurl,
        get_string($withdraw ? 'placementwisheswithdrawn' : 'placementwishesrecorded', 'mod_stage'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$offers = stage_placement_get_offers($theme->id);
$wishes = stage_placement_get_wishes($theme->id, $USER->id);
$assignment = stage_placement_get_assignment($theme->id, $USER->id);
$state = stage_placement_state($theme);
$required = stage_placement_required_wishes($theme, count($offers));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('placementoffers', 'mod_stage') . ' : ' . format_string($theme->name));
echo html_writer::link($viewurl, get_string('back'));

// Où en est l'étudiant.
if ($assignment && isset($offers[$assignment->offerid])) {
    $message = get_string('placementassignedto', 'mod_stage', (object) [
        'name' => format_string($offers[$assignment->offerid]->name),
        'rank' => stage_placement_rank_label($assignment->wishrank),
    ]);
    if (stage_placement_assignment_usable($assignment)) {
        $message .= ' ' . html_writer::link(
            new moodle_url('/mod/stage/student_register.php', ['id' => $cm->id, 'placement' => $theme->id]),
            get_string('placementcompleterequest', 'mod_stage'),
            ['class' => 'btn btn-primary ml-2']
        );
    } else {
        $message .= ' ' . get_string('placementrequestdone', 'mod_stage');
    }
    echo $OUTPUT->notification($message, \core\output\notification::NOTIFY_SUCCESS, false);
} else if ($state === STAGE_PLACEMENT_OPEN) {
    echo $OUTPUT->notification(
        (!empty($theme->placementdeadline)
            ? get_string('placementopenuntil', 'mod_stage', userdate($theme->placementdeadline))
            : get_string('placementopen', 'mod_stage'))
        . ' ' . get_string('placementmatchingrule', 'mod_stage'),
        \core\output\notification::NOTIFY_INFO,
        false
    );
} else if ($state === STAGE_PLACEMENT_CLOSED) {
    echo $OUTPUT->notification(get_string('placementclosedwaiting', 'mod_stage'), \core\output\notification::NOTIFY_INFO, false);
} else {
    echo $OUTPUT->notification(
        get_string(empty($theme->placementofflist) ? 'placementresultnone' : 'placementresultnoneofflist', 'mod_stage'),
        \core\output\notification::NOTIFY_WARNING,
        false
    );
}
if (!empty($theme->placementofflist) && !$assignment) {
    echo html_writer::tag('p', get_string('placementofflistinfo', 'mod_stage'));
}

// Classement des vœux : un menu par rang, tant que les vœux sont ouverts.
if (!$assignment && $state === STAGE_PLACEMENT_OPEN && $required > 0) {
    echo $OUTPUT->heading(get_string('placementmywishes', 'mod_stage'), 3);
    echo html_writer::tag('p', get_string('placementwishesexplain', 'mod_stage', $required));
    $options = [];
    foreach ($offers as $offer) {
        $place = stage_placement_offer_place($offer);
        $options[$offer->id] = format_string($offer->name) . ($place !== '' ? ' — ' . s($place) : '');
    }
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl, 'class' => 'mb-4']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    for ($rank = 1; $rank <= $required; $rank++) {
        $fieldid = 'id_wish_' . $rank;
        echo html_writer::start_div('form-group row');
        echo html_writer::label(get_string('placementwishrank', 'mod_stage', $rank), $fieldid, false, [
            'class' => 'col-md-2 col-form-label',
        ]);
        echo html_writer::div(html_writer::select(
            $options,
            'wish[' . $rank . ']',
            $wishes[$rank - 1] ?? 0,
            [0 => get_string('choosedots')],
            ['id' => $fieldid, 'class' => 'custom-select']
        ), 'col-md-8');
        echo html_writer::end_div();
    }
    echo html_writer::tag('button', get_string('placementsavewishes', 'mod_stage'), [
        'type' => 'submit', 'class' => 'btn btn-primary mr-2',
    ]);
    if ($wishes) {
        echo html_writer::tag('button', get_string('placementwithdrawwishes', 'mod_stage'), [
            'type' => 'submit', 'name' => 'withdraw', 'value' => 1, 'class' => 'btn btn-outline-danger',
            'onclick' => stage_confirm_onclick(get_string('placementwithdrawconfirm', 'mod_stage')),
        ]);
    }
    echo html_writer::end_tag('form');
} else if ($wishes) {
    echo $OUTPUT->heading(get_string('placementmywishes', 'mod_stage'), 3);
    $items = [];
    foreach ($wishes as $offerid) {
        $items[] = html_writer::tag('li', isset($offers[$offerid]) ? format_string($offers[$offerid]->name) : '-');
    }
    echo html_writer::tag('ol', implode('', $items));
}

// Organismes proposés.
echo $OUTPUT->heading(get_string('placementofferslist', 'mod_stage'), 3);
if (empty($offers)) {
    echo $OUTPUT->notification(get_string('placementnooffersyet', 'mod_stage'), 'info');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable stage-placement-offers';
    $table->head = [
        get_string('structure', 'mod_stage'),
        get_string('placementofferplace', 'mod_stage'),
        get_string('placementplaces', 'mod_stage'),
        get_string('placementofferdescription', 'mod_stage'),
    ];
    foreach ($offers as $offer) {
        $table->data[] = [
            html_writer::tag('strong', format_string($offer->name)),
            s(stage_placement_offer_place($offer)),
            (int) $offer->places,
            format_text((string) $offer->description, FORMAT_PLAIN),
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
