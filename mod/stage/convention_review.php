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
 * Revue par la DEVE d'une demande de convention soumise par l'étudiant : affiche le formulaire
 * rempli par l'étudiant, éditable, avec deux actions possibles : valider (enregistre les
 * éventuelles corrections, fait passer la convention au statut "éditée" et télécharge
 * immédiatement le PDF généré) ou refuser avec un commentaire obligatoire (envoyé par courriel à
 * l'étudiant, qui peut alors corriger et resoumettre sa demande depuis convention_request.php).
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

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:registerstages', $context);

$entry = $DB->get_record('stage_entry', ['id' => $entryid, 'stageid' => $stage->id], '*', MUST_EXIST);
$student = $DB->get_record('user', ['id' => $entry->userid], '*', MUST_EXIST);

$backurl = new moodle_url('/mod/stage/conventions.php', ['id' => $cm->id]);

if ((int) $entry->conventionstatus !== STAGE_CONVENTION_REQUESTED) {
    redirect($backurl, get_string('conventionnotrequested', 'mod_stage'), null,
        \core\output\notification::NOTIFY_INFO);
}

$referentteachers = stage_get_student_teachers($stage->id, $entry->userid);

$baseurl = new moodle_url('/mod/stage/convention_review.php', ['id' => $cm->id, 'entryid' => $entryid]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('conventionreview', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$mform = new convention_review_form($baseurl, ['referentteachers' => $referentteachers]);

$detail = stage_get_convention_detail($entry->id);
$formdata = (object) ['id' => $cm->id, 'entryid' => $entryid];
if ($detail) {
    foreach ($detail as $field => $value) {
        if (!in_array($field, ['id', 'entryid', 'timecreated', 'timemodified'], true)) {
            $formdata->$field = $value;
        }
    }
}
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
    stage_save_convention_detail($entry->id, $newdetail);

    if (!empty($data->validateconvention)) {
        stage_convention_mark_edited($entry, $USER->id);

        // Génère et télécharge immédiatement le PDF de la convention, plutôt que d'obliger la
        // DEVE à revenir ensuite sur la liste pour cliquer "Générer la convention" séparément.
        $entry = $DB->get_record('stage_entry', ['id' => $entry->id], '*', MUST_EXIST);
        $result = stage_build_convention_pdf($stage, $entry, $context);
        if ($result['error']) {
            redirect($backurl, get_string('conventionvalidatedpdferror', 'mod_stage', get_string($result['error'], 'mod_stage')),
                null, \core\output\notification::NOTIFY_WARNING);
        }
        $result['pdf']->Output($result['filename'], 'D');
        exit;
    } else if (!empty($data->rejectconvention)) {
        stage_reject_convention($entry, $USER->id, $data->rejectcomment);
        stage_notify_student_convention_rejected($stage, $cm, $entry, $data->rejectcomment);
        redirect($backurl, get_string('conventionrejected', 'mod_stage'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    redirect($backurl);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('conventionreview', 'mod_stage'));
echo html_writer::link($backurl, get_string('back'));

echo $OUTPUT->box(get_string('conventionreviewfor', 'mod_stage', fullname($student)), 'generalbox mb-3');

$mform->display();

echo $OUTPUT->footer();
