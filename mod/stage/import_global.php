<?php
// This file is part of Moodle - http://moodle.org/

/** Restauration des stages depuis l'export Excel global du module. */

use mod_stage\local\global_export_importer;

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:registerstages', $context);
require_capability('mod/stage:validatedeve', $context);

$url = new moodle_url('/mod/stage/import_global.php', ['id' => $cm->id]);
$backurl = new moodle_url('/mod/stage/register.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('globalimport', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));

$error = null;
$warnings = [];
$preview = null;

$normal = function($value) {
    return global_export_importer::normalize((string) $value);
};
$optionvalue = function($label, array $options, $default) use ($normal) {
    $needle = $normal($label);
    foreach ($options as $value => $optionlabel) {
        if ($needle === $normal($optionlabel) || $needle === $normal($value)) {
            return $value;
        }
    }
    return $default;
};
$yes = function($value) use ($normal) {
    return in_array($normal($value), ['1', 'yes', 'oui', 'true'], true);
};

if (optional_param('confirmimport', 0, PARAM_INT) && confirm_sesskey()) {
    $pending = $SESSION->stage_global_import[$stage->id] ?? null;
    unset($SESSION->stage_global_import[$stage->id]);
    if (!$pending || empty($pending['records'])) {
        $error = get_string('globalimportexpired', 'mod_stage');
    } else {
        $transaction = $DB->start_delegated_transaction();
        foreach ($pending['records'] as $saved) {
            $saved = (object) $saved;
            $entry = (object) $saved->entry;
            $entry->stageid = $stage->id;
            $entry->timecreated = $entry->timecreated ?: time();
            $entry->timemodified = $entry->timemodified ?: $entry->timecreated;
            $entryid = $DB->insert_record('stage_entry', $entry);
            if (!empty($saved->detail)) {
                $detail = (object) $saved->detail;
                $detail->entryid = $entryid;
                $detail->timecreated = $entry->timecreated;
                $detail->timemodified = $entry->timemodified;
                $DB->insert_record('stage_convention_detail', $detail);
            }
            if ($entry->datestart && $entry->dateend) {
                stage_save_entry_periods($entryid, [(object) [
                    'datestart' => $entry->datestart, 'dateend' => $entry->dateend,
                ]]);
            }
        }
        $transaction->allow_commit();
        redirect($backurl, get_string('globalimportdone', 'mod_stage', count($pending['records'])), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
}

if (data_submitted() && optional_param('previewimport', 0, PARAM_INT) && confirm_sesskey()) {
    unset($SESSION->stage_global_import[$stage->id]);
    $upload = $_FILES['xlsxfile'] ?? null;
    if (!$upload || $upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
        $error = get_string('importerrorupload', 'mod_stage');
    } else {
        try {
            $parsed = global_export_importer::read($upload['tmp_name']);
            $students = [];
            foreach (stage_get_enrolled_students($context) as $student) {
                $students[$normal($student->email)] = $student;
            }
            $themes = [];
            foreach (stage_get_themes($stage->id, true) as $theme) {
                $themes[$normal($theme->name)] = $theme;
            }
            $people = [];
            foreach (get_enrolled_users($context) as $person) {
                $people[$normal(fullname($person))] = $person;
            }
            $templates = [];
            foreach (stage_get_convention_templates($stage->id) as $template) {
                $templates[$normal($template->name)] = $template;
            }
            $statusoptions = [];
            foreach ([STAGE_STATUS_ANNULE, STAGE_STATUS_NON_VALIDE, STAGE_STATUS_ENREGISTRE,
                    STAGE_STATUS_EVAL_ETUDIANT, STAGE_STATUS_EVAL_ENSEIGNANT, STAGE_STATUS_VALIDE_DEVE] as $value) {
                $statusoptions[$value] = stage_status_label($value);
            }
            $conventionoptions = [];
            foreach ([STAGE_CONVENTION_REJECTED, STAGE_CONVENTION_NONE, STAGE_CONVENTION_REQUESTED,
                    STAGE_CONVENTION_EDITED, STAGE_CONVENTION_SIGNED, STAGE_CONVENTION_SIGNVET,
                    STAGE_CONVENTION_TEACHERPENDING, STAGE_CONVENTION_EXEMPT] as $value) {
                $conventionoptions[$value] = stage_convention_status_label($value);
            }
            $records = [];
            foreach ($parsed['records'] as $raw) {
                $student = $students[$normal($raw->email)] ?? null;
                $theme = $themes[$normal($raw->theme)] ?? null;
                if (!$student || !$theme) {
                    $warnings[] = get_string(!$student ? 'globalimportunknownstudent' : 'globalimportunknowntheme',
                        'mod_stage', (object) ['line' => $raw->line, 'value' => !$student ? $raw->email : $raw->theme]);
                    continue;
                }
                $duplicate = $DB->record_exists('stage_entry', ['stageid' => $stage->id, 'userid' => $student->id,
                    'themeid' => $theme->id, 'datestart' => $raw->datestart, 'dateend' => $raw->dateend]);
                if ($duplicate) {
                    $warnings[] = get_string('globalimportduplicate', 'mod_stage', $raw->line);
                    continue;
                }
                $personid = function($field) use ($raw, $people, $normal) {
                    return !empty($raw->$field) && isset($people[$normal($raw->$field)])
                        ? $people[$normal($raw->$field)]->id : null;
                };
                $studyyear = 0;
                for ($year = 1; $year <= 6; $year++) {
                    if ($normal($raw->studyyear ?? '') === $normal(stage_studyyear_label($year))) {
                        $studyyear = $year;
                    }
                }
                $template = !empty($raw->conventiontemplatename)
                    ? ($templates[$normal($raw->conventiontemplatename)] ?? null) : null;
                $entry = [
                    'themeid' => $theme->id, 'userid' => $student->id, 'studyyear' => $studyyear,
                    'structure' => $raw->structure ?? '', 'abroad' => $yes($raw->abroad ?? ''),
                    'country' => $raw->country ?? '', 'datestart' => $raw->datestart,
                    'dateend' => $raw->dateend, 'declaredduration' => (int) ($raw->declaredduration ?? 0),
                    'retainedduration' => (int) ($raw->retainedduration ?? 0),
                    'status' => $optionvalue($raw->status ?? '', $statusoptions, STAGE_STATUS_ENREGISTRE),
                    'studentselfeval' => $raw->studentselfeval ?? '', 'teacherid' => $personid('evaluatedby'),
                    'teachereval' => $raw->teachereval ?? '', 'teachertime' => $raw->teachertime ?? null,
                    'tutoreval' => $raw->tutoreval ?? '', 'tutortime' => $raw->tutortime ?? null,
                    'tutorbypassed' => $yes($raw->tutorbypassed ?? ''), 'deveuserid' => $personid('devevalidatedby'),
                    'devecomment' => $raw->devecomment ?? '', 'devetime' => $raw->devetime ?? null,
                    'conventiontemplateid' => $template ? $template->id : null,
                    'conventionstatus' => $optionvalue($raw->conventionstatus ?? '', $conventionoptions, STAGE_CONVENTION_NONE),
                    'conventionrejectedby' => $personid('conventionrejectedby'),
                    'conventionrejecttime' => $raw->conventionrejecttime ?? null,
                    'conventionrejectcomment' => $raw->conventionrejectcomment ?? '',
                    'conventionrequesttime' => $raw->conventionrequesttime ?? null,
                    'conventionteachervalidatedby' => $personid('conventionteachervalidatedby'),
                    'conventionteachervalidatetime' => $raw->conventionteachervalidatetime ?? null,
                    'conventioneditedby' => $personid('conventioneditedby'), 'conventionedittime' => $raw->conventionedittime ?? null,
                    'conventionsignedby' => $personid('conventionsignedby'), 'conventionsigntime' => $raw->conventionsigntime ?? null,
                    'cancelledby' => $personid('cancelledby'), 'canceltime' => $raw->canceltime ?? null,
                    'cancelcomment' => $raw->cancelcomment ?? '', 'timecreated' => $raw->timecreated ?? time(),
                    'timemodified' => $raw->timemodified ?? time(),
                ];
                $detailfields = ['studentaddress', 'studentphone', 'hostaddress', 'hostrepresentative',
                    'hostrepresentativetitle', 'hostservice', 'hostphone', 'hostemail', 'hostlocation', 'tutorname',
                    'tutorfunction', 'tutorphone', 'tutoremail', 'othermodality', 'gratificationamount', 'leavemodalities'];
                $detail = ['stagetype' => $optionvalue($raw->stagetype ?? '', stage_convention_stagetype_options(), 'obligatoire'),
                    'yearsituation' => $optionvalue($raw->yearsituation ?? '', stage_convention_yearsituation_options(), 'normal'),
                    'studentbirthdate' => $raw->studentbirthdate ?? null, 'referentteacherid' => $personid('referentteacher'),
                    'nightpresence' => $yes($raw->nightpresence ?? ''), 'sundaypresence' => $yes($raw->sundaypresence ?? ''),
                    'holidaypresence' => $yes($raw->holidaypresence ?? ''), 'homebased' => $yes($raw->homebased ?? ''),
                    'hasleave' => $yes($raw->hasleave ?? ''), 'leavedays' => (int) ($raw->leavedays ?? 0)];
                foreach ($detailfields as $field) {
                    $detail[$field] = $raw->$field ?? '';
                }
                $records[] = ['entry' => $entry, 'detail' => $detail, 'student' => fullname($student),
                    'theme' => format_string($theme->name)];
            }
            $preview = $records;
            $SESSION->stage_global_import[$stage->id] = ['records' => $records];
        } catch (Throwable $exception) {
            $error = $exception instanceof moodle_exception ? $exception->getMessage()
                : get_string('globalimportinvalid', 'mod_stage');
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('globalimport', 'mod_stage'));
echo html_writer::link($backurl, get_string('back'));
echo $OUTPUT->box(get_string('globalimport_help', 'mod_stage'), 'generalbox my-3');
if ($error) {
    echo $OUTPUT->notification($error, \core\output\notification::NOTIFY_ERROR);
}
if ($warnings) {
    echo $OUTPUT->notification(implode(html_writer::empty_tag('br'), array_map('s', $warnings)),
        \core\output\notification::NOTIFY_WARNING);
}
if ($preview !== null) {
    echo $OUTPUT->heading(get_string('globalimportpreview', 'mod_stage', count($preview)), 4);
    if ($preview) {
        $table = new html_table();
        $table->head = [get_string('student', 'mod_stage'), get_string('theme', 'mod_stage'),
            get_string('datestart', 'mod_stage'), get_string('status', 'mod_stage')];
        foreach ($preview as $item) {
            $entry = (object) $item['entry'];
            $table->data[] = [s($item['student']), s($item['theme']),
                $entry->datestart ? userdate($entry->datestart, get_string('strftimedateshort')) : '-',
                stage_status_label($entry->status)];
        }
        echo html_writer::table($table);
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirmimport', 'value' => 1]);
        echo html_writer::tag('button', get_string('globalimportconfirm', 'mod_stage'),
            ['type' => 'submit', 'class' => 'btn btn-primary']);
        echo html_writer::end_tag('form');
    }
} else {
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url, 'enctype' => 'multipart/form-data']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'previewimport', 'value' => 1]);
    echo html_writer::empty_tag('input', ['type' => 'file', 'name' => 'xlsxfile', 'accept' => '.xlsx',
        'required' => 'required', 'class' => 'form-control mb-3']);
    echo html_writer::tag('button', get_string('globalimportpreviewbutton', 'mod_stage'),
        ['type' => 'submit', 'class' => 'btn btn-primary']);
    echo html_writer::end_tag('form');
}
echo $OUTPUT->footer();
