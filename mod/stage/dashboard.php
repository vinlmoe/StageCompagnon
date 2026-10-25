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
 * Tableau de pilotage de la DEVE : avancement des validations pour l'ensemble des étudiants,
 * avec accès à la situation détaillée de chacun.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$id = required_param('id', PARAM_INT);
$studentid = optional_param('studentid', 0, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$tsort = optional_param('tsort', 'student', PARAM_ALPHA);
$tdir = optional_param('tdir', 'ASC', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

// Accessible à la DEVE (vision de tous les étudiants) et aux enseignants référents (vision
// restreinte aux étudiants qui leur sont attribués).
$isdeve = has_capability('mod/stage:viewall', $context);
$isteacher = has_capability('mod/stage:evaluateteacher', $context);
if (!$isdeve && !$isteacher) {
    require_capability('mod/stage:viewall', $context);
}
$restrictuserids = $isdeve ? null : array_keys(stage_get_assigned_students($stage->id, $USER->id));

$baseurl = new moodle_url('/mod/stage/dashboard.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('pilotage', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Situation détaillée d'un étudiant donné.
if ($studentid) {
    $student = $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST);
    if (!is_enrolled($context, $student) || ($restrictuserids !== null && !in_array($studentid, $restrictuserids))) {
        throw new moodle_exception('nopermissions', 'error', '', get_string('pilotage', 'mod_stage'));
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('pilotage', 'mod_stage') . ' - ' . fullname($student));
    echo html_writer::link($baseurl, get_string('back'));

    stage_print_student_dashboard($stage, $student->id, $cm, false, true);

    echo $OUTPUT->footer();
    exit;
}

// Vue d'ensemble : un étudiant par ligne, avec recherche par nom et tri. Page d'atterrissage de
// la DEVE et de l'enseignant référent (voir view.php, qui les redirige ici) : pas de lien
// "retour" vers view.php, remplacé par la barre de navigation entre les pages de gestion.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pilotage', 'mod_stage'));
echo stage_render_navlinks($cm, $context);

$listurl = new moodle_url($baseurl, ['search' => $search, 'tsort' => $tsort, 'tdir' => $tdir]);
echo stage_render_pilotage_search_form($baseurl, $cm->id, $search);

$rows = stage_get_pilotage_overview($stage->id, $context, $restrictuserids);
[$rows, $sortkey] = stage_sort_pilotage_rows($rows, $search, $tsort, $tdir);

if (empty($rows)) {
    echo $OUTPUT->notification(get_string('nostudents', 'mod_stage'), 'info');
} else {
    $columns = stage_pilotage_columns($listurl, $sortkey, $tdir, function ($row) use ($cm) {
        return new moodle_url('/mod/stage/dashboard.php', ['id' => $cm->id, 'studentid' => $row->user->id]);
    });
    echo stage_render_pilotage_table($columns, $rows, $page, $listurl);
}

echo $OUTPUT->footer();
