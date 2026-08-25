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
 * Import en masse de l'attribution des enseignants référents depuis un fichier CSV
 * (export Excel), par la DEVE. Chaque ligne remplace l'attribution existante de l'étudiant
 * (comme l'enregistrement depuis teachers.php).
 *
 * Colonnes attendues (avec en-tête) : studentemail;teacher1email;teacher2email
 * - studentemail : adresse de l'étudiant (doit être inscrit au cours)
 * - teacher1email : adresse du premier enseignant référent (doit être inscrit au cours)
 * - teacher2email : adresse d'un second enseignant référent (facultatif)
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
require_capability('mod/stage:manageteachers', $context);

$baseurl = new moodle_url('/mod/stage/teachers_import.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('importteacherscsv', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$students = stage_get_enrolled_students($context);
$studentsbyemail = [];
foreach ($students as $student) {
    $studentsbyemail[core_text::strtolower($student->email)] = $student;
}
$teachers = stage_get_potential_teachers($context);
$teachersbyemail = [];
foreach ($teachers as $teacher) {
    $teachersbyemail[core_text::strtolower($teacher->email)] = $teacher;
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

        $cir = new csv_import_reader(csv_import_reader::get_new_iid('stageteachers'), 'stageteachers');

        if ($cir->load_csv_content($content, 'UTF-8', $delimiter) === false) {
            $uploaderror = $cir->get_error();
            $cir->cleanup(true);
        } else {
            $results = (object) ['assigned' => 0, 'errors' => []];
            $cir->init();
            $linenum = 1;

            while ($row = $cir->next()) {
                $linenum++;
                $studentemail = isset($row[0]) ? trim($row[0]) : '';
                $teacher1email = isset($row[1]) ? trim($row[1]) : '';
                $teacher2email = isset($row[2]) ? trim($row[2]) : '';

                // Ignore les lignes vides et une éventuelle seconde ligne d'en-tête.
                if ($studentemail === '' || core_text::strtolower($studentemail) === 'studentemail') {
                    continue;
                }

                $student = $studentsbyemail[core_text::strtolower($studentemail)] ?? null;
                if (!$student) {
                    $results->errors[] = get_string('importerrorunknownemail', 'mod_stage', (object) [
                        'line' => $linenum, 'email' => $studentemail,
                    ]);
                    continue;
                }

                $teacherids = [];
                foreach ([$teacher1email, $teacher2email] as $teacheremail) {
                    if ($teacheremail === '') {
                        continue;
                    }
                    $teacher = $teachersbyemail[core_text::strtolower($teacheremail)] ?? null;
                    if (!$teacher) {
                        $results->errors[] = get_string('importerrorunknownteacher', 'mod_stage', (object) [
                            'line' => $linenum, 'email' => $teacheremail,
                        ]);
                        continue;
                    }
                    $teacherids[] = $teacher->id;
                }

                stage_set_student_teachers($stage->id, $student->id, $teacherids);
                $results->assigned++;
            }
            $cir->cleanup(true);
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importteacherscsv', 'mod_stage'));
echo html_writer::link(new moodle_url('/mod/stage/teachers.php', ['id' => $cm->id]), get_string('back'));

if ($uploaderror !== null) {
    echo $OUTPUT->notification($uploaderror, \core\output\notification::NOTIFY_ERROR);
}

if ($results) {
    echo $OUTPUT->notification(get_string('importteachersresult', 'mod_stage', $results->assigned),
        \core\output\notification::NOTIFY_SUCCESS);
    if (!empty($results->errors)) {
        echo $OUTPUT->notification(implode(html_writer::empty_tag('br'), $results->errors),
            \core\output\notification::NOTIFY_WARNING);
    }
}

echo $OUTPUT->box(get_string('importteacherscsv_help', 'mod_stage'), 'generalbox mb-3');

echo html_writer::start_tag('form', [
    'method' => 'post', 'action' => $baseurl, 'enctype' => 'multipart/form-data',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'file', 'name' => 'csvfile', 'accept' => '.csv', 'required' => 'required']);
echo html_writer::tag('button', get_string('import', 'mod_stage'),
    ['type' => 'submit', 'class' => 'btn btn-primary ml-2']);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
