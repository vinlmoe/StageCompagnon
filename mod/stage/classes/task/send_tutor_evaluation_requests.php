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

namespace mod_stage\task;

/**
 * Tâche planifiée : envoie au maître de stage son invitation à évaluer le stage le jour où celui-
 * ci commence (plutôt qu'à la soumission de l'auto-évaluation de l'étudiant, qui peut avoir lieu
 * bien avant le début effectif du stage).
 *
 * Chaque saisie n'est invitée qu'une fois avec succès (stage_entry.tutorrequesttime) ; voir
 * stage_get_entries_needing_tutor_request() pour les conditions d'éligibilité.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_tutor_evaluation_requests extends \core\task\scheduled_task {
    /**
     * Nom de la tâche tel qu'affiché dans l'administration des tâches planifiées.
     *
     * @return string
     */
    public function get_name() {
        return get_string('tasktutorevaluationrequests', 'mod_stage');
    }

    /**
     * Parcourt les saisies dont le stage a commencé et envoie l'invitation à chaque maître de
     * stage concerné.
     *
     * @return void
     */
    public function execute() {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/stage/lib.php');
        require_once($CFG->dirroot . '/mod/stage/locallib.php');

        // Le plafond d'âge (voir stage_get_entries_needing_tutor_request()) borne déjà la liste.
        // Le nombre d'envois par passage est plafonné ici, et non dans la requête : sinon des
        // adresses invalides en tête de liste occuperaient le lot à chaque passage, et les
        // invitations suivantes ne partiraient jamais.
        $entries = stage_get_entries_needing_tutor_request();
        if (empty($entries)) {
            return;
        }

        // Adresses des maîtres de stage, en une requête par paquet : une adresse invalide est
        // écartée sans tentative d'envoi.
        $emails = [];
        foreach (array_chunk(array_keys($entries), STAGE_SQL_CHUNK) as $chunk) {
            $emails += $DB->get_records_list('stage_convention_detail', 'entryid', $chunk, '', 'entryid, tutoremail');
        }

        // Les instances et modules sont résolus au fil de l'eau et mémorisés : une même promotion
        // partage la même instance, inutile de la relire à chaque saisie.
        $stages = [];
        $cms = [];
        $sent = 0;
        $attempts = 0;
        $invalid = 0;
        $remaining = 0;

        foreach ($entries as $entry) {
            $email = trim((string) ($emails[$entry->id]->tutoremail ?? ''));
            if (!validate_email($email)) {
                $invalid++;
                continue;
            }
            if ($attempts >= STAGE_TUTOR_REQUEST_BATCH) {
                $remaining++;
                continue;
            }
            if (!array_key_exists($entry->stageid, $stages)) {
                $stages[$entry->stageid] = $DB->get_record('stage', ['id' => $entry->stageid]);
                $cms[$entry->stageid] = $stages[$entry->stageid]
                    ? get_coursemodule_from_instance('stage', $entry->stageid, 0, false, IGNORE_MISSING) : false;
            }
            $stage = $stages[$entry->stageid];
            $cm = $cms[$entry->stageid];
            if (!$stage || !$cm) {
                continue;
            }

            // stage_maybe_request_tutor_evaluation() revérifie elle-même l'éligibilité (activation,
            // coordonnées, invitation déjà envoyée) : la requête SQL n'est qu'un premier filtre.
            $attempts++;
            if (stage_maybe_request_tutor_evaluation($stage, $cm, $entry)) {
                $sent++;
            }
        }

        if ($attempts > $sent) {
            mtrace('mod_stage : ' . ($attempts - $sent) . ' invitation(s) de maîtres de stage non envoyée(s), '
                . 'nouvelle tentative au prochain passage.');
        }
        if ($invalid > 0) {
            mtrace("mod_stage : $invalid invitation(s) de maîtres de stage en attente d'une adresse valide.");
        }
        if ($remaining > 0) {
            mtrace("mod_stage : $remaining invitation(s) de maîtres de stage reportée(s) au prochain passage.");
        }
        if ($sent > 0) {
            mtrace("mod_stage : $sent invitation(s) à évaluer le stage envoyée(s) aux maîtres de stage.");
        }
    }
}
