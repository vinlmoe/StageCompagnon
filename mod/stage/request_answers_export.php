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
 * Export des réponses au questionnaire de demande de stage (pré-stage) d'une thématique, pour la
 * DEVE et les enseignants responsables de cette thématique : une ligne par stage, une colonne par
 * question (voir stage_get_request_answers_export()), dans le format choisi (Excel, CSV, ODS...).
 *
 * Les filtres passés en paramètre sont ceux de la liste des stages de la thématique
 * (theme_stages.php) : l'export porte sur les stages que la liste affiche.
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
$filterstatus = stage_optional_status_param('status');
$dataformat = optional_param('dataformat', 'excel', PARAM_ALPHA);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

$theme = $DB->get_record('stage_theme', ['id' => $themeid, 'stageid' => $stage->id], '*', MUST_EXIST);

// Mêmes droits que la liste des stages de la thématique : la DEVE, ou un responsable de la
// thématique, qui voit déjà ces réponses en validant les demandes.
if (!has_capability('mod/stage:viewall', $context) && !stage_is_theme_teacher($theme->id, $USER->id)) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('requestanswersexport', 'mod_stage'));
}

$entries = stage_get_filtered_entries(
    $stage->id,
    ['search' => $search, 'themeid' => $theme->id, 'status' => $filterstatus],
    'student',
    'ASC'
);
$export = stage_get_request_answers_export($theme, $entries);

$filename = clean_filename(get_string('requestanswersfilename', 'mod_stage', stage_plain_string($theme->name)));
\core\dataformat::download_data(
    $filename,
    $dataformat,
    array_values($export['columns']),
    array_map('array_values', $export['rows'])
);
