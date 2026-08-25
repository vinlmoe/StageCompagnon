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

$links = [];
if ($canmanagethemes) {
    $links[] = [get_string('managethemes', 'mod_stage'), new moodle_url('/mod/stage/themes.php', ['id' => $cm->id])];
    $links[] = [get_string('conventiontemplates', 'mod_stage'),
        new moodle_url('/mod/stage/convention_templates.php', ['id' => $cm->id])];
}
if ($canmanageteachers) {
    $links[] = [get_string('manageteachers', 'mod_stage'), new moodle_url('/mod/stage/teachers.php', ['id' => $cm->id])];
}
if ($canmanagethemes) {
    $links[] = [get_string('importfromcourse', 'mod_stage'), new moodle_url('/mod/stage/administration_import.php', ['id' => $cm->id])];
}

echo html_writer::start_tag('ul', ['class' => 'list-unstyled']);
foreach ($links as [$label, $url]) {
    echo html_writer::tag('li', html_writer::link($url, $label, ['class' => 'btn btn-secondary d-block mb-2', 'style' => 'width:fit-content']));
}
echo html_writer::end_tag('ul');

echo $OUTPUT->footer();
