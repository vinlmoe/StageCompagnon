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
 * Détail en lecture seule d'une saisie de stage, pour la DEVE ou l'enseignant référent de
 * l'étudiant concerné (accessible notamment depuis le tableau de pilotage) : informations
 * générales du stage, détails de la convention le cas échéant, évaluations (étudiant, enseignant,
 * DEVE) quand elles existent, et motifs de refus de convention / annulation le cas échéant.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$id = required_param('id', PARAM_INT);
$entryid = required_param('entryid', PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

$entry = $DB->get_record('stage_entry', ['id' => $entryid, 'stageid' => $stage->id], '*', MUST_EXIST);

$isdeve = has_capability('mod/stage:viewall', $context);
$isassignedteacher = has_capability('mod/stage:evaluateteacher', $context)
    && in_array($entry->userid, array_keys(stage_get_assigned_students($stage->id, $USER->id)));
if (!$isdeve && !$isassignedteacher) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('viewdetails', 'mod_stage'));
}

$student = $DB->get_record('user', ['id' => $entry->userid], '*', MUST_EXIST);
$theme = $DB->get_record('stage_theme', ['id' => $entry->themeid]);

// Résout en une fois les utilisateurs référencés par la saisie (évaluateur, DEVE, annulation,
// refus de convention...), pour affichage de leur nom plutôt que de leur seul id.
$refuserids = array_unique(array_filter([
    $entry->teacherid, $entry->deveuserid, $entry->cancelledby, $entry->conventionrejectedby,
    $entry->conventionteachervalidatedby, $entry->conventioneditedby, $entry->conventionsignedby,
]));
$refusers = $refuserids ? $DB->get_records_list('user', 'id', $refuserids) : [];
$userlabel = function($userid) use ($refusers) {
    return isset($refusers[$userid]) ? fullname($refusers[$userid]) : '-';
};

$template = $entry->conventiontemplateid
    ? $DB->get_record('stage_convention_template', ['id' => $entry->conventiontemplateid])
    : null;
$detail = stage_get_convention_detail($entry->id);

$baseurl = new moodle_url('/mod/stage/entrydetail.php', ['id' => $cm->id, 'entryid' => $entry->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('viewdetails', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('viewdetails', 'mod_stage') . ' - ' . fullname($student));

$backurl = new moodle_url('/mod/stage/dashboard.php', ['id' => $cm->id, 'studentid' => $student->id]);
echo html_writer::link($backurl, get_string('back'));

$dateformat = get_string('strftimedate', 'langconfig');
$datetimeformat = get_string('strftimedatetime', 'langconfig');

// Chaque section est rendue en tableau libellé/valeur (stage_render_detail_section()) plutôt qu'en
// suite de paragraphes : la page en enchaîne une quarantaine, l'alignement des valeurs en colonne
// les rend nettement plus faciles à parcourir. Les lignes vides et les sections entièrement vides
// disparaissent d'elles-mêmes.

// 1. Le stage lui-même : de quel stage il s'agit et où il en est.
echo stage_render_entry_summary($entry, $theme);

// Jours de stage effectifs sélectionnés par l'étudiant parmi les plages de la saisie, s'il y en a.
$periods = stage_get_or_seed_entry_periods($entry);
if (!empty($periods)) {
    echo $OUTPUT->heading(get_string('workdays', 'mod_stage'), 4);
    echo stage_render_workday_picker($periods, stage_get_entry_workdays($entry->id), false);
}

// 2. Annulation, le cas échéant : à placer juste après le stage, c'est l'information qui explique
// tout le reste de la page.
if ((int) $entry->status === STAGE_STATUS_ANNULE) {
    echo stage_render_detail_section(get_string('cancelentry', 'mod_stage'), [
        get_string('cancelledby', 'mod_stage') => $userlabel($entry->cancelledby),
        get_string('canceltime', 'mod_stage') => $entry->canceltime
            ? userdate($entry->canceltime, $datetimeformat) : null,
        get_string('cancelcomment', 'mod_stage') => $entry->cancelcomment
            ? format_text($entry->cancelcomment, FORMAT_PLAIN) : null,
    ]);
}

// 3. Suivi de la convention : son avancement, qui a fait quoi et quand.
$conventionrows = [
    get_string('conventiontemplatename', 'mod_stage') => $template
        ? format_string($template->name) . ' (' . stage_convention_lang_label($template->lang) . ')' : null,
    get_string('conventionrequestdate', 'mod_stage') => $entry->conventionrequesttime
        ? userdate($entry->conventionrequesttime, $datetimeformat) : null,
    get_string('conventionvalidatedby', 'mod_stage') => !empty($entry->conventionteachervalidatedby)
        ? $userlabel($entry->conventionteachervalidatedby) . ' - '
            . userdate($entry->conventionteachervalidatetime, $datetimeformat) : null,
    get_string('conventioneditedby', 'mod_stage') => !empty($entry->conventioneditedby)
        ? $userlabel($entry->conventioneditedby) . ' - ' . userdate($entry->conventionedittime, $datetimeformat) : null,
    get_string('conventionsignedby', 'mod_stage') => !empty($entry->conventionsignedby)
        ? $userlabel($entry->conventionsignedby) . ' - ' . userdate($entry->conventionsigntime, $datetimeformat) : null,
];
// Motif de refus de convention, le cas échéant.
if ((int) $entry->conventionstatus === STAGE_CONVENTION_REJECTED) {
    $conventionrows[get_string('conventionrejectedby', 'mod_stage')] = $entry->conventionrejecttime
        ? $userlabel($entry->conventionrejectedby) . ' - ' . userdate($entry->conventionrejecttime, $datetimeformat)
        : null;
    $conventionrows[get_string('conventionrejectcomment', 'mod_stage')] = $entry->conventionrejectcomment
        ? format_text($entry->conventionrejectcomment, FORMAT_PLAIN) : null;
}
echo stage_render_detail_section(get_string('conventionfollowup', 'mod_stage'), $conventionrows);

// 4. Contenu de la convention, tel que saisi par l'étudiant puis corrigé aux validations.
if ($detail) {
    echo stage_render_detail_section(get_string('conventionsupervision', 'mod_stage'), [
        get_string('conventionreferentteacher', 'mod_stage') => !empty($detail->referentteacherid)
            ? $userlabel($detail->referentteacherid) : null,
        get_string('conventionyearsituation', 'mod_stage') =>
            stage_convention_yearsituation_options()[$detail->yearsituation] ?? $detail->yearsituation,
        get_string('conventionstagetype', 'mod_stage') =>
            stage_convention_stagetype_options()[$detail->stagetype] ?? $detail->stagetype,
        get_string('conventiontutorname', 'mod_stage') => s($detail->tutorname),
        get_string('conventiontutorfunction', 'mod_stage') => s($detail->tutorfunction),
        get_string('conventiontutorphone', 'mod_stage') => s($detail->tutorphone),
        get_string('conventiontutoremail', 'mod_stage') => s($detail->tutoremail),
    ]);

    echo stage_render_detail_section(get_string('conventionstudent', 'mod_stage'), [
        get_string('conventionbirthdate', 'mod_stage') => $detail->studentbirthdate
            ? userdate($detail->studentbirthdate, $dateformat) : null,
        get_string('conventionstudentaddress', 'mod_stage') => s($detail->studentaddress),
        get_string('conventionstudentphone', 'mod_stage') => s($detail->studentphone),
    ]);

    echo stage_render_detail_section(get_string('conventionhoststructure', 'mod_stage'), [
        get_string('conventionhostaddress', 'mod_stage') => s($detail->hostaddress),
        get_string('conventionhostrepresentative', 'mod_stage') => s($detail->hostrepresentative),
        get_string('conventionhostrepresentativetitle', 'mod_stage') => s($detail->hostrepresentativetitle),
        get_string('conventionhostservice', 'mod_stage') => s($detail->hostservice),
        get_string('conventionhostphone', 'mod_stage') => s($detail->hostphone),
        get_string('conventionhostemail', 'mod_stage') => s($detail->hostemail),
        get_string('conventionhostlocation', 'mod_stage') => s($detail->hostlocation),
    ]);

    $modalities = [];
    if (!empty($detail->nightpresence)) {
        $modalities[] = get_string('conventionnightpresence', 'mod_stage');
    }
    if (!empty($detail->sundaypresence)) {
        $modalities[] = get_string('conventionsundaypresence', 'mod_stage');
    }
    if (!empty($detail->holidaypresence)) {
        $modalities[] = get_string('conventionholidaypresence', 'mod_stage');
    }
    if (!empty($detail->homebased)) {
        $modalities[] = get_string('conventionhomebased', 'mod_stage');
    }
    if (!empty($detail->othermodality)) {
        $modalities[] = s($detail->othermodality);
    }
    echo stage_render_detail_section(get_string('conventionmodalities', 'mod_stage'), [
        get_string('conventionmodalities', 'mod_stage') => !empty($modalities)
            ? html_writer::alist($modalities) : null,
        get_string('conventiongratification', 'mod_stage') => s($detail->gratificationamount),
        get_string('conventionleavedays', 'mod_stage') => !empty($detail->hasleave) ? $detail->leavedays : null,
        get_string('conventionleavemodalities', 'mod_stage') => !empty($detail->hasleave) && $detail->leavemodalities
            ? format_text($detail->leavemodalities, FORMAT_PLAIN) : null,
    ]);
}

// 5. Évaluations successives, dans l'ordre du circuit : l'étudiant s'auto-évalue, l'enseignant
// référent évalue, la DEVE valide. Chaque section rappelle qui a évalué et quand, au-dessus du
// contenu de l'évaluation.
$answers = stage_get_answers($entry->id);

$studentquestions = stage_get_questions($entry->themeid, 'student');
if (!empty($studentquestions) || $entry->studentselfeval) {
    echo $OUTPUT->heading(get_string('studentselfeval', 'mod_stage'), 4);
    echo !empty($studentquestions)
        ? stage_render_answers_readonly($studentquestions, $answers)
        : html_writer::div(format_text($entry->studentselfeval, FORMAT_HTML));
}

$teacherquestions = stage_get_questions($entry->themeid, 'teacher');
if (!empty($teacherquestions) || $entry->teachereval) {
    echo $OUTPUT->heading(get_string('teachereval', 'mod_stage'), 4);
    if (!empty($entry->teacherid)) {
        echo html_writer::tag('p', html_writer::tag('strong', get_string('evaluatedby', 'mod_stage') . ' : ')
            . $userlabel($entry->teacherid)
            . ($entry->teachertime ? ' - ' . userdate($entry->teachertime, $datetimeformat) : ''),
            ['class' => 'text-muted']);
    }
    echo !empty($teacherquestions)
        ? stage_render_answers_readonly($teacherquestions, $answers)
        : html_writer::div(format_text($entry->teachereval, FORMAT_PLAIN));
}

if (!empty($stage->tutorevaluationenabled)) {
    $tutorquestions = stage_get_questions($entry->themeid, 'tutor');
    if (!empty($tutorquestions) || $entry->tutoreval) {
        echo $OUTPUT->heading(get_string('tutorevalheading', 'mod_stage'), 4);
        if ($entry->tutortime) {
            echo html_writer::tag('p', html_writer::tag('strong', get_string('evaluatedby', 'mod_stage') . ' : ')
                . userdate($entry->tutortime, $datetimeformat), ['class' => 'text-muted']);
        }
        echo (!empty($tutorquestions) && $entry->tutortime)
            ? stage_render_answers_readonly($tutorquestions, $answers)
            : html_writer::div(format_text((string) $entry->tutoreval, FORMAT_PLAIN));
    }
}

if ($entry->devecomment || !empty($entry->deveuserid)) {
    echo $OUTPUT->heading(get_string('devecomment', 'mod_stage'), 4);
    if (!empty($entry->deveuserid)) {
        echo html_writer::tag('p', html_writer::tag('strong', get_string('status_validedeve', 'mod_stage') . ' : ')
            . $userlabel($entry->deveuserid)
            . ($entry->devetime ? ' - ' . userdate($entry->devetime, $datetimeformat) : ''),
            ['class' => 'text-muted']);
    }
    if ($entry->devecomment) {
        echo html_writer::div(format_text($entry->devecomment, FORMAT_PLAIN));
    }
}

echo $OUTPUT->footer();
