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
 * Ajout ou modification d'un organisme proposé pour une thématique (DEVE).
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/placement_offer_form.php');

use mod_stage\form\placement_offer_form;

$id = required_param('id', PARAM_INT);
$themeid = required_param('themeid', PARAM_INT);
$offerid = optional_param('offerid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:managethemes', $context);

$theme = $DB->get_record('stage_theme', ['id' => $themeid, 'stageid' => $stage->id], '*', MUST_EXIST);
$offer = $offerid
    ? $DB->get_record('stage_placement_offer', ['id' => $offerid, 'themeid' => $theme->id], '*', MUST_EXIST) : null;

$listurl = new moodle_url('/mod/stage/placement_offers.php', ['id' => $cm->id, 'themeid' => $theme->id]);
$baseurl = new moodle_url('/mod/stage/placement_offer.php', ['id' => $cm->id, 'themeid' => $theme->id, 'offerid' => $offerid]);
$pagetitle = get_string($offer ? 'placementeditoffer' : 'placementaddoffer', 'mod_stage');
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . $pagetitle);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$mform = new placement_offer_form($baseurl);
$formdata = $offer ? clone $offer : new stdClass();
$formdata->id = $cm->id;
$formdata->themeid = $theme->id;
$formdata->offerid = $offerid;
$mform->set_data($formdata);

if ($mform->is_cancelled()) {
    redirect($listurl);
} else if ($data = $mform->get_data()) {
    unset($data->id);
    stage_placement_save_offer($theme->id, $data, $offerid);
    redirect($listurl, get_string('placementoffersaved', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($pagetitle . ' : ' . format_string($theme->name));
$mform->display();
echo $OUTPUT->footer();
