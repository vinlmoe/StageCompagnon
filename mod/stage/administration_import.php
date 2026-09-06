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
 * Import des thématiques, gabarits de convention, logos et informations d'établissement depuis
 * une autre instance de mod_stage (généralement dans un autre cours), pour éviter à la DEVE de
 * ressaisir/retéléverser ces éléments à chaque nouvelle année ou nouveau cours.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/administration_import_form.php');

use mod_stage\form\administration_import_form;

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:managethemes', $context);

$baseurl = new moodle_url('/mod/stage/administration_import.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('importfromcourse', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$backurl = new moodle_url('/mod/stage/administration.php', ['id' => $cm->id]);

$sourceoptions = stage_get_importable_stage_instances($stage->id);
if (empty($sourceoptions)) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('importfromcourse', 'mod_stage'));
    echo html_writer::link($backurl, get_string('back'));
    echo $OUTPUT->notification(get_string('noimportsources', 'mod_stage'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

$mform = new administration_import_form($baseurl, ['sourceoptions' => $sourceoptions]);
$mform->set_data((object) ['id' => $cm->id]);

if ($mform->is_cancelled()) {
    redirect($backurl);
} else if ($data = $mform->get_data()) {
    $sourcestage = $DB->get_record('stage', ['id' => $data->sourcestageid], '*', MUST_EXIST);
    $sourcecm = get_coursemodule_from_instance('stage', $sourcestage->id, 0, false, MUST_EXIST);
    $sourcecontext = context_module::instance($sourcecm->id);
    // La capacité est revérifiée sur la source : elle a pu changer depuis l'affichage du
    // formulaire.
    require_capability('mod/stage:managethemes', $sourcecontext);

    $result = stage_import_from_stage($sourcestage, $sourcecontext, $stage, $context, [
        'themes' => !empty($data->importthemes),
        'templates' => !empty($data->importtemplates),
        'logos' => !empty($data->importlogos),
        'emails' => !empty($data->importemails),
        'establishment' => !empty($data->importestablishment),
    ]);
    $result->establishmenttext = get_string(
        $result->establishment ? 'importdoneestablishmentyes' : 'importdoneestablishmentno', 'mod_stage');

    redirect($backurl, get_string('importdone', 'mod_stage', $result), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importfromcourse', 'mod_stage'));
echo html_writer::link($backurl, get_string('back'));

echo $OUTPUT->box(get_string('importfromcourse_help', 'mod_stage'), 'generalbox mb-3');

$mform->display();

echo $OUTPUT->footer();
