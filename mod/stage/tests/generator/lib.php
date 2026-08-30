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
 * Générateur de données de test pour mod_stage : au-delà de la création de l'instance elle-même
 * (déjà couverte par testing_module_generator via $generator->create_module('stage', ...)), les
 * tests ont besoin de créer des thématiques, des saisies et des attributions d'enseignants
 * référents sans reproduire à chaque fois les valeurs par défaut de leurs tables respectives.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_stage_generator extends testing_module_generator {

    /**
     * Crée une thématique de stage.
     *
     * @param stdClass|int $stage Instance de stage (ou son id).
     * @param array $record Champs à surcharger : name, mandatory, minstudyyear, maxstudyyear,
     *                      requiredduration, visible.
     * @return stdClass La thématique créée.
     */
    public function create_theme($stage, array $record = []) {
        global $DB;

        $stageid = is_object($stage) ? $stage->id : $stage;

        $record = array_merge([
            'stageid' => $stageid,
            'name' => 'Thématique ' . ($DB->count_records('stage_theme', ['stageid' => $stageid]) + 1),
            'description' => '',
            'mandatory' => 1,
            'requiredduration' => 0,
            'minstudyyear' => 0,
            'maxstudyyear' => 0,
            'sortorder' => 0,
            'visible' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ], $record);

        $id = $DB->insert_record('stage_theme', (object) $record);
        return $DB->get_record('stage_theme', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Enregistre un stage pour un étudiant, avec sa plage de dates (voir stage_register_entry(),
     * qui la crée automatiquement à partir de datestart/dateend).
     *
     * @param stdClass|int $stage
     * @param int $userid
     * @param stdClass|int $theme
     * @param array $record Champs à surcharger : structure, datestart, dateend, declaredduration,
     *                      studyyear, conventionstatus, abroad, country.
     * @return stdClass La saisie créée.
     */
    public function create_entry($stage, $userid, $theme, array $record = []) {
        global $DB;

        $stageid = is_object($stage) ? $stage->id : $stage;
        $themeid = is_object($theme) ? $theme->id : $theme;

        $record = array_merge([
            'structure' => 'Structure de test',
            'datestart' => make_timestamp(2026, 3, 1),
            'dateend' => make_timestamp(2026, 3, 15),
            'declaredduration' => 10,
            'studyyear' => 0,
            'conventionstatus' => STAGE_CONVENTION_NONE,
            'abroad' => 0,
            'country' => '',
        ], $record);

        $entryid = stage_register_entry(
            $stageid, $userid, $themeid, $record['structure'],
            $record['datestart'], $record['dateend'], $record['declaredduration'],
            $record['studyyear'], $record['conventionstatus'], $record['abroad'], $record['country']
        );

        return $DB->get_record('stage_entry', ['id' => $entryid], '*', MUST_EXIST);
    }

    /**
     * Attribue un enseignant référent à un étudiant pour un stage donné.
     *
     * @param stdClass|int $stage
     * @param int $studentid
     * @param int $teacherid
     * @return void
     */
    public function assign_teacher($stage, $studentid, $teacherid) {
        $stageid = is_object($stage) ? $stage->id : $stage;
        stage_set_student_teachers($stageid, $studentid, [$teacherid]);
    }
}
