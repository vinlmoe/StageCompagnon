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
 * Tâche planifiée des organismes proposés : relance, quelques jours avant la clôture, les étudiants
 * sans vœux complets ; apparie les étudiants des thématiques dont les vœux viennent d'être clos (voir
 * stage_placement_run_matching()), et annonce à chacun le résultat.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run_placement_matching extends \core\task\scheduled_task {
    /**
     * Nom de la tâche tel qu'affiché dans l'administration des tâches planifiées.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskplacementmatching', 'mod_stage');
    }

    /**
     * Parcourt les thématiques à apparier.
     *
     * @return void
     */
    public function execute() {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/stage/lib.php');
        require_once($CFG->dirroot . '/mod/stage/locallib.php');

        // Relance des étudiants sans vœux complets, quelques jours avant la clôture.
        foreach (stage_placement_get_themes_to_remind() as $theme) {
            $stage = $DB->get_record('stage', ['id' => $theme->stageid]);
            $cm = $stage ? get_coursemodule_from_instance('stage', $stage->id, 0, false, IGNORE_MISSING) : null;
            if (!$cm || empty($theme->visible)) {
                continue;
            }
            $sent = \mod_stage\local\placement_notifier::reminders($stage, $cm, $theme);
            mtrace("mod_stage : thématique {$theme->id}, relance avant la clôture des vœux, $sent courriel(s).");
        }

        foreach (stage_placement_get_themes_to_match() as $theme) {
            $stage = $DB->get_record('stage', ['id' => $theme->stageid]);
            $cm = $stage ? get_coursemodule_from_instance('stage', $stage->id, 0, false, IGNORE_MISSING) : null;
            if (!$cm) {
                continue;
            }
            // Une thématique en échec (verrou de l'activité occupé, par exemple) ne prive pas les
            // suivantes de leur appariement : elle sera reprise au passage suivant.
            try {
                // La thématique est relue et recontrôlée sous le verrou : la DEVE peut avoir repoussé
                // la date ou désactivé l'option pendant le traitement des thématiques précédentes.
                $result = stage_placement_run_matching($theme, null, true);
            } catch (\Throwable $e) {
                mtrace("mod_stage : appariement de la thématique {$theme->id} en échec : " . $e->getMessage());
                continue;
            }
            if ($result->skipped) {
                continue;
            }
            $sent = \mod_stage\local\placement_notifier::results($stage, $cm, $theme, $result);
            mtrace(sprintf(
                'mod_stage : thématique %d appariée, %d affecté(s), %d sans affectation, %d courriel(s).',
                $theme->id,
                count($result->assigned),
                count($result->unassigned),
                $sent
            ));
        }
    }
}
