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
 * Étape de sauvegarde de la structure d'une instance de mod_stagesynthesis.
 *
 * @package   mod_stagesynthesis
 * @category  backup
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Décrit l'arbre XML (stagesynthesis.xml) d'une instance de mod_stagesynthesis.
 *
 * L'activité n'a pas de données de suivi propres : toute la synthèse est lue dans les activités
 * mod_stage liées. Seuls l'instance et la liste de ces liens sont donc sauvegardés.
 *
 * @package   mod_stagesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_stagesynthesis_activity_structure_step extends backup_activity_structure_step {
    /**
     * Construit la structure sauvegardée.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {

        $stagesynthesis = new backup_nested_element('stagesynthesis', ['id'], [
            'name', 'intro', 'introformat', 'timecreated', 'timemodified',
        ]);

        $links = new backup_nested_element('links');
        $link = new backup_nested_element('link', ['id'], ['stagecmid', 'timecreated']);

        $stagesynthesis->add_child($links);
        $links->add_child($link);

        $stagesynthesis->set_source_table('stagesynthesis', ['id' => backup::VAR_ACTIVITYID]);
        $link->set_source_table('stagesynthesis_link', ['synthesisid' => backup::VAR_PARENTID], 'id');

        $stagesynthesis->annotate_files('mod_stagesynthesis', 'intro', null);

        return $this->prepare_activity_structure($stagesynthesis);
    }
}
