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
 * Page d'administration de l'activité (DEVE) : regroupe les pages de paramétrage utilisées
 * ponctuellement — thématiques, gabarits de convention, attribution des enseignants référents et
 * import depuis une autre instance.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

$canmanagethemes = has_capability('mod/stage:managethemes', $context);
$canmanageteachers = has_capability('mod/stage:manageteachers', $context);
if (!$canmanagethemes && !$canmanageteachers) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('administration', 'mod_stage'));
}

$baseurl = new moodle_url('/mod/stage/administration.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('administration', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('administration', 'mod_stage'));
echo html_writer::link(new moodle_url('/mod/stage/view.php', ['id' => $cm->id]), get_string('back'));

// Les pages de paramétrage sont regroupées par objet (ce que l'étudiant doit faire, comment la
// convention est produite, qui suit les étudiants, mise en route) plutôt que présentées en une
// liste de boutons indifférenciée, et chacune est accompagnée de ce à quoi elle sert : ces pages
// sont visitées ponctuellement, rarement par la même personne, et leurs intitulés seuls ne
// suffisaient pas à savoir laquelle ouvrir.
$sections = [];
if ($canmanagethemes) {
    $sections[] = [get_string('adminsectionrequirements', 'mod_stage'), [
        [
            get_string('managethemes', 'mod_stage'),
            get_string('managethemes_desc', 'mod_stage'),
            new moodle_url('/mod/stage/themes.php', ['id' => $cm->id]),
        ],
        [
            get_string('manageyearrequirements', 'mod_stage'),
            get_string('manageyearrequirements_desc', 'mod_stage'),
            new moodle_url('/mod/stage/year_requirements.php', ['id' => $cm->id]),
        ],
    ]];
    $sections[] = [get_string('adminsectionconventions', 'mod_stage'), [
        [
            get_string('conventiontemplates', 'mod_stage'),
            get_string('conventiontemplates_desc', 'mod_stage'),
            new moodle_url('/mod/stage/convention_templates.php', ['id' => $cm->id]),
        ],
    ]];
    $sections[] = [get_string('adminsectionnotifications', 'mod_stage'), [
        [
            get_string('notifications', 'mod_stage'),
            get_string('notifications_desc', 'mod_stage'),
            new moodle_url('/mod/stage/notifications.php', ['id' => $cm->id]),
        ],
    ]];
}
if ($canmanageteachers) {
    $sections[] = [get_string('adminsectionteachers', 'mod_stage'), [
        [
            get_string('manageteachers', 'mod_stage'),
            get_string('manageteachers_desc', 'mod_stage'),
            new moodle_url('/mod/stage/teachers.php', ['id' => $cm->id]),
        ],
    ]];
}
$setuplinks = [];
if ($canmanagethemes) {
    $setuplinks[] = [
        get_string('importfromcourse', 'mod_stage'),
        get_string('importfromcourse_desc', 'mod_stage'),
        new moodle_url('/mod/stage/administration_import.php', ['id' => $cm->id]),
    ];
}
if (has_capability('mod/stage:registerstages', $context)) {
    if (has_capability('mod/stage:validatedeve', $context)) {
        $setuplinks[] = [
            get_string('historicalimport', 'mod_stage'),
            get_string('historicalimport_desc', 'mod_stage'),
            new moodle_url('/mod/stage/import_historical.php', ['id' => $cm->id]),
        ];
    }
    $setuplinks[] = [
        get_string('transferstudent', 'mod_stage'),
        get_string('transferstudent_desc', 'mod_stage'),
        new moodle_url('/mod/stage/transfer.php', ['id' => $cm->id]),
    ];
}
if (!empty($setuplinks)) {
    $sections[] = [get_string('adminsectionsetup', 'mod_stage'), $setuplinks];
}

foreach ($sections as [$sectiontitle, $entries]) {
    echo $OUTPUT->heading($sectiontitle, 4);
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->head = [get_string('adminsectionpage', 'mod_stage'), get_string('adminsectionpurpose', 'mod_stage')];
    foreach ($entries as [$label, $description, $url]) {
        $table->data[] = [
            html_writer::link($url, $label, ['class' => 'btn btn-secondary']),
            $description,
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
