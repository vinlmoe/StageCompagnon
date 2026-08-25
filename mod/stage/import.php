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
 * Import en masse des stages depuis un fichier CSV (export Excel), par la DEVE.
 *
 * Colonnes attendues (avec en-tête) : email;theme;structure;datestart;dateend;duration
 * - email : adresse de l'étudiant (doit être inscrit au cours)
 * - theme : nom exact d'une thématique existante
 * - structure : structure d'accueil (facultatif)
 * - datestart, dateend : format AAAA-MM-JJ (facultatif)
 * - duration : durée déclarée en jours
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:registerstages', $context);

$baseurl = new moodle_url('/mod/stage/import.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('importcsv', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$themes = stage_get_themes($stage->id, true);
$students = stage_get_enrolled_students($context);
$studentsbyemail = [];
foreach ($students as $student) {
    $studentsbyemail[core_text::strtolower($student->email)] = $student;
}
$themesbyname = [];
foreach ($themes as $theme) {
    $themesbyname[core_text::strtolower(trim($theme->name))] = $theme;
}

$results = null;
$uploaderror = null;

if (data_submitted() && confirm_sesskey()) {
    $upload = $_FILES['csvfile'] ?? null;

    if (empty($upload) || $upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
        $uploaderror = get_string('importerrorupload', 'mod_stage');
    } else {
        $content = file_get_contents($upload['tmp_name']);
        // Excel francophone exporte en points-virgules ; on accepte aussi la virgule.
        $delimiter = (strpos($content, ';') !== false) ? 'semicolon' : 'comma';

        $cir = new csv_import_reader(csv_import_reader::get_new_iid('stage'), 'stage');

        if ($cir->load_csv_content($content, 'UTF-8', $delimiter) === false) {
            $uploaderror = $cir->get_error();
            $cir->cleanup(true);
        } else {
            $results = (object) ['created' => 0, 'errors' => []];
            $records = [];
            $cir->init();
            // La première ligne est consommée comme en-tête par load_csv_content().
            $linenum = 1;

            // Doublons détectés contre les stages déjà enregistrés, et entre les lignes
            // du fichier lui-même (un même étudiant répété deux fois sur la même thématique).
            $existingpairs = stage_get_existing_theme_pairs($stage->id);

            while ($row = $cir->next()) {
                $linenum++;
                // Colonnes attendues : email, theme, structure, datestart, dateend, duration.
                $email = isset($row[0]) ? trim($row[0]) : '';
                $themename = isset($row[1]) ? trim($row[1]) : '';
                $structure = isset($row[2]) ? trim($row[2]) : '';
                $datestartraw = isset($row[3]) ? trim($row[3]) : '';
                $dateendraw = isset($row[4]) ? trim($row[4]) : '';
                $duration = isset($row[5]) ? (int) trim($row[5]) : 0;

                // Ignore les lignes vides et une éventuelle seconde ligne d'en-tête.
                if ($email === '' || $themename === '' || core_text::strtolower($email) === 'email') {
                    continue;
                }

                $student = $studentsbyemail[core_text::strtolower($email)] ?? null;
                if (!$student) {
                    $results->errors[] = get_string('importerrorunknownemail', 'mod_stage', (object) [
                        'line' => $linenum, 'email' => $email,
                    ]);
                    continue;
                }

                $theme = $themesbyname[core_text::strtolower($themename)] ?? null;
                if (!$theme) {
                    $results->errors[] = get_string('importerrorunknowntheme', 'mod_stage', (object) [
                        'line' => $linenum, 'theme' => $themename,
                    ]);
                    continue;
                }

                $start = $datestartraw ? strtotime($datestartraw) : false;
                $end = $dateendraw ? strtotime($dateendraw) : false;

                $pairkey = stage_duplicate_key($student->id, $theme->id, $start ?: null, $end ?: null);
                if (isset($existingpairs[$pairkey])) {
                    $results->errors[] = get_string('importerrorduplicate', 'mod_stage', (object) [
                        'line' => $linenum, 'email' => $email, 'theme' => $themename,
                    ]);
                    continue;
                }
                $existingpairs[$pairkey] = true;

                $records[] = (object) [
                    'stageid' => $stage->id,
                    'userid' => $student->id,
                    'themeid' => $theme->id,
                    'structure' => $structure,
                    'datestart' => $start ?: null,
                    'dateend' => $end ?: null,
                    'declaredduration' => $duration,
                    'retainedduration' => 0,
                    'status' => STAGE_STATUS_ENREGISTRE,
                    'timecreated' => time(),
                    'timemodified' => time(),
                ];
            }
            $cir->cleanup(true);

            // Insertion groupée : un import de plusieurs centaines de lignes ne doit pas
            // déclencher autant de requêtes individuelles.
            if ($records) {
                $DB->insert_records('stage_entry', $records);
                $results->created = count($records);
            }
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importcsv', 'mod_stage'));
echo html_writer::link(new moodle_url('/mod/stage/register.php', ['id' => $cm->id]), get_string('back'));

if ($uploaderror !== null) {
    echo $OUTPUT->notification($uploaderror, \core\output\notification::NOTIFY_ERROR);
}

if ($results) {
    echo $OUTPUT->notification(get_string('importresult', 'mod_stage', $results->created),
        \core\output\notification::NOTIFY_SUCCESS);
    if (!empty($results->errors)) {
        echo $OUTPUT->notification(implode(html_writer::empty_tag('br'), $results->errors),
            \core\output\notification::NOTIFY_WARNING);
    }
}

echo $OUTPUT->box(get_string('importcsv_help', 'mod_stage'), 'generalbox mb-3');

echo html_writer::start_tag('form', [
    'method' => 'post', 'action' => $baseurl, 'enctype' => 'multipart/form-data',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'file', 'name' => 'csvfile', 'accept' => '.csv', 'required' => 'required']);
echo html_writer::tag('button', get_string('import', 'mod_stage'),
    ['type' => 'submit', 'class' => 'btn btn-primary ml-2']);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
