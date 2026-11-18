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
 * Tâche ponctuelle : annonce par courriel le résultat d'un appariement lancé à la main par la
 * DEVE. Un courriel par étudiant ne tient pas dans le délai d'une page pour une promotion entière :
 * la page met l'envoi en file, et le cron le fait.
 *
 * Données : themeid, assigned (userid => offerid), unassigned (userid[]).
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notify_placement_results extends \core\task\adhoc_task {
    /**
     * Met en file l'annonce d'un appariement.
     *
     * @param int $themeid
     * @param \stdClass $result Retour de stage_placement_run_matching().
     * @return void
     */
    public static function queue(int $themeid, \stdClass $result): void {
        if (!$result->assigned && !$result->unassigned) {
            return;
        }
        $task = new self();
        $task->set_component('mod_stage');
        $task->set_custom_data((object) [
            'themeid' => $themeid,
            'assigned' => $result->assigned,
            'unassigned' => array_values($result->unassigned),
        ]);
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Envoie les courriels.
     *
     * @return void
     */
    public function execute() {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/stage/lib.php');
        require_once($CFG->dirroot . '/mod/stage/locallib.php');

        $data = $this->get_custom_data();
        $theme = $DB->get_record('stage_theme', ['id' => $data->themeid]);
        $stage = $theme ? $DB->get_record('stage', ['id' => $theme->stageid]) : null;
        $cm = $stage ? get_coursemodule_from_instance('stage', $stage->id, 0, false, IGNORE_MISSING) : null;
        if (!$cm) {
            // Thématique ou activité supprimée depuis : plus rien à annoncer.
            return;
        }
        $sent = \mod_stage\local\placement_notifier::results($stage, $cm, $theme, (object) [
            'assigned' => array_map('intval', (array) $data->assigned),
            'unassigned' => array_map('intval', (array) $data->unassigned),
        ]);
        mtrace("mod_stage : résultat de l'appariement de la thématique {$theme->id} annoncé, $sent courriel(s).");
    }
}
