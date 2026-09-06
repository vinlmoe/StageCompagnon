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
 * Téléchargement en une archive ZIP de tous les rapports de stage déposés sur une thématique,
 * pour la DEVE et les enseignants responsables de cette thématique. Les filtres passés en
 * paramètre sont ceux de la liste (theme_stages.php) : l'archive contient exactement ce que la
 * liste affiche, un dossier par étudiant.
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
$search = optional_param('search', '', PARAM_TEXT);
$filterstatus = optional_param('status', '', PARAM_RAW);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_sesskey();

$theme = $DB->get_record('stage_theme', ['id' => $themeid, 'stageid' => $stage->id], '*', MUST_EXIST);

if (!has_capability('mod/stage:viewall', $context) && !stage_is_theme_teacher($theme->id, $USER->id)) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('reportfiles', 'mod_stage'));
}

$entries = stage_get_filtered_entries($stage->id,
    ['search' => $search, 'themeid' => $theme->id, 'status' => $filterstatus], 'student', 'ASC');

stage_send_reports_zip($context, $entries, stage_get_entry_users($entries),
    get_string('reportszipname', 'mod_stage', format_string($theme->name)));
