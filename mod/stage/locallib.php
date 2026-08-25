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
 * Fonctions métier internes pour mod_stage.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/stage/lib.php');

/**
 * Retourne le libellé lisible d'un statut de stage.
 *
 * @param int $status
 * @return string
 */
function stage_status_label($status, $lang = null) {
    switch ((int) $status) {
        case STAGE_STATUS_ANNULE:
            return get_string('status_annule', 'mod_stage', null, $lang);
        case STAGE_STATUS_NON_VALIDE:
            return get_string('status_nonvalide', 'mod_stage', null, $lang);
        case STAGE_STATUS_ENREGISTRE:
            return get_string('status_enregistre', 'mod_stage', null, $lang);
        case STAGE_STATUS_EVAL_ETUDIANT:
            return get_string('status_evaletudiant', 'mod_stage', null, $lang);
        case STAGE_STATUS_EVAL_ENSEIGNANT:
            return get_string('status_evalenseignant', 'mod_stage', null, $lang);
        case STAGE_STATUS_VALIDE_DEVE:
            return get_string('status_validedeve', 'mod_stage', null, $lang);
        default:
            return '';
    }
}

/**
 * Retourne une classe CSS de badge selon le statut.
 *
 * @param int $status
 * @return string
 */
function stage_status_badgeclass($status) {
    switch ((int) $status) {
        case STAGE_STATUS_ANNULE:
            return 'badge-dark';
        case STAGE_STATUS_NON_VALIDE:
            return 'badge-danger';
        case STAGE_STATUS_ENREGISTRE:
            return 'badge-secondary';
        case STAGE_STATUS_EVAL_ETUDIANT:
            return 'badge-info';
        case STAGE_STATUS_EVAL_ENSEIGNANT:
            return 'badge-primary';
        case STAGE_STATUS_VALIDE_DEVE:
            return 'badge-success';
        default:
            return 'badge-secondary';
    }
}

/**
 * Retourne le libellé lisible d'un statut de convention de stage.
 *
 * @param int $status
 * @return string
 */
function stage_convention_status_label($status) {
    switch ((int) $status) {
        case STAGE_CONVENTION_REJECTED:
            return get_string('conventionstatus_rejected', 'mod_stage');
        case STAGE_CONVENTION_REQUESTED:
            return get_string('conventionstatus_requested', 'mod_stage');
        case STAGE_CONVENTION_EDITED:
            return get_string('conventionstatus_edited', 'mod_stage');
        case STAGE_CONVENTION_SIGNED:
            return get_string('conventionstatus_signed', 'mod_stage');
        case STAGE_CONVENTION_SIGNVET:
            return get_string('conventionstatus_signvet', 'mod_stage');
        case STAGE_CONVENTION_TEACHERPENDING:
            return get_string('conventionstatus_teacherpending', 'mod_stage');
        default:
            return get_string('conventionstatus_none', 'mod_stage');
    }
}

/**
 * Indique si un statut de convention équivaut à "signée" (ouvre le droit à l'auto-évaluation et
 * à l'évaluation) : signée via le circuit de gestion de convention de ce plugin, ou signée sur
 * SignVet (stages enregistrés en masse par la DEVE, hors de ce circuit).
 *
 * @param int $status
 * @return bool
 */
function stage_convention_is_signed($status) {
    return in_array((int) $status, [STAGE_CONVENTION_SIGNED, STAGE_CONVENTION_SIGNVET], true);
}

/**
 * Retourne une classe CSS de badge selon le statut de convention.
 *
 * @param int $status
 * @return string
 */
function stage_convention_status_badgeclass($status) {
    switch ((int) $status) {
        case STAGE_CONVENTION_REJECTED:
            return 'badge-danger';
        case STAGE_CONVENTION_REQUESTED:
            return 'badge-info';
        case STAGE_CONVENTION_EDITED:
            return 'badge-primary';
        case STAGE_CONVENTION_SIGNED:
            return 'badge-success';
        case STAGE_CONVENTION_SIGNVET:
            return 'badge-success';
        case STAGE_CONVENTION_TEACHERPENDING:
            return 'badge-warning';
        default:
            return 'badge-secondary';
    }
}

/**
 * Indique si les demandes de convention de ce stage doivent d'abord être validées par
 * l'enseignant.e référent.e de l'étudiant avant d'être visibles par la DEVE (paramètre général
 * réglé par la DEVE, voir convention_templates.php).
 *
 * @param stdClass $stage
 * @return bool
 */
function stage_convention_requires_teacher_validation(stdClass $stage) {
    return !empty($stage->conventionrequireteachervalidation);
}

/**
 * Enregistre le paramètre général "validation enseignant avant transmission à la DEVE".
 *
 * @param int $stageid
 * @param bool $require
 * @return void
 */
function stage_save_convention_teacher_validation_setting($stageid, $require) {
    global $DB;

    $DB->update_record('stage', (object) [
        'id' => $stageid,
        'conventionrequireteachervalidation' => $require ? 1 : 0,
        'timemodified' => time(),
    ]);
}

/**
 * Liste les thématiques d'une activité stage, triées par année d'étude puis par ordre défini.
 *
 * @param int $stageid
 * @param bool $onlyvisible
 * @return array
 */
function stage_get_themes($stageid, $onlyvisible = false) {
    global $DB;

    $params = ['stageid' => $stageid];
    $where = 'stageid = :stageid';
    if ($onlyvisible) {
        $where .= ' AND visible = 1';
    }
    return $DB->get_records_select('stage_theme', $where, $params, 'studyyear ASC, sortorder ASC, name ASC');
}

/**
 * Options d'année d'étude proposées pour une thématique, afin d'organiser leur affichage pour
 * les étudiants (0 = non spécifiée, commune à toutes les années).
 *
 * @return array int => libellé
 */
function stage_studyyear_options($lang = null) {
    $options = [0 => get_string('studyyear_unspecified', 'mod_stage', null, $lang)];
    for ($year = 1; $year <= 6; $year++) {
        $options[$year] = get_string('studyyear_n', 'mod_stage', $year, $lang);
    }
    return $options;
}

/**
 * Libellé lisible d'une année d'étude de thématique.
 *
 * @param int $studyyear
 * @return string
 */
function stage_studyyear_label($studyyear, $lang = null) {
    $options = stage_studyyear_options($lang);
    return $options[(int) $studyyear] ?? $options[0];
}

/**
 * Libellé d'une thématique pour une liste déroulante : nom, année d'étude (si précisée) et
 * mention "obligatoire" le cas échéant, pour aider la DEVE et les enseignants à s'y retrouver.
 *
 * @param stdClass $theme
 * @return string
 */
function stage_theme_option_label(stdClass $theme) {
    $label = format_string($theme->name);
    if (!empty($theme->studyyear)) {
        $label .= ' - ' . stage_studyyear_label($theme->studyyear);
    }
    if (!empty($theme->mandatory)) {
        $label .= ' (' . get_string('mandatory', 'mod_stage') . ')';
    }
    return $label;
}

/**
 * Récupère les stages d'un étudiant, indexés par thématique.
 *
 * @param int $stageid
 * @param int $userid
 * @return array
 */
function stage_get_student_entries($stageid, $userid) {
    global $DB;

    return $DB->get_records('stage_entry', ['stageid' => $stageid, 'userid' => $userid], 'timecreated DESC');
}

/**
 * Calcule la durée totale retenue (validée DEVE) pour un étudiant, globale et par thématique.
 *
 * @param int $stageid
 * @param int $userid
 * @return stdClass
 */
function stage_get_student_progress($stageid, $userid) {
    global $DB;

    $themes = stage_get_themes($stageid, true);
    $entries = stage_get_student_entries($stageid, $userid);

    $progress = new stdClass();
    $progress->themes = [];
    $progress->totalretained = 0;
    $progress->totaldeclared = 0;

    foreach ($themes as $theme) {
        $t = new stdClass();
        $t->theme = $theme;
        $t->entries = [];
        $t->retained = 0;
        $t->declared = 0;
        $t->done = false;
        $progress->themes[$theme->id] = $t;
    }

    foreach ($entries as $entry) {
        $progress->totaldeclared += $entry->declaredduration;
        if ($entry->status == STAGE_STATUS_VALIDE_DEVE) {
            $progress->totalretained += $entry->retainedduration;
        }
        if (isset($progress->themes[$entry->themeid])) {
            $progress->themes[$entry->themeid]->entries[] = $entry;
            $progress->themes[$entry->themeid]->declared += $entry->declaredduration;
            if ($entry->status == STAGE_STATUS_VALIDE_DEVE) {
                $progress->themes[$entry->themeid]->retained += $entry->retainedduration;
            }
        }
    }

    foreach ($progress->themes as $themeid => $t) {
        if ($t->theme->mandatory) {
            $progress->themes[$themeid]->done = ($t->retained >= $t->theme->requiredduration) && $t->theme->requiredduration > 0;
        }
    }

    return $progress;
}

/**
 * Retourne les étudiants inscrits au cours (rôle student) dans le contexte du module.
 *
 * @param context $context
 * @return array
 */
function stage_get_enrolled_students(context $context) {
    return get_enrolled_users($context, 'mod/stage:submit', 0, 'u.*', 'u.lastname, u.firstname');
}

/**
 * Normalise un nom pour un rapprochement tolérant aux accents/casse/espaces multiples (ex.
 * import StageVet, voir import_stagevet.php, qui ne fournit pas toujours d'adresse e-mail
 * exploitable pour identifier l'étudiant).
 *
 * @param string $name
 * @return string
 */
function stage_normalize_name($name) {
    $name = core_text::strtolower(trim($name));
    $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    if ($transliterated !== false) {
        $name = $transliterated;
    }
    $name = preg_replace('/[^a-z]+/', ' ', $name);
    return trim(preg_replace('/\s+/', ' ', $name));
}

/**
 * Retourne les enseignants pouvant être référents (capacité evaluateteacher).
 *
 * @param context $context
 * @return array
 */
function stage_get_potential_teachers(context $context) {
    return get_enrolled_users($context, 'mod/stage:evaluateteacher', 0, 'u.*', 'u.lastname, u.firstname');
}

/**
 * Retourne les identifiants des étudiants attribués à un enseignant référent, pour ce stage.
 *
 * @param int $stageid
 * @param int $teacherid
 * @return array userid => userid
 */
function stage_get_assigned_students($stageid, $teacherid) {
    global $DB;

    return $DB->get_records_menu('stage_entry_teacher', ['stageid' => $stageid, 'teacherid' => $teacherid],
        '', 'studentid, studentid');
}

/**
 * Enregistre l'attribution d'un ou plusieurs enseignants référents à un étudiant.
 * Remplace les attributions existantes de l'étudiant pour ce stage.
 *
 * @param int $stageid
 * @param int $studentid
 * @param array $teacherids
 * @return void
 */
function stage_set_student_teachers($stageid, $studentid, array $teacherids) {
    global $DB;

    $teacherids = array_filter(array_unique(array_map('intval', $teacherids)));

    // Ne réécrit rien si l'attribution est inchangée : évite un delete + N inserts par
    // étudiant lorsque la DEVE enregistre le tableau complet sans avoir tout modifié.
    $existing = $DB->get_fieldset_select('stage_entry_teacher', 'teacherid',
        'stageid = :stageid AND studentid = :studentid', ['stageid' => $stageid, 'studentid' => $studentid]);
    $existing = array_map('intval', $existing);
    sort($existing);
    $wanted = $teacherids;
    sort($wanted);
    if ($existing === $wanted) {
        return;
    }

    $DB->delete_records('stage_entry_teacher', ['stageid' => $stageid, 'studentid' => $studentid]);

    $records = [];
    foreach ($teacherids as $teacherid) {
        $records[] = (object) [
            'stageid' => $stageid,
            'studentid' => $studentid,
            'teacherid' => $teacherid,
        ];
    }
    if ($records) {
        $DB->insert_records('stage_entry_teacher', $records);
    }
}

/**
 * Enregistre un stage pour un étudiant, à l'initiative de la DEVE (ou de l'étudiant lui-même,
 * voir student_register.php).
 *
 * @param int $stageid
 * @param int $studentid
 * @param int $themeid
 * @param string $structure
 * @param int $datestart
 * @param int $dateend
 * @param int $declaredduration
 * @param int $conventionstatus Statut de convention initial : STAGE_CONVENTION_NONE par défaut,
 *                               ou STAGE_CONVENTION_SIGNVET pour un enregistrement en masse
 *                               (stages déjà signés sur SignVet, hors circuit de gestion de
 *                               convention de ce plugin).
 * @return int Id de la saisie créée.
 */
function stage_register_entry($stageid, $studentid, $themeid, $structure, $datestart, $dateend, $declaredduration,
        $conventionstatus = STAGE_CONVENTION_NONE) {
    global $DB;

    $record = new stdClass();
    $record->stageid = $stageid;
    $record->userid = $studentid;
    $record->themeid = $themeid;
    $record->structure = $structure;
    $record->datestart = $datestart;
    $record->dateend = $dateend;
    $record->declaredduration = $declaredduration;
    $record->retainedduration = 0;
    $record->status = STAGE_STATUS_ENREGISTRE;
    $record->conventionstatus = $conventionstatus;
    $record->timecreated = time();
    $record->timemodified = time();

    return $DB->insert_record('stage_entry', $record);
}

/**
 * Met à jour les données de fond (thématique, structure, dates, durée) d'une saisie de stage,
 * à l'initiative de la DEVE.
 *
 * @param stdClass $entry
 * @param int $themeid
 * @param string $structure
 * @param int $datestart
 * @param int $dateend
 * @param int $declaredduration
 * @return void
 */
function stage_update_entry_details(stdClass $entry, $themeid, $structure, $datestart, $dateend, $declaredduration) {
    global $DB;

    $entry->themeid = $themeid;
    $entry->structure = $structure;
    $entry->datestart = $datestart;
    $entry->dateend = $dateend;
    $entry->declaredduration = $declaredduration;
    $entry->timemodified = time();
    $DB->update_record('stage_entry', $entry);
}

/**
 * Applique la validation étudiant (auto-évaluation) sur une saisie de stage.
 *
 * @param stdClass $entry
 * @param string|null $selfeval Commentaire libre, ou null pour conserver la valeur existante
 *                              (cas d'un formulaire de questions défini par la DEVE).
 * @return void
 */
function stage_apply_student_eval(stdClass $entry, $selfeval = null) {
    global $DB;

    if ($selfeval !== null) {
        $entry->studentselfeval = $selfeval;
    }
    if ($entry->status < STAGE_STATUS_EVAL_ETUDIANT) {
        $entry->status = STAGE_STATUS_EVAL_ETUDIANT;
    }
    $entry->timemodified = time();
    $DB->update_record('stage_entry', $entry);
}

/**
 * Applique la validation enseignant sur une saisie de stage.
 *
 * @param stdClass $entry
 * @param int $teacherid
 * @param string|null $comment Commentaire libre, ou null pour conserver la valeur existante
 *                             (cas d'un formulaire de questions défini par la DEVE).
 * @return void
 */
function stage_apply_teacher_eval(stdClass $entry, $teacherid, $comment = null) {
    global $DB;

    $entry->teacherid = $teacherid;
    if ($comment !== null) {
        $entry->teachereval = $comment;
    }
    $entry->teachertime = time();
    if ($entry->status < STAGE_STATUS_EVAL_ENSEIGNANT) {
        $entry->status = STAGE_STATUS_EVAL_ENSEIGNANT;
    }
    $entry->timemodified = time();
    $DB->update_record('stage_entry', $entry);
}

/**
 * Applique la validation finale DEVE sur une saisie de stage (unitaire ou en masse).
 *
 * @param stdClass $entry
 * @param int $deveuserid
 * @param int $retainedduration Durée retenue en jours (0 = reprendre la durée déclarée).
 * @param string $comment
 * @return void
 */
function stage_apply_deve_validation(stdClass $entry, $deveuserid, $retainedduration, $comment = '') {
    global $DB;

    $entry->deveuserid = $deveuserid;
    $entry->devecomment = $comment;
    $entry->devetime = time();
    $entry->retainedduration = $retainedduration > 0 ? $retainedduration : $entry->declaredduration;
    $entry->status = STAGE_STATUS_VALIDE_DEVE;
    $entry->timemodified = time();
    $DB->update_record('stage_entry', $entry);
}

/**
 * Marque une saisie de stage comme non validée par l'enseignant référent, à la place de la
 * valider. Comme pour une évaluation normale, la saisie n'est alors plus modifiable par
 * l'étudiant ni par l'enseignant : seule la DEVE peut la réinitialiser (stage_reset_entry).
 *
 * @param stdClass $entry
 * @param int $teacherid
 * @param string $comment Motif de non-validation.
 * @return void
 */
function stage_reject_by_teacher(stdClass $entry, $teacherid, $comment) {
    global $DB;

    $entry->teacherid = $teacherid;
    $entry->teachereval = $comment;
    $entry->teachertime = time();
    $entry->status = STAGE_STATUS_NON_VALIDE;
    $entry->timemodified = time();
    $DB->update_record('stage_entry', $entry);
}

/**
 * Marque une saisie de stage comme non validée par la DEVE, à la place de la valider.
 *
 * @param stdClass $entry
 * @param int $deveuserid
 * @param string $comment Motif de non-validation.
 * @return void
 */
function stage_reject_by_deve(stdClass $entry, $deveuserid, $comment) {
    global $DB;

    $entry->deveuserid = $deveuserid;
    $entry->devecomment = $comment;
    $entry->devetime = time();
    $entry->status = STAGE_STATUS_NON_VALIDE;
    $entry->timemodified = time();
    $DB->update_record('stage_entry', $entry);
}

/**
 * Réinitialise une saisie de stage à son état initial (à faire auto-évaluer), pour permettre à
 * l'étudiant et à l'enseignant référent de la modifier à nouveau. Action réservée à la DEVE :
 * l'auto-évaluation et l'évaluation enseignant ne sont pas modifiables une fois soumises, sauf
 * après ce type de réinitialisation.
 *
 * @param stdClass $entry
 * @return void
 */
function stage_reset_entry(stdClass $entry) {
    global $DB;

    $entry->status = STAGE_STATUS_ENREGISTRE;
    $entry->timemodified = time();
    $DB->update_record('stage_entry', $entry);
}

/**
 * Annule un stage, à tout moment et quel que soit son statut actuel (la DEVE reste seule
 * décisionnaire). La saisie est conservée telle quelle (aucune donnée supprimée) : seul le
 * statut passe à "Annulé", avec un commentaire obligatoire expliquant le motif.
 *
 * @param stdClass $entry
 * @param int $byuserid
 * @param string $comment
 * @return void
 */
function stage_cancel_entry(stdClass $entry, $byuserid, $comment) {
    global $DB;

    $entry->status = STAGE_STATUS_ANNULE;
    $entry->cancelledby = $byuserid;
    $entry->canceltime = time();
    $entry->cancelcomment = $comment;
    $entry->timemodified = time();
    $DB->update_record('stage_entry', $entry);
}

/**
 * Liste les enseignants référents attribués à un étudiant pour un stage donné.
 *
 * @param int $stageid
 * @param int $studentid
 * @return array Enregistrements user, indexés par id.
 */
function stage_get_student_teachers($stageid, $studentid) {
    global $DB;

    $sql = "SELECT u.*
              FROM {stage_entry_teacher} et
              JOIN {user} u ON u.id = et.teacherid
             WHERE et.stageid = :stageid AND et.studentid = :studentid";
    return $DB->get_records_sql($sql, ['stageid' => $stageid, 'studentid' => $studentid]);
}

/**
 * Envoie un e-mail aux enseignants référents d'un étudiant lorsque celui-ci vient de
 * s'auto-évaluer, pour qu'ils sachent qu'une saisie attend leur évaluation.
 *
 * @param stdClass $stage
 * @param stdClass $cm Course module.
 * @param stdClass $entry
 * @param stdClass $student
 * @return void
 */
function stage_notify_teachers_selfeval(stdClass $stage, stdClass $cm, stdClass $entry, stdClass $student) {
    $teachers = stage_get_student_teachers($stage->id, $entry->userid);
    if (empty($teachers)) {
        return;
    }

    $url = new moodle_url('/mod/stage/teacher.php', ['id' => $cm->id, 'entryid' => $entry->id]);
    $subject = get_string('selfevalnotifsubject', 'mod_stage', format_string($stage->name));
    $noreply = core_user::get_noreply_user();

    foreach ($teachers as $teacher) {
        $body = get_string('selfevalnotifbody', 'mod_stage', (object) [
            'student' => fullname($student),
            'stage' => format_string($stage->name),
            'url' => $url->out(false),
        ]);
        email_to_user($teacher, $noreply, $subject, $body);
    }
}

/**
 * Liste les questions d'évaluation définies par la DEVE pour une thématique et un type
 * d'évaluation donnés ('student' ou 'teacher').
 *
 * @param int $themeid
 * @param string $evaltype 'student' ou 'teacher'
 * @return array
 */
function stage_get_questions($themeid, $evaltype) {
    global $DB;

    $sql = "SELECT q.*
              FROM {stage_question} q
              JOIN {stage_question_theme} qt ON qt.questionid = q.id
             WHERE qt.themeid = :themeid AND q.evaltype = :evaltype
          ORDER BY q.sortorder ASC, q.id ASC";
    return $DB->get_records_sql($sql, ['themeid' => $themeid, 'evaltype' => $evaltype]);
}

/**
 * Liste les ids des thématiques auxquelles une question est actuellement associée.
 *
 * @param int $questionid
 * @return int[]
 */
function stage_get_question_themeids($questionid) {
    global $DB;

    return array_values($DB->get_fieldset_select('stage_question_theme', 'themeid', 'questionid = ?', [$questionid]));
}

/**
 * Remplace les associations thématique(s) <-> question par la liste donnée, ce qui permet de
 * réutiliser une même question (même intitulé, mêmes options) pour plusieurs thématiques.
 *
 * @param int $questionid
 * @param int[] $themeids
 * @return void
 */
function stage_set_question_themes($questionid, array $themeids) {
    global $DB;

    $themeids = array_unique(array_map('intval', $themeids));

    if (empty($themeids)) {
        $DB->delete_records('stage_question_theme', ['questionid' => $questionid]);
        return;
    }

    [$insql, $inparams] = $DB->get_in_or_equal($themeids, SQL_PARAMS_NAMED, 'th', false);
    $DB->delete_records_select('stage_question_theme', "questionid = :questionid AND themeid $insql",
        array_merge(['questionid' => $questionid], $inparams));

    foreach ($themeids as $themeid) {
        if (!$DB->record_exists('stage_question_theme', ['questionid' => $questionid, 'themeid' => $themeid])) {
            $DB->insert_record('stage_question_theme', (object) [
                'questionid' => $questionid,
                'themeid' => $themeid,
                'timecreated' => time(),
            ]);
        }
    }
}

/**
 * Supprime l'association d'une question à une thématique donnée. Si la question n'est plus
 * associée à aucune thématique après cette suppression, elle est entièrement supprimée (avec
 * les réponses déjà enregistrées pour cette question).
 *
 * @param int $questionid
 * @param int $themeid
 * @return void
 */
function stage_unlink_question_theme($questionid, $themeid) {
    global $DB;

    $DB->delete_records('stage_question_theme', ['questionid' => $questionid, 'themeid' => $themeid]);

    if (!$DB->record_exists('stage_question_theme', ['questionid' => $questionid])) {
        $DB->delete_records('stage_answer', ['questionid' => $questionid]);
        $DB->delete_records('stage_question', ['id' => $questionid]);
    }
}

/**
 * Liste les questions d'un stage déjà définies pour d'autres thématiques que celle donnée, afin
 * de permettre à la DEVE de réutiliser une question existante sans la recréer.
 *
 * @param int $stageid
 * @param int $themeid Thématique courante, exclue des associations déjà en place.
 * @return array
 */
function stage_get_reusable_questions($stageid, $themeid) {
    global $DB;

    $sql = "SELECT DISTINCT q.*
              FROM {stage_question} q
              JOIN {stage_question_theme} qt ON qt.questionid = q.id
             WHERE q.stageid = :stageid
               AND q.id NOT IN (
                    SELECT questionid FROM {stage_question_theme} WHERE themeid = :themeid
               )
          ORDER BY q.name ASC";
    return $DB->get_records_sql($sql, ['stageid' => $stageid, 'themeid' => $themeid]);
}

/**
 * Découpe le champ "options" (une option par ligne) d'une question à choix multiples.
 *
 * @param stdClass $question
 * @return array
 */
function stage_question_options(stdClass $question) {
    $lines = preg_split('/\r\n|\r|\n/', (string) $question->options);
    $options = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '') {
            $options[] = $line;
        }
    }
    return $options;
}

/**
 * Récupère les réponses déjà enregistrées pour une saisie de stage, indexées par question.
 *
 * @param int $entryid
 * @return array questionid => stdClass
 */
function stage_get_answers($entryid) {
    global $DB;

    return $DB->get_records('stage_answer', ['entryid' => $entryid], '', 'questionid, id, answertext');
}

/**
 * Enregistre les réponses soumises pour un jeu de questions et une saisie de stage.
 *
 * @param int $entryid
 * @param array $questions Liste de stage_question
 * @param array $submitted Tableau questionid => valeur soumise
 * @return void
 */
function stage_save_answers($entryid, array $questions, array $submitted) {
    global $DB;

    $existing = stage_get_answers($entryid);
    foreach ($questions as $question) {
        $value = $submitted[$question->id] ?? '';
        $value = is_array($value) ? implode(', ', $value) : (string) $value;

        if (isset($existing[$question->id])) {
            $answer = $existing[$question->id];
            $answer->answertext = $value;
            $answer->timemodified = time();
            $DB->update_record('stage_answer', $answer);
        } else {
            $DB->insert_record('stage_answer', (object) [
                'entryid' => $entryid,
                'questionid' => $question->id,
                'answertext' => $value,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }
    }
}

/**
 * Lit les réponses soumises pour un jeu de questions, depuis les paramètres de la requête.
 *
 * @param array $questions Liste de stage_question
 * @return array questionid => valeur soumise
 */
function stage_get_submitted_answers(array $questions) {
    $submitted = [];
    foreach ($questions as $question) {
        $submitted[$question->id] = optional_param('q_' . $question->id, '', PARAM_TEXT);
    }
    return $submitted;
}

/**
 * Produit le HTML des champs de formulaire correspondant à un jeu de questions
 * d'évaluation, pré-remplis avec les réponses déjà enregistrées.
 *
 * Partagé par le formulaire d'auto-évaluation de l'étudiant et celui de l'enseignant.
 *
 * @param array $questions Liste de stage_question
 * @param array $answers Réponses existantes, indexées par questionid
 * @return string
 */
function stage_render_question_fields(array $questions, array $answers) {
    $out = '';

    foreach ($questions as $question) {
        $current = isset($answers[$question->id]) ? $answers[$question->id]->answertext : '';
        $fieldname = 'q_' . $question->id;
        $required = $question->required ? ['required' => 'required'] : [];

        $out .= html_writer::start_tag('div', ['class' => 'form-group mb-3']);
        $out .= html_writer::tag('label', format_string($question->name) . ($question->required ? ' *' : ''),
            ['for' => $fieldname]);

        if ($question->qtype === 'choice') {
            foreach (stage_question_options($question) as $index => $option) {
                $optionid = $fieldname . '_' . $index;
                $out .= html_writer::start_tag('div', ['class' => 'form-check']);
                $out .= html_writer::empty_tag('input', array_merge([
                    'type' => 'radio',
                    'name' => $fieldname,
                    'value' => $option,
                    'id' => $optionid,
                    'class' => 'form-check-input',
                    'checked' => ($current === $option) ? 'checked' : null,
                ], $required));
                $out .= html_writer::tag('label', s($option), ['for' => $optionid, 'class' => 'form-check-label']);
                $out .= html_writer::end_tag('div');
            }
        } else {
            $out .= html_writer::tag('textarea', s($current), array_merge([
                'name' => $fieldname,
                'id' => $fieldname,
                'rows' => 3,
                'class' => 'form-control',
            ], $required));
        }

        $out .= html_writer::end_tag('div');
    }

    return $out;
}

/**
 * Charge en une seule requête les utilisateurs référencés par un ensemble de saisies,
 * avec les champs nécessaires à fullname().
 *
 * @param array $entries Liste de stage_entry
 * @return array userid => stdClass
 */
function stage_get_entry_users(array $entries) {
    global $DB;

    $userids = [];
    foreach ($entries as $entry) {
        $userids[$entry->userid] = $entry->userid;
    }
    if (empty($userids)) {
        return [];
    }

    $fields = 'id, ' . implode(', ', \core_user\fields::get_name_fields());
    return $DB->get_records_list('user', 'id', $userids, '', $fields);
}

/**
 * Produit le HTML, en lecture seule, des questions d'un formulaire et des réponses
 * qui y ont été apportées. Utilisé pour montrer l'auto-évaluation de l'étudiant à
 * l'enseignant référent et à la DEVE.
 *
 * @param array $questions Liste de stage_question
 * @param array $answers Réponses existantes, indexées par questionid
 * @return string
 */
function stage_render_answers_readonly(array $questions, array $answers) {
    if (empty($questions)) {
        return '';
    }

    $out = html_writer::start_tag('dl', ['class' => 'stage-answers']);
    foreach ($questions as $question) {
        $current = isset($answers[$question->id]) ? trim((string) $answers[$question->id]->answertext) : '';
        $out .= html_writer::tag('dt', format_string($question->name));
        $out .= html_writer::tag('dd', $current !== ''
            ? nl2br(s($current))
            : html_writer::tag('em', get_string('noanswer', 'mod_stage')));
    }
    $out .= html_writer::end_tag('dl');

    return $out;
}

/**
 * Construit la clé identifiant un doublon : même étudiant, même thématique et mêmes dates
 * de stage (un étudiant peut légitimement refaire la même thématique à une autre période).
 *
 * @param int $userid
 * @param int $themeid
 * @param int|null $datestart
 * @param int|null $dateend
 * @return string
 */
function stage_duplicate_key($userid, $themeid, $datestart, $dateend) {
    return $userid . ':' . $themeid . ':' . (int) $datestart . ':' . (int) $dateend;
}

/**
 * Renvoie l'ensemble des stages déjà enregistrés pour une activité (étudiant, thématique et
 * dates), pour détecter les doublons lors d'un enregistrement en masse ou d'un import.
 *
 * @param int $stageid
 * @return array clé stage_duplicate_key() => true
 */
function stage_get_existing_theme_pairs($stageid) {
    global $DB;

    $pairs = [];
    $rows = $DB->get_records('stage_entry', ['stageid' => $stageid], '', 'id, userid, themeid, datestart, dateend');
    foreach ($rows as $row) {
        $pairs[stage_duplicate_key($row->userid, $row->themeid, $row->datestart, $row->dateend)] = true;
    }
    return $pairs;
}

/**
 * Indique si un étudiant a déjà un stage sur cette thématique avec ces mêmes dates, pour
 * empêcher la création d'un doublon depuis le formulaire d'enregistrement unitaire de la DEVE.
 *
 * @param int $stageid
 * @param int $userid
 * @param int $themeid
 * @param int|null $datestart
 * @param int|null $dateend
 * @param int $excludeentryid Saisie à ignorer (cas d'une édition sur elle-même).
 * @return bool
 */
function stage_entry_is_duplicate($stageid, $userid, $themeid, $datestart, $dateend, $excludeentryid = 0) {
    global $DB;

    $params = [
        'stageid' => $stageid,
        'userid' => $userid,
        'themeid' => $themeid,
        'datestart' => (int) $datestart,
        'dateend' => (int) $dateend,
    ];
    $sql = 'stageid = :stageid AND userid = :userid AND themeid = :themeid
            AND COALESCE(datestart, 0) = :datestart AND COALESCE(dateend, 0) = :dateend';
    if ($excludeentryid) {
        $sql .= ' AND id <> :excludeentryid';
        $params['excludeentryid'] = $excludeentryid;
    }

    return $DB->record_exists_select('stage_entry', $sql, $params);
}

/**
 * Colonnes disponibles pour le tri des listes de saisies de stage (DEVE / enseignant).
 *
 * @return array clé de tri => libellé
 */
function stage_entry_sort_options() {
    return [
        'student' => get_string('student', 'mod_stage'),
        'theme' => get_string('theme', 'mod_stage'),
        'status' => get_string('status', 'mod_stage'),
        'duration' => get_string('declaredduration', 'mod_stage'),
        'timecreated' => get_string('registeredon', 'mod_stage'),
    ];
}

/**
 * Recherche/tri des saisies de stage, pour les listes de la DEVE et des enseignants référents.
 *
 * @param int $stageid
 * @param array $filters ['search' => nom étudiant, 'themeid' => int, 'status' => int]
 * @param string $sort Une des clés de stage_entry_sort_options().
 * @param string $dir 'ASC' ou 'DESC'.
 * @param array|null $restrictuserids Si fourni, limite aux saisies de ces étudiants (enseignant référent).
 * @return array
 */
function stage_get_filtered_entries($stageid, array $filters = [], $sort = 'timecreated', $dir = 'DESC',
        ?array $restrictuserids = null) {
    global $DB;

    if ($restrictuserids !== null && empty($restrictuserids)) {
        return [];
    }

    $params = ['stageid' => $stageid];
    $where = ['e.stageid = :stageid'];

    if (!empty($filters['search'])) {
        $fullname = $DB->sql_concat('u.firstname', "' '", 'u.lastname');
        $where[] = $DB->sql_like($fullname, ':search', false, false);
        $params['search'] = '%' . $DB->sql_like_escape($filters['search']) . '%';
    }
    if (!empty($filters['themeid'])) {
        $where[] = 'e.themeid = :themeid';
        $params['themeid'] = (int) $filters['themeid'];
    }
    if (isset($filters['status']) && $filters['status'] !== '') {
        $where[] = 'e.status = :status';
        $params['status'] = (int) $filters['status'];
    }
    if (isset($filters['statuslt']) && $filters['statuslt'] !== '') {
        $where[] = 'e.status < :statuslt';
        $params['statuslt'] = (int) $filters['statuslt'];
    }
    if ($restrictuserids !== null) {
        [$insql, $inparams] = $DB->get_in_or_equal($restrictuserids, SQL_PARAMS_NAMED, 'ru');
        $where[] = "e.userid $insql";
        $params += $inparams;
    }

    $sortmap = [
        'student' => 'u.lastname, u.firstname',
        'theme' => 't.name',
        'status' => 'e.status',
        'duration' => 'e.declaredduration',
        'timecreated' => 'e.timecreated',
    ];
    $sortcolumn = $sortmap[$sort] ?? $sortmap['timecreated'];
    $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

    $sql = "SELECT e.*
              FROM {stage_entry} e
              JOIN {user} u ON u.id = e.userid
         LEFT JOIN {stage_theme} t ON t.id = e.themeid
             WHERE " . implode(' AND ', $where) . "
          ORDER BY $sortcolumn $dir, e.id $dir";

    return $DB->get_records_sql($sql, $params);
}

/**
 * Construit l'URL de tri (nouvelle colonne ou inversion du sens) pour un en-tête de tableau.
 *
 * @param moodle_url $baseurl
 * @param string $key
 * @param string $currentsort
 * @param string $currentdir
 * @return moodle_url
 */
function stage_sort_url(moodle_url $baseurl, $key, $currentsort, $currentdir) {
    $newdir = ($currentsort === $key && strtoupper($currentdir) === 'ASC') ? 'DESC' : 'ASC';
    $url = new moodle_url($baseurl);
    $url->params(['tsort' => $key, 'tdir' => $newdir]);
    return $url;
}

/**
 * Rend un lien d'en-tête de colonne triable, avec indicateur de sens si c'est la colonne active.
 *
 * @param string $label
 * @param string $key
 * @param moodle_url $baseurl
 * @param string $currentsort
 * @param string $currentdir
 * @return string
 */
function stage_sort_header($label, $key, moodle_url $baseurl, $currentsort, $currentdir) {
    $indicator = '';
    if ($currentsort === $key) {
        $indicator = ' ' . (strtoupper($currentdir) === 'ASC' ? '▲' : '▼');
    }
    return html_writer::link(stage_sort_url($baseurl, $key, $currentsort, $currentdir), $label . $indicator);
}

/**
 * Rend le formulaire de recherche/filtre (nom étudiant, thématique, étape de validation)
 * au-dessus des listes de saisies de stage. Les autres paramètres présents dans $baseurl
 * (id, mode...) sont préservés en champs cachés.
 *
 * @param moodle_url $baseurl URL courante de la page, avec les valeurs de filtre déjà appliquées.
 * @param array $themes Thématiques proposées dans le filtre.
 * @param string $search
 * @param int $themeid
 * @param string $status
 * @param bool $showstatus Affiche ou non le filtre par étape de validation (inutile sur une
 *                          liste déjà restreinte à un sous-ensemble de statuts, ex. saisies en attente DEVE).
 * @return string
 */
function stage_render_list_filters(moodle_url $baseurl, array $themes, $search, $themeid, $status, $showstatus = true) {
    $formurl = new moodle_url($baseurl);
    $formurl->remove_params('search', 'themeid', 'status', 'tsort', 'tdir');

    $out = html_writer::start_tag('form', ['method' => 'get', 'action' => $formurl, 'class' => 'form-inline stage-filters mb-3']);
    foreach ($formurl->params() as $key => $value) {
        $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $key, 'value' => $value]);
    }
    $out .= html_writer::empty_tag('input', [
        'type' => 'text', 'name' => 'search', 'value' => s($search),
        'placeholder' => get_string('searchstudent', 'mod_stage'), 'class' => 'form-control mr-2',
    ]);

    $themeoptions = [0 => get_string('allthemes', 'mod_stage')];
    foreach ($themes as $theme) {
        $themeoptions[$theme->id] = format_string($theme->name);
    }
    $out .= html_writer::select($themeoptions, 'themeid', $themeid, false, ['class' => 'form-control mr-2']);

    if ($showstatus) {
        $statusoptions = ['' => get_string('allstatuses', 'mod_stage')];
        foreach ([STAGE_STATUS_ANNULE, STAGE_STATUS_NON_VALIDE, STAGE_STATUS_ENREGISTRE, STAGE_STATUS_EVAL_ETUDIANT,
                STAGE_STATUS_EVAL_ENSEIGNANT, STAGE_STATUS_VALIDE_DEVE] as $statuscode) {
            $statusoptions[$statuscode] = stage_status_label($statuscode);
        }
        $out .= html_writer::select($statusoptions, 'status', $status, false, ['class' => 'form-control mr-2']);
    }

    $out .= html_writer::empty_tag('input', [
        'type' => 'submit', 'value' => get_string('search'), 'class' => 'btn btn-secondary mr-2',
    ]);
    $out .= html_writer::link($formurl, get_string('resetfilters', 'mod_stage'), ['class' => 'btn btn-link']);
    $out .= html_writer::end_tag('form');

    return $out;
}

/**
 * Découpe un tableau pour l'affichage d'une page, et rend la barre de pagination correspondante.
 * Le tableau complet est supposé déjà filtré/trié : on ne pagine que l'affichage.
 *
 * @param array $items
 * @param int $page Page courante (0-indexée).
 * @param moodle_url $baseurl URL de la page, avec les filtres déjà appliqués (le paramètre
 *                            "page" y est ajouté par la barre de pagination elle-même).
 * @param int $perpage
 * @return array [page d'éléments à afficher, html de la barre de pagination]
 */
function stage_paginate(array $items, $page, moodle_url $baseurl, $perpage = STAGE_LIST_PERPAGE) {
    global $OUTPUT;

    $items = array_values($items);
    $total = count($items);
    $pageitems = array_slice($items, $page * $perpage, $perpage);
    $pagingbar = $total > $perpage ? $OUTPUT->render(new paging_bar($total, $page, $perpage, $baseurl)) : '';

    return [$pageitems, $pagingbar];
}

/**
 * Vue de pilotage DEVE : pour chaque étudiant inscrit, l'avancement des thématiques
 * obligatoires, la durée totale retenue et le nombre de saisies encore en attente.
 *
 * @param int $stageid
 * @param context $context
 * @param array|null $restrictuserids Si fourni, limite aux étudiants de cette liste (enseignant référent).
 * @return array Liste d'objets {user, progress, entrycount, pendingcount, mandatorytotal, mandatorydone, complete}
 */
function stage_get_pilotage_overview($stageid, context $context, ?array $restrictuserids = null) {
    $students = stage_get_enrolled_students($context);
    if ($restrictuserids !== null) {
        $students = array_filter($students, function($student) use ($restrictuserids) {
            return in_array($student->id, $restrictuserids);
        });
    }

    $rows = [];
    foreach ($students as $student) {
        $progress = stage_get_student_progress($stageid, $student->id);
        $entries = stage_get_student_entries($stageid, $student->id);

        $pending = 0;
        foreach ($entries as $entry) {
            if ($entry->status < STAGE_STATUS_VALIDE_DEVE) {
                $pending++;
            }
        }

        $mandatorytotal = 0;
        $mandatorydone = 0;
        foreach ($progress->themes as $t) {
            if ($t->theme->mandatory) {
                $mandatorytotal++;
                if ($t->done) {
                    $mandatorydone++;
                }
            }
        }

        $rows[] = (object) [
            'user' => $student,
            'progress' => $progress,
            'entrycount' => count($entries),
            'pendingcount' => $pending,
            'mandatorytotal' => $mandatorytotal,
            'mandatorydone' => $mandatorydone,
            'complete' => $mandatorytotal > 0 && $mandatorydone === $mandatorytotal,
        ];
    }

    return $rows;
}

/**
 * Rend la barre de liens de navigation entre les pages de gestion de l'activité, affichée en
 * haut de view.php et de dashboard.php (page d'atterrissage de la DEVE et de l'enseignant
 * référent, voir view.php). Les pages d'administration (thématiques, gabarits de convention,
 * enseignants référents) sont regroupées sous un seul lien "Administration", en fin de liste.
 *
 * @param stdClass $cm Course module.
 * @param context $context Contexte du module stage.
 * @return string HTML, chaîne vide si l'utilisateur n'a accès à aucun lien.
 */
function stage_render_navlinks(stdClass $cm, context $context) {
    $navlinks = [];
    if (has_capability('mod/stage:registerstages', $context)) {
        $navlinks[] = html_writer::link(new moodle_url('/mod/stage/register.php', ['id' => $cm->id]),
            get_string('registerstages', 'mod_stage'));
        $navlinks[] = html_writer::link(new moodle_url('/mod/stage/conventions.php', ['id' => $cm->id]),
            get_string('conventions', 'mod_stage'));
    }
    if (has_capability('mod/stage:validatedeve', $context)) {
        $navlinks[] = html_writer::link(new moodle_url('/mod/stage/deve.php', ['id' => $cm->id]),
            get_string('devevalidation', 'mod_stage'));
    }
    if (has_capability('mod/stage:evaluateteacher', $context)) {
        $navlinks[] = html_writer::link(new moodle_url('/mod/stage/teacher.php', ['id' => $cm->id]),
            get_string('teachervalidation', 'mod_stage'));
    }
    if (has_capability('mod/stage:viewall', $context) || has_capability('mod/stage:evaluateteacher', $context)) {
        $navlinks[] = html_writer::link(new moodle_url('/mod/stage/dashboard.php', ['id' => $cm->id]),
            get_string('pilotage', 'mod_stage'));
    }
    if (has_capability('mod/stage:viewall', $context)) {
        $navlinks[] = html_writer::link(new moodle_url('/mod/stage/export.php', ['id' => $cm->id]),
            get_string('exportexcel', 'mod_stage'));
    }
    if (has_capability('mod/stage:managethemes', $context) || has_capability('mod/stage:manageteachers', $context)) {
        $navlinks[] = html_writer::link(new moodle_url('/mod/stage/administration.php', ['id' => $cm->id]),
            get_string('administration', 'mod_stage'));
    }

    if (empty($navlinks)) {
        return '';
    }
    return html_writer::div(implode(' | ', $navlinks), 'generalbox stage-navlinks');
}

/**
 * Affiche l'avancement d'un étudiant (thématiques obligatoires et liste de ses saisies).
 * Utilisé par la page de l'étudiant lui-même (avec lien de saisie de l'auto-évaluation, si
 * $cm est fourni) et par le tableau de pilotage de la DEVE (lecture seule).
 *
 * @param stdClass $stage
 * @param int $userid
 * @param stdClass|null $cm Course module, pour afficher les liens d'action.
 * @param bool $selfevallink Affiche le lien de saisie de l'auto-évaluation de l'étudiant
 *                            (page de l'étudiant lui-même uniquement).
 * @param bool $detaillink Affiche un lien vers le détail en lecture seule de chaque saisie
 *                          (tableau de pilotage DEVE / enseignant référent).
 * @return void
 */
function stage_print_student_dashboard(stdClass $stage, $userid, $cm = null, $selfevallink = false, $detaillink = false) {
    global $OUTPUT;

    $progress = stage_get_student_progress($stage->id, $userid);

    // Les thématiques obligatoires sont déjà triées par année d'étude (stage_get_themes) :
    // on les regroupe sous un sous-titre par année pour organiser la vision de l'étudiant.
    echo $OUTPUT->heading(get_string('mandatorythemes', 'mod_stage'), 4);
    $mandatorythemes = array_filter($progress->themes, function($t) {
        return $t->theme->mandatory;
    });
    if (empty($mandatorythemes)) {
        echo $OUTPUT->notification(get_string('nomandatorythemes', 'mod_stage'), 'info');
    } else {
        $currentyear = null;
        $table = null;
        foreach ($mandatorythemes as $t) {
            if ($currentyear === null || $t->theme->studyyear != $currentyear) {
                if ($table !== null) {
                    echo html_writer::table($table);
                }
                $currentyear = $t->theme->studyyear;
                echo $OUTPUT->heading(stage_studyyear_label($currentyear), 5);
                $table = new html_table();
                $table->head = [
                    get_string('theme', 'mod_stage'),
                    get_string('requiredduration', 'mod_stage'),
                    get_string('retainedduration', 'mod_stage'),
                    get_string('status', 'mod_stage'),
                ];
            }
            $status = $t->done
                ? html_writer::span(get_string('themedone', 'mod_stage'), 'badge badge-success')
                : html_writer::span(get_string('themetodo', 'mod_stage'), 'badge badge-warning');
            $table->data[] = [
                format_string($t->theme->name),
                $t->theme->requiredduration,
                $t->retained,
                $status,
            ];
        }
        echo html_writer::table($table);
    }

    echo $OUTPUT->heading(get_string('allmystages', 'mod_stage'), 4);
    $themes = stage_get_themes($stage->id);
    $entries = stage_get_student_entries($stage->id, $userid);

    $table = new html_table();
    $table->head = [
        get_string('theme', 'mod_stage'),
        get_string('studyyear', 'mod_stage'),
        get_string('structure', 'mod_stage'),
        get_string('declaredduration', 'mod_stage'),
        get_string('retainedduration', 'mod_stage'),
        get_string('status', 'mod_stage'),
        get_string('conventionstatus', 'mod_stage'),
    ];
    if ($cm && ($selfevallink || $detaillink)) {
        $table->head[] = get_string('actions', 'mod_stage');
    }
    foreach ($entries as $entry) {
        $theme = $themes[$entry->themeid] ?? null;
        $themename = $theme ? format_string($theme->name) : '-';
        $badge = html_writer::span(stage_status_label($entry->status), 'badge ' . stage_status_badgeclass($entry->status));
        $conventionbadge = html_writer::span(stage_convention_status_label($entry->conventionstatus),
            'badge ' . stage_convention_status_badgeclass($entry->conventionstatus));
        $row = [
            $themename,
            $theme ? stage_studyyear_label($theme->studyyear) : '-',
            $entry->structure,
            $entry->declaredduration,
            $entry->retainedduration,
            $badge,
            $conventionbadge,
        ];
        if ($cm && ($selfevallink || $detaillink)) {
            $actions = [];
            if ($selfevallink) {
                if ((int) $entry->conventionstatus === STAGE_CONVENTION_NONE) {
                    $actions[] = html_writer::link(
                        new moodle_url('/mod/stage/convention_request.php', ['id' => $cm->id, 'entryid' => $entry->id]),
                        get_string('requestconvention', 'mod_stage')
                    );
                } else if ((int) $entry->conventionstatus === STAGE_CONVENTION_REJECTED) {
                    $actions[] = html_writer::link(
                        new moodle_url('/mod/stage/convention_request.php', ['id' => $cm->id, 'entryid' => $entry->id]),
                        get_string('requestconvention', 'mod_stage')
                    );
                } else if (stage_convention_is_signed($entry->conventionstatus)) {
                    $actions[] = html_writer::link(
                        new moodle_url('/mod/stage/entry.php', ['id' => $cm->id, 'entryid' => $entry->id]),
                        get_string('selfeval', 'mod_stage')
                    );
                    // Le PDF de la convention signée n'existe que pour le circuit de gestion de
                    // convention de ce plugin (STAGE_CONVENTION_SIGNED), et seulement si la DEVE
                    // en a effectivement téléversé un (facultatif, voir convention_sign.php) : les
                    // stages enregistrés en masse (SignVet) n'en ont jamais.
                    if ((int) $entry->conventionstatus === STAGE_CONVENTION_SIGNED
                            && stage_get_signed_convention_file(context_module::instance($cm->id), $entry->id)) {
                        $actions[] = html_writer::link(
                            new moodle_url('/mod/stage/convention_signed.php', ['id' => $cm->id, 'entryid' => $entry->id]),
                            get_string('downloadsignedconvention', 'mod_stage')
                        );
                    }
                }
                // Convention demandée mais pas encore signée : rien à faire côté étudiant pour
                // l'instant, le badge de statut ci-dessus suffit à le renseigner.
            }
            if ($detaillink) {
                $actions[] = html_writer::link(
                    new moodle_url('/mod/stage/entrydetail.php', ['id' => $cm->id, 'entryid' => $entry->id]),
                    get_string('viewdetails', 'mod_stage')
                );
            }
            $row[] = implode(' | ', $actions);
        }
        $table->data[] = $row;
    }
    if (empty($table->data)) {
        echo $OUTPUT->notification(get_string('nostages', 'mod_stage'), 'info');
    } else {
        echo html_writer::table($table);
    }

    echo $OUTPUT->heading(get_string('totalretained', 'mod_stage', $progress->totalretained), 4);
}

/**
 * Retourne les informations de l'établissement d'enseignement (VetAgro Sup) affichées sur la
 * page 1 de la convention, éditables par la DEVE (voir convention_templates.php). Une valeur par
 * défaut ("VetAgro Sup", pas d'autre coordonnée) est utilisée tant que la DEVE n'a rien renseigné.
 *
 * @param stdClass $stage
 * @return stdClass {name, address, representative, representativetitle, phone, email}
 */
function stage_get_establishment_info(stdClass $stage) {
    return (object) [
        'name' => $stage->establishmentname ?: 'VetAgro Sup',
        'address' => $stage->establishmentaddress ?? '',
        'representative' => $stage->establishmentrepresentative ?? '',
        'representativetitle' => $stage->establishmentrepresentativetitle ?? '',
        'phone' => $stage->establishmentphone ?? '',
        'email' => $stage->establishmentemail ?? '',
    ];
}

/**
 * Enregistre les informations de l'établissement d'enseignement (VetAgro Sup) affichées sur la
 * page 1 de la convention.
 *
 * @param int $stageid
 * @param stdClass $data {establishmentname, establishmentaddress, establishmentrepresentative,
 *                        establishmentrepresentativetitle, establishmentphone, establishmentemail}
 * @return void
 */
function stage_save_establishment_info($stageid, stdClass $data) {
    global $DB;

    $DB->update_record('stage', (object) [
        'id' => $stageid,
        'establishmentname' => $data->establishmentname,
        'establishmentaddress' => $data->establishmentaddress,
        'establishmentrepresentative' => $data->establishmentrepresentative,
        'establishmentrepresentativetitle' => $data->establishmentrepresentativetitle,
        'establishmentphone' => $data->establishmentphone,
        'establishmentemail' => $data->establishmentemail,
        'timemodified' => time(),
    ]);
}

/**
 * Liste les gabarits de convention disponibles pour un stage.
 *
 * @param int $stageid
 * @param string|null $lang Si fourni, ne retourne que les gabarits dans cette langue ('fr'/'en').
 * @return array
 */
function stage_get_convention_templates($stageid, $lang = null) {
    global $DB;

    $params = ['stageid' => $stageid];
    $where = 'stageid = :stageid';
    if ($lang !== null) {
        $where .= ' AND lang = :lang';
        $params['lang'] = $lang;
    }
    return $DB->get_records_select('stage_convention_template', $where, $params, 'name ASC');
}

/**
 * Liste les autres instances de mod_stage (généralement dans d'autres cours) depuis lesquelles
 * l'utilisateur courant peut importer thématiques, gabarits de convention, logos et informations
 * d'établissement (voir stage_import_from_stage()) : celles où il/elle a la capacité de gérer les
 * thématiques.
 *
 * @param int $excludestageid Instance courante, à exclure de la liste.
 * @return array int (stageid) => string (libellé "Cours - Activité")
 */
function stage_get_importable_stage_instances($excludestageid) {
    global $DB;

    $options = [];
    $stages = $DB->get_records_select('stage', 'id != :id', ['id' => $excludestageid], 'id ASC');
    foreach ($stages as $otherstage) {
        $cm = get_coursemodule_from_instance('stage', $otherstage->id, 0, false, IGNORE_MISSING);
        if (!$cm) {
            continue;
        }
        $context = context_module::instance($cm->id);
        if (!has_capability('mod/stage:managethemes', $context)) {
            continue;
        }
        $course = get_course($cm->course);
        $options[$otherstage->id] = format_string($course->fullname) . ' - ' . format_string($otherstage->name);
    }
    return $options;
}

/**
 * Copie les thématiques d'une instance source vers une instance cible (nouvelles thématiques,
 * les originales ne sont pas modifiées). N'importe pas les questions d'évaluation personnalisées
 * associées.
 *
 * @param int $sourcestageid
 * @param int $targetstageid
 * @return int Nombre de thématiques copiées.
 */
function stage_import_themes($sourcestageid, $targetstageid) {
    global $DB;

    $themes = stage_get_themes($sourcestageid);
    foreach ($themes as $theme) {
        $DB->insert_record('stage_theme', (object) [
            'stageid' => $targetstageid,
            'name' => $theme->name,
            'description' => $theme->description,
            'mandatory' => $theme->mandatory,
            'requiredduration' => $theme->requiredduration,
            'studyyear' => $theme->studyyear,
            'sortorder' => $theme->sortorder,
            'visible' => $theme->visible,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }
    return count($themes);
}

/**
 * Copie les gabarits de convention (nom, langue, et le fichier PDF associé) d'une instance
 * source vers une instance cible.
 *
 * @param context $sourcecontext Contexte du module source.
 * @param int $sourcestageid
 * @param context $targetcontext Contexte du module cible.
 * @param int $targetstageid
 * @return int Nombre de gabarits copiés.
 */
function stage_import_convention_templates(context $sourcecontext, $sourcestageid, context $targetcontext,
        $targetstageid) {
    global $DB;

    $fs = get_file_storage();
    $templates = stage_get_convention_templates($sourcestageid);
    foreach ($templates as $template) {
        $newtemplateid = $DB->insert_record('stage_convention_template', (object) [
            'stageid' => $targetstageid,
            'name' => $template->name,
            'lang' => $template->lang,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $sourcefile = stage_get_convention_template_file($sourcecontext, $template->id);
        if ($sourcefile) {
            $fs->create_file_from_storedfile([
                'contextid' => $targetcontext->id,
                'itemid' => $newtemplateid,
            ], $sourcefile);
        }
    }
    return count($templates);
}

/**
 * Copie les deux logos (fichiers PNG) d'une instance source vers une instance cible, en
 * remplaçant ceux déjà présents sur l'instance cible s'il y en a.
 *
 * @param context $sourcecontext Contexte du module source.
 * @param context $targetcontext Contexte du module cible.
 * @return int Nombre de logos copiés (0 à 2).
 */
function stage_import_convention_logos(context $sourcecontext, context $targetcontext) {
    $fs = get_file_storage();
    $copied = 0;
    foreach (['left', 'right'] as $side) {
        $sourcefile = stage_get_convention_logo_file($sourcecontext, $side);
        if (!$sourcefile) {
            continue;
        }
        $filearea = $side === 'right' ? 'conventionlogoright' : 'conventionlogoleft';
        $fs->delete_area_files($targetcontext->id, 'mod_stage', $filearea, 0);
        $fs->create_file_from_storedfile([
            'contextid' => $targetcontext->id,
        ], $sourcefile);
        $copied++;
    }
    return $copied;
}

/**
 * Copie les informations de l'établissement d'enseignement d'une instance source vers une
 * instance cible (écrase celles déjà renseignées sur l'instance cible).
 *
 * @param int $sourcestageid
 * @param int $targetstageid
 * @return void
 */
function stage_import_establishment_info($sourcestageid, $targetstageid) {
    global $DB;

    $sourcestage = $DB->get_record('stage', ['id' => $sourcestageid], '*', MUST_EXIST);
    $info = stage_get_establishment_info($sourcestage);
    stage_save_establishment_info($targetstageid, (object) [
        'establishmentname' => $info->name,
        'establishmentaddress' => $info->address,
        'establishmentrepresentative' => $info->representative,
        'establishmentrepresentativetitle' => $info->representativetitle,
        'establishmentphone' => $info->phone,
        'establishmentemail' => $info->email,
    ]);
}

/**
 * Importe, selon les options choisies, les thématiques, gabarits de convention, logos et/ou
 * informations d'établissement d'une autre instance de mod_stage vers l'instance courante (voir
 * administration_import.php). L'appelant est responsable de vérifier au préalable que
 * l'utilisateur a la capacité de gérer les thématiques sur les deux instances.
 *
 * @param stdClass $sourcestage
 * @param context $sourcecontext
 * @param stdClass $targetstage
 * @param context $targetcontext
 * @param array $options ['themes' => bool, 'templates' => bool, 'logos' => bool, 'establishment' => bool]
 * @return stdClass Résumé : {themes: int, templates: int, logos: int, establishment: bool}
 */
function stage_import_from_stage(stdClass $sourcestage, context $sourcecontext, stdClass $targetstage,
        context $targetcontext, array $options) {
    $result = (object) ['themes' => 0, 'templates' => 0, 'logos' => 0, 'establishment' => false];

    if (!empty($options['themes'])) {
        $result->themes = stage_import_themes($sourcestage->id, $targetstage->id);
    }
    if (!empty($options['templates'])) {
        $result->templates = stage_import_convention_templates($sourcecontext, $sourcestage->id, $targetcontext,
            $targetstage->id);
    }
    if (!empty($options['logos'])) {
        $result->logos = stage_import_convention_logos($sourcecontext, $targetcontext);
    }
    if (!empty($options['establishment'])) {
        stage_import_establishment_info($sourcestage->id, $targetstage->id);
        $result->establishment = true;
    }

    return $result;
}

/**
 * Liste les saisies suivies dans le circuit de gestion de convention de ce plugin, visibles par
 * la DEVE (exclut les stages sans convention, signées sur SignVet, et en attente de validation
 * par l'enseignant référent, hors de ce circuit ou pas encore transmises à la DEVE), avec
 * recherche par nom d'étudiant et tri, pour la page conventions.php (DEVE).
 *
 * @param int $stageid
 * @param string $search Nom d'étudiant recherché.
 * @param string $sort Une des clés : 'student', 'theme', 'status', 'requested'.
 * @param string $dir 'ASC' ou 'DESC'.
 * @return array
 */
function stage_get_convention_entries($stageid, $search = '', $sort = 'requested', $dir = 'DESC') {
    global $DB;

    $params = [
        'stageid' => $stageid, 'none' => STAGE_CONVENTION_NONE, 'signvet' => STAGE_CONVENTION_SIGNVET,
        'pending' => STAGE_CONVENTION_TEACHERPENDING,
    ];
    // "!= none" plutôt que "> none" : une convention refusée (statut -1) doit rester visible ici
    // (avec son motif) pour que la DEVE garde trace de la demande tant que l'étudiant ne l'a pas
    // corrigée et resoumise.
    $where = [
        'e.stageid = :stageid', 'e.conventionstatus != :none', 'e.conventionstatus != :signvet',
        'e.conventionstatus != :pending',
    ];

    if ($search !== '') {
        $fullname = $DB->sql_concat('u.firstname', "' '", 'u.lastname');
        $where[] = $DB->sql_like($fullname, ':search', false, false);
        $params['search'] = '%' . $DB->sql_like_escape($search) . '%';
    }

    $sortmap = [
        'student' => 'u.lastname, u.firstname',
        'theme' => 't.name',
        'status' => 'e.conventionstatus',
        'requested' => 'e.conventionrequesttime',
    ];
    $sortcolumn = $sortmap[$sort] ?? $sortmap['requested'];
    $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

    $sql = "SELECT e.*
              FROM {stage_entry} e
              JOIN {user} u ON u.id = e.userid
         LEFT JOIN {stage_theme} t ON t.id = e.themeid
             WHERE " . implode(' AND ', $where) . "
          ORDER BY $sortcolumn $dir, e.id DESC";

    return $DB->get_records_sql($sql, $params);
}

/**
 * Liste les demandes de convention en attente de validation par un enseignant.e référent.e
 * donné.e (voir stage_convention_requires_teacher_validation()), pour ses seuls étudiants
 * attribués.
 *
 * @param int $stageid
 * @param int $teacherid
 * @return array
 */
function stage_get_teacher_pending_convention_entries($stageid, $teacherid) {
    global $DB;

    $assignedids = array_keys(stage_get_assigned_students($stageid, $teacherid));
    if (empty($assignedids)) {
        return [];
    }

    [$insql, $inparams] = $DB->get_in_or_equal($assignedids, SQL_PARAMS_NAMED, 'stud');
    $params = array_merge($inparams, ['stageid' => $stageid, 'pending' => STAGE_CONVENTION_TEACHERPENDING]);

    return $DB->get_records_select('stage_entry',
        "stageid = :stageid AND conventionstatus = :pending AND userid $insql", $params,
        'conventionrequesttime ASC');
}

/**
 * Langues proposées pour un gabarit de convention (et pour la demande de l'étudiant, qui en
 * hérite selon le gabarit choisi).
 *
 * @return array 'fr'/'en' => libellé
 */
function stage_convention_lang_options() {
    return [
        'fr' => get_string('conventionlang_fr', 'mod_stage'),
        'en' => get_string('conventionlang_en', 'mod_stage'),
    ];
}

/**
 * Libellé lisible d'une langue de convention.
 *
 * @param string $lang
 * @return string
 */
function stage_convention_lang_label($lang) {
    $options = stage_convention_lang_options();
    return $options[$lang] ?? $lang;
}

/**
 * Situations d'année d'étude proposées pour la case A1..A5 de la convention (année normale,
 * redoublant.e, ou dette d'UE).
 *
 * @param string|null $lang
 * @return array 'normal'/'redoublant'/'detteue' => libellé
 */
function stage_convention_yearsituation_options($lang = null) {
    return [
        'normal' => get_string('conventionyearsituation_normal', 'mod_stage', null, $lang),
        'redoublant' => get_string('conventionyearsituation_redoublant', 'mod_stage', null, $lang),
        'detteue' => get_string('conventionyearsituation_detteue', 'mod_stage', null, $lang),
    ];
}

/**
 * Libellé combinant l'année d'étude de la thématique (A1..A5) et la situation de l'étudiant
 * (normale, redoublant.e, dette d'UE), tel qu'affiché dans la case A de la convention.
 *
 * @param int $studyyear
 * @param string $yearsituation
 * @param string|null $lang
 * @return string
 */
function stage_convention_year_label($studyyear, $yearsituation, $lang = null) {
    $studyyear = (int) $studyyear;
    $base = $studyyear >= 1 && $studyyear <= 5 ? 'A' . $studyyear : stage_studyyear_label($studyyear, $lang);
    $situations = stage_convention_yearsituation_options($lang);
    if ($yearsituation === 'normal' || empty($yearsituation)) {
        return $base;
    }
    return $base . ' ' . ($situations[$yearsituation] ?? '');
}

/**
 * Types de stage proposés (obligatoire ou complémentaire / EP).
 *
 * @param string|null $lang
 * @return array 'obligatoire'/'complementaire' => libellé
 */
function stage_convention_stagetype_options($lang = null) {
    return [
        'obligatoire' => get_string('conventionstagetype_obligatoire', 'mod_stage', null, $lang),
        'complementaire' => get_string('conventionstagetype_complementaire', 'mod_stage', null, $lang),
    ];
}

/**
 * Récupère les informations complémentaires de convention d'une saisie (coordonnées,
 * organisme d'accueil, tuteur, modalités, gratification, congés).
 *
 * @param int $entryid
 * @return stdClass|false
 */
function stage_get_convention_detail($entryid) {
    global $DB;

    return $DB->get_record('stage_convention_detail', ['entryid' => $entryid]);
}

/**
 * Enregistre (création ou mise à jour) les informations complémentaires de convention d'une
 * saisie, saisies par l'étudiant lors de sa demande.
 *
 * @param int $entryid
 * @param stdClass $data Champs de stage_convention_detail (sans id/entryid/timecreated/timemodified).
 * @return void
 */
function stage_save_convention_detail($entryid, stdClass $data) {
    global $DB;

    $data->entryid = $entryid;
    $data->timemodified = time();

    $existing = stage_get_convention_detail($entryid);
    if ($existing) {
        $data->id = $existing->id;
        $DB->update_record('stage_convention_detail', $data);
    } else {
        $data->timecreated = time();
        $DB->insert_record('stage_convention_detail', $data);
    }
}

/**
 * Enregistre la demande de convention d'un étudiant : choix du gabarit, passage au statut
 * "demandée" (ou "en attente de validation enseignant" si l'option est activée pour ce stage,
 * voir stage_convention_requires_teacher_validation()). Réservé à l'étudiant propriétaire de la
 * saisie (voir convention_request.php, student_register.php).
 *
 * @param stdClass $entry
 * @param int $templateid
 * @param bool $requireteachervalidation
 * @return void
 */
function stage_request_convention(stdClass $entry, $templateid, $requireteachervalidation = false) {
    global $DB;

    $entry->conventiontemplateid = $templateid;
    $entry->conventionstatus = $requireteachervalidation
        ? STAGE_CONVENTION_TEACHERPENDING : STAGE_CONVENTION_REQUESTED;
    $entry->conventionrequesttime = time();
    $entry->timemodified = time();
    $DB->update_record('stage_entry', $entry);
}

/**
 * Fait passer une demande de convention validée par l'enseignant référent au statut "demandée",
 * la rendant visible par la DEVE.
 *
 * @param stdClass $entry
 * @param int $byuserid
 * @return void
 */
function stage_teacher_validate_convention(stdClass $entry, $byuserid) {
    global $DB;

    $entry->conventionstatus = STAGE_CONVENTION_REQUESTED;
    $entry->conventionteachervalidatedby = $byuserid;
    $entry->conventionteachervalidatetime = time();
    $entry->timemodified = time();
    $DB->update_record('stage_entry', $entry);
}

/**
 * Envoie un e-mail aux enseignants référents d'un étudiant lorsqu'une demande de convention
 * attend leur validation avant transmission à la DEVE.
 *
 * @param stdClass $stage
 * @param stdClass $cm Course module.
 * @param stdClass $entry
 * @return void
 */
function stage_notify_teacher_convention_pending(stdClass $stage, stdClass $cm, stdClass $entry) {
    $teachers = stage_get_student_teachers($stage->id, $entry->userid);
    if (empty($teachers)) {
        return;
    }

    $url = new moodle_url('/mod/stage/teacher.php', ['id' => $cm->id]);
    $subject = get_string('conventionteacherpendingnotifsubject', 'mod_stage', format_string($stage->name));
    foreach ($teachers as $teacher) {
        $body = get_string('conventionteacherpendingnotifbody', 'mod_stage', (object) [
            'stage' => format_string($stage->name),
            'url' => $url->out(false),
        ]);
        email_to_user($teacher, core_user::get_noreply_user(), $subject, $body);
    }
}

/**
 * Fait passer une convention demandée au statut "éditée" (DEVE).
 *
 * @param stdClass $entry
 * @param int $byuserid
 * @return void
 */
function stage_convention_mark_edited(stdClass $entry, $byuserid) {
    global $DB;

    $entry->conventionstatus = STAGE_CONVENTION_EDITED;
    $entry->conventioneditedby = $byuserid;
    $entry->conventionedittime = time();
    $entry->timemodified = time();
    $DB->update_record('stage_entry', $entry);
}

/**
 * Fait passer une convention éditée au statut "signée" (DEVE). Ouvre le droit à
 * l'auto-évaluation de l'étudiant et à l'évaluation de l'enseignant référent.
 *
 * @param stdClass $entry
 * @param int $byuserid
 * @return void
 */
function stage_convention_mark_signed(stdClass $entry, $byuserid) {
    global $DB;

    $entry->conventionstatus = STAGE_CONVENTION_SIGNED;
    $entry->conventionsignedby = $byuserid;
    $entry->conventionsigntime = time();
    $entry->timemodified = time();
    $DB->update_record('stage_entry', $entry);
}

/**
 * Refuse une demande de convention (DEVE), avec un commentaire obligatoire expliquant le motif.
 * Le statut repasse à "refusée" : l'étudiant peut alors modifier et resoumettre sa demande
 * depuis convention_request.php, qui la fait repasser à "demandée".
 *
 * @param stdClass $entry
 * @param int $byuserid
 * @param string $comment
 * @return void
 */
function stage_reject_convention(stdClass $entry, $byuserid, $comment) {
    global $DB;

    $entry->conventionstatus = STAGE_CONVENTION_REJECTED;
    $entry->conventionrejectedby = $byuserid;
    $entry->conventionrejecttime = time();
    $entry->conventionrejectcomment = $comment;
    $entry->timemodified = time();
    $DB->update_record('stage_entry', $entry);
}

/**
 * Envoie un e-mail à l'étudiant lorsque la DEVE refuse sa demande de convention, pour qu'il
 * sache qu'une correction est attendue et pourquoi.
 *
 * @param stdClass $stage
 * @param stdClass $cm Course module.
 * @param stdClass $entry
 * @param string $comment
 * @return void
 */
function stage_notify_student_convention_rejected(stdClass $stage, stdClass $cm, stdClass $entry, $comment) {
    global $DB;

    $student = $DB->get_record('user', ['id' => $entry->userid]);
    if (!$student) {
        return;
    }

    $url = new moodle_url('/mod/stage/convention_request.php', ['id' => $cm->id, 'entryid' => $entry->id]);
    $subject = get_string('conventionrejectednotifsubject', 'mod_stage', format_string($stage->name));
    $body = get_string('conventionrejectednotifbody', 'mod_stage', (object) [
        'stage' => format_string($stage->name),
        'comment' => $comment,
        'url' => $url->out(false),
    ]);
    email_to_user($student, core_user::get_noreply_user(), $subject, $body);
}

/**
 * Récupère le fichier PDF d'un gabarit de convention (stocké via l'API fichiers de Moodle,
 * itemid = id du gabarit).
 *
 * @param context $context Contexte du module stage.
 * @param int $templateid
 * @return \stored_file|null
 */
function stage_get_convention_template_file(context $context, $templateid) {
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_stage', 'conventiontemplate', $templateid, 'itemid', false);
    return $files ? reset($files) : null;
}

/**
 * Récupère le logo (gauche ou droit) affiché sur la page 1 de toutes les conventions du stage.
 *
 * @param context $context Contexte du module stage.
 * @param string $side 'left' ou 'right'.
 * @return \stored_file|null
 */
function stage_get_convention_logo_file(context $context, $side) {
    $fs = get_file_storage();
    $filearea = $side === 'right' ? 'conventionlogoright' : 'conventionlogoleft';
    $files = $fs->get_area_files($context->id, 'mod_stage', $filearea, 0, 'itemid', false);
    return $files ? reset($files) : null;
}

/**
 * Récupère la convention de stage signée (PDF scanné), téléversée par la DEVE lors du passage au
 * statut "signée".
 *
 * @param context $context Contexte du module stage.
 * @param int $entryid
 * @return \stored_file|null
 */
function stage_get_signed_convention_file(context $context, $entryid) {
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_stage', 'signedconvention', $entryid, 'itemid', false);
    return $files ? reset($files) : null;
}

/**
 * Copie un fichier stocké par l'API fichiers de Moodle vers un fichier temporaire sur disque,
 * pour les usages (TCPDF, FPDI) qui exigent un chemin de fichier réel plutôt qu'un contenu en
 * mémoire. L'appelant est responsable de supprimer le fichier retourné (unlink) une fois fini.
 *
 * @param \stored_file $file
 * @return string Chemin du fichier temporaire.
 */
function stage_stored_file_to_temp(\stored_file $file) {
    $tmppath = tempnam(sys_get_temp_dir(), 'stageconv_');
    $file->copy_content_to($tmppath);
    return $tmppath;
}

/**
 * Construit le PDF complet de la convention d'une saisie : une page 1 générée à partir des
 * données de la base (logos et informations d'établissement configurés par la DEVE), suivie des
 * pages du gabarit choisi par l'étudiant, réimportées via FPDI.
 *
 * Appelée par convention.php (téléchargement à la demande) et convention_review.php
 * (téléchargement immédiat après validation).
 *
 * @param stdClass $stage
 * @param stdClass $entry
 * @param context $context Contexte du module stage.
 * @return array ['error' => string|null (clé de chaîne de langue mod_stage), 'pdf' => objet FPDI
 *               prêt pour Output(), ou null en cas d'erreur, 'filename' => string|null]
 */
function stage_build_convention_pdf(stdClass $stage, stdClass $entry, context $context) {
    global $DB, $CFG;

    if (empty($entry->conventiontemplateid)) {
        return ['error' => 'conventionnotemplatechosen', 'pdf' => null, 'filename' => null];
    }

    $conventiontemplate = $DB->get_record('stage_convention_template', ['id' => $entry->conventiontemplateid]);
    $conventionlang = $conventiontemplate ? $conventiontemplate->lang : 'fr';

    $templatefile = stage_get_convention_template_file($context, $entry->conventiontemplateid);
    if (!$templatefile) {
        return ['error' => 'conventiontemplatemissing', 'pdf' => null, 'filename' => null];
    }

    $fpdiautoload = $CFG->dirroot . '/mod/stage/thirdparty/vendor/autoload.php';
    if (!is_readable($fpdiautoload)) {
        return ['error' => 'conventionfpdimissing', 'pdf' => null, 'filename' => null];
    }
    require_once($fpdiautoload);
    require_once($CFG->dirroot . '/mod/stage/classes/pdf/convention_pdf.php');

    // Rassemble les données affichées sur la page 1.
    $student = $DB->get_record('user', ['id' => $entry->userid], '*', MUST_EXIST);
    $theme = $DB->get_record('stage_theme', ['id' => $entry->themeid]);
    $detail = stage_get_convention_detail($entry->id);
    if (!$detail) {
        // Valeurs neutres pour une demande créée directement en base, sans détail associé.
        $detail = (object) array_fill_keys([
            'yearsituation', 'stagetype', 'studentbirthdate', 'studentaddress', 'studentphone',
            'hostaddress', 'hostrepresentative', 'hostrepresentativetitle', 'hostservice', 'hostphone',
            'hostemail', 'hostlocation', 'tutorname', 'tutorfunction', 'tutorphone', 'tutoremail',
            'nightpresence', 'sundaypresence', 'holidaypresence', 'homebased', 'othermodality',
            'hasleave', 'leavedays', 'leavemodalities', 'gratificationamount', 'referentteacherid',
        ], null);
        $detail->yearsituation = 'normal';
        $detail->stagetype = 'obligatoire';
    }

    // Enseignant référent choisi lors de la demande ; son courriel est toujours lu sur son
    // compte. À défaut de choix enregistré, on retient le premier enseignant attribué.
    $referentteacher = null;
    if (!empty($detail->referentteacherid)) {
        $referentteacher = $DB->get_record('user', ['id' => $detail->referentteacherid]);
    }
    if (!$referentteacher) {
        $studentteachers = stage_get_student_teachers($stage->id, $entry->userid);
        $referentteacher = $studentteachers ? reset($studentteachers) : null;
    }

    // Les libellés (statut, année d'étude...) sont dans la langue du gabarit choisi par
    // l'étudiant, pas dans celle de la session de qui génère le PDF (généralement la DEVE) : voir
    // convention_pdf.php::str().
    $dateformat = get_string('strftimedate', 'langconfig', null, $conventionlang);
    $establishmentinfo = stage_get_establishment_info($stage);
    $stagedata = [
        'establishment' => [
            'name' => $establishmentinfo->name,
            'address' => $establishmentinfo->address,
            'representative' => $establishmentinfo->representative,
            'representativetitle' => $establishmentinfo->representativetitle,
            'phone' => $establishmentinfo->phone,
            'email' => $establishmentinfo->email,
        ],
        'hoststructure' => (string) $entry->structure,
        'yearlabel' => $theme ? stage_convention_year_label($theme->studyyear, $detail->yearsituation, $conventionlang)
            : '-',
        'stagetypelabel' => stage_convention_stagetype_options($conventionlang)[$detail->stagetype] ?? $detail->stagetype,
        'host' => [
            'address' => (string) $detail->hostaddress,
            'representative' => (string) $detail->hostrepresentative,
            'representativetitle' => (string) $detail->hostrepresentativetitle,
            'service' => (string) $detail->hostservice,
            'phone' => (string) $detail->hostphone,
            'email' => (string) $detail->hostemail,
            'location' => (string) $detail->hostlocation,
        ],
        'student' => [
            'fullname' => fullname($student),
            'email' => $student->email,
            'birthdate' => $detail->studentbirthdate ? userdate($detail->studentbirthdate, $dateformat) : '-',
            'address' => (string) $detail->studentaddress,
            'phone' => (string) $detail->studentphone,
        ],
        'theme' => [
            'name' => $theme ? format_string($theme->name) : '-',
        ],
        'dates' => [
            'start' => $entry->datestart ? userdate($entry->datestart, $dateformat) : '-',
            'end' => $entry->dateend ? userdate($entry->dateend, $dateformat) : '-',
        ],
        'duration' => [
            'declared' => $entry->declaredduration,
            'retained' => $entry->retainedduration,
        ],
        'statuslabel' => stage_status_label($entry->status, $conventionlang),
        'referentteacher' => [
            'name' => $referentteacher ? fullname($referentteacher) : '-',
            'email' => $referentteacher ? $referentteacher->email : '-',
        ],
        'tutor' => [
            'name' => (string) $detail->tutorname,
            'function' => (string) $detail->tutorfunction,
            'phone' => (string) $detail->tutorphone,
            'email' => (string) $detail->tutoremail,
        ],
        'modalities' => [
            'night' => (bool) $detail->nightpresence,
            'sunday' => (bool) $detail->sundaypresence,
            'holiday' => (bool) $detail->holidaypresence,
            'homebased' => (bool) $detail->homebased,
            'other' => (string) $detail->othermodality,
        ],
        'gratification' => (string) $detail->gratificationamount,
        'leave' => [
            'has' => (bool) $detail->hasleave,
            'days' => $detail->leavedays,
            'modalities' => (string) $detail->leavemodalities,
        ],
    ];

    // Les gabarits/logos sont stockés via l'API fichiers de Moodle : TCPDF/FPDI ont besoin d'un
    // chemin de fichier réel, on les copie donc vers des fichiers temporaires, nettoyés à la fin.
    $tempfiles = [];
    $templatepath = stage_stored_file_to_temp($templatefile);
    $tempfiles[] = $templatepath;

    $logoleftpath = null;
    $logofile = stage_get_convention_logo_file($context, 'left');
    if ($logofile) {
        $logoleftpath = stage_stored_file_to_temp($logofile);
        $tempfiles[] = $logoleftpath;
    }
    $logorightpath = null;
    $logofile = stage_get_convention_logo_file($context, 'right');
    if ($logofile) {
        $logorightpath = stage_stored_file_to_temp($logofile);
        $tempfiles[] = $logorightpath;
    }

    // Page 1 : générée dynamiquement avec la classe \pdf de Moodle (TCPDF), en PDF brut (chaîne),
    // pour être réimportée ci-dessous comme un PDF source parmi d'autres.
    $page1 = new \mod_stage\pdf\convention_pdf('P', 'mm', 'A4', true, 'UTF-8', false);
    $page1->generate_page1($stagedata, $logoleftpath, $logorightpath, $conventionlang);
    $page1pdf = $page1->Output('', 'S');

    // Assemblage final : la page 1 générée ci-dessus, suivie des pages du gabarit choisi par
    // l'étudiant, toutes deux réimportées par FPDI comme sources PDF.
    $merger = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
    $merger->setPrintHeader(false);
    $merger->setPrintFooter(false);

    $streamreader = \setasign\Fpdi\PdfParser\StreamReader::createByString($page1pdf);
    $pagecount = $merger->setSourceFile($streamreader);
    for ($pageno = 1; $pageno <= $pagecount; $pageno++) {
        $tplidx = $merger->importPage($pageno);
        $size = $merger->getTemplateSize($tplidx);
        $merger->AddPage($size['orientation'], [$size['width'], $size['height']]);
        $merger->useTemplate($tplidx);
    }

    $articlespagecount = $merger->setSourceFile($templatepath);
    for ($pageno = 1; $pageno <= $articlespagecount; $pageno++) {
        $tplidx = $merger->importPage($pageno);
        $size = $merger->getTemplateSize($tplidx);
        $merger->AddPage($size['orientation'], [$size['width'], $size['height']]);
        $merger->useTemplate($tplidx);
    }

    foreach ($tempfiles as $tempfile) {
        unlink($tempfile);
    }

    $filename = clean_filename('convention_stage_' . fullname($student) . '_' . $entry->id . '.pdf');

    return ['error' => null, 'pdf' => $merger, 'filename' => $filename];
}
