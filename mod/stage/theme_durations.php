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
 * Gestion, par la DEVE, de la durée de stage obligatoire requise pour une thématique, déclinée par
 * année d'étude (une thématique peut couvrir plusieurs années, voir stage_theme.minstudyyear /
 * maxstudyyear, avec une durée requise différente pour chacune).
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
$theme = $DB->get_record('stage_theme', ['id' => $themeid, 'stageid' => $stage->id], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:managethemes', $context);

$baseurl = new moodle_url('/mod/stage/theme_durations.php', ['id' => $cm->id, 'themeid' => $theme->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('managethemedurations', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Années sur lesquelles la thématique s'applique : sa plage [min, max], ou uniquement l'année 0
// (non spécifiée) si elle ne précise pas d'année.
$minyear = (int) $theme->minstudyyear;
$maxyear = (int) $theme->maxstudyyear;
if (empty($minyear) && empty($maxyear)) {
    $years = [0];
} else {
    $minyear = $minyear ?: $maxyear;
    $maxyear = $maxyear ?: $minyear;
    $years = range(min($minyear, $maxyear), max($minyear, $maxyear));
}

if (data_submitted() && confirm_sesskey()) {
    foreach ($years as $year) {
        $duration = optional_param('duration_' . $year, 0, PARAM_INT);
        stage_set_theme_duration($theme->id, $year, $duration);
    }
    redirect($baseurl, get_string('themedurationssaved', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$durations = stage_get_theme_durations($theme->id);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managethemedurations', 'mod_stage') . ' : ' . format_string($theme->name));
echo html_writer::link(new moodle_url('/mod/stage/themes.php', ['id' => $cm->id]), get_string('back'));

// Une durée unique définie sur la thématique (voir sa fiche) prime toujours sur les durées par
// année ci-dessous : autant prévenir la DEVE plutôt que de la laisser modifier des valeurs
// ignorées (l'un ou l'autre, pas les deux, voir stage_get_theme_duration()).
if (!empty($theme->requiredduration)) {
    echo $OUTPUT->notification(get_string('durationflatignored', 'mod_stage', $theme->requiredduration), 'warning');
}

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

$table = new html_table();
$table->head = [get_string('studyyear', 'mod_stage'), get_string('requiredduration', 'mod_stage')];
foreach ($years as $year) {
    $input = html_writer::empty_tag('input', [
        'type' => 'number',
        'min' => 0,
        'name' => 'duration_' . $year,
        'value' => $durations[$year] ?? 0,
        'class' => 'form-control',
        'style' => 'width:8em',
    ]);
    $table->data[] = [stage_studyyear_label($year), $input];
}
echo html_writer::table($table);

echo html_writer::empty_tag('input', [
    'type' => 'submit', 'value' => get_string('savechanges'), 'class' => 'btn btn-primary mt-2',
]);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
