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
 * Passage d'une convention de stage au statut "signée" (DEVE) : le PDF de la convention
 * effectivement signée (scan du document papier) peut être téléversé ici, facultativement. Cette
 * étape ouvre le droit à l'auto-évaluation de l'étudiant et à l'évaluation de l'enseignant
 * référent, que le PDF soit fourni ou non ; s'il est fourni, il devient téléchargeable par
 * l'étudiant, la DEVE et l'enseignant référent (convention_signed.php).
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/convention_sign_form.php');

use mod_stage\form\convention_sign_form;

$id = required_param('id', PARAM_INT);
$entryid = required_param('entryid', PARAM_INT);
$returnurlparam = optional_param('returnurl', '', PARAM_LOCALURL);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:registerstages', $context);

$entry = $DB->get_record('stage_entry', ['id' => $entryid, 'stageid' => $stage->id], '*', MUST_EXIST);
$student = $DB->get_record('user', ['id' => $entry->userid], '*', MUST_EXIST);

// Accessible depuis la liste des conventions mais aussi depuis register.php et
// stage_render_entry_management_actions() (résumé de l'étudiant, tableau de pilotage...) : le
// retour honore l'origine réelle si elle a été transmise, à défaut la liste des conventions.
$backurl = $returnurlparam !== ''
    ? new moodle_url($returnurlparam)
    : new moodle_url('/mod/stage/conventions.php', ['id' => $cm->id]);

if ((int) $entry->conventionstatus !== STAGE_CONVENTION_EDITED) {
    redirect($backurl);
}

$baseurl = new moodle_url('/mod/stage/convention_sign.php', ['id' => $cm->id, 'entryid' => $entryid]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('conventionmarksigned', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$filemanageroptions = ['subdirs' => 0, 'maxfiles' => 1, 'maxbytes' => $CFG->maxbytes, 'accepted_types' => ['.pdf']];

$mform = new convention_sign_form($baseurl, ['studentname' => fullname($student)]);

$draftitemid = file_get_submitted_draft_itemid('signedfile');
file_prepare_draft_area($draftitemid, $context->id, 'mod_stage', 'signedconvention', $entryid, $filemanageroptions);

$mform->set_data((object) ['id' => $cm->id, 'entryid' => $entryid, 'signedfile' => $draftitemid]);

if ($mform->is_cancelled()) {
    redirect($backurl);
} else if ($data = $mform->get_data()) {
    file_save_draft_area_files($data->signedfile, $context->id, 'mod_stage', 'signedconvention', $entryid,
        $filemanageroptions);
    stage_convention_mark_signed($entry, $USER->id);
    redirect($backurl, get_string('conventionmarkedsigned', 'mod_stage'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('conventionmarksigned', 'mod_stage'));
echo html_writer::link($backurl, get_string('back'));

echo $OUTPUT->box(get_string('conventionsignedfile_help', 'mod_stage'), 'generalbox mb-3');

$mform->display();

echo $OUTPUT->footer();
