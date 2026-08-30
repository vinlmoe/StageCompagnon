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
 * Transfert d'un étudiant et de ses stages vers une autre instance de l'activité (généralement
 * dans un autre cours) : redoublement, changement de promotion, réorientation. Les stages sont
 * déplacés et non copiés, de sorte que le bilan de l'étudiant le suive au lieu de rester dans le
 * cours qu'il quitte.
 *
 * Le transfert se fait en deux temps : choix de l'étudiant et de la destination, puis récapitulatif
 * de ce qui sera transféré, de ce qui sera perdu et de ce qui l'empêche, avant confirmation. Le
 * transfert n'étant pas réversible d'un clic, rien n'est fait avant cette confirmation.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/transfer_form.php');

use mod_stage\form\transfer_form;

$id = required_param('id', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$targetstageid = optional_param('targetstageid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:registerstages', $context);

$baseurl = new moodle_url('/mod/stage/transfer.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('transferstudent', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$backurl = new moodle_url('/mod/stage/administration.php', ['id' => $cm->id]);

$targets = stage_get_transfer_target_instances($stage->id);
$students = [];
foreach (stage_get_enrolled_students($context) as $student) {
    $students[$student->id] = fullname($student);
}

if (empty($targets) || empty($students)) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('transferstudent', 'mod_stage'));
    echo html_writer::link($backurl, get_string('back'));
    echo $OUTPUT->notification(empty($targets)
        ? get_string('transfernotargets', 'mod_stage') : get_string('nostudents', 'mod_stage'),
        \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

// Étape 2 : récapitulatif et confirmation. Le plan est systématiquement recalculé, y compris à la
// confirmation : rien ne garantit que les thématiques ou les inscriptions n'ont pas changé entre
// l'affichage du récapitulatif et le clic de confirmation.
if ($userid && $targetstageid) {
    if (!array_key_exists($userid, $students) || !array_key_exists($targetstageid, $targets)) {
        throw new moodle_exception('nopermissions', 'error', '', get_string('transferstudent', 'mod_stage'));
    }

    $student = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    $targetstage = $DB->get_record('stage', ['id' => $targetstageid], '*', MUST_EXIST);
    $targetcm = get_coursemodule_from_instance('stage', $targetstage->id, 0, false, MUST_EXIST);
    $targetcontext = context_module::instance($targetcm->id);
    require_capability('mod/stage:registerstages', $targetcontext);

    $plan = stage_plan_student_transfer($stage, $targetstage, $userid);

    if ($confirm && empty($plan->blockers) && confirm_sesskey()) {
        $count = stage_execute_student_transfer($stage, $context, $targetstage, $targetcontext, $userid, $plan);
        redirect($baseurl, get_string('transferdone', 'mod_stage', (object) [
            'count' => $count,
            'student' => fullname($student),
            'target' => $targets[$targetstageid],
        ]), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('transferstudent', 'mod_stage'));
    echo html_writer::link($baseurl, get_string('back'));

    echo stage_render_detail_section(get_string('transfersummary', 'mod_stage'), [
        get_string('student', 'mod_stage') => fullname($student),
        get_string('transfersource', 'mod_stage') =>
            format_string($course->fullname) . ' - ' . format_string($stage->name),
        get_string('transfertarget', 'mod_stage') => $targets[$targetstageid],
        get_string('transferentrycount', 'mod_stage') => count($plan->entries),
    ]);

    // Ce qui sera transféré, stage par stage : la DEVE doit pouvoir vérifier que c'est bien de ces
    // stages qu'il s'agit avant de valider une opération non réversible.
    if (!empty($plan->entries)) {
        $themes = stage_get_themes($stage->id);
        $table = new html_table();
        $table->head = [
            get_string('theme', 'mod_stage'),
            get_string('studyyear', 'mod_stage'),
            get_string('structure', 'mod_stage'),
            get_string('retainedduration', 'mod_stage'),
            get_string('status', 'mod_stage'),
            get_string('conventionstatus', 'mod_stage'),
        ];
        foreach ($plan->entries as $entry) {
            $theme = $themes[$entry->themeid] ?? null;
            $table->data[] = [
                $theme ? format_string($theme->name) : '-',
                stage_studyyear_label($entry->studyyear),
                $entry->structure,
                $entry->retainedduration,
                html_writer::span(stage_status_label($entry->status),
                    'badge ' . stage_status_badgeclass($entry->status)),
                html_writer::span(stage_convention_status_label($entry->conventionstatus),
                    'badge ' . stage_convention_status_badgeclass($entry->conventionstatus)),
            ];
        }
        echo $OUTPUT->heading(get_string('transferentries', 'mod_stage'), 4);
        echo html_writer::table($table);
    }

    foreach ($plan->blockers as $blocker) {
        echo $OUTPUT->notification($blocker, \core\output\notification::NOTIFY_ERROR);
    }
    foreach ($plan->warnings as $warning) {
        echo $OUTPUT->notification($warning, \core\output\notification::NOTIFY_WARNING);
    }

    if (empty($plan->blockers)) {
        echo $OUTPUT->notification(get_string('transferirreversible', 'mod_stage'),
            \core\output\notification::NOTIFY_INFO);
        $confirmurl = new moodle_url($baseurl, [
            'userid' => $userid, 'targetstageid' => $targetstageid, 'confirm' => 1, 'sesskey' => sesskey(),
        ]);
        echo html_writer::link($confirmurl, get_string('transferconfirm', 'mod_stage'),
            ['class' => 'btn btn-primary mr-2']);
        echo html_writer::link($baseurl, get_string('cancel'), ['class' => 'btn btn-secondary']);
    }

    echo $OUTPUT->footer();
    exit;
}

// Étape 1 : choix de l'étudiant et de la destination.
$mform = new transfer_form($baseurl, ['students' => $students, 'targets' => $targets]);
$mform->set_data((object) ['id' => $cm->id]);

if ($mform->is_cancelled()) {
    redirect($backurl);
} else if ($data = $mform->get_data()) {
    redirect(new moodle_url($baseurl, ['userid' => $data->userid, 'targetstageid' => $data->targetstageid]));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('transferstudent', 'mod_stage'));
echo html_writer::link($backurl, get_string('back'));

echo $OUTPUT->box(get_string('transferstudent_help', 'mod_stage'), 'generalbox mb-3');

$mform->display();

echo $OUTPUT->footer();
