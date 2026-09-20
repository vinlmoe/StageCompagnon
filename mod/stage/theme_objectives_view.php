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
 * Consultation des documents et de la check-list d'objectifs d'une thématique.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$id = required_param('id', PARAM_INT);
$themeid = required_param('themeid', PARAM_INT);
$returnurlparam = optional_param('returnurl', '', PARAM_LOCALURL);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:view', $context);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);
$theme = $DB->get_record('stage_theme', ['id' => $themeid, 'stageid' => $stage->id], '*', MUST_EXIST);

$baseurl = new moodle_url('/mod/stage/theme_objectives_view.php', [
    'id' => $cm->id, 'themeid' => $theme->id, 'returnurl' => $returnurlparam,
]);
$backurl = $returnurlparam !== '' ? new moodle_url($returnurlparam) : new moodle_url('/mod/stage/view.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('themeobjectives', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('themeobjectives', 'mod_stage') . ' - ' . format_string($theme->name));
echo html_writer::link($backurl, get_string('back'), ['class' => 'btn btn-secondary mb-3']);
echo stage_render_theme_objectives_content($context, $cm, $theme);
echo $OUTPUT->footer();
