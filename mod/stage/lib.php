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
 * Rapport de stage : aucun dépôt de document n'est demandé à l'étudiant sur cette thématique.
 */
define('STAGE_REPORT_NONE', 0);
/**
 * Rapport de stage : le dépôt de documents est proposé lors de l'auto-évaluation, mais
 * l'étudiant peut soumettre son auto-évaluation sans avoir rien déposé.
 */
define('STAGE_REPORT_OPTIONAL', 1);
/**
 * Rapport de stage : le dépôt d'au moins un document est exigé pour pouvoir soumettre
 * l'auto-évaluation.
 */
define('STAGE_REPORT_REQUIRED', 2);

/**
 * Zone de fichiers (file area) des rapports de stage déposés par les étudiants, l'itemid étant
 * l'identifiant de la saisie (stage_entry.id).
 */
define('STAGE_REPORT_FILEAREA', 'report');

/**
 * Nombre de jours avant le début d'un stage à partir duquel l'étudiant est relancé si sa
 * convention n'est toujours pas signée (voir \mod_stage\task\send_convention_reminders).
 */
define('STAGE_CONVENTION_REMINDER_DAYS', 7);

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
            // Faux tant que backup/moodle2/ n'est pas fourni : déclarer la prise en charge sans
            // les classes de sauvegarde correspondantes fait échouer la sauvegarde de tout cours
            // contenant l'activité, au lieu de simplement l'en exclure.
            return false;
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
    global $CFG, $DB;

    require_once($CFG->dirroot . '/mod/stage/locallib.php');

    if (!$DB->get_record('stage', ['id' => $id])) {
        return false;
    }

    // Saisies et tout ce qui leur est rattaché (périodes, jours ouvrés, détail de convention,
    // réponses). Les fichiers sont laissés à Moodle, qui supprime le contexte du module et ses
    // zones de fichiers juste après.
    stage_delete_entries($DB->get_fieldset_select('stage_entry', 'id', 'stageid = ?', [$id]));
    $DB->delete_records('stage_entry_teacher', ['stageid' => $id]);
    $questionids = $DB->get_fieldset_select('stage_question', 'id', 'stageid = ?', [$id]);
    if ($questionids) {
        [$insql, $inparams] = $DB->get_in_or_equal($questionids);
        $DB->delete_records_select('stage_question_theme', "questionid $insql", $inparams);
    }
    $DB->delete_records('stage_question', ['stageid' => $id]);
    $themeids = $DB->get_fieldset_select('stage_theme', 'id', 'stageid = ?', [$id]);
    if ($themeids) {
        [$insql, $inparams] = $DB->get_in_or_equal($themeids);
        $DB->delete_records_select('stage_theme_teacher', "themeid $insql", $inparams);
        // Durées par année d'étude : rattachées à la thématique, elles doivent disparaître avec elle.
        $DB->delete_records_select('stage_theme_duration', "themeid $insql", $inparams);
    }
    $DB->delete_records('stage_theme', ['stageid' => $id]);
    $DB->delete_records('stage_convention_template', ['stageid' => $id]);
    // Durées exigées par année et modèles de courriels sont rattachés à l'instance elle-même.
    $DB->delete_records('stage_year_requirement', ['stageid' => $id]);
    $DB->delete_records('stage_email_template', ['stageid' => $id]);
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
