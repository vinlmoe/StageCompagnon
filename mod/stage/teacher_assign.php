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
 * Ancienne page d'attribution des enseignants référents d'un étudiant : l'attribution se fait
 * désormais directement dans le tableau de teachers.php. Redirige vers ce tableau, filtré sur
 * l'étudiant, pour ne pas casser les liens et favoris existants.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$studentid = required_param('studentid', PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
require_login($cm->course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:manageteachers', $context);

// Seul un inscrit du cours est retrouvé : son nom n'est pas révélé pour n'importe quel compte.
$student = $DB->get_record('user', ['id' => $studentid, 'deleted' => 0]);
if (!$student || !is_enrolled($context, $student)) {
    redirect(new moodle_url('/mod/stage/teachers.php', ['id' => $cm->id]));
}
redirect(new moodle_url('/mod/stage/teachers.php', ['id' => $cm->id, 'search' => fullname($student)]));
