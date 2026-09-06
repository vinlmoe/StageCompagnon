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
 * Library of interface functions and constants for mod_stagesynthesis.
 *
 * @package   mod_stagesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Returns the list of features supported by this module.
 *
 * @param string $feature FEATURE_xx constant.
 * @return mixed True/false or null depending on the feature.
 */
function stagesynthesis_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            // Faux tant que backup/moodle2/ n'est pas fourni : déclarer la prise en charge sans
            // les classes de sauvegarde correspondantes fait échouer la sauvegarde de tout cours
            // contenant l'activité, au lieu de simplement l'en exclure.
            return false;
        case FEATURE_MOD_PURPOSE:
            return defined('MOD_PURPOSE_ADMINISTRATION') ? MOD_PURPOSE_ADMINISTRATION : MOD_PURPOSE_OTHER;
        default:
            return null;
    }
}

/**
 * Saves a new instance of mod_stagesynthesis in the database.
 *
 * @param stdClass $moduleinstance
 * @param mod_stagesynthesis_mod_form|null $mform
 * @return int The id of the newly inserted record.
 */
function stagesynthesis_add_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->timecreated = time();
    $moduleinstance->timemodified = $moduleinstance->timecreated;

    return $DB->insert_record('stagesynthesis', $moduleinstance);
}

/**
 * Updates an instance of mod_stagesynthesis in the database.
 *
 * @param stdClass $moduleinstance
 * @param mod_stagesynthesis_mod_form|null $mform
 * @return bool True on success.
 */
function stagesynthesis_update_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->timemodified = time();
    $moduleinstance->id = $moduleinstance->instance;

    return $DB->update_record('stagesynthesis', $moduleinstance);
}

/**
 * Removes an instance of mod_stagesynthesis from the database.
 *
 * @param int $id Id of the module instance.
 * @return bool True on success.
 */
function stagesynthesis_delete_instance($id) {
    global $DB;

    if (!$DB->get_record('stagesynthesis', ['id' => $id])) {
        return false;
    }

    $DB->delete_records('stagesynthesis_link', ['synthesisid' => $id]);
    $DB->delete_records('stagesynthesis', ['id' => $id]);

    return true;
}
