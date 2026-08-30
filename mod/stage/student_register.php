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
 * Permet à l'étudiant d'enregistrer lui-même un stage et de demander sa convention en une seule
 * saisie : crée la saisie (comme register.php le fait pour la DEVE), puis enchaîne directement
 * sur la demande de convention (comme convention_request.php), plutôt que d'obliger l'étudiant à
 * attendre que la DEVE enregistre le stage avant de pouvoir demander sa convention.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/student_register_form.php');

use mod_stage\form\student_register_form;

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:submit', $context);

$baseurl = new moodle_url('/mod/stage/student_register.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('registerstageandconvention', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$viewurl = new moodle_url('/mod/stage/view.php', ['id' => $cm->id]);

$themes = stage_get_themes($stage->id, true);
if (empty($themes)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('nothemesyet', 'mod_stage'), \core\output\notification::NOTIFY_ERROR);
    echo html_writer::link($viewurl, get_string('back'));
    echo $OUTPUT->footer();
    exit;
}

$templates = stage_get_convention_templates($stage->id);
if (empty($templates)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('noconventiontemplatesyet', 'mod_stage'), \core\output\notification::NOTIFY_ERROR);
    echo html_writer::link($viewurl, get_string('back'));
    echo $OUTPUT->footer();
    exit;
}

$referentteachers = stage_get_student_teachers($stage->id, $USER->id);
if (empty($referentteachers)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('noreferentteacheryet', 'mod_stage'), \core\output\notification::NOTIFY_ERROR);
    echo html_writer::link($viewurl, get_string('back'));
    echo $OUTPUT->footer();
    exit;
}

$mform = new student_register_form($baseurl, [
    'themes' => $themes,
    'templates' => $templates,
    'referentteachers' => $referentteachers,
    'stageid' => $stage->id,
    'userid' => $USER->id,
    'stage' => $stage,
]);
$mform->set_data((object) ['id' => $cm->id]);

if ($mform->is_cancelled()) {
    redirect($viewurl);
} else if ($data = $mform->get_data()) {
    // Les dates du stage sont déduites des plages saisies, seul endroit où elles se renseignent
    // (voir stage_save_entry_periods()). Le formulaire a déjà refusé une saisie sans plage ou avec
    // des plages qui se recoupent.
    $periods = stage_extract_submitted_periods($data);
    $entryid = stage_register_entry($stage->id, $USER->id, $data->themeid, $data->structure,
        min(array_column($periods, 'datestart')), max(array_column($periods, 'dateend')),
        $data->declaredduration, $data->studyyear, STAGE_CONVENTION_NONE, $data->abroad, $data->country);
    $entry = $DB->get_record('stage_entry', ['id' => $entryid], '*', MUST_EXIST);

    $requireteachervalidation = stage_convention_requires_teacher_validation($stage);
    stage_request_convention($entry, $data->conventiontemplateid, $requireteachervalidation);

    $detail = new stdClass();
    $detail->referentteacherid = $data->referentteacherid;
    $detail->yearsituation = $data->yearsituation;
    $detail->stagetype = $data->stagetype;
    $detail->studentbirthdate = $data->studentbirthdate ?: null;
    $detail->studentaddress = $data->studentaddress;
    $detail->studentphone = $data->studentphone;
    $detail->hostaddress = $data->hostaddress;
    $detail->hostrepresentative = $data->hostrepresentative;
    $detail->hostrepresentativetitle = $data->hostrepresentativetitle;
    $detail->hostservice = $data->hostservice;
    $detail->hostphone = $data->hostphone;
    $detail->hostemail = $data->hostemail;
    $detail->hostlocation = $data->hostlocation;
    $detail->tutorname = $data->tutorname;
    $detail->tutorfunction = $data->tutorfunction;
    $detail->tutorphone = $data->tutorphone;
    $detail->tutoremail = $data->tutoremail;
    $detail->nightpresence = !empty($data->nightpresence) ? 1 : 0;
    $detail->sundaypresence = !empty($data->sundaypresence) ? 1 : 0;
    $detail->holidaypresence = !empty($data->holidaypresence) ? 1 : 0;
    $detail->homebased = !empty($data->homebased) ? 1 : 0;
    $detail->othermodality = $data->othermodality;
    $detail->hasleave = !empty($data->hasleave) ? 1 : 0;
    $detail->leavedays = $detail->hasleave ? $data->leavedays : null;
    $detail->leavemodalities = $detail->hasleave ? $data->leavemodalities : '';
    $detail->gratificationamount = $data->gratificationamount;
    stage_save_convention_detail($entry->id, $detail);
    stage_save_entry_periods($entry->id, $periods);

    if ($requireteachervalidation) {
        stage_notify_teacher_convention_pending($stage, $cm, $entry);
    }

    redirect($viewurl, get_string('stageandconventionregistered', 'mod_stage'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('registerstageandconvention', 'mod_stage'));
echo html_writer::link($viewurl, get_string('back'));

echo $OUTPUT->box(get_string('registerstageandconvention_help', 'mod_stage'), 'generalbox mb-3');
echo stage_render_abroad_rules($stage);

$mform->display();

echo $OUTPUT->footer();
