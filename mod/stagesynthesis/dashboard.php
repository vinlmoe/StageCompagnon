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
 * Tableau de pilotage : reprend mod_stage/dashboard.php (avancement des validations, avec accès à
 * la situation détaillée de chaque étudiant), combiné sur toutes les activités "Gestion des
 * stages" liées où l'utilisateur connecté est enseignant référent. Page d'atterrissage de
 * l'activité (voir view.php, qui y redirige) : mêmes conventions que sur l'écran d'origine.
 *
 * @package   mod_stagesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stagesynthesis/locallib.php');

$id = required_param('id', PARAM_INT);
$studentid = optional_param('studentid', 0, PARAM_INT);
$studentcmid = optional_param('cmid', 0, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$tsort = optional_param('tsort', 'student', PARAM_ALPHA);
$tdir = optional_param('tdir', 'ASC', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);

$cm = get_coursemodule_from_id('stagesynthesis', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stagesynthesis = $DB->get_record('stagesynthesis', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stagesynthesis:view', $context);

$baseurl = new moodle_url('/mod/stagesynthesis/dashboard.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stagesynthesis->name) . ' - ' . get_string('pilotage', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$activelinks = stagesynthesis_get_active_links($stagesynthesis->id, $USER->id);

// Situation détaillée d'un étudiant donné : délègue entièrement à stage_print_student_dashboard(),
// qui affiche déjà tout (synthèse, thématiques, mobilité...) pour une activité mod_stage donnée --
// seul le repérage de l'activité et de l'étudiant dans le périmètre change ici.
if ($studentid) {
    if (!isset($activelinks[$studentcmid])) {
        throw new moodle_exception('nopermissions', 'error', '', get_string('pilotage', 'mod_stage'));
    }
    $link = $activelinks[$studentcmid];
    if (!in_array($studentid, $link->assignedids, true)) {
        throw new moodle_exception('nopermissions', 'error', '', get_string('pilotage', 'mod_stage'));
    }
    $student = $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('pilotage', 'mod_stage') . ' - ' . fullname($student));
    echo html_writer::link($baseurl, get_string('back'));

    stage_print_student_dashboard($link->stage, $student->id, $link->cm, false, true);

    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($stagesynthesis->name));
echo html_writer::link(
    new moodle_url('/mod/stagesynthesis/entries.php', ['id' => $cm->id]),
    get_string('teachervalidation', 'mod_stage')
);

if ($stagesynthesis->intro) {
    echo $OUTPUT->box(format_module_intro('stagesynthesis', $stagesynthesis, $cm->id), 'generalbox mod_introbox');
}

echo stagesynthesis_render_managelinks_notice($stagesynthesis, $cm, $context);

if (empty($activelinks)) {
    echo $OUTPUT->notification(get_string('nostudents', 'mod_stagesynthesis'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$listurl = new moodle_url($baseurl, ['search' => $search, 'tsort' => $tsort, 'tdir' => $tdir]);
echo stage_render_pilotage_search_form($baseurl, $cm->id, $search);

$rows = stagesynthesis_get_pilotage_rows($activelinks);
[$rows, $sortkey] = stage_sort_pilotage_rows($rows, $search, $tsort, $tdir, [
    'course' => function ($row) {
        return core_text::strtolower($row->coursename);
    },
]);

if (empty($rows)) {
    echo $OUTPUT->notification(get_string('nostudents', 'mod_stage'), 'info');
} else {
    // Colonnes communes de mod_stage, avec le cours en tête et l'année d'étude après l'étudiant :
    // ce sont les deux informations qui distinguent les lignes d'une vue multi-promotions.
    $common = stage_pilotage_columns($listurl, $sortkey, $tdir, function ($row) use ($cm) {
        return new moodle_url(
            '/mod/stagesynthesis/dashboard.php',
            ['id' => $cm->id, 'studentid' => $row->user->id, 'cmid' => $row->cmid]
        );
    });
    $columns = [
        'course' => [
            'head' => stage_sort_header(get_string('course'), 'course', $listurl, $sortkey, $tdir),
            'cell' => function ($row) {
                return format_string($row->coursename);
            },
        ],
        'student' => $common['student'],
        'studyyear' => [
            'head' => get_string('currentstudyyear', 'mod_stage'),
            'cell' => function ($row) {
                return stage_studyyear_label($row->currentstudyyear);
            },
        ],
    ] + $common;
    echo $OUTPUT->render(new \mod_stage\output\pilotage_table($columns, $rows, $page, $listurl));
}

echo $OUTPUT->footer();
