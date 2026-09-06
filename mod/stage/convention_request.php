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
 * Demande de convention de stage par l'étudiant : choix de la langue et d'un gabarit parmi ceux
 * proposés par la DEVE, ainsi que toutes les informations de la page 1 de la convention que la
 * DEVE ne connaît pas déjà (coordonnées de l'étudiant, organisme d'accueil, tuteur, modalités
 * particulières, gratification, congés). Si l'option est activée pour ce stage (voir
 * stage_convention_requires_teacher_validation()), la demande doit d'abord être validée par un
 * enseignant.e référent.e (convention_teacher_validate.php) avant d'être visible par la DEVE, qui
 * la valide ensuite (passage au statut "éditée" puis "signée", ce qui ouvre le droit à
 * l'auto-évaluation et à l'évaluation) depuis conventions.php.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/convention_request_form.php');

use mod_stage\form\convention_request_form;

$id = required_param('id', PARAM_INT);
$entryid = required_param('entryid', PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:submit', $context);

$entry = $DB->get_record('stage_entry', ['id' => $entryid, 'stageid' => $stage->id], '*', MUST_EXIST);
if ($entry->userid != $USER->id) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('requestconvention', 'mod_stage'));
}

$baseurl = new moodle_url('/mod/stage/convention_request.php', ['id' => $cm->id, 'entryid' => $entryid]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('requestconvention', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$viewurl = new moodle_url('/mod/stage/view.php', ['id' => $cm->id]);

$requeststatus = (int) $entry->conventionstatus;
if ($requeststatus !== STAGE_CONVENTION_NONE && $requeststatus !== STAGE_CONVENTION_REJECTED) {
    redirect($viewurl, get_string('conventionalreadyrequested', 'mod_stage'), null,
        \core\output\notification::NOTIFY_INFO);
}

$templates = stage_get_convention_templates($stage->id);
if (empty($templates)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('noconventiontemplatesyet', 'mod_stage'), \core\output\notification::NOTIFY_ERROR);
    echo html_writer::link($viewurl, get_string('back'));
    echo $OUTPUT->footer();
    exit;
}

$referentteachers = stage_get_student_teachers($stage->id, $entry->userid);
if (empty($referentteachers)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('noreferentteacheryet', 'mod_stage'), \core\output\notification::NOTIFY_ERROR);
    echo html_writer::link($viewurl, get_string('back'));
    echo $OUTPUT->footer();
    exit;
}

$periods = array_values(stage_get_or_seed_entry_periods($entry));
$mform = new convention_request_form($baseurl, [
    'templates' => $templates, 'referentteachers' => $referentteachers, 'periods' => $periods,
]);

$formdata = (object) ['id' => $cm->id, 'entryid' => $entryid];
$existingdetail = stage_get_convention_detail($entry->id);
if ($existingdetail) {
    foreach ($existingdetail as $field => $value) {
        if (!in_array($field, ['id', 'entryid', 'timecreated', 'timemodified'], true)) {
            $formdata->$field = $value;
        }
    }
}
$formdata->perioddatestart = array_map(function($period) {
    return $period->datestart;
}, $periods);
$formdata->perioddateend = array_map(function($period) {
    return $period->dateend;
}, $periods);
$mform->set_data($formdata);

if ($mform->is_cancelled()) {
    redirect($viewurl);
} else if ($data = $mform->get_data()) {
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
    $detail->paperrequestedbystudent = !empty($data->paperrequestedbystudent) ? 1 : 0;
    stage_save_convention_detail($entry->id, $detail);
    stage_save_entry_periods($entry->id, stage_extract_submitted_periods($data));

    if ($requireteachervalidation) {
        stage_notify_teacher_convention_pending($stage, $cm, $entry);
    }

    redirect($viewurl, get_string('conventionrequested', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('requestconvention', 'mod_stage'));
echo html_writer::link($viewurl, get_string('back'));

if ($requeststatus === STAGE_CONVENTION_REJECTED && !empty($entry->conventionrejectcomment)) {
    echo $OUTPUT->notification(
        get_string('conventionrejectedexplain', 'mod_stage', $entry->conventionrejectcomment),
        \core\output\notification::NOTIFY_WARNING
    );
}

echo $OUTPUT->box(get_string('requestconvention_help', 'mod_stage'), 'generalbox mb-3');

$mform->display();

echo $OUTPUT->footer();
