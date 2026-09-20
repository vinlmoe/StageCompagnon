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
 * Import en masse des stages depuis un export CSV de StageVet (conventions déjà générées et
 * signées par StageVet, hors du circuit de gestion de convention de ce plugin), par la DEVE.
 *
 * Colonnes lues par nom d'en-tête (voir $columnmap ci-dessous), dans l'ordre où StageVet les
 * exporte habituellement : "Nom étudiant", "Prénom étudiant", "Email étudiant" (souvent vide côté
 * StageVet : l'étudiant est alors identifié par nom/prénom), "Thème", "Début (convention)",
 * "Fin (convention)", "Durée (convention)", "Organisme (convention)" et l'ensemble des
 * coordonnées de convention (organisme, tuteur, maître de stage, modalités, gratification,
 * congés). Le nom exact d'une thématique déjà créée dans l'activité est requis pour chaque ligne
 * (comme pour import.php), StageVet n'utilisant pas les mêmes intitulés par défaut.
 *
 * Chaque stage importé est enregistré au statut "Enregistré" avec le statut de convention
 * "Signée (SignVet)" (voir STAGE_CONVENTION_SIGNVET) : déjà signé hors de ce plugin, l'
 * auto-évaluation de l'étudiant est immédiatement ouverte. Les informations de convention
 * disponibles dans l'export sont malgré tout enregistrées (stage_convention_detail), à titre de
 * référence consultable, mais ne déclenchent aucune génération de PDF ni gestion de convention
 * dans ce plugin.
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
require_capability('mod/stage:registerstages', $context);

$baseurl = new moodle_url('/mod/stage/import_stagevet.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('importstagevetcsv', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$results = null;
$uploaderror = null;

if (data_submitted() && confirm_sesskey()) {
    $upload = $_FILES['csvfile'] ?? null;

    if (empty($upload) || $upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
        $uploaderror = get_string('importerrorupload', 'mod_stage');
    } else {
        $import = \mod_stage\local\csv_importer::stagevet(
            $stage,
            $context,
            file_get_contents($upload['tmp_name'])
        );
        $results = $import['results'];
        $uploaderror = $import['error'];
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importstagevetcsv', 'mod_stage'));
echo html_writer::link(new moodle_url('/mod/stage/register.php', ['id' => $cm->id]), get_string('back'));

if ($uploaderror !== null) {
    echo $OUTPUT->notification($uploaderror, \core\output\notification::NOTIFY_ERROR);
}

if ($results) {
    echo $OUTPUT->notification(
        get_string('importresult', 'mod_stage', $results->created),
        \core\output\notification::NOTIFY_SUCCESS
    );

    // Rapport groupé : un étudiant ou une thématique manquant sur cent lignes ne doit apparaître
    // qu'une fois, avec la liste des lignes concernées, plutôt que cent messages identiques.
    if (!empty($results->unknownstudents)) {
        echo $OUTPUT->heading(get_string(
            'importstagevetunknownstudentsreport',
            'mod_stage',
            count($results->unknownstudents)
        ), 4);
        $lines = [];
        foreach ($results->unknownstudents as $name => $linenums) {
            $lines[] = get_string('importstagevetreportline', 'mod_stage', (object) [
                'value' => $name, 'lines' => implode(', ', $linenums),
            ]);
        }
        echo $OUTPUT->notification(
            implode(html_writer::empty_tag('br'), $lines),
            \core\output\notification::NOTIFY_WARNING
        );
    }

    if (!empty($results->unknownthemes)) {
        echo $OUTPUT->heading(get_string(
            'importstagevetunknownthemesreport',
            'mod_stage',
            count($results->unknownthemes)
        ), 4);
        $lines = [];
        foreach ($results->unknownthemes as $name => $linenums) {
            $lines[] = get_string('importstagevetreportline', 'mod_stage', (object) [
                'value' => $name, 'lines' => implode(', ', $linenums),
            ]);
        }
        echo $OUTPUT->notification(
            implode(html_writer::empty_tag('br'), $lines),
            \core\output\notification::NOTIFY_WARNING
        );
    }

    if (!empty($results->errors)) {
        echo $OUTPUT->notification(
            implode(html_writer::empty_tag('br'), $results->errors),
            \core\output\notification::NOTIFY_WARNING
        );
    }
}

echo $OUTPUT->box(get_string('importstagevetcsv_help', 'mod_stage'), 'generalbox mb-3');

echo html_writer::start_tag('form', [
    'method' => 'post', 'action' => $baseurl, 'enctype' => 'multipart/form-data',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'file', 'name' => 'csvfile', 'accept' => '.csv', 'required' => 'required']);
echo html_writer::tag(
    'button',
    get_string('import', 'mod_stage'),
    ['type' => 'submit', 'class' => 'btn btn-primary ml-2']
);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
