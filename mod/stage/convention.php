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
 * Génère la convention de stage (PDF) d'une saisie donnée : une page 1 recréée dynamiquement à
 * partir des données de la base (avec les deux logos et les informations d'établissement
 * configurés par la DEVE), suivie des pages 2 à 4 (articles juridiques, texte fixe) du gabarit
 * choisi par l'étudiant lors de sa demande de convention (voir convention_request.php,
 * convention_templates.php), réimportées via FPDI. L'assemblage lui-même est fait par
 * stage_build_convention_pdf() (locallib.php), réutilisée aussi par convention_review.php pour un
 * téléchargement immédiat après validation.
 *
 * Accessible à la DEVE à tout moment (y compris pour réimprimer une convention déjà signée ou
 * plus tard, avec le choix d'ajouter un cadre de signatures), et, une fois la convention éditée
 * par la DEVE (STAGE_CONVENTION_EDITED) et tant qu'elle n'est pas encore signée, à l'étudiant
 * propriétaire de la saisie et à son enseignant référent : ces deux derniers ne font que la
 * télécharger telle qu'éditée par la DEVE (pas de régénération ni de cadre de signatures), sans
 * passer par l'écran de choix réservé à la DEVE ; au-delà (signée), ils doivent utiliser
 * convention_signed.php à la place (voir stage_print_student_dashboard()).
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
$returnurlparam = optional_param('returnurl', '', PARAM_LOCALURL);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

$entry = $DB->get_record('stage_entry', ['id' => $entryid, 'stageid' => $stage->id], '*', MUST_EXIST);

// Comme convention_signed.php : la DEVE y a toujours accès ; l'étudiant propriétaire et son
// enseignant référent n'y accèdent que le temps où la convention éditée n'est pas encore signée
// (au-delà, convention_signed.php prend le relais).
$isdeve = has_capability('mod/stage:registerstages', $context);
$isowner = ((int) $entry->userid === (int) $USER->id) && has_capability('mod/stage:submit', $context);
$isreferentteacher = has_capability('mod/stage:evaluateteacher', $context)
    && array_key_exists($entry->userid, stage_get_assigned_students($stage->id, $USER->id));
if (!$isdeve && !$isowner && !$isreferentteacher) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('generateconvention', 'mod_stage'));
}
if (!$isdeve && (int) $entry->conventionstatus !== STAGE_CONVENTION_EDITED) {
    throw new moodle_exception('conventionnotedited', 'mod_stage');
}

// Le titre reflète l'action réelle de l'utilisateur : la DEVE l'édite (et peut la régénérer à
// tout moment), l'étudiant et l'enseignant référent ne font que la consulter.
$pagetitle = $isdeve ? get_string('generateconvention', 'mod_stage') : get_string('viewconvention', 'mod_stage');

// L'écran de retour est celui d'où l'on vient (liste des conventions, enregistrement des stages,
// tableau de pilotage...), à défaut la liste des conventions.
$backurl = $returnurlparam !== ''
    ? new moodle_url($returnurlparam)
    : new moodle_url('/mod/stage/conventions.php', ['id' => $cm->id]);
$baseurl = new moodle_url('/mod/stage/convention.php',
    ['id' => $cm->id, 'entryid' => $entryid, 'returnurl' => $returnurlparam]);

// Demande d'abord si un cadre de signatures (stagiaire, maître de stage, responsable de
// l'organisme d'accueil, enseignant.e référent.e, établissement) doit être ajouté en bas de la
// page 1, pour une convention imprimée destinée à être signée à la main, plutôt que de générer
// systématiquement l'un ou l'autre. Ce choix ne concerne que la DEVE, seule à imprimer la
// convention pour la faire signer : l'étudiant et l'enseignant référent ne font que télécharger
// la convention déjà éditée par la DEVE, sans passer par cet écran intermédiaire.
if ($isdeve && !optional_param('confirmgenerate', 0, PARAM_BOOL)) {
    $PAGE->set_url($baseurl);
    $PAGE->set_title(format_string($stage->name) . ' - ' . $pagetitle);
    $PAGE->set_heading(format_string($course->fullname));
    $PAGE->set_context($context);

    echo $OUTPUT->header();
    echo $OUTPUT->heading($pagetitle);
    echo html_writer::link($backurl, get_string('back'));

    echo html_writer::start_tag('form',
        ['method' => 'get', 'action' => new moodle_url('/mod/stage/convention.php'), 'class' => 'mt-3']);
    echo html_writer::input_hidden_params($baseurl);
    echo html_writer::start_div('form-check mb-3');
    echo html_writer::checkbox('withsignatures', 1, false, get_string('includesignatureblock', 'mod_stage'),
        ['id' => 'id_withsignatures', 'class' => 'form-check-input']);
    echo html_writer::end_div();
    echo html_writer::empty_tag('input',
        ['type' => 'hidden', 'name' => 'confirmgenerate', 'value' => 1]);
    echo html_writer::empty_tag('input',
        ['type' => 'submit', 'value' => $pagetitle, 'class' => 'btn btn-primary']);
    echo html_writer::end_tag('form');

    echo $OUTPUT->footer();
    exit;
}

// Le cadre de signatures est une option d'impression réservée à la DEVE (voir ci-dessus) : sans
// objet pour un simple téléchargement par l'étudiant ou l'enseignant référent.
$withsignatures = $isdeve && optional_param('withsignatures', 0, PARAM_BOOL);

// Second temps : le fichier lui-même, demandé par le cadre invisible de la page ci-dessous. Les
// causes d'échec ayant déjà été écartées à l'affichage de cette page, une erreur ici ne peut plus
// venir que d'un changement entre-temps : l'exception suffit, il n'y a personne pour la lire dans
// un cadre invisible de toute façon.
if (optional_param('download', 0, PARAM_BOOL)) {
    $result = stage_build_convention_pdf($stage, $entry, $context, $withsignatures);
    if ($result['error']) {
        throw new moodle_exception($result['error'], 'mod_stage');
    }
    $result['pdf']->Output($result['filename'], 'D');
    exit;
}

$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . $pagetitle);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Ce qui empêcherait de construire le PDF est vérifié avant de lancer quoi que ce soit : une
// erreur survenant dans le cadre de téléchargement ne serait jamais montrée à la DEVE.
$error = stage_check_convention_pdf_prerequisites($entry, $context);
if ($error !== null) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string($error, 'mod_stage'), \core\output\notification::NOTIFY_ERROR);
    echo html_writer::link($backurl, get_string('back'));
    echo $OUTPUT->footer();
    exit;
}

// Premier temps : la convention se télécharge en pièce jointe, ce qui ne fait pas changer de
// page ; sans cette étape, la DEVE resterait sur le formulaire ci-dessus au lieu de revenir à sa
// liste.
$downloadurl = new moodle_url($baseurl, [
    'confirmgenerate' => 1, 'withsignatures' => $withsignatures ? 1 : 0, 'download' => 1,
]);

echo $OUTPUT->header();
echo $OUTPUT->heading($pagetitle);
echo stage_render_download_and_return($downloadurl, $backurl);
echo $OUTPUT->footer();
