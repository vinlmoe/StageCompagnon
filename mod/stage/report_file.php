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
 * Téléchargement d'un document du rapport de stage déposé par un étudiant. Comme pour la
 * convention signée (convention_signed.php), le contrôle d'accès est fait ici plutôt que par un
 * callback pluginfile : le droit dépend de la saisie concernée (étudiant propriétaire, DEVE,
 * enseignant référent de l'étudiant, enseignant responsable de la thématique).
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$id = required_param('id', PARAM_INT);
$entryid = required_param('entryid', PARAM_INT);
$pathnamehash = required_param('pathnamehash', PARAM_ALPHANUM);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

$entry = $DB->get_record('stage_entry', ['id' => $entryid, 'stageid' => $stage->id], '*', MUST_EXIST);

if (!stage_can_access_reports($stage, $entry, $context)) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('reportfiles', 'mod_stage'));
}

// Le fichier est cherché parmi ceux de la saisie plutôt que par son seul hachage : un hachage
// valide pointant vers une autre saisie (ou une autre zone de fichiers) ne doit rien renvoyer.
$files = stage_get_report_files($context, $entry->id);
$file = $files[$pathnamehash] ?? null;
if (!$file) {
    throw new moodle_exception('reportfilemissing', 'mod_stage');
}

send_stored_file($file, 0, 0, true);
