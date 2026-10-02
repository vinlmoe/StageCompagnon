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
 * Paramétrage des conventions de stage par la DEVE : la liste des gabarits (PDF des articles
 * juridiques, proposés au choix de l'étudiant lors de sa demande de convention), chacun édité sur
 * sa propre page (convention_template.php), puis, en un seul formulaire enregistré en une fois,
 * les paramètres généraux, les informations de l'établissement d'enseignement (VetAgro Sup) et
 * les logos affichés sur la page 1 de toutes les conventions du stage.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/conventions_admin_form.php');

use mod_stage\form\conventions_admin_form;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$templateid = optional_param('templateid', 0, PARAM_INT);

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

// Ancien lien d'édition d'un gabarit : l'édition a désormais sa propre page.
if ($action === 'edit') {
    redirect(new moodle_url('/mod/stage/convention_template.php', ['id' => $cm->id, 'templateid' => $templateid]));
}

// Suppression d'un gabarit : refusée tant qu'une demande de convention l'utilise, la convention
// déjà produite (ou à produire) en dépendant.
if ($action === 'delete' && $templateid) {
    require_sesskey();
    $template = $DB->get_record('stage_convention_template', ['id' => $templateid, 'stageid' => $stage->id], '*', MUST_EXIST);
    if ($DB->record_exists('stage_entry', ['conventiontemplateid' => $template->id])) {
        redirect($baseurl, get_string('conventiontemplateinuse', 'mod_stage'), null, \core\output\notification::NOTIFY_ERROR);
    }
    get_file_storage()->delete_area_files($context->id, 'mod_stage', 'conventiontemplate', $template->id);
    $DB->delete_records('stage_convention_template', ['id' => $template->id]);
    redirect($baseurl, get_string('conventiontemplatedeleted', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$mform = new conventions_admin_form($baseurl);
$logooptions = conventions_admin_form::logo_file_options();

// Valeurs initiales, dont une zone de brouillon par logo.
$establishmentinfo = stage_get_establishment_info($stage);
$formdata = [
    'id' => $cm->id,
    'conventionrequireteachervalidation' => stage_convention_requires_teacher_validation($stage) ? 1 : 0,
    'establishmentname' => $establishmentinfo->name,
    'establishmentaddress' => $establishmentinfo->address,
    'establishmentrepresentative' => $establishmentinfo->representative,
    'establishmentrepresentativetitle' => $establishmentinfo->representativetitle,
    'establishmentphone' => $establishmentinfo->phone,
    'establishmentemail' => $establishmentinfo->email,
    'establishmentsignatory' => $establishmentinfo->signatory,
];
foreach (['logoleft' => 'conventionlogoleft', 'logoright' => 'conventionlogoright'] as $field => $filearea) {
    $draftitemid = file_get_submitted_draft_itemid($field);
    file_prepare_draft_area($draftitemid, $context->id, 'mod_stage', $filearea, 0, $logooptions);
    $formdata[$field] = $draftitemid;
}
$mform->set_data($formdata);

if ($data = $mform->get_data()) {
    stage_save_convention_teacher_validation_setting($stage->id, !empty($data->conventionrequireteachervalidation));
    stage_save_establishment_info($stage->id, $data);
    foreach (['logoleft' => 'conventionlogoleft', 'logoright' => 'conventionlogoright'] as $field => $filearea) {
        file_save_draft_area_files($data->$field, $context->id, 'mod_stage', $filearea, 0, $logooptions);
    }
    redirect($baseurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('conventiontemplates', 'mod_stage'));
echo html_writer::link(new moodle_url('/mod/stage/administration.php', ['id' => $cm->id]), get_string('back'));

// Gabarits : la liste, avec un lien d'édition et d'ajout vers leur page dédiée.
echo $OUTPUT->heading(get_string('conventiontemplatelist', 'mod_stage'), 3, 'mt-3');
echo html_writer::link(
    new moodle_url('/mod/stage/convention_template.php', ['id' => $cm->id]),
    get_string('addconventiontemplate', 'mod_stage'),
    ['class' => 'btn btn-primary d-block mt-2 mb-3', 'style' => 'width:fit-content']
);
$templates = stage_get_convention_templates($stage->id);
if (empty($templates)) {
    echo $OUTPUT->notification(get_string('noconventiontemplatesyet', 'mod_stage'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('conventiontemplatename', 'mod_stage'),
        get_string('conventionlang', 'mod_stage'),
        get_string('conventiontemplatefile', 'mod_stage'),
        get_string('conventiontemplateusage', 'mod_stage'),
        get_string('actions', 'mod_stage'),
    ];
    $fs = get_file_storage();
    foreach ($templates as $template) {
        $editurl = new moodle_url('/mod/stage/convention_template.php', ['id' => $cm->id, 'templateid' => $template->id]);
        $files = $fs->get_area_files($context->id, 'mod_stage', 'conventiontemplate', $template->id, 'filename', false);
        $file = reset($files);
        // Le nom du PDF seulement : le plugin ne sert pas ces fichiers en téléchargement direct,
        // on les retrouve dans le gestionnaire de fichiers de la page d'édition.
        $filelink = $file
            ? s($file->get_filename())
            : html_writer::span(get_string('conventiontemplatenofile', 'mod_stage'), 'text-danger');
        $usage = $DB->count_records('stage_entry', ['conventiontemplateid' => $template->id]);
        $actions = stage_render_actions([get_string('edit') => $editurl]);
        if (!$usage) {
            $deleteurl = new moodle_url($baseurl, [
                'action' => 'delete', 'templateid' => $template->id, 'sesskey' => sesskey(),
            ]);
            $actions .= html_writer::link($deleteurl, get_string('delete'), [
                'class' => 'btn btn-sm btn-outline-danger mr-1 mb-1',
                'onclick' => "return confirm('" . addslashes_js(get_string('confirmdeleteconventiontemplate', 'mod_stage')) . "');",
            ]);
        }
        $table->data[] = [
            html_writer::link($editurl, format_string($template->name)),
            stage_convention_lang_label($template->lang),
            $filelink,
            $usage,
            $actions,
        ];
    }
    echo html_writer::table($table);
}

$mform->display();

echo $OUTPUT->footer();
