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
 * Activation de l'évaluation par le maître de stage et personnalisation des e-mails envoyés par
 * l'activité (DEVE), en un seul formulaire enregistré en une fois.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/notifications_form.php');

use mod_stage\form\notifications_form;

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:managethemes', $context);

$baseurl = new moodle_url('/mod/stage/notifications.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('notifications', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$definitions = stage_get_email_definitions();
$formdata = [
    'id' => $cm->id,
    'tutorevaluationenabled' => !empty($stage->tutorevaluationenabled) ? 1 : 0,
];
$customized = [];
foreach (array_keys($definitions) as $key) {
    $custom = stage_get_custom_email_template($stage->id, $key);
    if ($custom) {
        $customized[] = $key;
    }
    $formdata['subject_' . $key] = $custom->subject ?? '';
    $formdata['body_' . $key] = $custom->body ?? '';
}

$mform = new notifications_form($baseurl, ['definitions' => $definitions, 'customized' => $customized]);
$mform->set_data($formdata);

if ($data = $mform->get_data()) {
    stage_save_tutor_evaluation_setting($stage->id, !empty($data->tutorevaluationenabled));
    foreach (array_keys($definitions) as $key) {
        stage_save_email_template(
            $stage->id,
            $key,
            (string) ($data->{'subject_' . $key} ?? ''),
            (string) ($data->{'body_' . $key} ?? '')
        );
    }
    redirect($baseurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('notifications', 'mod_stage'));
echo html_writer::link(new moodle_url('/mod/stage/administration.php', ['id' => $cm->id]), get_string('back'));

$mform->display();

echo $OUTPUT->footer();
