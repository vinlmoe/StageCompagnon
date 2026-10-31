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
 * Validation par la DEVE de la mobilité d'études d'un étudiant (case à cocher et lieu), depuis
 * son volet du tableau de pilotage (voir stage_render_study_mobility_form()). Une mobilité
 * d'études validée satisfait l'obligation de mobilité internationale de l'activité.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$id = required_param('id', PARAM_INT);
$studentid = required_param('studentid', PARAM_INT);
$validated = optional_param('studymobility', 0, PARAM_BOOL);
$location = optional_param('location', '', PARAM_TEXT);
$returnurlparam = optional_param('returnurl', '', PARAM_LOCALURL);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($cm->course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:validatedeve', $context);
require_sesskey();

$student = $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST);
if (!is_enrolled($context, $student)) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('studymobility', 'mod_stage'));
}

$returnurl = $returnurlparam !== ''
    ? new moodle_url($returnurlparam)
    : new moodle_url('/mod/stage/dashboard.php', ['id' => $cm->id, 'studentid' => $student->id]);

if ($validated && trim($location) === '') {
    redirect(
        $returnurl,
        get_string('errorstudymobilitylocationrequired', 'mod_stage'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// Sous le verrou de l'activité, comme l'import des mobilités : la lecture de la mobilité existante
// puis son insertion ne doivent pas croiser un import qui valide le même étudiant.
$lock = stage_get_activity_lock($stage->id);
try {
    stage_set_student_study_mobility($stage->id, $student->id, $validated, $location, $USER->id);
} finally {
    $lock->release();
}
redirect(
    $returnurl,
    get_string($validated ? 'studymobilitysaved' : 'studymobilityremoved', 'mod_stage'),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
