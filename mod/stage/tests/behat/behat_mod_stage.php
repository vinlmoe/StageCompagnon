<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Pas Behat propres à mod_stage : navigation vers les pages de gestion d'une activité.
 *
 * Usage : « I am on the "Nom de l'activité" "mod_stage > Teacher validation" page ».
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_stage extends behat_base {
    /**
     * Pages d'une activité, désignée par son nom ou son idnumber.
     *
     * Types reconnus : Activity (page d'accueil), Register (enregistrement des stages par la DEVE),
     * Conventions, Teacher validation, Academic office validation, Pilotage, Evaluation lists.
     * Pour une thématique à organismes proposés, désignée par « Activité > Thématique » : Placement
     * offers (organismes), Placement matching (vœux et appariement), Placement wishes (étudiant).
     * Convention request et Entry detail : demande de convention et détail du dernier stage d'un
     * étudiant sur une thématique, désigné par « Activité > identifiant de l'étudiant > Thématique ».
     *
     * @param string $type
     * @param string $identifier
     * @return moodle_url
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        $pages = [
            'activity' => 'view.php',
            'register' => 'register.php',
            'conventions' => 'conventions.php',
            'teacher validation' => 'teacher.php',
            'academic office validation' => 'deve.php',
            'pilotage' => 'dashboard.php',
            'evaluation lists' => 'evallists.php',
        ];
        $key = strtolower($type);
        // Pages d'une thématique à organismes proposés : « Activité > Thématique ».
        $themepages = [
            'placement offers' => 'placement_offers.php',
            'placement matching' => 'placement_matching.php',
            'placement wishes' => 'placement.php',
        ];
        // Demande de convention d'un stage déjà enregistré : « Activité > étudiant > Thématique ».
        if ($key === 'convention request' || $key === 'entry detail') {
            global $DB;
            [$activity, $username, $themename] = array_map('trim', explode('>', $identifier, 3));
            $cm = $this->get_cm_by_activity_name('stage', $activity);
            $themeid = $DB->get_field('stage_theme', 'id', ['stageid' => $cm->instance, 'name' => $themename], MUST_EXIST);
            $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
            $entryid = $DB->get_field_sql(
                'SELECT MAX(id) FROM {stage_entry} WHERE stageid = ? AND themeid = ? AND userid = ?',
                [$cm->instance, $themeid, $userid]
            );
            $page = $key === 'entry detail' ? 'entrydetail.php' : 'convention_request.php';
            return new moodle_url('/mod/stage/' . $page, ['id' => $cm->id, 'entryid' => $entryid]);
        }
        if (isset($themepages[$key])) {
            global $DB;
            [$activity, $themename] = array_map('trim', explode('>', $identifier, 2));
            $cm = $this->get_cm_by_activity_name('stage', $activity);
            $themeid = $DB->get_field('stage_theme', 'id', ['stageid' => $cm->instance, 'name' => $themename], MUST_EXIST);
            return new moodle_url('/mod/stage/' . $themepages[$key], ['id' => $cm->id, 'themeid' => $themeid]);
        }
        if (!isset($pages[$key])) {
            throw new Exception('Unrecognised mod_stage page type "' . $type . '"');
        }
        $cm = $this->get_cm_by_activity_name('stage', $identifier);
        return new moodle_url('/mod/stage/' . $pages[$key], ['id' => $cm->id]);
    }
}
