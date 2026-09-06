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
 * Validation par l'enseignant.e référent.e d'une demande de convention de stage, avant
 * transmission à la DEVE (option "Exiger la validation de l'enseignant.e référent.e", voir
 * convention_templates.php). Affiche le même formulaire complet, éditable, que la revue DEVE
 * (convention_review.php) : l'enseignant.e peut corriger n'importe quel champ saisi par
 * l'étudiant avant de valider (transmet la demande à la DEVE) ou de refuser avec un commentaire
 * obligatoire (envoyé à l'étudiant pour correction, exactement comme un refus par la DEVE).
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/convention_review_form.php');

use mod_stage\form\convention_review_form;

$id = required_param('id', PARAM_INT);
$entryid = required_param('entryid', PARAM_INT);
$returnurlparam = optional_param('returnurl', '', PARAM_LOCALURL);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:evaluateteacher', $context);

$entry = $DB->get_record('stage_entry', ['id' => $entryid, 'stageid' => $stage->id], '*', MUST_EXIST);
$assignedids = array_keys(stage_get_assigned_students($stage->id, $USER->id));
if (!in_array((int) $entry->userid, $assignedids, true)) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('conventionteachervalidation', 'mod_stage'));
}

$student = $DB->get_record('user', ['id' => $entry->userid], '*', MUST_EXIST);

// Accessible depuis la liste de teacher.php mais aussi depuis stage_render_entry_management_actions()
// (résumé de l'étudiant, tableau de pilotage...) : le retour honore l'origine réelle si elle a été
// transmise, à défaut la liste de teacher.php.
$backurl = $returnurlparam !== ''
    ? new moodle_url($returnurlparam)
    : new moodle_url('/mod/stage/teacher.php', ['id' => $cm->id]);

if ((int) $entry->conventionstatus !== STAGE_CONVENTION_TEACHERPENDING) {
    redirect($backurl);
}

$referentteachers = stage_get_student_teachers($stage->id, $entry->userid);

// Le returnurl est intégré à l'URL d'action elle-même (et non ajouté en champ caché) : un
// moodleform ne reporte pas automatiquement les paramètres GET de la requête d'origine sur sa
// propre soumission, il serait donc perdu à la validation/au refus/à l'annulation sans cela.
$baseurl = new moodle_url('/mod/stage/convention_teacher_validate.php',
    ['id' => $cm->id, 'entryid' => $entryid, 'returnurl' => $returnurlparam]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('conventionteachervalidation', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$periods = array_values(stage_get_or_seed_entry_periods($entry));
$mform = new convention_review_form($baseurl, [
    'referentteachers' => $referentteachers, 'periods' => $periods,
    // Propre à l'enseignant référent : la DEVE ne voit pas cette case (convention_review.php),
    // seulement son résultat combiné à celle de l'étudiant (voir stage_convention_paper_requested_info()).
    'showpaperrequest' => true,
]);

$detail = stage_get_convention_detail($entry->id);
$formdata = (object) ['id' => $cm->id, 'entryid' => $entryid];
if ($detail) {
    foreach ($detail as $field => $value) {
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
    redirect($backurl);
} else if ($data = $mform->get_data()) {
    $newdetail = new stdClass();
    $newdetail->referentteacherid = $data->referentteacherid;
    $newdetail->yearsituation = $data->yearsituation;
    $newdetail->stagetype = $data->stagetype;
    $newdetail->studentbirthdate = $data->studentbirthdate ?: null;
    $newdetail->studentaddress = $data->studentaddress;
    $newdetail->studentphone = $data->studentphone;
    $newdetail->hostaddress = $data->hostaddress;
    $newdetail->hostrepresentative = $data->hostrepresentative;
    $newdetail->hostrepresentativetitle = $data->hostrepresentativetitle;
    $newdetail->hostservice = $data->hostservice;
    $newdetail->hostphone = $data->hostphone;
    $newdetail->hostemail = $data->hostemail;
    $newdetail->hostlocation = $data->hostlocation;
    $newdetail->tutorname = $data->tutorname;
    $newdetail->tutorfunction = $data->tutorfunction;
    $newdetail->tutorphone = $data->tutorphone;
    $newdetail->tutoremail = $data->tutoremail;
    $newdetail->nightpresence = !empty($data->nightpresence) ? 1 : 0;
    $newdetail->sundaypresence = !empty($data->sundaypresence) ? 1 : 0;
    $newdetail->holidaypresence = !empty($data->holidaypresence) ? 1 : 0;
    $newdetail->homebased = !empty($data->homebased) ? 1 : 0;
    $newdetail->othermodality = $data->othermodality;
    $newdetail->hasleave = !empty($data->hasleave) ? 1 : 0;
    $newdetail->leavedays = $newdetail->hasleave ? $data->leavedays : null;
    $newdetail->leavemodalities = $newdetail->hasleave ? $data->leavemodalities : '';
    $newdetail->gratificationamount = $data->gratificationamount;
    // La case de l'étudiant n'est pas éditable ici (voir convention_review_form) : sa valeur est
    // reprise telle quelle depuis la demande initiale, plutôt que la case de l'enseignant référent
    // ci-dessous, propre à ce formulaire.
    $newdetail->paperrequestedbystudent = !empty($detail->paperrequestedbystudent) ? 1 : 0;
    $newdetail->paperrequestedbyteacher = !empty($data->paperrequestedbyteacher) ? 1 : 0;
    stage_save_convention_detail($entry->id, $newdetail);
    stage_save_entry_periods($entry->id, stage_extract_submitted_periods($data));

    if (!empty($data->validateconvention)) {
        stage_teacher_validate_convention($entry, $USER->id);
        redirect($backurl, get_string('conventionteachervalidated', 'mod_stage'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    } else if (!empty($data->rejectconvention)) {
        stage_reject_convention($entry, $USER->id, $data->rejectcomment);
        stage_notify_student_convention_rejected($stage, $cm, $entry, $data->rejectcomment);
        redirect($backurl, get_string('conventionrejected', 'mod_stage'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    redirect($backurl);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('conventionteachervalidation', 'mod_stage'));
echo html_writer::link($backurl, get_string('back'));

echo $OUTPUT->box(get_string('conventionteachervalidatefor', 'mod_stage', fullname($student)), 'generalbox mb-3');

$mform->display();

echo $OUTPUT->footer();
