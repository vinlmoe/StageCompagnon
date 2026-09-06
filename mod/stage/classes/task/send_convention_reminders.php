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
 * Tâche planifiée : relance par courriel les étudiants dont le stage commence dans quelques jours
 * alors que leur convention n'est toujours pas signée (voir STAGE_CONVENTION_REMINDER_DAYS).
 *
 * Chaque saisie n'est relancée qu'une fois (stage_entry.conventionremindertime), et seulement si
 * le courriel part réellement : un échec ponctuel est ainsi retenté au passage suivant.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_convention_reminders extends \core\task\scheduled_task {

    /**
     * Nom de la tâche tel qu'affiché dans l'administration des tâches planifiées.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskconventionreminders', 'mod_stage');
    }

    /**
     * Parcourt les saisies à relancer et envoie un courriel à chaque étudiant concerné.
     *
     * @return void
     */
    public function execute() {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/stage/lib.php');
        require_once($CFG->dirroot . '/mod/stage/locallib.php');

        $entries = stage_get_entries_needing_convention_reminder();
        if (empty($entries)) {
            return;
        }

        // Les instances, modules et thématiques sont résolus au fil de l'eau et mémorisés : une
        // même promotion partage la même instance, inutile de la relire à chaque saisie.
        $stages = [];
        $cms = [];
        $themes = [];
        $students = [];
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

            if (!array_key_exists($entry->userid, $students)) {
                $students[$entry->userid] = $DB->get_record('user', ['id' => $entry->userid]);
            }
            $student = $students[$entry->userid];
            // Un compte supprimé ou suspendu ne reçoit rien : la relance resterait en attente et
            // repartirait à chaque passage du cron.
            if (!$student || !empty($student->deleted) || !empty($student->suspended)) {
                $DB->set_field('stage_entry', 'conventionremindertime', time(), ['id' => $entry->id]);
                continue;
            }

            if (!array_key_exists($entry->themeid, $themes)) {
                $themes[$entry->themeid] = $DB->get_record('stage_theme', ['id' => $entry->themeid]) ?: null;
            }

            if (stage_notify_student_convention_reminder($stage, $cm, $entry, $student, $themes[$entry->themeid])) {
                $sent++;
            }
        }

        if ($sent > 0) {
            mtrace("mod_stage : $sent relance(s) de convention non signée envoyée(s).");
        }
    }
}
