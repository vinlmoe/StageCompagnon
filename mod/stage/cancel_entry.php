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
 * Annulation d'un stage par la DEVE, à tout moment et quel que soit son statut actuel : la saisie
 * est conservée telle quelle (aucune donnée supprimée), seul le statut passe à "Annulé" avec un
 * commentaire obligatoire expliquant le motif.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/cancel_entry_form.php');

use mod_stage\form\cancel_entry_form;

$id = required_param('id', PARAM_INT);
$entryid = required_param('entryid', PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:registerstages', $context);

$entry = $DB->get_record('stage_entry', ['id' => $entryid, 'stageid' => $stage->id], '*', MUST_EXIST);
$student = $DB->get_record('user', ['id' => $entry->userid], '*', MUST_EXIST);

$backurl = new moodle_url('/mod/stage/register.php', ['id' => $cm->id]);

if ((int) $entry->status === STAGE_STATUS_ANNULE) {
    redirect($backurl);
}

$baseurl = new moodle_url('/mod/stage/cancel_entry.php', ['id' => $cm->id, 'entryid' => $entryid]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('cancelentry', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$mform = new cancel_entry_form($baseurl, ['studentname' => fullname($student)]);
$mform->set_data((object) ['id' => $cm->id, 'entryid' => $entryid]);

if ($mform->is_cancelled()) {
    redirect($backurl);
} else if ($data = $mform->get_data()) {
    stage_cancel_entry($entry, $USER->id, $data->cancelcomment);
    redirect($backurl, get_string('stagecancelled', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('cancelentry', 'mod_stage'));
echo html_writer::link($backurl, get_string('back'));

echo $OUTPUT->box(get_string('confirmcancelentry', 'mod_stage'), 'generalbox mb-3');

$mform->display();

echo $OUTPUT->footer();
