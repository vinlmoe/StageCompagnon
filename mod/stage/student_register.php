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
 * Avec un entryid transmis, la page passe en mode édition : la saisie existante (enregistrée par
 * la DEVE, ou sa propre demande refusée) est complétée/corrigée plutôt que dupliquée, en réutilisant
 * le même formulaire pour ne maintenir qu'un seul jeu de champs. C'est ce mode, plutôt que
 * convention_request.php, qu'utilise désormais le bouton « Demander la convention » (voir
 * locallib.php, stage_render_entry_management_actions()).
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
$entryid = optional_param('entryid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:submit', $context);

$viewurl = new moodle_url('/mod/stage/view.php', ['id' => $cm->id]);

// Mode édition : la saisie doit appartenir à l'étudiant connecté et ne pas déjà avoir de
// convention en cours (mêmes conditions que convention_request.php, qu'il remplace pour ce cas).
$existingentry = null;
if ($entryid) {
    $existingentry = $DB->get_record('stage_entry', ['id' => $entryid, 'stageid' => $stage->id], '*', MUST_EXIST);
    if ($existingentry->userid != $USER->id) {
        throw new moodle_exception('nopermissions', 'error', '', get_string('registerstageandconvention', 'mod_stage'));
    }
    $existingstatus = (int) $existingentry->conventionstatus;
    if ($existingstatus !== STAGE_CONVENTION_NONE && $existingstatus !== STAGE_CONVENTION_REJECTED) {
        redirect($viewurl, get_string('conventionalreadyrequested', 'mod_stage'), null,
            \core\output\notification::NOTIFY_INFO);
    }
}

$pagetitle = get_string($existingentry ? 'requestconvention' : 'registerstageandconvention', 'mod_stage');

$baseurl = new moodle_url('/mod/stage/student_register.php',
    $existingentry ? ['id' => $cm->id, 'entryid' => $entryid] : ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . $pagetitle);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

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

$existingperiods = $existingentry ? array_values(stage_get_or_seed_entry_periods($existingentry)) : [];

$mform = new student_register_form($baseurl, [
    'themes' => $themes,
    'templates' => $templates,
    'referentteachers' => $referentteachers,
    'stageid' => $stage->id,
    'userid' => $USER->id,
    'stage' => $stage,
    'editing' => $existingentry !== null,
    'excludeentryid' => $entryid,
    'periods' => $existingperiods,
]);

if ($existingentry) {
    // Préremplit avec la saisie existante et sa convention déjà partiellement saisie, le cas
    // échéant (demande refusée à corriger) : mêmes champs que convention_request.php, plus ceux de
    // l'enregistrement du stage lui-même (thématique, dates, structure...), éditables ici aussi.
    $periods = $existingperiods;
    $formdata = (object) [
        'id' => $cm->id, 'entryid' => $entryid,
        'themeid' => $existingentry->themeid, 'studyyear' => $existingentry->studyyear,
        'structure' => $existingentry->structure, 'abroad' => $existingentry->abroad,
        'country' => $existingentry->country, 'declaredduration' => $existingentry->declaredduration,
        'perioddatestart' => array_map(fn($period) => $period->datestart, $periods),
        'perioddateend' => array_map(fn($period) => $period->dateend, $periods),
    ];
    $existingdetail = stage_get_convention_detail($existingentry->id);
    if ($existingdetail) {
        foreach ($existingdetail as $field => $value) {
            if (!in_array($field, ['id', 'entryid', 'timecreated', 'timemodified'], true)) {
                $formdata->$field = $value;
            }
        }
    }
    $mform->set_data($formdata);
} else {
    $mform->set_data((object) ['id' => $cm->id]);
}

if ($mform->is_cancelled()) {
    redirect($viewurl);
} else if ($data = $mform->get_data()) {
    // Les dates du stage sont déduites des plages saisies, seul endroit où elles se renseignent
    // (voir stage_save_entry_periods()). Le formulaire a déjà refusé une saisie sans plage ou avec
    // des plages qui se recoupent.
    $periods = stage_extract_submitted_periods($data);

    if ($existingentry) {
        // Mode édition : on complète/corrige la saisie existante plutôt que d'en créer une autre.
        $DB->update_record('stage_entry', (object) [
            'id' => $existingentry->id,
            'themeid' => $data->themeid,
            'studyyear' => $data->studyyear,
            'structure' => $data->structure,
            'abroad' => !empty($data->abroad) ? 1 : 0,
            'country' => !empty($data->abroad) ? $data->country : '',
            'declaredduration' => $data->declaredduration,
            'timemodified' => time(),
        ]);
        $entry = $DB->get_record('stage_entry', ['id' => $existingentry->id], '*', MUST_EXIST);
    } else {
        $newentryid = stage_register_entry($stage->id, $USER->id, $data->themeid, $data->structure,
            min(array_column($periods, 'datestart')), max(array_column($periods, 'dateend')),
            $data->declaredduration, $data->studyyear, STAGE_CONVENTION_NONE, $data->abroad, $data->country);
        $entry = $DB->get_record('stage_entry', ['id' => $newentryid], '*', MUST_EXIST);
    }

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
    stage_save_entry_periods($entry->id, $periods);

    if ($requireteachervalidation) {
        stage_notify_teacher_convention_pending($stage, $cm, $entry);
    }

    redirect($viewurl, get_string($existingentry ? 'conventionrequested' : 'stageandconventionregistered', 'mod_stage'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($pagetitle);
echo html_writer::link($viewurl, get_string('back'));

if (!$existingentry) {
    echo $OUTPUT->box(get_string('registerstageandconvention_help', 'mod_stage'), 'generalbox mb-3');
    echo stage_render_abroad_rules($stage);
}

$mform->display();

echo $OUTPUT->footer();
