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

namespace mod_stage\local;

/**
 * Exports des thématiques à organismes proposés : vœux et affectations des étudiants, et trace d'un
 * tour d'appariement (ordre de passage, vœux de chacun à ce moment, organisme attribué).
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class placement_export {
    /**
     * Tableau récapitulatif des affectations d'une thématique, pour l'export : un étudiant par ligne,
     * avec ses vœux dans l'ordre, l'organisme attribué et le rang du vœu satisfait.
     *
     * @param \stdClass $theme
     * @param array $students Étudiants à lister (id => utilisateur).
     * @return array {columns: string[], rows: array[]}
     */
    public static function offers(\stdClass $theme, array $students) {
        $offers = stage_placement_get_offers($theme->id);
        $wishes = stage_placement_get_all_wishes($theme->id);
        $assignments = stage_placement_get_assignments($theme->id);
        $choices = max(1, (int) $theme->placementchoices);
        foreach ($wishes as $userwishes) {
            $choices = max($choices, count($userwishes));
        }

        $columns = [get_string('lastname'), get_string('firstname'), get_string('email')];
        for ($rank = 1; $rank <= $choices; $rank++) {
            $columns[] = get_string('placementwishrank', 'mod_stage', $rank);
        }
        $columns[] = get_string('placementassignment', 'mod_stage');
        $columns[] = get_string('placementsatisfiedwish', 'mod_stage');

        $rows = [];
        foreach ($students as $student) {
            $row = [$student->lastname, $student->firstname, $student->email];
            $userwishes = $wishes[$student->id] ?? [];
            for ($index = 0; $index < $choices; $index++) {
                $offerid = $userwishes[$index] ?? 0;
                $row[] = isset($offers[$offerid]) ? stage_plain_string($offers[$offerid]->name) : '';
            }
            $assignment = $assignments[$student->id] ?? null;
            $row[] = $assignment && isset($offers[$assignment->offerid])
                ? stage_plain_string($offers[$assignment->offerid]->name) : '';
            $row[] = $assignment ? \mod_stage\output\placement_view::rank_label($assignment->wishrank) : '';
            $rows[] = $row;
        }
        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * Trace d'un tour d'appariement, pour l'export : un étudiant par ligne, dans l'ordre de passage,
     * avec ses vœux au moment du tour et l'organisme attribué.
     *
     * @param \stdClass $round Enregistrement stage_placement_round.
     * @return array {columns: string[], rows: array[]}
     */
    public static function round(\stdClass $round) {
        global $DB;

        $offers = $DB->get_records_menu('stage_placement_offer', ['themeid' => $round->themeid], '', 'id, name');
        $name = fn($offerid) => isset($offers[$offerid]) ? stage_plain_string($offers[$offerid]) : '#' . $offerid;
        $draws = $DB->get_records('stage_placement_draw', ['roundid' => $round->id], 'position ASC');
        $users = $draws ? $DB->get_records_list('user', 'id', array_column($draws, 'userid')) : [];
        $columns = [
            get_string('placementroundposition', 'mod_stage'), get_string('lastname'), get_string('firstname'),
            get_string('email'), get_string('placementwishes', 'mod_stage'), get_string('placementassignment', 'mod_stage'),
            get_string('placementsatisfiedwish', 'mod_stage'),
        ];
        $rows = [];
        foreach ($draws as $draw) {
            $user = $users[$draw->userid] ?? null;
            $wishes = $draw->wishes === null || $draw->wishes === '' ? [] : explode(',', $draw->wishes);
            $rows[] = [
                (int) $draw->position,
                $user ? $user->lastname : '',
                $user ? $user->firstname : '',
                $user ? $user->email : '',
                implode(' ; ', array_map($name, $wishes)),
                $draw->offerid ? $name($draw->offerid) : '',
                $draw->offerid ? \mod_stage\output\placement_view::rank_label($draw->wishrank) : '',
            ];
        }
        array_unshift($rows, [
            get_string('placementroundseed', 'mod_stage', (object) [
                'seed' => (int) $round->seed, 'mode' => $round->mode, 'date' => userdate($round->timecreated),
            ]), '', '', '', '', '', '',
        ]);
        return ['columns' => $columns, 'rows' => $rows];
    }
}
