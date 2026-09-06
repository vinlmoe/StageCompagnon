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
 * Vue principale de l'activité mod_stage : tableau de bord de l'étudiant connecté. La DEVE et
 * les enseignants référents sont redirigés directement vers le tableau de pilotage
 * (dashboard.php), qui est leur page d'atterrissage habituelle ; ils n'ont pas besoin de la
 * section "Mes stages", réservée aux étudiants.
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
require_capability('mod/stage:view', $context);

// L'activité déclare FEATURE_COMPLETION_TRACKS_VIEWS : la consultation doit être enregistrée ici,
// avant la redirection ci-dessous, sans quoi l'achèvement « consulter l'activité » ne serait
// jamais atteint pour les rôles qui n'affichent jamais cette page.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// La DEVE et les enseignants référents ont pour page d'atterrissage le tableau de pilotage,
// plutôt que cette page (tableau de bord de l'étudiant, sans intérêt pour eux).
if (has_capability('mod/stage:viewall', $context) || has_capability('mod/stage:evaluateteacher', $context)) {
    redirect(new moodle_url('/mod/stage/dashboard.php', ['id' => $cm->id]));
}

$PAGE->set_url('/mod/stage/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($stage->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($stage->name));

if ($stage->intro) {
    echo $OUTPUT->box(format_module_intro('stage', $stage, $cm->id), 'generalbox mod_introbox', 'stageintro');
}

echo stage_render_navlinks($cm, $context);

// Section "Mes stages" : réservée aux étudiants. La DEVE est normalement déjà redirigée
// ci-dessus, la condition la couvre aussi pour un rôle sans la capacité viewall.
if (has_capability('mod/stage:submit', $context) && !has_capability('mod/stage:registerstages', $context)) {
    echo $OUTPUT->heading(get_string('mystages', 'mod_stage'), 3);
    echo $OUTPUT->notification(get_string('registeredbydeve', 'mod_stage'), 'info');

    echo html_writer::link(new moodle_url('/mod/stage/student_register.php', ['id' => $cm->id]),
        get_string('registerstageandconvention', 'mod_stage'), ['class' => 'btn btn-primary d-block mb-3', 'style' => 'width:fit-content']);

    stage_print_student_dashboard($stage, $USER->id, $cm, true, false);
}

echo $OUTPUT->footer();
