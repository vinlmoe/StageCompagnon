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
 * Gestion des thématiques de stage par la DEVE : ajout, édition, suppression,
 * définition en masse ou unitaire des thématiques obligatoires et de leur durée.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/theme_form.php');

use mod_stage\form\theme_form;

$id = required_param('id', PARAM_INT);
$themeid = optional_param('themeid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:managethemes', $context);

$baseurl = new moodle_url('/mod/stage/themes.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('managethemes', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Suppression d'une thématique.
if ($action === 'delete' && $themeid) {
    require_sesskey();
    $theme = $DB->get_record('stage_theme', ['id' => $themeid, 'stageid' => $stage->id], '*', MUST_EXIST);
    if (!$DB->record_exists('stage_entry', ['themeid' => $theme->id])) {
        $DB->delete_records('stage_theme', ['id' => $theme->id]);
        redirect($baseurl, get_string('themedeleted', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        redirect($baseurl, get_string('themeinuse', 'mod_stage'), null, \core\output\notification::NOTIFY_ERROR);
    }
}

// Bascule rapide obligatoire/facultatif (action unitaire "un par un").
if ($action === 'togglemandatory' && $themeid) {
    require_sesskey();
    $theme = $DB->get_record('stage_theme', ['id' => $themeid, 'stageid' => $stage->id], '*', MUST_EXIST);
    $theme->mandatory = $theme->mandatory ? 0 : 1;
    $theme->timemodified = time();
    $DB->update_record('stage_theme', $theme);
    redirect($baseurl);
}

// Bascule rapide activée/désactivée : une thématique désactivée (visible = 0) n'est plus
// proposée aux étudiants (enregistrement DEVE en masse/unitaire, auto-enregistrement étudiant),
// mais reste affichée ici et sur les stages déjà enregistrés sur cette thématique.
if ($action === 'togglevisible' && $themeid) {
    require_sesskey();
    $theme = $DB->get_record('stage_theme', ['id' => $themeid, 'stageid' => $stage->id], '*', MUST_EXIST);
    $theme->visible = $theme->visible ? 0 : 1;
    $theme->timemodified = time();
    $DB->update_record('stage_theme', $theme);
    redirect($baseurl, get_string('themevisibilitytoggled', 'mod_stage'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// Formulaire d'ajout / édition d'une thématique.
if ($action === 'edit') {
    $formurl = new moodle_url('/mod/stage/themes.php', ['id' => $cm->id, 'action' => 'edit', 'themeid' => $themeid]);
    $mform = new theme_form($formurl);
    $theme = null;
    if ($themeid) {
        $theme = $DB->get_record('stage_theme', ['id' => $themeid, 'stageid' => $stage->id], '*', MUST_EXIST);
        $theme->themeid = $theme->id;
        $theme->id = $cm->id;
        $mform->set_data($theme);
    } else {
        $mform->set_data(['id' => $cm->id, 'themeid' => 0]);
    }

    if ($mform->is_cancelled()) {
        redirect($baseurl);
    } else if ($data = $mform->get_data()) {
        $record = new stdClass();
        $record->stageid = $stage->id;
        $record->name = $data->name;
        $record->description = $data->description;
        $record->mandatory = !empty($data->mandatory) ? 1 : 0;
        $record->requiredduration = $data->requiredduration;
        $record->studyyear = $data->studyyear;
        $record->sortorder = $data->sortorder;
        $record->visible = !empty($data->visible) ? 1 : 0;
        $record->timemodified = time();

        if (!empty($data->themeid)) {
            $record->id = $data->themeid;
            $DB->update_record('stage_theme', $record);
        } else {
            $record->timecreated = time();
            $DB->insert_record('stage_theme', $record);
        }
        redirect($baseurl, get_string('themesaved', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('managethemes', 'mod_stage'));
    $mform->display();
    echo $OUTPUT->footer();
    exit;
}

// Traitement de la mise à jour en masse (obligatoire + durée requise pour chaque thématique).
if ($action === 'bulksave' && data_submitted() && confirm_sesskey()) {
    $themes = stage_get_themes($stage->id);
    foreach ($themes as $theme) {
        $mandatory = optional_param('mandatory_' . $theme->id, 0, PARAM_INT);
        $duration = optional_param('duration_' . $theme->id, 0, PARAM_INT);
        $studyyear = optional_param('studyyear_' . $theme->id, 0, PARAM_INT);
        $theme->mandatory = $mandatory ? 1 : 0;
        $theme->requiredduration = $duration;
        $theme->studyyear = $studyyear;
        $theme->timemodified = time();
        $DB->update_record('stage_theme', $theme);
    }
    redirect($baseurl, get_string('bulkthemessaved', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Vue liste + formulaire de mise à jour en masse.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managethemes', 'mod_stage'));

echo html_writer::link(new moodle_url('/mod/stage/administration.php', ['id' => $cm->id]), get_string('back'));

echo html_writer::link(new moodle_url('/mod/stage/themes.php', ['id' => $cm->id, 'action' => 'edit']),
    get_string('addtheme', 'mod_stage'), ['class' => 'btn btn-primary d-block mt-2 mb-3', 'style' => 'width:fit-content']);
echo $OUTPUT->notification(get_string('themevisible_help', 'mod_stage'), 'info');

$themes = stage_get_themes($stage->id);

if (empty($themes)) {
    echo $OUTPUT->notification(get_string('nothemesyet', 'mod_stage'), 'info');
} else {
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'bulksave']);

    $table = new html_table();
    $table->head = [
        get_string('theme', 'mod_stage'),
        get_string('studyyear', 'mod_stage'),
        get_string('mandatory', 'mod_stage'),
        get_string('requiredduration', 'mod_stage'),
        get_string('visible'),
        get_string('actions', 'mod_stage'),
    ];
    foreach ($themes as $theme) {
        $mandatorycb = html_writer::checkbox('mandatory_' . $theme->id, 1, (bool) $theme->mandatory, '');
        $durationinput = html_writer::empty_tag('input', [
            'type' => 'number',
            'min' => 0,
            'name' => 'duration_' . $theme->id,
            'value' => $theme->requiredduration,
            'class' => 'form-control',
            'style' => 'width:6em',
        ]);
        $studyyearselect = html_writer::select(stage_studyyear_options(), 'studyyear_' . $theme->id, $theme->studyyear,
            false, ['class' => 'form-control']);
        $togglevisibleurl = new moodle_url('/mod/stage/themes.php',
            ['id' => $cm->id, 'action' => 'togglevisible', 'themeid' => $theme->id, 'sesskey' => sesskey()]);
        $visible = html_writer::link($togglevisibleurl,
            $theme->visible ? get_string('yes') : get_string('no'),
            ['class' => $theme->visible ? 'badge badge-success' : 'badge badge-secondary']);

        $editurl = new moodle_url('/mod/stage/themes.php', ['id' => $cm->id, 'action' => 'edit', 'themeid' => $theme->id]);
        $toggleurl = new moodle_url('/mod/stage/themes.php',
            ['id' => $cm->id, 'action' => 'togglemandatory', 'themeid' => $theme->id, 'sesskey' => sesskey()]);
        $deleteurl = new moodle_url('/mod/stage/themes.php',
            ['id' => $cm->id, 'action' => 'delete', 'themeid' => $theme->id, 'sesskey' => sesskey()]);
        $questionsurl = new moodle_url('/mod/stage/questions.php', ['id' => $cm->id, 'themeid' => $theme->id]);

        $actions = html_writer::link($editurl, get_string('edit')) . ' | '
            . html_writer::link($toggleurl, get_string('toggle', 'mod_stage')) . ' | '
            . html_writer::link($questionsurl, get_string('evalquestions', 'mod_stage')) . ' | '
            . html_writer::link($deleteurl, get_string('delete'),
                ['onclick' => "return confirm('" . get_string('confirmdeletetheme', 'mod_stage') . "');"]);

        $table->data[] = [format_string($theme->name), $studyyearselect, $mandatorycb, $durationinput, $visible, $actions];
    }
    echo html_writer::table($table);

    echo html_writer::empty_tag('input', [
        'type' => 'submit', 'value' => get_string('savebulkchanges', 'mod_stage'), 'class' => 'btn btn-primary mt-2',
    ]);
    echo html_writer::end_tag('form');
}

echo $OUTPUT->footer();
