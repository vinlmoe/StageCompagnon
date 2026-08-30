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
 * Library of interface functions and constants for mod_stage.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** Stage saisi par l'étudiant, pas encore auto-évalué. */
define('STAGE_STATUS_ENREGISTRE', 0);
/** Auto-évalué par l'étudiant. */
define('STAGE_STATUS_EVAL_ETUDIANT', 1);
/** Évalué par l'enseignant référent. */
define('STAGE_STATUS_EVAL_ENSEIGNANT', 2);
/** Validé par la DEVE (validation finale). */
define('STAGE_STATUS_VALIDE_DEVE', 3);
/** Rejeté (par l'enseignant référent ou la DEVE) : nécessite une réinitialisation par la DEVE. */
define('STAGE_STATUS_NON_VALIDE', -1);
/** Annulé par la DEVE, à tout moment : état terminal, la saisie est conservée telle quelle. */
define('STAGE_STATUS_ANNULE', -2);
/** Nombre de lignes par page pour les listes paginées (DEVE / enseignant référent). */
define('STAGE_LIST_PERPAGE', 40);

/** Convention de stage : refusée par la DEVE avec commentaire, à corriger par l'étudiant. */
define('STAGE_CONVENTION_REJECTED', -1);
/** Convention de stage : pas encore demandée par l'étudiant. */
define('STAGE_CONVENTION_NONE', 0);
/** Convention de stage : demandée par l'étudiant (gabarit choisi), en attente de la DEVE. */
define('STAGE_CONVENTION_REQUESTED', 1);
/** Convention de stage : éditée par la DEVE (prête à être imprimée et signée). */
define('STAGE_CONVENTION_EDITED', 2);
/** Convention de stage : signée. Condition requise pour ouvrir l'auto-évaluation et l'évaluation. */
define('STAGE_CONVENTION_SIGNED', 3);
/**
 * Convention de stage : signée électroniquement sur SignVet, hors du circuit de gestion de
 * convention de ce plugin (pas de gabarit, de génération de PDF ni de PDF signé à téléverser).
 * Statut attribué automatiquement aux stages enregistrés en masse par la DEVE (voir
 * register.php, mode "bulk"), qui sont déjà signés sur SignVet au moment de leur enregistrement.
 * Ouvre le droit à l'auto-évaluation et à l'évaluation au même titre que STAGE_CONVENTION_SIGNED.
 */
define('STAGE_CONVENTION_SIGNVET', 4);
/**
 * Convention de stage : demande soumise par l'étudiant, en attente de validation par
 * l'enseignant.e référent.e AVANT d'être visible par la DEVE (voir
 * stage_convention_requires_teacher_validation()). N'existe que si l'option est activée dans les
 * paramètres généraux des conventions ; sinon la demande passe directement au statut
 * STAGE_CONVENTION_REQUESTED.
 */
define('STAGE_CONVENTION_TEACHERPENDING', 5);
/**
 * Convention de stage : dispensée par la DEVE lors de l'enregistrement du stage (stage ne
 * nécessitant pas de convention). Ouvre le droit à l'auto-évaluation et à l'évaluation au même
 * titre que STAGE_CONVENTION_SIGNED, sans qu'aucune convention ne soit jamais demandée ni signée.
 */
define('STAGE_CONVENTION_EXEMPT', 6);

/**
 * Returns the list of features supported by this module.
 *
 * @param string $feature FEATURE_xx constant.
 * @return mixed True/false or null depending on the feature.
 */
function stage_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_MOD_PURPOSE:
            return defined('MOD_PURPOSE_ADMINISTRATION') ? MOD_PURPOSE_ADMINISTRATION : 'administration';
        default:
            return null;
    }
}

/**
 * Saves a new instance of mod_stage into the database.
 *
 * @param stdClass $moduleinstance
 * @param mod_stage_mod_form|null $mform
 * @return int New instance id.
 */
function stage_add_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->timecreated = time();
    $moduleinstance->timemodified = $moduleinstance->timecreated;

    return $DB->insert_record('stage', $moduleinstance);
}

/**
 * Updates an instance of mod_stage in the database.
 *
 * @param stdClass $moduleinstance
 * @param mod_stage_mod_form|null $mform
 * @return bool True on success.
 */
function stage_update_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->timemodified = time();
    $moduleinstance->id = $moduleinstance->instance;

    return $DB->update_record('stage', $moduleinstance);
}

/**
 * Removes an instance of mod_stage from the database.
 *
 * @param int $id Id of the module instance.
 * @return bool True on success.
 */
function stage_delete_instance($id) {
    global $DB;

    if (!$DB->get_record('stage', ['id' => $id])) {
        return false;
    }

    $entries = $DB->get_records('stage_entry', ['stageid' => $id], '', 'id');
    foreach ($entries as $entry) {
        $DB->delete_records('stage_answer', ['entryid' => $entry->id]);
        $DB->delete_records('stage_convention_detail', ['entryid' => $entry->id]);
    }
    $DB->delete_records('stage_entry_teacher', ['stageid' => $id]);
    $DB->delete_records('stage_entry', ['stageid' => $id]);
    $questionids = $DB->get_fieldset_select('stage_question', 'id', 'stageid = ?', [$id]);
    if ($questionids) {
        [$insql, $inparams] = $DB->get_in_or_equal($questionids);
        $DB->delete_records_select('stage_question_theme', "questionid $insql", $inparams);
    }
    $DB->delete_records('stage_question', ['stageid' => $id]);
    $DB->delete_records('stage_theme', ['stageid' => $id]);
    $DB->delete_records('stage_convention_template', ['stageid' => $id]);
    $DB->delete_records('stage', ['id' => $id]);

    return true;
}

/**
 * Returns a small object with summary information about what a user has done
 * with a given particular instance of this module.
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param cm_info|stdClass $mod
 * @param stdClass $stage
 * @return stdClass|null
 */
function stage_user_outline($course, $user, $mod, $stage) {
    global $DB;

    $count = $DB->count_records('stage_entry', ['stageid' => $stage->id, 'userid' => $user->id]);
    if (!$count) {
        return null;
    }
    $result = new stdClass();
    $result->info = get_string('numstages', 'mod_stage', $count);
    $result->time = time();
    return $result;
}
