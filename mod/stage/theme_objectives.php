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
 * Gestion par la DEVE des objectifs de stage d'une thématique, en deux volets complémentaires :
 * les documents qui définissent ces objectifs (téléchargeables par l'étudiant et les enseignants
 * depuis la page de synthèse, et par le maître de stage depuis la page d'évaluation à jeton), et
 * la check-list que l'étudiant devra renseigner lors de sa demande de convention, chaque élément
 * laissé décoché devant être justifié dans un champ libre.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/theme_objectives_form.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/checklist_item_form.php');

use mod_stage\form\theme_objectives_form;
use mod_stage\form\checklist_item_form;

$id = required_param('id', PARAM_INT);
$themeid = required_param('themeid', PARAM_INT);
$itemid = optional_param('itemid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);
$theme = $DB->get_record('stage_theme', ['id' => $themeid, 'stageid' => $stage->id], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:managethemes', $context);

$baseurl = new moodle_url('/mod/stage/theme_objectives.php', ['id' => $cm->id, 'themeid' => $theme->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('themeobjectives', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$filemanageroptions = ['subdirs' => 0, 'maxfiles' => 20, 'maxbytes' => $CFG->maxbytes];

// Suppression d'un élément de check-list : emporte les réponses déjà données par les étudiants,
// qui n'auraient plus d'objectif auquel se rattacher (voir stage_delete_theme_checklist_item()).
if ($action === 'delete' && $itemid) {
    require_sesskey();
    $DB->get_record('stage_theme_checklist', ['id' => $itemid, 'themeid' => $theme->id], '*', MUST_EXIST);
    stage_delete_theme_checklist_item($itemid);
    redirect($baseurl, get_string('checklistitemdeleted', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Ajout / édition d'un élément de check-list.
if ($action === 'edit') {
    $formurl = new moodle_url(
        '/mod/stage/theme_objectives.php',
        ['id' => $cm->id, 'themeid' => $theme->id, 'action' => 'edit', 'itemid' => $itemid]
    );
    $itemform = new checklist_item_form($formurl);
    if ($itemid) {
        $item = $DB->get_record('stage_theme_checklist', ['id' => $itemid, 'themeid' => $theme->id], '*', MUST_EXIST);
        $itemform->set_data([
            'id' => $cm->id, 'themeid' => $theme->id, 'itemid' => $item->id,
            'name' => $item->name, 'description' => $item->description, 'sortorder' => $item->sortorder,
        ]);
    } else {
        $itemform->set_data(['id' => $cm->id, 'themeid' => $theme->id, 'itemid' => 0]);
    }

    if ($itemform->is_cancelled()) {
        redirect($baseurl);
    } else if ($data = $itemform->get_data()) {
        $record = new stdClass();
        $record->themeid = $theme->id;
        $record->name = $data->name;
        $record->description = $data->description;
        $record->sortorder = $data->sortorder;
        $record->timemodified = time();
        if (!empty($data->itemid)) {
            $record->id = $data->itemid;
            $DB->update_record('stage_theme_checklist', $record);
        } else {
            $record->timecreated = time();
            $DB->insert_record('stage_theme_checklist', $record);
        }
        redirect($baseurl, get_string('checklistitemsaved', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('themechecklist', 'mod_stage') . ' - ' . format_string($theme->name));
    $itemform->display();
    echo $OUTPUT->footer();
    exit;
}

// Documents d'objectifs : formulaire séparé, affiché en tête de page.
$filesform = new theme_objectives_form($baseurl, ['filemanageroptions' => $filemanageroptions]);
$draftitemid = file_get_submitted_draft_itemid('objectivefiles');
file_prepare_draft_area(
    $draftitemid,
    $context->id,
    'mod_stage',
    STAGE_THEME_OBJECTIVE_FILEAREA,
    $theme->id,
    $filemanageroptions
);
$filesform->set_data((object) ['id' => $cm->id, 'themeid' => $theme->id, 'objectivefiles' => $draftitemid]);

if ($filesdata = $filesform->get_data()) {
    file_save_draft_area_files(
        $filesdata->objectivefiles,
        $context->id,
        'mod_stage',
        STAGE_THEME_OBJECTIVE_FILEAREA,
        $theme->id,
        $filemanageroptions
    );
    redirect($baseurl, get_string('themeobjectivessaved', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('themeobjectives', 'mod_stage') . ' - ' . format_string($theme->name));
echo html_writer::link(new moodle_url('/mod/stage/themes.php', ['id' => $cm->id]), get_string('back'));

echo $OUTPUT->heading(get_string('themeobjectivefiles', 'mod_stage'), 4);
echo $OUTPUT->box(get_string('themeobjectivefiles_help', 'mod_stage'), 'generalbox mb-3');
$filesform->display();

echo $OUTPUT->heading(get_string('themechecklist', 'mod_stage'), 4);
echo $OUTPUT->box(get_string('themechecklist_help', 'mod_stage'), 'generalbox mb-3');

echo html_writer::link(
    new moodle_url('/mod/stage/theme_objectives.php', ['id' => $cm->id, 'themeid' => $theme->id, 'action' => 'edit']),
    get_string('addchecklistitem', 'mod_stage'),
    ['class' => 'btn btn-primary d-block mt-2 mb-3', 'style' => 'width:fit-content']
);

$items = stage_get_theme_checklist($theme->id);
if (empty($items)) {
    echo $OUTPUT->notification(get_string('nochecklistitemsyet', 'mod_stage'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('checklistitem', 'mod_stage'),
        get_string('checklistitemdescription', 'mod_stage'),
        get_string('sortorder', 'mod_stage'),
        get_string('actions', 'mod_stage'),
    ];
    foreach ($items as $item) {
        $editurl = new moodle_url(
            '/mod/stage/theme_objectives.php',
            ['id' => $cm->id, 'themeid' => $theme->id, 'action' => 'edit', 'itemid' => $item->id]
        );
        $deleteurl = new moodle_url(
            '/mod/stage/theme_objectives.php',
            ['id' => $cm->id, 'themeid' => $theme->id, 'action' => 'delete', 'itemid' => $item->id,
                'sesskey' => sesskey()]
        );
        $actions = stage_render_actions([get_string('edit') => $editurl])
            . html_writer::link($deleteurl, get_string('delete'), [
                'class' => 'btn btn-sm btn-outline-danger mr-1 mb-1',
                'onclick' => "return confirm('" . get_string('confirmdeletechecklistitem', 'mod_stage') . "');",
            ]);
        $table->data[] = [
            format_string($item->name),
            trim((string) $item->description) !== '' ? format_text($item->description, FORMAT_PLAIN) : '-',
            $item->sortorder,
            $actions,
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
