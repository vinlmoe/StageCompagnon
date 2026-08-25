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

namespace mod_stage\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

/**
 * Service web renvoyant les stages de l'utilisateur courant pour une activité donnée.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_my_stages extends \external_api {

    /**
     * Parameters definition.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters() {
        return new \external_function_parameters([
            'cmid' => new \external_value(PARAM_INT, "Id du module d'activité stage"),
        ]);
    }

    /**
     * Returns the current user's own internship entries.
     *
     * @param int $cmid
     * @return array
     */
    public static function execute($cmid) {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);

        $cm = get_coursemodule_from_id('stage', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/stage:submit', $context);

        $stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);
        $entries = stage_get_student_entries($stage->id, $USER->id);

        $result = [];
        foreach ($entries as $entry) {
            $result[] = [
                'id' => $entry->id,
                'themeid' => $entry->themeid,
                'structure' => (string) $entry->structure,
                'datestart' => (int) $entry->datestart,
                'dateend' => (int) $entry->dateend,
                'declaredduration' => (int) $entry->declaredduration,
                'retainedduration' => (int) $entry->retainedduration,
                'status' => (int) $entry->status,
                'statuslabel' => stage_status_label($entry->status),
            ];
        }

        return $result;
    }

    /**
     * Return value definition.
     *
     * @return \external_multiple_structure
     */
    public static function execute_returns() {
        return new \external_multiple_structure(
            new \external_single_structure([
                'id' => new \external_value(PARAM_INT, 'Id de la saisie'),
                'themeid' => new \external_value(PARAM_INT, 'Id de la thématique'),
                'structure' => new \external_value(PARAM_TEXT, "Structure d'accueil"),
                'datestart' => new \external_value(PARAM_INT, 'Date de début (timestamp)'),
                'dateend' => new \external_value(PARAM_INT, 'Date de fin (timestamp)'),
                'declaredduration' => new \external_value(PARAM_INT, 'Durée déclarée (jours)'),
                'retainedduration' => new \external_value(PARAM_INT, 'Durée retenue (jours)'),
                'status' => new \external_value(PARAM_INT, 'Statut (0-3)'),
                'statuslabel' => new \external_value(PARAM_TEXT, 'Libellé du statut'),
            ])
        );
    }
}
