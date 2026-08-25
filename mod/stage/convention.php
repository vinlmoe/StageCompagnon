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
 * Génère la convention de stage (PDF) d'une saisie donnée, pour la DEVE : une page 1 recréée
 * dynamiquement à partir des données de la base (avec les deux logos et les informations
 * d'établissement configurés par la DEVE), suivie des pages 2 à 4 (articles juridiques, texte
 * fixe) du gabarit choisi par l'étudiant lors de sa demande de convention (voir
 * convention_request.php, convention_templates.php), réimportées via FPDI. L'assemblage lui-même
 * est fait par stage_build_convention_pdf() (locallib.php), réutilisée aussi par
 * convention_review.php pour un téléchargement immédiat après validation.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$id = required_param('id', PARAM_INT);
$entryid = required_param('entryid', PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
// La génération de la convention est réservée à la DEVE, comme l'enregistrement des stages :
// pas de capacité dédiée (mod/stage:exportconvention) tant qu'aucun rôle n'a besoin d'y accéder
// sans avoir aussi mod/stage:registerstages.
require_capability('mod/stage:registerstages', $context);

$entry = $DB->get_record('stage_entry', ['id' => $entryid, 'stageid' => $stage->id], '*', MUST_EXIST);

$backurl = new moodle_url('/mod/stage/conventions.php', ['id' => $cm->id]);

$result = stage_build_convention_pdf($stage, $entry, $context);
if ($result['error']) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string($result['error'], 'mod_stage'), \core\output\notification::NOTIFY_ERROR);
    echo html_writer::link($backurl, get_string('back'));
    echo $OUTPUT->footer();
    exit;
}

$result['pdf']->Output($result['filename'], 'D');
