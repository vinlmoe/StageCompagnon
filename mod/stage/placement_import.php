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
 * Import Excel des organismes proposés d'une thématique (DEVE), avec un modèle téléchargeable qui
 * reprend les organismes déjà proposés (voir \mod_stage\local\placement_offer_importer).
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_stage\local\placement_offer_importer;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/excellib.class.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$id = required_param('id', PARAM_INT);
$themeid = required_param('themeid', PARAM_INT);
$template = optional_param('template', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:managethemes', $context);

$theme = $DB->get_record('stage_theme', ['id' => $themeid, 'stageid' => $stage->id], '*', MUST_EXIST);

$params = ['id' => $cm->id, 'themeid' => $theme->id];
$baseurl = new moodle_url('/mod/stage/placement_import.php', $params);
$listurl = new moodle_url('/mod/stage/placement_offers.php', $params);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('placementimport', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Modèle : une colonne par information, et une ligne par organisme déjà proposé, pour corriger la
// liste dans un tableur puis la réimporter.
if ($template) {
    $workbook = new MoodleExcelWorkbook('-');
    $workbook->send(clean_filename(get_string('placementimporttemplatefile', 'mod_stage') . '.xlsx'));
    $sheet = $workbook->add_worksheet(get_string('placementoffers', 'mod_stage'));
    $headerformat = new MoodleExcelFormat(['bold' => 1]);
    $headers = placement_offer_importer::template_headers();
    foreach (array_values($headers) as $col => $header) {
        $sheet->write_string(0, $col, $header, $headerformat);
    }
    $row = 1;
    foreach (stage_placement_get_offers($theme->id) as $offer) {
        foreach (array_keys($headers) as $col => $field) {
            if ($field === 'places') {
                $sheet->write_number($row, $col, (int) $offer->places);
            } else {
                $sheet->write_string($row, $col, (string) $offer->$field);
            }
        }
        $row++;
    }
    $workbook->close();
    exit;
}

$results = null;
$uploaderror = null;

if (data_submitted() && confirm_sesskey()) {
    [$uploadpath, $uploaderror] = stage_get_uploaded_file('xlsxfile');
    if ($uploadpath) {
        try {
            $results = placement_offer_importer::import($theme, placement_offer_importer::read($uploadpath));
        } catch (moodle_exception $e) {
            $uploaderror = $e->getMessage();
        } catch (Throwable $e) {
            // Un fichier qui n'est pas un classeur (document renommé en .xlsx) ne doit pas finir en
            // erreur fatale.
            $uploaderror = get_string('historicalimportinvalidfile', 'mod_stage');
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('placementimport', 'mod_stage') . ' : ' . format_string($theme->name));
echo html_writer::link($listurl, get_string('back'));

if ($uploaderror !== null) {
    echo $OUTPUT->notification($uploaderror, \core\output\notification::NOTIFY_ERROR);
}
if ($results) {
    echo $OUTPUT->notification(
        get_string('placementimportresult', 'mod_stage', $results),
        \core\output\notification::NOTIFY_SUCCESS
    );
    if (!empty($results->errors)) {
        echo $OUTPUT->notification(
            implode(html_writer::empty_tag('br'), array_map('s', $results->errors)),
            \core\output\notification::NOTIFY_WARNING
        );
    }
}

echo $OUTPUT->box(get_string('placementimport_help', 'mod_stage'), 'generalbox mb-3');
echo html_writer::div(html_writer::link(
    new moodle_url($baseurl, ['template' => 1]),
    get_string('placementimporttemplate', 'mod_stage'),
    ['class' => 'btn btn-outline-secondary']
), 'mb-3');

echo html_writer::start_tag('form', [
    'method' => 'post', 'action' => $baseurl, 'enctype' => 'multipart/form-data',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', [
    'type' => 'file', 'name' => 'xlsxfile', 'required' => 'required',
    'accept' => '.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
]);
echo html_writer::tag(
    'button',
    get_string('import', 'mod_stage'),
    ['type' => 'submit', 'class' => 'btn btn-primary ml-2']
);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
