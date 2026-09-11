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
 * Étape de restauration de la structure d'une instance de mod_stagesynthesis.
 *
 * @package   mod_stagesynthesis
 * @category  backup
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restaure l'arbre décrit par backup_stagesynthesis_activity_structure_step.
 *
 * @package   mod_stagesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_stagesynthesis_activity_structure_step extends restore_activity_structure_step {
    /**
     * Déclare les chemins à restaurer.
     *
     * @return array
     */
    protected function define_structure() {

        $paths = [];

        $paths[] = new restore_path_element('stagesynthesis', '/activity/stagesynthesis');
        $paths[] = new restore_path_element('stagesynthesis_link', '/activity/stagesynthesis/links/link');

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restaure l'instance elle-même.
     *
     * @param array $data
     */
    protected function process_stagesynthesis($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();

        $newitemid = $DB->insert_record('stagesynthesis', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restaure un lien vers une activité « Gestion des stages ».
     *
     * L'identifiant de course-module est conservé tel quel à ce stade : l'activité mod_stage visée
     * peut n'être restaurée qu'après celle-ci. La correspondance est appliquée par after_restore(),
     * une fois toutes les activités du cours restaurées.
     *
     * @param array $data
     */
    protected function process_stagesynthesis_link($data) {
        global $DB;

        $data = (object) $data;
        unset($data->id);
        $data->synthesisid = $this->get_new_parentid('stagesynthesis');

        $DB->insert_record('stagesynthesis_link', $data);
    }

    /**
     * Rattache les fichiers de la description.
     */
    protected function after_execute() {
        $this->add_related_files('mod_stagesynthesis', 'intro', null);
    }

    /**
     * Fait pointer les liens vers les activités mod_stage réellement restaurées.
     *
     * Un lien dont l'activité d'origine n'a pas été restaurée n'est conservé que sur le même site,
     * où l'identifiant de course-module d'origine désigne toujours la bonne activité ; ailleurs il
     * désignerait une activité sans rapport et est donc supprimé.
     */
    protected function after_restore() {
        global $DB;

        $synthesisid = $this->task->get_activityid();
        if (!$synthesisid) {
            return;
        }

        foreach ($DB->get_records('stagesynthesis_link', ['synthesisid' => $synthesisid]) as $link) {
            $newcmid = $this->get_mappingid('course_module', $link->stagecmid);

            if (!$newcmid) {
                $stillvalid = $this->task->is_samesite()
                    && get_coursemodule_from_id('stage', $link->stagecmid, 0, false, IGNORE_MISSING);
                if (!$stillvalid) {
                    $DB->delete_records('stagesynthesis_link', ['id' => $link->id]);
                }
                continue;
            }

            if ((int) $newcmid === (int) $link->stagecmid) {
                continue;
            }

            // L'index (synthesisid, stagecmid) est unique : si la cible est déjà liée, ce lien
            // ferait doublon.
            if (
                $DB->record_exists(
                    'stagesynthesis_link',
                    ['synthesisid' => $synthesisid, 'stagecmid' => $newcmid]
                )
            ) {
                $DB->delete_records('stagesynthesis_link', ['id' => $link->id]);
                continue;
            }

            $DB->set_field('stagesynthesis_link', 'stagecmid', $newcmid, ['id' => $link->id]);
        }
    }
}
