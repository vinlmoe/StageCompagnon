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
 * Service web permettant à la DEVE d'enregistrer un ou plusieurs stages,
 * par exemple depuis un script externe (ex. lecture d'un fichier Excel/CSV côté client).
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class register_entries extends \external_api {

    /**
     * Parameters definition.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters() {
        return new \external_function_parameters([
            'cmid' => new \external_value(PARAM_INT, "Id du module d'activité stage"),
            'entries' => new \external_multiple_structure(
                new \external_single_structure([
                    'userid' => new \external_value(PARAM_INT, "Id de l'étudiant"),
                    'themeid' => new \external_value(PARAM_INT, 'Id de la thématique'),
                    'structure' => new \external_value(PARAM_TEXT, "Structure d'accueil", VALUE_DEFAULT, ''),
                    'datestart' => new \external_value(PARAM_INT, 'Date de début (timestamp)', VALUE_DEFAULT, 0),
                    'dateend' => new \external_value(PARAM_INT, 'Date de fin (timestamp)', VALUE_DEFAULT, 0),
                    'declaredduration' => new \external_value(PARAM_INT, 'Durée déclarée en jours'),
                ])
            ),
        ]);
    }

    /**
     * Registers one or several internship entries on behalf of students.
     *
     * @param int $cmid
     * @param array $entries
     * @return array
     */
    public static function execute($cmid, $entries) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid, 'entries' => $entries]);

        $cm = get_coursemodule_from_id('stage', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/stage:registerstages', $context);

        $stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);
        $enrolled = array_flip(array_keys(stage_get_enrolled_students($context)));
        $validthemeids = array_flip(array_keys(stage_get_themes($stage->id)));
        // Un même étudiant a déjà un stage sur cette thématique : on l'écarte plutôt que
        // de créer un doublon silencieux via l'API.
        $existingpairs = stage_get_existing_theme_pairs($stage->id);

        $created = [];
        $duplicates = [];
        foreach ($params['entries'] as $entrydata) {
            if (!isset($enrolled[$entrydata['userid']]) || !isset($validthemeids[$entrydata['themeid']])) {
                continue;
            }
            $pairkey = stage_duplicate_key(
                $entrydata['userid'], $entrydata['themeid'], $entrydata['datestart'] ?: null, $entrydata['dateend'] ?: null
            );
            if (isset($existingpairs[$pairkey])) {
                $duplicates[] = $entrydata['userid'];
                continue;
            }
            $existingpairs[$pairkey] = true;

            $id = stage_register_entry(
                $stage->id,
                $entrydata['userid'],
                $entrydata['themeid'],
                $entrydata['structure'],
                $entrydata['datestart'] ?: null,
                $entrydata['dateend'] ?: null,
                $entrydata['declaredduration']
            );
            $created[] = $id;
        }

        return [
            'createdentryids' => $created,
            'createdcount' => count($created),
            'duplicateuserids' => $duplicates,
        ];
    }

    /**
     * Return value definition.
     *
     * @return \external_single_structure
     */
    public static function execute_returns() {
        return new \external_single_structure([
            'createdentryids' => new \external_multiple_structure(new \external_value(PARAM_INT, 'Id de saisie créée')),
            'createdcount' => new \external_value(PARAM_INT, 'Nombre de stages créés'),
            'duplicateuserids' => new \external_multiple_structure(
                new \external_value(PARAM_INT, 'Id étudiant écarté (stage déjà existant sur cette thématique)')
            ),
        ]);
    }
}
