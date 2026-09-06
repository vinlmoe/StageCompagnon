<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Import de l'ancien classeur Excel « Suivi des STAGES et EP ».
 *
 * @package mod_stage
 */

use mod_stage\local\historical_importer;

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

$baseurl = new moodle_url('/mod/stage/import_historical.php', ['id' => $cm->id]);
$backurl = new moodle_url('/mod/stage/register.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('historicalimport', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$themes = stage_get_themes($stage->id, true);
$themeoptions = [];
foreach ($themes as $theme) {
    $themeoptions[$theme->id] = format_string($theme->name);
}
$warnings = [];
$preview = null;
$error = null;
$unmatchedthemes = [];

/**
 * Résout étudiants, enseignants et thématiques après lecture du classeur. Les thématiques non
 * reconnues sont conservées pour demander une correspondance, et non plus écartées.
 */
$resolvepreview = function(array $rawrecords, int $epthemeid, array $thememap = [], array $basewarnings = []) use (
        $context, $themes, $stage, $DB) {
    $resolvedwarnings = $basewarnings;
    $studentsbyemail = [];
    foreach (stage_get_enrolled_students($context) as $student) {
        $studentsbyemail[core_text::strtolower(trim($student->email))] = $student;
    }
    $teachersbyname = [];
    foreach (stage_get_potential_teachers($context) as $teacher) {
        $teachersbyname[stage_normalize_name(fullname($teacher))] = $teacher;
    }
    $themesbyname = [];
    foreach ($themes as $theme) {
        $themesbyname[stage_normalize_name($theme->name)] = $theme;
    }

    $resolved = [];
    $unmatched = [];
    $seen = [];
    foreach ($rawrecords as $rawrecord) {
        $record = (object) $rawrecord;
        $student = $studentsbyemail[core_text::strtolower(trim($record->email))] ?? null;
        if (!$student) {
            $resolvedwarnings[] = get_string('historicalimportunknownemail', 'mod_stage', (object) [
                'source' => $record->source, 'email' => $record->email,
            ]);
            continue;
        }

        if ($record->stagetype === 'complementaire') {
            $theme = $themes[$epthemeid] ?? null;
        } else {
            $themekey = sha1($record->themename);
            $mappedid = (int) ($thememap[$themekey] ?? 0);
            $theme = $themesbyname[stage_normalize_name($record->themename)] ?? null;
            if (!$theme && $mappedid && isset($themes[$mappedid])) {
                $theme = $themes[$mappedid];
            }
            if (!$theme) {
                $unmatched[$themekey] = $record->themename;
                continue;
            }
        }

        $fingerprint = implode('|', [$student->id, $theme->id, $record->structure,
            $record->studyyear, $record->duration]);
        if (isset($seen[$fingerprint]) || $DB->record_exists('stage_entry', [
                'stageid' => $stage->id,
                'userid' => $student->id,
                'themeid' => $theme->id,
                'structure' => $record->structure,
                'studyyear' => $record->studyyear,
                'declaredduration' => $record->duration,
            ])) {
            $resolvedwarnings[] = get_string('historicalimportduplicate', 'mod_stage', $record->source);
            continue;
        }
        $seen[$fingerprint] = true;
        $record->userid = $student->id;
        $record->themeid = $theme->id;
        $teacher = $record->teachername !== ''
            ? ($teachersbyname[stage_normalize_name($record->teachername)] ?? null) : null;
        $record->teacherid = $teacher ? $teacher->id : 0;
        if ($record->teachername !== '' && !$teacher) {
            $resolvedwarnings[] = get_string('historicalimportunknownteacher', 'mod_stage', (object) [
                'source' => $record->source, 'teacher' => $record->teachername,
            ]);
        }
        $record->studentlabel = fullname($student);
        $record->themelabel = format_string($theme->name);
        $resolved[] = $record;
    }
    return [$resolved, $resolvedwarnings, $unmatched];
};

// La prévisualisation sérialisée ne contient que des valeurs du classeur et des identifiants déjà
// résolus côté serveur. Elle est remplacée à chaque nouveau téléversement et supprimée après
// confirmation, afin qu'une actualisation ne puisse pas rejouer l'import.
if (optional_param('confirmimport', 0, PARAM_INT) && confirm_sesskey()) {
    $pending = $SESSION->stage_historical_import[$stage->id] ?? null;
    unset($SESSION->stage_historical_import[$stage->id]);
    if (!$pending || empty($pending['ready']) || empty($pending['records'])) {
        $error = get_string('historicalimportexpired', 'mod_stage');
    } else {
        $transaction = $DB->start_delegated_transaction();
        $created = 0;
        foreach ($pending['records'] as $candidate) {
            $candidate = (object) $candidate;
            $entryid = stage_register_entry(
                $stage->id,
                $candidate->userid,
                $candidate->themeid,
                $candidate->structure,
                $candidate->datestart ?: null,
                $candidate->dateend ?: null,
                $candidate->duration,
                $candidate->studyyear,
                STAGE_CONVENTION_NONE
            );
            $entry = $DB->get_record('stage_entry', ['id' => $entryid], '*', MUST_EXIST);
            if (!empty($candidate->teacherid)) {
                $entry->teacherid = $candidate->teacherid;
                $entry->teachertime = time();
                $entry->teachereval = get_string('historicalimportcomment', 'mod_stage');
                $assigned = array_keys(stage_get_student_teachers($stage->id, $candidate->userid));
                $assigned[] = $candidate->teacherid;
                stage_set_student_teachers($stage->id, $candidate->userid, $assigned);
            }
            stage_apply_deve_validation($entry, $USER->id, $candidate->duration,
                get_string('historicalimportcomment', 'mod_stage'));
            stage_set_entry_stagetype($entryid, $candidate->stagetype);
            $created++;
        }
        $transaction->allow_commit();
        redirect($backurl, get_string('historicalimportdone', 'mod_stage', $created), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
}

// Deuxième étape éventuelle : la DEVE choisit une thématique Moodle pour chaque intitulé de
// colonne Excel inconnu. Le classeur n'a pas besoin d'être téléversé une seconde fois.
if (optional_param('mapthemes', 0, PARAM_INT) && confirm_sesskey()) {
    $pending = $SESSION->stage_historical_import[$stage->id] ?? null;
    if (!$pending || empty($pending['rawrecords'])) {
        $error = get_string('historicalimportexpired', 'mod_stage');
    } else {
        $thememap = optional_param_array('thememap', [], PARAM_INT);
        [$preview, $warnings, $unmatchedthemes] = $resolvepreview(
            $pending['rawrecords'], (int) $pending['epthemeid'], $thememap, $pending['basewarnings'] ?? []);
        if ($unmatchedthemes) {
            $error = get_string('historicalimportmapallthemes', 'mod_stage');
        }
        $SESSION->stage_historical_import[$stage->id]['records'] = array_map(function($record) {
            return (array) $record;
        }, $preview);
        $SESSION->stage_historical_import[$stage->id]['ready'] = empty($unmatchedthemes);
    }
}

if (data_submitted() && optional_param('previewimport', 0, PARAM_INT) && confirm_sesskey()) {
    unset($SESSION->stage_historical_import[$stage->id]);
    $upload = $_FILES['xlsxfile'] ?? null;
    $epthemeid = optional_param('epthemeid', 0, PARAM_INT);
    if (!$upload || $upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
        $error = get_string('importerrorupload', 'mod_stage');
    } else if (!$epthemeid || !isset($themes[$epthemeid])) {
        $error = get_string('historicalimportselecteptheme', 'mod_stage');
    } else {
        try {
            $parsed = historical_importer::read($upload['tmp_name']);
            [$preview, $warnings, $unmatchedthemes] = $resolvepreview(
                $parsed['records'], $epthemeid, [], $parsed['warnings']);
            $SESSION->stage_historical_import[$stage->id] = [
                'rawrecords' => array_map(function($record) {
                    return (array) $record;
                }, $parsed['records']),
                'basewarnings' => $parsed['warnings'],
                'epthemeid' => $epthemeid,
                'records' => array_map(function($record) {
                    return (array) $record;
                }, $preview),
                'ready' => empty($unmatchedthemes),
            ];
        } catch (moodle_exception $exception) {
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            $error = get_string('historicalimportinvalidfile', 'mod_stage');
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('historicalimport', 'mod_stage'));
echo html_writer::link($backurl, get_string('back'));
echo $OUTPUT->box(get_string('historicalimport_help', 'mod_stage'), 'generalbox my-3');

if ($error !== null) {
    echo $OUTPUT->notification($error, \core\output\notification::NOTIFY_ERROR);
}
if (!empty($warnings)) {
    echo $OUTPUT->heading(get_string('historicalimportwarnings', 'mod_stage', count($warnings)), 4);
    echo $OUTPUT->notification(implode(html_writer::empty_tag('br'), array_map('s', $warnings)),
        \core\output\notification::NOTIFY_WARNING);
}

if ($unmatchedthemes) {
    echo $OUTPUT->heading(get_string('historicalimportmapthemes', 'mod_stage'), 4);
    echo $OUTPUT->box(get_string('historicalimportmapthemes_help', 'mod_stage'), 'generalbox mb-3');
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'mapthemes', 'value' => 1]);
    $mappingtable = new html_table();
    $mappingtable->head = [get_string('historicalimportexceltheme', 'mod_stage'),
        get_string('historicalimportmoodletheme', 'mod_stage')];
    foreach ($unmatchedthemes as $themekey => $themename) {
        $mappingtable->data[] = [s($themename), html_writer::select(
            $themeoptions,
            'thememap[' . $themekey . ']',
            0,
            ['' => get_string('choosedots')],
            ['required' => 'required', 'class' => 'form-control']
        )];
    }
    echo html_writer::table($mappingtable);
    echo html_writer::tag('button', get_string('historicalimportapplymapping', 'mod_stage'),
        ['type' => 'submit', 'class' => 'btn btn-primary']);
    echo html_writer::end_tag('form');
}

if ($preview !== null) {
    echo $OUTPUT->heading(get_string('historicalimportpreview', 'mod_stage', count($preview)), 4);
    if ($preview) {
        $table = new html_table();
        $table->head = [get_string('student', 'mod_stage'), get_string('theme', 'mod_stage'),
            get_string('studyyear', 'mod_stage'), get_string('structure', 'mod_stage'),
            get_string('retainedduration', 'mod_stage'), get_string('conventionstagetype', 'mod_stage')];
        foreach ($preview as $record) {
            $dates = ($record->datestart && $record->dateend)
                ? userdate($record->datestart, get_string('strftimedateshort')) . ' – '
                    . userdate($record->dateend, get_string('strftimedateshort'))
                : get_string('historicalimportnodates', 'mod_stage');
            $table->data[] = [s($record->studentlabel), s($record->themelabel),
                stage_studyyear_label($record->studyyear), s($record->structure) . html_writer::empty_tag('br')
                    . html_writer::span($dates, 'text-muted'), $record->duration,
                get_string('conventionstagetype_' . $record->stagetype, 'mod_stage')];
        }
        echo html_writer::table($table);
        if (!$unmatchedthemes) {
            echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirmimport', 'value' => 1]);
            echo html_writer::tag('button', get_string('historicalimportconfirm', 'mod_stage'),
                ['type' => 'submit', 'class' => 'btn btn-primary']);
            echo html_writer::end_tag('form');
        }
    }
} else {
    echo html_writer::start_tag('form', [
        'method' => 'post', 'action' => $baseurl, 'enctype' => 'multipart/form-data',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'previewimport', 'value' => 1]);
    echo html_writer::tag('label', get_string('historicalimportfile', 'mod_stage'), ['for' => 'xlsxfile']);
    echo html_writer::empty_tag('input', [
        'type' => 'file', 'name' => 'xlsxfile', 'id' => 'xlsxfile', 'accept' => '.xlsx',
        'required' => 'required', 'class' => 'form-control mb-3',
    ]);
    echo html_writer::tag('label', get_string('historicalimporteptheme', 'mod_stage'), ['for' => 'epthemeid']);
    echo html_writer::select($themeoptions, 'epthemeid', 0, ['' => get_string('choosedots')],
        ['id' => 'epthemeid', 'class' => 'form-control mb-3', 'required' => 'required']);
    echo html_writer::tag('button', get_string('historicalimportpreviewbutton', 'mod_stage'),
        ['type' => 'submit', 'class' => 'btn btn-primary']);
    echo html_writer::end_tag('form');
}

echo $OUTPUT->footer();
