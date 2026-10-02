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
/**
 * Paramétrage des conventions de stage par la DEVE, en une seule page enregistrée en une fois :
 * paramètres généraux, gabarits (PDF des articles juridiques, proposés au choix de l'étudiant
 * lors de sa demande de convention), informations de l'établissement d'enseignement (VetAgro Sup)
 * et logos affichés sur la page 1 de toutes les conventions du stage.
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

$templates = stage_get_convention_templates($stage->id);
// Un gabarit choisi par au moins une demande de convention ne peut pas être supprimé : la
// convention déjà produite (ou à produire) en dépend.
$inuse = [];
foreach ($templates as $template) {
    $count = $DB->count_records('stage_entry', ['conventiontemplateid' => $template->id]);
    if ($count) {
        $inuse[(int) $template->id] = $count;
    }
}

$mform = new conventions_admin_form($baseurl, ['templates' => $templates, 'inuse' => $inuse]);

$templateoptions = conventions_admin_form::template_file_options();
$logooptions = conventions_admin_form::logo_file_options();

// Valeurs initiales, dont une zone de brouillon par gestionnaire de fichiers.
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
foreach ($templates as $template) {
    $tid = (int) $template->id;
    $draftitemid = file_get_submitted_draft_itemid('templatefile_' . $tid);
    file_prepare_draft_area($draftitemid, $context->id, 'mod_stage', 'conventiontemplate', $tid, $templateoptions);
    $formdata['templatename_' . $tid] = $template->name;
    $formdata['templatelang_' . $tid] = $template->lang;
    $formdata['templatefile_' . $tid] = $draftitemid;
}
$newdraftitemid = file_get_submitted_draft_itemid('newtemplatefile');
file_prepare_draft_area($newdraftitemid, null, 'mod_stage', 'conventiontemplate', null, $templateoptions);
$formdata['newtemplatefile'] = $newdraftitemid;
foreach (['logoleft' => 'conventionlogoleft', 'logoright' => 'conventionlogoright'] as $field => $filearea) {
    $draftitemid = file_get_submitted_draft_itemid($field);
    file_prepare_draft_area($draftitemid, $context->id, 'mod_stage', $filearea, 0, $logooptions);
    $formdata[$field] = $draftitemid;
}
$mform->set_data($formdata);

if ($data = $mform->get_data()) {
    $now = time();

    stage_save_convention_teacher_validation_setting($stage->id, !empty($data->conventionrequireteachervalidation));
    stage_save_establishment_info($stage->id, $data);

    foreach ($templates as $template) {
        $tid = (int) $template->id;
        if (!empty($data->{'templatedelete_' . $tid}) && empty($inuse[$tid])) {
            get_file_storage()->delete_area_files($context->id, 'mod_stage', 'conventiontemplate', $tid);
            $DB->delete_records('stage_convention_template', ['id' => $tid]);
            continue;
        }
        $lang = $data->{'templatelang_' . $tid};
        $template->name = $data->{'templatename_' . $tid};
        $template->lang = array_key_exists($lang, stage_convention_lang_options()) ? $lang : $template->lang;
        $template->timemodified = $now;
        $DB->update_record('stage_convention_template', $template);
        file_save_draft_area_files(
            $data->{'templatefile_' . $tid},
            $context->id,
            'mod_stage',
            'conventiontemplate',
            $tid,
            $templateoptions
        );
    }

    if (trim((string) $data->newtemplatename) !== '') {
        $lang = array_key_exists($data->newtemplatelang, stage_convention_lang_options()) ? $data->newtemplatelang : 'fr';
        $newid = $DB->insert_record('stage_convention_template', (object) [
            'stageid' => $stage->id,
            'name' => $data->newtemplatename,
            'lang' => $lang,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        file_save_draft_area_files(
            $data->newtemplatefile,
            $context->id,
            'mod_stage',
            'conventiontemplate',
            $newid,
            $templateoptions
        );
    }

    foreach (['logoleft' => 'conventionlogoleft', 'logoright' => 'conventionlogoright'] as $field => $filearea) {
        file_save_draft_area_files($data->$field, $context->id, 'mod_stage', $filearea, 0, $logooptions);
    }

    redirect($baseurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('conventiontemplates', 'mod_stage'));
echo html_writer::link(new moodle_url('/mod/stage/administration.php', ['id' => $cm->id]), get_string('back'));

$mform->display();

echo $OUTPUT->footer();
