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
 * Gestion, par la DEVE, de la durée totale de stage obligatoire requise pour chaque année
 * d'étude, toutes thématiques confondues (les stages complémentaires ne comptent pas dans ce
 * bilan, voir stage_get_student_year_progress()), ainsi que de l'obligation de mobilité
 * internationale de ce stage (nombre de jours à l'étranger requis, année avant laquelle elle doit
 * être satisfaite, et consigne affichée aux étudiants) : elle n'est pas liée à une thématique,
 * tous les stages comptent ici, obligatoires ou complémentaires (voir
 * stage_get_student_abroad_progress()).
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:managethemes', $context);

$baseurl = new moodle_url('/mod/stage/year_requirements.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('manageyearrequirements', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$years = range(1, 6);

if (data_submitted() && confirm_sesskey()) {
    foreach ($years as $year) {
        $duration = optional_param('duration_' . $year, 0, PARAM_INT);
        stage_set_year_requirement($stage->id, $year, $duration);
    }

    $stage->requiredabroaddays = optional_param('requiredabroaddays', 0, PARAM_INT);
    $stage->abroadbeforeyear = optional_param('abroadbeforeyear', 0, PARAM_INT);
    $stage->abroadrule = optional_param('abroadrule', '', PARAM_TEXT);
    $stage->timemodified = time();
    $DB->update_record('stage', $stage);

    redirect($baseurl, get_string('yearrequirementssaved', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$requirements = stage_get_year_requirements($stage->id);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manageyearrequirements', 'mod_stage'));
echo html_writer::link(new moodle_url('/mod/stage/administration.php', ['id' => $cm->id]), get_string('back'));
echo $OUTPUT->notification(get_string('yearrequirements_help', 'mod_stage'), 'info');

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

$table = new html_table();
$table->head = [get_string('studyyear', 'mod_stage'), get_string('totalrequiredduration', 'mod_stage')];
foreach ($years as $year) {
    $input = html_writer::empty_tag('input', [
        'type' => 'number',
        'min' => 0,
        'name' => 'duration_' . $year,
        'value' => $requirements[$year] ?? 0,
        'class' => 'form-control',
        'style' => 'width:8em',
    ]);
    $table->data[] = [stage_studyyear_label($year), $input];
}
echo html_writer::table($table);

// Obligation de mobilité internationale : pas liée à une thématique, commune à tous les stages.
echo $OUTPUT->heading(get_string('abroadtotal', 'mod_stage'), 4);

echo html_writer::tag('label', get_string('requiredabroaddays', 'mod_stage'), ['for' => 'requiredabroaddays']);
echo html_writer::empty_tag('input', [
    'type' => 'number', 'min' => 0, 'name' => 'requiredabroaddays', 'id' => 'requiredabroaddays',
    'value' => $stage->requiredabroaddays, 'class' => 'form-control', 'style' => 'width:8em',
]);
echo $OUTPUT->render(new \help_icon('requiredabroaddays', 'mod_stage'));

echo html_writer::tag('label', get_string('abroadbeforeyear', 'mod_stage'), ['for' => 'abroadbeforeyear']);
echo html_writer::select(stage_studyyear_options(), 'abroadbeforeyear', $stage->abroadbeforeyear, false,
    ['id' => 'abroadbeforeyear', 'class' => 'form-control', 'style' => 'width:auto']);

echo html_writer::tag('label', get_string('abroadrule', 'mod_stage'), ['for' => 'abroadrule']);
echo html_writer::tag('textarea', s($stage->abroadrule),
    ['name' => 'abroadrule', 'id' => 'abroadrule', 'rows' => 3, 'class' => 'form-control']);
echo $OUTPUT->render(new \help_icon('abroadrule', 'mod_stage'));

echo html_writer::empty_tag('input', [
    'type' => 'submit', 'value' => get_string('savechanges'), 'class' => 'btn btn-primary mt-2',
]);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
