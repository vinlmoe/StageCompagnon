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
 * Gestion par la DEVE des gabarits de convention de stage (PDF des articles juridiques,
 * proposés au choix de l'étudiant lors de sa demande de convention), des informations de
 * l'établissement d'enseignement (VetAgro Sup) et des deux logos affichés sur la page 1 de
 * toutes les conventions du stage.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/convention_template_form.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/convention_logos_form.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/convention_establishment_form.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/convention_settings_form.php');

use mod_stage\form\convention_template_form;
use mod_stage\form\convention_logos_form;
use mod_stage\form\convention_establishment_form;
use mod_stage\form\convention_settings_form;

$id = required_param('id', PARAM_INT);
$templateid = optional_param('templateid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:managethemes', $context);

$baseurl = new moodle_url('/mod/stage/convention_templates.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('conventiontemplates', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$filemanageroptions = ['subdirs' => 0, 'maxfiles' => 1, 'maxbytes' => $CFG->maxbytes, 'accepted_types' => ['.pdf']];

// Suppression d'un gabarit (protégée si déjà utilisé par une demande de convention).
if ($action === 'delete' && $templateid) {
    require_sesskey();
    $template = $DB->get_record('stage_convention_template', ['id' => $templateid, 'stageid' => $stage->id], '*',
        MUST_EXIST);
    if (!$DB->record_exists('stage_entry', ['conventiontemplateid' => $template->id])) {
        get_file_storage()->delete_area_files($context->id, 'mod_stage', 'conventiontemplate', $template->id);
        $DB->delete_records('stage_convention_template', ['id' => $template->id]);
        redirect($baseurl, get_string('conventiontemplatedeleted', 'mod_stage'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    } else {
        redirect($baseurl, get_string('conventiontemplateinuse', 'mod_stage'), null,
            \core\output\notification::NOTIFY_ERROR);
    }
}

// Ajout / édition d'un gabarit.
if ($action === 'edit') {
    $template = null;
    if ($templateid) {
        $template = $DB->get_record('stage_convention_template', ['id' => $templateid, 'stageid' => $stage->id], '*',
            MUST_EXIST);
    }

    $formurl = new moodle_url('/mod/stage/convention_templates.php',
        ['id' => $cm->id, 'action' => 'edit', 'templateid' => $templateid]);
    $mform = new convention_template_form($formurl, ['editing' => (bool) $template]);

    $draftitemid = file_get_submitted_draft_itemid('templatefile');
    file_prepare_draft_area($draftitemid, $template ? $context->id : null, 'mod_stage', 'conventiontemplate',
        $template ? $template->id : null, $filemanageroptions);

    $toform = new stdClass();
    $toform->id = $cm->id;
    $toform->templateid = $templateid;
    $toform->templatefile = $draftitemid;
    if ($template) {
        $toform->name = $template->name;
        $toform->lang = $template->lang;
    }
    $mform->set_data($toform);

    if ($mform->is_cancelled()) {
        redirect($baseurl);
    } else if ($data = $mform->get_data()) {
        if ($template) {
            $template->name = $data->name;
            $template->lang = $data->lang;
            $template->timemodified = time();
            $DB->update_record('stage_convention_template', $template);
            $savedtemplateid = $template->id;
        } else {
            $savedtemplateid = $DB->insert_record('stage_convention_template', (object) [
                'stageid' => $stage->id,
                'name' => $data->name,
                'lang' => $data->lang,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }
        file_save_draft_area_files($data->templatefile, $context->id, 'mod_stage', 'conventiontemplate',
            $savedtemplateid, $filemanageroptions);
        redirect($baseurl, get_string('conventiontemplatesaved', 'mod_stage'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('conventiontemplates', 'mod_stage'));
    $mform->display();
    echo $OUTPUT->footer();
    exit;
}

// Paramètres généraux des conventions (formulaire séparé, affiché en tête de page).
$settingsform = new convention_settings_form($baseurl);
$settingsform->set_data((object) [
    'id' => $cm->id,
    'conventionrequireteachervalidation' => stage_convention_requires_teacher_validation($stage) ? 1 : 0,
]);

if ($settingsdata = $settingsform->get_data()) {
    stage_save_convention_teacher_validation_setting($stage->id, !empty($settingsdata->conventionrequireteachervalidation));
    redirect($baseurl, get_string('conventionsettingssaved', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Informations de l'établissement d'enseignement (VetAgro Sup), affichées sur la page 1 de
// toutes les conventions de ce stage (formulaire séparé, affiché sous la liste des gabarits).
$establishmentform = new convention_establishment_form($baseurl);
$establishmentinfo = stage_get_establishment_info($stage);
$establishmentform->set_data((object) [
    'id' => $cm->id,
    'establishmentname' => $establishmentinfo->name,
    'establishmentaddress' => $establishmentinfo->address,
    'establishmentrepresentative' => $establishmentinfo->representative,
    'establishmentrepresentativetitle' => $establishmentinfo->representativetitle,
    'establishmentphone' => $establishmentinfo->phone,
    'establishmentemail' => $establishmentinfo->email,
    'establishmentsignatory' => $establishmentinfo->signatory,
]);

if ($establishmentdata = $establishmentform->get_data()) {
    stage_save_establishment_info($stage->id, $establishmentdata);
    redirect($baseurl, get_string('conventionestablishmentsaved', 'mod_stage'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// Enregistrement des deux logos (formulaire séparé, affiché sous la liste des gabarits).
$logosform = new convention_logos_form($baseurl);
$logodraftleft = file_get_submitted_draft_itemid('logoleft');
$logodraftright = file_get_submitted_draft_itemid('logoright');
file_prepare_draft_area($logodraftleft, $context->id, 'mod_stage', 'conventionlogoleft', 0,
    ['subdirs' => 0, 'maxfiles' => 1, 'maxbytes' => 2 * 1024 * 1024, 'accepted_types' => ['.png']]);
file_prepare_draft_area($logodraftright, $context->id, 'mod_stage', 'conventionlogoright', 0,
    ['subdirs' => 0, 'maxfiles' => 1, 'maxbytes' => 2 * 1024 * 1024, 'accepted_types' => ['.png']]);
$logosform->set_data((object) ['id' => $cm->id, 'logoleft' => $logodraftleft, 'logoright' => $logodraftright]);

if ($logosdata = $logosform->get_data()) {
    file_save_draft_area_files($logosdata->logoleft, $context->id, 'mod_stage', 'conventionlogoleft', 0,
        ['subdirs' => 0, 'maxfiles' => 1, 'maxbytes' => 2 * 1024 * 1024, 'accepted_types' => ['.png']]);
    file_save_draft_area_files($logosdata->logoright, $context->id, 'mod_stage', 'conventionlogoright', 0,
        ['subdirs' => 0, 'maxfiles' => 1, 'maxbytes' => 2 * 1024 * 1024, 'accepted_types' => ['.png']]);
    redirect($baseurl, get_string('conventionlogossaved', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('conventiontemplates', 'mod_stage'));
echo html_writer::link(new moodle_url('/mod/stage/administration.php', ['id' => $cm->id]), get_string('back'));

echo $OUTPUT->heading(get_string('generalsettings', 'mod_stage'), 4);
$settingsform->display();

echo html_writer::link(new moodle_url('/mod/stage/convention_templates.php', ['id' => $cm->id, 'action' => 'edit']),
    get_string('addconventiontemplate', 'mod_stage'), ['class' => 'btn btn-primary d-block mt-2 mb-3', 'style' => 'width:fit-content']);

$templates = stage_get_convention_templates($stage->id);
if (empty($templates)) {
    echo $OUTPUT->notification(get_string('noconventiontemplatesyet', 'mod_stage'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('conventiontemplatename', 'mod_stage'),
        get_string('conventionlang', 'mod_stage'),
        get_string('actions', 'mod_stage'),
    ];
    foreach ($templates as $template) {
        $editurl = new moodle_url('/mod/stage/convention_templates.php',
            ['id' => $cm->id, 'action' => 'edit', 'templateid' => $template->id]);
        $deleteurl = new moodle_url('/mod/stage/convention_templates.php',
            ['id' => $cm->id, 'action' => 'delete', 'templateid' => $template->id, 'sesskey' => sesskey()]);
        $actions = stage_render_actions([get_string('edit') => $editurl])
            . html_writer::link($deleteurl, get_string('delete'), [
                'class' => 'btn btn-sm btn-outline-danger mr-1 mb-1',
                'onclick' => "return confirm('" . get_string('confirmdeleteconventiontemplate', 'mod_stage') . "');",
            ]);
        $table->data[] = [format_string($template->name), stage_convention_lang_label($template->lang), $actions];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->heading(get_string('conventionestablishment', 'mod_stage'), 4);
echo $OUTPUT->box(get_string('conventionestablishment_help', 'mod_stage'), 'generalbox mb-3');
$establishmentform->display();

echo $OUTPUT->heading(get_string('conventionlogos', 'mod_stage'), 4);
echo $OUTPUT->box(get_string('conventionlogos_help', 'mod_stage'), 'generalbox mb-3');
$logosform->display();

echo $OUTPUT->footer();
