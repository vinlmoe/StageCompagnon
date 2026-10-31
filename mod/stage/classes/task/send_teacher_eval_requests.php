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
 * Tâche ad hoc : invite par courriel les enseignants référents à évaluer des stages dont les deux
 * évaluations amont (étudiant, maître de stage) viennent d'être importées de StageVet.
 *
 * Ces courriels partaient pendant la requête web de l'import : un import de plusieurs centaines de
 * stages dépassait le temps d'exécution d'une page, et un échec en cours de route laissait la DEVE
 * sans savoir qui avait été prévenu. L'import décide qui inviter (voir
 * \mod_stage\local\csv_importer::stagevet()) et confie l'envoi à cette tâche, exécutée par le cron.
 *
 * Données : stageid, entryids (stages à soumettre à leur enseignant référent).
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_teacher_eval_requests extends \core\task\adhoc_task {
    /**
     * Envoie les invitations.
     *
     * @return void
     */
    public function execute() {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/stage/lib.php');
        require_once($CFG->dirroot . '/mod/stage/locallib.php');

        $data = $this->get_custom_data();
        $stage = $DB->get_record('stage', ['id' => (int) ($data->stageid ?? 0)]);
        $cm = $stage ? get_coursemodule_from_instance('stage', $stage->id, 0, false, IGNORE_MISSING) : false;
        if (!$stage || !$cm) {
            // Activité supprimée depuis l'import : plus personne à inviter.
            return;
        }

        $sent = 0;
        foreach (array_map('intval', (array) ($data->entryids ?? [])) as $entryid) {
            $entry = $DB->get_record('stage_entry', ['id' => $entryid, 'stageid' => $stage->id]);
            // Stage supprimé, annulé ou déjà évalué depuis l'import : l'invitation n'a plus d'objet.
            if (!$entry || !in_array((int) $entry->status, [STAGE_STATUS_ENREGISTRE, STAGE_STATUS_EVAL_ETUDIANT], true)) {
                continue;
            }
            $student = $DB->get_record('user', ['id' => $entry->userid, 'deleted' => 0]);
            if ($student && stage_notify_teachers_eval_request($stage, $cm, $entry, $student)) {
                $sent++;
            }
        }
        mtrace("mod_stage : enseignants référents invités à évaluer $sent stage(s) importé(s).");
    }
}
