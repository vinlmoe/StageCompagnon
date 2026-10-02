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
 * Création ou modification d'un gabarit de convention de stage par la DEVE : son nom, sa langue
 * et le PDF des articles juridiques (pages 2 à 4). La liste des gabarits est présentée dans
 * convention_templates.php.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/convention_template_form.php');

use mod_stage\form\convention_template_form;

$id = required_param('id', PARAM_INT);
$templateid = optional_param('templateid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);
$template = $templateid
    ? $DB->get_record('stage_convention_template', ['id' => $templateid, 'stageid' => $stage->id], '*', MUST_EXIST)
    : null;

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:managethemes', $context);

$listurl = new moodle_url('/mod/stage/convention_templates.php', ['id' => $cm->id]);
$baseurl = new moodle_url('/mod/stage/convention_template.php', ['id' => $cm->id, 'templateid' => $templateid]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('conventiontemplates', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$filemanageroptions = ['subdirs' => 0, 'maxfiles' => 1, 'maxbytes' => $CFG->maxbytes, 'accepted_types' => ['.pdf']];
$mform = new convention_template_form($baseurl, ['editing' => (bool) $template]);

if ($mform->is_cancelled()) {
    redirect($listurl);
}

$draftitemid = file_get_submitted_draft_itemid('templatefile');
file_prepare_draft_area(
    $draftitemid,
    $template ? $context->id : null,
    'mod_stage',
    'conventiontemplate',
    $template ? $template->id : null,
    $filemanageroptions
);
$mform->set_data((object) [
    'id' => $cm->id,
    'templateid' => $templateid,
    'templatefile' => $draftitemid,
    'name' => $template->name ?? '',
    'lang' => $template->lang ?? 'fr',
]);

if ($data = $mform->get_data()) {
    $lang = array_key_exists($data->lang, stage_convention_lang_options()) ? $data->lang : 'fr';
    if ($template) {
        $template->name = $data->name;
        $template->lang = $lang;
        $template->timemodified = time();
        $DB->update_record('stage_convention_template', $template);
        $savedid = $template->id;
    } else {
        $savedid = $DB->insert_record('stage_convention_template', (object) [
            'stageid' => $stage->id,
            'name' => $data->name,
            'lang' => $lang,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }
    file_save_draft_area_files($data->templatefile, $context->id, 'mod_stage', 'conventiontemplate', $savedid, $filemanageroptions);
    redirect($listurl, get_string('conventiontemplatesaved', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($template
    ? get_string('conventiontemplate', 'mod_stage') . ' : ' . format_string($template->name)
    : get_string('addconventiontemplate', 'mod_stage'));
echo html_writer::link($listurl, get_string('back'));
if ($template && ($usage = $DB->count_records('stage_entry', ['conventiontemplateid' => $template->id]))) {
    echo $OUTPUT->notification(get_string('conventiontemplateusedby', 'mod_stage', $usage), 'info');
}
$mform->display();
echo $OUTPUT->footer();
