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
 * Chaque saisie n'est invitée qu'une fois (stage_entry.tutortoken, généré au premier envoi) ; voir
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

        $entries = stage_get_entries_needing_tutor_request();
        if (empty($entries)) {
            return;
        }

        // Les instances et modules sont résolus au fil de l'eau et mémorisés : une même promotion
        // partage la même instance, inutile de la relire à chaque saisie.
        $stages = [];
        $cms = [];
        $sent = 0;

        foreach ($entries as $entry) {
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
            // coordonnées, jeton déjà présent) : la requête SQL n'est qu'un premier filtre.
            stage_maybe_request_tutor_evaluation($stage, $cm, $entry);
            if (!empty($entry->tutortoken)) {
                $sent++;
            }
        }

        if ($sent > 0) {
            mtrace("mod_stage : $sent invitation(s) à évaluer le stage envoyée(s) aux maîtres de stage.");
        }
    }
}
