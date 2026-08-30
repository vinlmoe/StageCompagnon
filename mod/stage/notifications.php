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
 * l'activité (DEVE).
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/notifications_settings_form.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/email_template_form.php');

use mod_stage\form\notifications_settings_form;
use mod_stage\form\email_template_form;

$id = required_param('id', PARAM_INT);
$emailkey = optional_param('emailkey', '', PARAM_ALPHANUMEXT);

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

$settingsform = new notifications_settings_form($baseurl);
$settingsform->set_data((object) [
    'id' => $cm->id,
    'tutorevaluationenabled' => !empty($stage->tutorevaluationenabled) ? 1 : 0,
]);
if ($settingsdata = $settingsform->get_data()) {
    stage_save_tutor_evaluation_setting($stage->id, !empty($settingsdata->tutorevaluationenabled));
    redirect($baseurl, get_string('conventionsettingssaved', 'mod_stage'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

$definitions = stage_get_email_definitions();

// Un e-mail précis a été soumis : on ne traite que son propre formulaire, identifié par emailkey.
if ($emailkey && isset($definitions[$emailkey])) {
    $formurl = new moodle_url('/mod/stage/notifications.php', ['id' => $cm->id, 'emailkey' => $emailkey]);
    $emailform = new email_template_form($formurl, ['definition' => $definitions[$emailkey]]);
    $custom = stage_get_custom_email_template($stage->id, $emailkey);
    $emailform->set_data((object) [
        'id' => $cm->id,
        'emailkey' => $emailkey,
        'subject' => $custom->subject ?? '',
        'body' => $custom->body ?? '',
    ]);
    if ($emaildata = $emailform->get_data()) {
        stage_save_email_template($stage->id, $emailkey, $emaildata->subject, $emaildata->body);
        redirect($baseurl, get_string('notificationssaved', 'mod_stage'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('notifications', 'mod_stage'));
echo html_writer::link(new moodle_url('/mod/stage/administration.php', ['id' => $cm->id]), get_string('back'));

$settingsform->display();

echo $OUTPUT->heading(get_string('notificationssettings', 'mod_stage'), 3);
echo html_writer::tag('p', get_string('notificationssettings_help', 'mod_stage'), ['class' => 'text-muted']);

foreach ($definitions as $key => $definition) {
    $formurl = new moodle_url('/mod/stage/notifications.php', ['id' => $cm->id, 'emailkey' => $key]);
    $form = new email_template_form($formurl, ['definition' => $definition]);
    $custom = stage_get_custom_email_template($stage->id, $key);
    $form->set_data((object) [
        'id' => $cm->id,
        'emailkey' => $key,
        'subject' => $custom->subject ?? '',
        'body' => $custom->body ?? '',
    ]);
    $form->display();
}

echo $OUTPUT->footer();
