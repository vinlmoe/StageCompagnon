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
 * Téléchargement d'un document d'objectifs de stage rattaché à une thématique. Comme pour les
 * rapports de stage (report_file.php), le contrôle d'accès est fait ici plutôt que par un callback
 * pluginfile, parce qu'il ne dépend pas seulement du contexte : la page est ouverte soit par un
 * utilisateur connecté (étudiant, enseignant, DEVE), soit par le maître de stage, qui n'a pas de
 * compte Moodle et ne présente que le jeton reçu par courriel (voir tutor_eval.php).
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Pas de require_login() lorsqu'un jeton est présenté, et c'est volontaire : le maître de stage
// télécharge les objectifs depuis la page d'évaluation qu'il a ouverte avec ce même jeton, sans
// compte Moodle. Le jeton désigne une saisie et une seule, donc une thématique et une seule : il
// ne donne accès qu'aux documents de celle-ci. Pour tous les autres, require_login() ci-dessous.
// phpcs:ignore moodle.Files.RequireLogin.Missing
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$token = optional_param('token', '', PARAM_ALPHANUM);
$themeid = required_param('themeid', PARAM_INT);
$pathnamehash = required_param('pathnamehash', PARAM_ALPHANUM);

if ($token !== '') {
    // Accès du maître de stage : le jeton fait foi, et seule la thématique du stage qu'il désigne
    // est consultable.
    $entry = stage_get_entry_by_tutor_token($token);
    if (!$entry || (int) $entry->themeid !== $themeid) {
        throw new moodle_exception('tutorevalinvalidtoken', 'mod_stage');
    }
    $stage = $DB->get_record('stage', ['id' => $entry->stageid], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('stage', $stage->id, 0, false, MUST_EXIST);
    $context = context_module::instance($cm->id);
} else {
    $id = required_param('id', PARAM_INT);
    $cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
    $course = get_course($cm->course);
    $stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

    require_login($course, true, $cm);
    $context = context_module::instance($cm->id);
    // Les objectifs décrivent ce qui est attendu sur une thématique : ils n'ont rien de personnel
    // et sont ouverts à tous ceux qui voient l'activité, étudiants comme enseignants.
    require_capability('mod/stage:view', $context);

    $DB->get_record('stage_theme', ['id' => $themeid, 'stageid' => $stage->id], '*', MUST_EXIST);
}

// Le fichier est cherché parmi ceux de la thématique plutôt que par son seul hachage : un hachage
// valide pointant vers une autre thématique (ou une autre zone de fichiers) ne doit rien renvoyer.
$files = stage_get_theme_objective_files($context, $themeid);
$file = $files[$pathnamehash] ?? null;
if (!$file) {
    throw new moodle_exception('themeobjectivefilemissing', 'mod_stage');
}

send_stored_file($file, 0, 0, true);
