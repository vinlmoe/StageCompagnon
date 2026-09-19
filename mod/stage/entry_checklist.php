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
 * Correction de la check-list d'objectifs d'une saisie de stage par la DEVE ou par l'enseignant
 * référent de l'étudiant. L'étudiant la renseigne lors de sa demande de convention ; elle ne
 * figure pas dans la convention elle-même, mais reste attachée à la saisie, consultable dans son
 * détail (entrydetail.php) et corrigeable ici.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/entry_checklist_form.php');

use mod_stage\form\entry_checklist_form;

$id = required_param('id', PARAM_INT);
$entryid = required_param('entryid', PARAM_INT);
$returnurlparam = optional_param('returnurl', '', PARAM_LOCALURL);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

$entry = $DB->get_record('stage_entry', ['id' => $entryid, 'stageid' => $stage->id], '*', MUST_EXIST);
if (!stage_can_edit_entry_checklist($stage, $entry, $context)) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('themechecklist', 'mod_stage'));
}

$student = $DB->get_record('user', ['id' => $entry->userid], '*', MUST_EXIST);
$theme = $DB->get_record('stage_theme', ['id' => $entry->themeid]);

$urlparams = ['id' => $cm->id, 'entryid' => $entry->id];
if ($returnurlparam !== '') {
    $urlparams['returnurl'] = $returnurlparam;
}
$baseurl = new moodle_url('/mod/stage/entry_checklist.php', $urlparams);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('themechecklist', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Le retour honore l'origine réelle si elle a été transmise, à défaut le détail de la saisie,
// d'où l'on vient normalement.
$returnurl = $returnurlparam !== ''
    ? new moodle_url($returnurlparam)
    : new moodle_url('/mod/stage/entrydetail.php', ['id' => $cm->id, 'entryid' => $entry->id]);

$checklistitems = stage_get_theme_checklist($entry->themeid);
if (empty($checklistitems)) {
    redirect($returnurl, get_string('nochecklistitemsyet', 'mod_stage'), null, \core\output\notification::NOTIFY_INFO);
}

$mform = new entry_checklist_form($baseurl, ['checklistitems' => $checklistitems]);
$mform->set_data(array_merge(
    ['id' => $cm->id, 'entryid' => $entry->id, 'returnurl' => $returnurlparam],
    stage_checklist_form_data($checklistitems, stage_get_entry_checklist($entry->id))
));

if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $mform->get_data()) {
    stage_save_entry_checklist(
        $entry->id,
        $checklistitems,
        stage_extract_submitted_checklist($data, $checklistitems)
    );
    redirect($returnurl, get_string('checklistsaved', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('themechecklist', 'mod_stage') . ' - ' . fullname($student));
echo html_writer::link($returnurl, get_string('back'));

echo stage_render_entry_summary($entry, $theme, $student);

$mform->display();

echo $OUTPUT->footer();
