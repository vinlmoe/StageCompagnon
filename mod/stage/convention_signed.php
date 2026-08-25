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
 * Téléchargement de la convention de stage signée (PDF scanné, téléversé par la DEVE lors du
 * passage au statut "signée" depuis convention_sign.php), pour l'étudiant propriétaire de la
 * saisie, la DEVE, ou l'enseignant référent auquel l'étudiant est attribué.
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

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

$entry = $DB->get_record('stage_entry', ['id' => $entryid, 'stageid' => $stage->id], '*', MUST_EXIST);

$isowner = ((int) $entry->userid === (int) $USER->id) && has_capability('mod/stage:submit', $context);
$isdeve = has_capability('mod/stage:registerstages', $context);
$isreferentteacher = has_capability('mod/stage:evaluateteacher', $context)
    && array_key_exists($entry->userid, stage_get_assigned_students($stage->id, $USER->id));
if (!$isowner && !$isdeve && !$isreferentteacher) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('conventionsignedfile', 'mod_stage'));
}

if ((int) $entry->conventionstatus !== STAGE_CONVENTION_SIGNED) {
    throw new moodle_exception('conventionnotsignedyet', 'mod_stage');
}

$file = stage_get_signed_convention_file($context, $entry->id);
if (!$file) {
    throw new moodle_exception('conventionsignedfilemissing', 'mod_stage');
}

send_stored_file($file, 0, 0, true);
