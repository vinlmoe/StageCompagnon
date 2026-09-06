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
        case STAGE_CONVENTION_EXEMPT:
            return get_string('conventionstatus_exempt', 'mod_stage');
        default:
            return get_string('conventionstatus_none', 'mod_stage');
    }
}

/**
 * Indique si un statut de convention équivaut à "signée" (ouvre le droit à l'auto-évaluation et
 * à l'évaluation) : signée via le circuit de gestion de convention de ce plugin, signée sur
 * SignVet (stages enregistrés en masse par la DEVE, hors de ce circuit), ou dispensée de
 * convention par la DEVE lors de l'enregistrement du stage.
 *
 * @param int $status
 * @return bool
 */
function stage_convention_is_signed($status) {
    return in_array((int) $status, [STAGE_CONVENTION_SIGNED, STAGE_CONVENTION_SIGNVET, STAGE_CONVENTION_EXEMPT], true);
}

/**
 * Message d'information à afficher à la DEVE (convention_review.php) quand une convention papier
 * (cadre de signatures) a été demandée par l'étudiant lors de sa demande (convention_request.php)
 * et/ou par l'enseignant référent lors de sa validation (convention_teacher_validate.php), pour
 * qu'elle sache pourquoi la case "withsignatures" est précochée sans avoir à rouvrir leurs
 * demandes respectives.
 *
 * @param stdClass|false $detail Retour de stage_get_convention_detail().
 * @return string|null Texte, ou null si personne n'a demandé de convention papier.
 */
function stage_convention_paper_requested_info($detail) {
    $bystudent = !empty($detail->paperrequestedbystudent);
    $byteacher = !empty($detail->paperrequestedbyteacher);
    if ($bystudent && $byteacher) {
        return get_string('conventionpaperrequestedbyboth', 'mod_stage');
    } else if ($bystudent) {
        return get_string('conventionpaperrequestedbystudentonly', 'mod_stage');
    } else if ($byteacher) {
        return get_string('conventionpaperrequestedbyteacheronly', 'mod_stage');
    }
    return null;
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
        case STAGE_CONVENTION_EXEMPT:
            return 'badge-success';
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
 * Active ou désactive l'évaluation par le maître de stage pour l'activité.
 *
 * @param int $stageid
 * @param bool $enabled
 * @return void
 */
function stage_save_tutor_evaluation_setting($stageid, $enabled) {
    global $DB;

    $DB->update_record('stage', (object) [
        'id' => $stageid,
        'tutorevaluationenabled' => $enabled ? 1 : 0,
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
    return $DB->get_records_select('stage_theme', $where, $params, 'minstudyyear ASC, maxstudyyear ASC, sortorder ASC, name ASC');
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
 * Libellé lisible de la plage d'années d'étude d'une thématique (année minimum - année maximum).
 * Si les deux bornes sont identiques ou que l'une d'elles n'est pas spécifiée, un libellé simple
 * est renvoyé plutôt qu'une plage.
 *
 * @param int $minstudyyear
 * @param int $maxstudyyear
 * @param string|null $lang
 * @return string
 */
function stage_studyyear_range_label($minstudyyear, $maxstudyyear, $lang = null) {
    $minstudyyear = (int) $minstudyyear;
    $maxstudyyear = (int) $maxstudyyear;
    if ($minstudyyear == $maxstudyyear || empty($minstudyyear) || empty($maxstudyyear)) {
        return stage_studyyear_label($minstudyyear ?: $maxstudyyear, $lang);
    }
    return stage_studyyear_label($minstudyyear, $lang) . ' - ' . stage_studyyear_label($maxstudyyear, $lang);
}

/**
 * Libellé d'une thématique pour une liste déroulante : nom, plage d'années d'étude (si précisée)
 * et mention "obligatoire" le cas échéant, pour aider la DEVE et les enseignants à s'y retrouver.
 *
 * @param stdClass $theme
 * @return string
 */
function stage_theme_option_label(stdClass $theme) {
    $label = format_string($theme->name);
    if (!empty($theme->minstudyyear) || !empty($theme->maxstudyyear)) {
        $label .= ' - ' . stage_studyyear_range_label($theme->minstudyyear, $theme->maxstudyyear);
    }
    if (!empty($theme->mandatory)) {
        $label .= ' (' . get_string('mandatory', 'mod_stage') . ')';
    }
    return $label;
}

/**
 * Affiche un encart rappelant la consigne de mobilité internationale de ce stage
 * (stage->abroadrule), pour que l'étudiant en prenne connaissance avant de déclarer un stage à
 * l'étranger. L'obligation n'est pas liée à une thématique : elle est commune à toutes.
 *
 * @param stdClass $stage
 * @return string HTML, chaîne vide si aucune consigne n'est définie.
 */
function stage_render_abroad_rules(stdClass $stage) {
    if (empty($stage->abroadrule)) {
        return '';
    }
    $text = format_text($stage->abroadrule, FORMAT_PLAIN);
    if (!empty($stage->requiredabroaddays)) {
        $text .= ' (' . get_string('abroaddaysrequired', 'mod_stage') . ' : ' . $stage->requiredabroaddays . ')';
    }
    return html_writer::tag('div',
        html_writer::tag('p', html_writer::tag('strong', get_string('abroadrule', 'mod_stage'))) . html_writer::tag('p', $text),
        ['class' => 'alert alert-info']);
}

/**
 * Retourne la durée obligatoire requise pour une thématique, pour une année d'étude donnée
 * (utilisée à l'année finale de la thématique pour une thématique à plage, voir
 * stage_theme_final_year()). Une thématique définit soit une durée unique pour l'ensemble de la
 * thématique (stage_theme.requiredduration), soit une durée par année (stage_theme_duration) :
 * l'un ou l'autre. La durée unique, si définie (non nulle), est toujours prioritaire. À défaut,
 * se rabat sur la durée par année définie pour l'année 0 (non spécifiée) si aucune valeur
 * n'existe pour cette année précise, puis sur 0.
 *
 * @param int $themeid
 * @param int $studyyear
 * @return int
 */
function stage_get_theme_duration($themeid, $studyyear) {
    global $DB;

    $flat = $DB->get_field('stage_theme', 'requiredduration', ['id' => $themeid]);
    if (!empty($flat)) {
        return (int) $flat;
    }

    $duration = $DB->get_field('stage_theme_duration', 'requiredduration',
        ['themeid' => $themeid, 'studyyear' => (int) $studyyear]);
    if ($duration === false && !empty($studyyear)) {
        $duration = $DB->get_field('stage_theme_duration', 'requiredduration', ['themeid' => $themeid, 'studyyear' => 0]);
    }
    return $duration !== false ? (int) $duration : 0;
}

/**
 * Retourne les durées obligatoires requises pour une thématique, indexées par année d'étude
 * (0 = non spécifiée), pour affichage/édition dans la page de gestion des durées.
 *
 * @param int $themeid
 * @return array int => int
 */
function stage_get_theme_durations($themeid) {
    global $DB;

    $durations = [];
    foreach ($DB->get_records('stage_theme_duration', ['themeid' => $themeid]) as $record) {
        $durations[(int) $record->studyyear] = (int) $record->requiredduration;
    }
    return $durations;
}

/**
 * Retourne le nombre de jours de stage à l'étranger retenus pour un étudiant, tous stages
 * confondus (obligatoires ou complémentaires) et quelle que soit la thématique, pour vérifier
 * l'obligation de mobilité internationale de ce stage (stage->requiredabroaddays). Contrairement
 * au bilan des durées obligatoires, les stages complémentaires comptent ici.
 *
 * @param int $stageid
 * @param int $userid
 * @return int
 */
function stage_get_student_abroad_days($stageid, $userid) {
    global $DB;

    return (int) $DB->get_field_sql(
        'SELECT COALESCE(SUM(retainedduration), 0)
           FROM {stage_entry}
          WHERE stageid = :stageid AND userid = :userid AND abroad = 1 AND status = :status',
        ['stageid' => $stageid, 'userid' => $userid, 'status' => STAGE_STATUS_VALIDE_DEVE]
    );
}

/**
 * Définit (crée ou met à jour) la durée obligatoire requise pour une thématique et une année
 * d'étude donnée.
 *
 * @param int $themeid
 * @param int $studyyear
 * @param int $requiredduration
 * @return void
 */
function stage_set_theme_duration($themeid, $studyyear, $requiredduration) {
    global $DB;

    $existing = $DB->get_record('stage_theme_duration', ['themeid' => $themeid, 'studyyear' => (int) $studyyear]);
    if ($existing) {
        $existing->requiredduration = $requiredduration;
        $existing->timemodified = time();
        $DB->update_record('stage_theme_duration', $existing);
    } else {
        $DB->insert_record('stage_theme_duration', (object) [
            'themeid' => $themeid,
            'studyyear' => (int) $studyyear,
            'requiredduration' => $requiredduration,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }
}

/**
 * Retourne la durée totale obligatoire requise pour une année d'étude, toutes thématiques
 * confondues (hors stages complémentaires).
 *
 * @param int $stageid
 * @param int $studyyear
 * @return int
 */
function stage_get_year_requirement($stageid, $studyyear) {
    global $DB;

    $duration = $DB->get_field('stage_year_requirement', 'requiredduration',
        ['stageid' => $stageid, 'studyyear' => (int) $studyyear]);
    return $duration !== false ? (int) $duration : 0;
}

/**
 * Retourne les durées totales obligatoires requises par année d'étude pour ce stage, indexées par
 * année (0 = non spécifiée).
 *
 * @param int $stageid
 * @return array int => int
 */
function stage_get_year_requirements($stageid) {
    global $DB;

    $requirements = [];
    foreach ($DB->get_records('stage_year_requirement', ['stageid' => $stageid]) as $record) {
        $requirements[(int) $record->studyyear] = (int) $record->requiredduration;
    }
    return $requirements;
}

/**
 * Définit (crée ou met à jour) la durée totale obligatoire requise pour une année d'étude donnée.
 *
 * @param int $stageid
 * @param int $studyyear
 * @param int $requiredduration
 * @return void
 */
function stage_set_year_requirement($stageid, $studyyear, $requiredduration) {
    global $DB;

    $existing = $DB->get_record('stage_year_requirement', ['stageid' => $stageid, 'studyyear' => (int) $studyyear]);
    if ($existing) {
        $existing->requiredduration = $requiredduration;
        $existing->timemodified = time();
        $DB->update_record('stage_year_requirement', $existing);
    } else {
        $DB->insert_record('stage_year_requirement', (object) [
            'stageid' => $stageid,
            'studyyear' => (int) $studyyear,
            'requiredduration' => $requiredduration,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }
}

/**
 * Retourne les années d'étude sur lesquelles un étudiant peut positionner un stage : l'année
 * courante des étudiants (N, voir stage->currentstudyyear), l'année précédente (N-1, en cas de
 * dette) et l'année suivante (N+1, pour anticiper). Si aucune année courante n'est définie pour ce
 * stage, toutes les années sont proposées (aucune restriction possible).
 *
 * @param stdClass $stage
 * @return array int => libellé, pour un select
 */
function stage_studyyear_selectable_options(stdClass $stage) {
    $currentyear = (int) $stage->currentstudyyear;
    if (empty($currentyear)) {
        return stage_studyyear_options();
    }
    $alloptions = stage_studyyear_options();
    $options = [];
    foreach ([$currentyear - 1, $currentyear, $currentyear + 1] as $year) {
        if (isset($alloptions[$year])) {
            $options[$year] = $alloptions[$year];
        }
    }
    return $options;
}

/**
 * Année d'étude "finale" d'une thématique obligatoire, à laquelle sa durée requise est vérifiée
 * (voir stage_get_student_year_progress()) : la dernière année de sa plage [minstudyyear,
 * maxstudyyear]. Par exemple une thématique de A2 à A4 n'est vérifiée qu'en A4, de façon
 * cumulative sur toute sa plage. Une thématique sans plage définie (minstudyyear = maxstudyyear =
 * 0) n'a pas d'année finale : elle est vérifiée chaque année, sur les seules saisies de cette
 * année (voir l'appelant).
 *
 * @param stdClass $theme
 * @return int|null
 */
function stage_theme_final_year(stdClass $theme) {
    $min = (int) $theme->minstudyyear;
    $max = (int) $theme->maxstudyyear;
    if (empty($min) && empty($max)) {
        return null;
    }
    return max($min ?: $max, $max ?: $min);
}

/**
 * Calcule, pour un étudiant et pour chaque année d'étude concernée, si les objectifs sont
 * atteints : la durée totale obligatoire requise pour l'année (toutes thématiques confondues) ET
 * la durée requise pour chaque thématique obligatoire due cette année-là (hors stages
 * complémentaires dans les deux cas). Une thématique bornée à une plage d'années (par exemple de
 * A2 à A4) n'est vérifiée qu'à sa dernière année (A4), de façon cumulative sur l'ensemble de sa
 * plage : les années intermédiaires (A2, A3) ne sont pas bloquées par elle. Une thématique sans
 * plage définie est vérifiée chaque année, sur les seules saisies de cette année. Une année n'est
 * retenue que si un objectif y est défini (durée totale ou durée d'au moins une thématique due
 * cette année-là) ou que l'étudiant y a des saisies.
 *
 * @param int $stageid
 * @param int $userid
 * @return array int => stdClass{studyyear, retained, required, totaldone, themes, abroad,
 *               complementary, done}
 *               'themes' est un tableau de stdClass{theme, required, retained, done}
 *               'abroad' est absent, sauf à l'année avant laquelle la mobilité internationale
 *               doit être satisfaite (stage->abroadbeforeyear) : stdClass{required, retained, done}
 *               (voir stage_get_student_abroad_progress()).
 *               'complementary' est la durée retenue des stages complémentaires (EP) de l'année,
 *               à titre informatif uniquement (voir stage_get_student_complementary_days()) : ne
 *               compte dans aucune des conditions ci-dessous.
 *               'done' est vrai seulement si la durée totale, toutes les thématiques obligatoires
 *               dues cette année-là (celles ayant une durée requise > 0) ET, à l'année concernée,
 *               l'obligation de mobilité internationale sont validées.
 */
function stage_get_student_year_progress($stageid, $userid) {
    global $DB;

    $entries = stage_get_student_entries($stageid, $userid);
    $stagetypes = stage_get_entry_stagetypes(array_keys($entries));
    $mandatorythemes = array_filter(stage_get_themes($stageid, true), function($theme) {
        return !empty($theme->mandatory);
    });
    $abroadbeforeyear = (int) $DB->get_field('stage', 'abroadbeforeyear', ['id' => $stageid]);
    $abroadprogress = $abroadbeforeyear > 0 ? stage_get_student_abroad_progress($stageid, $userid) : null;

    // Années à considérer : celles où l'étudiant a des saisies, celles où une durée totale est
    // définie, l'année finale de chaque thématique obligatoire bornée à une plage, et l'année
    // avant laquelle la mobilité internationale doit être satisfaite, le cas échéant.
    $years = [];
    foreach ($entries as $entry) {
        $years[(int) $entry->studyyear] = true;
    }
    foreach (stage_get_year_requirements($stageid) as $year => $required) {
        if ($required > 0) {
            $years[$year] = true;
        }
    }
    foreach ($mandatorythemes as $theme) {
        $finalyear = stage_theme_final_year($theme);
        if ($finalyear !== null) {
            $years[$finalyear] = true;
        }
    }
    if ($abroadprogress !== null && $abroadprogress->required > 0) {
        $years[$abroadbeforeyear] = true;
    }
    $complementary = stage_get_student_complementary_days($stageid, $userid);
    foreach ($complementary->byyear as $year => $days) {
        $years[$year] = true;
    }

    // Durées retenues (hors stages complémentaires), regroupées par année, par (thématique,
    // année) et cumulées par thématique (toutes années confondues, pour la vérification finale
    // des thématiques bornées à une plage).
    $retainedbyyear = [];
    $retainedbythemeyear = [];
    $retainedbytheme = [];
    foreach ($entries as $entry) {
        if (($stagetypes[$entry->id] ?? 'obligatoire') === 'complementaire') {
            continue;
        }
        if ($entry->status != STAGE_STATUS_VALIDE_DEVE) {
            continue;
        }
        $year = (int) $entry->studyyear;
        $retainedbyyear[$year] = ($retainedbyyear[$year] ?? 0) + $entry->retainedduration;
        $key = $entry->themeid . ':' . $year;
        $retainedbythemeyear[$key] = ($retainedbythemeyear[$key] ?? 0) + $entry->retainedduration;
        $retainedbytheme[$entry->themeid] = ($retainedbytheme[$entry->themeid] ?? 0) + $entry->retainedduration;
    }

    $byyear = [];
    foreach (array_keys($years) as $year) {
        $required = stage_get_year_requirement($stageid, $year);
        $retained = $retainedbyyear[$year] ?? 0;
        $totaldone = $required <= 0 || $retained >= $required;

        $themerows = [];
        $themesdone = true;
        foreach ($mandatorythemes as $theme) {
            $finalyear = stage_theme_final_year($theme);
            if ($finalyear !== null) {
                // Thématique bornée à une plage : vérifiée uniquement à sa dernière année, de
                // façon cumulative sur toute la plage (les années intermédiaires ne l'incluent pas).
                if ($year != $finalyear) {
                    continue;
                }
                $themerequired = stage_get_theme_duration($theme->id, $finalyear);
                $themeretained = $retainedbytheme[$theme->id] ?? 0;
            } else {
                // Pas de plage définie : due chaque année, sur les seules saisies de cette année.
                $themerequired = stage_get_theme_duration($theme->id, $year);
                $themeretained = $retainedbythemeyear[$theme->id . ':' . $year] ?? 0;
            }
            $themedone = $themerequired <= 0 || $themeretained >= $themerequired;
            if ($themerequired > 0 && !$themedone) {
                $themesdone = false;
            }

            $themerows[] = (object) [
                'theme' => $theme,
                'required' => $themerequired,
                'retained' => $themeretained,
                'done' => $themedone,
            ];
        }

        // Obligation de mobilité internationale, vérifiée à l'année avant laquelle elle doit
        // être satisfaite (pas liée à une thématique, voir stage_get_student_abroad_progress()).
        $abroad = null;
        $abroaddone = true;
        if ($abroadprogress !== null && $abroadprogress->required > 0 && $year == $abroadbeforeyear) {
            $abroad = $abroadprogress;
            $abroaddone = $abroadprogress->done;
        }

        $byyear[$year] = (object) [
            'studyyear' => $year,
            'retained' => $retained,
            'required' => $required,
            'totaldone' => $totaldone,
            'themes' => $themerows,
            'abroad' => $abroad,
            // Stages complémentaires (EP) de cette année, à titre informatif uniquement : ne
            // comptent pas dans le décompte des stages obligatoires ni dans 'done'.
            'complementary' => $complementary->byyear[$year] ?? 0,
            'done' => $totaldone && $themesdone && $abroaddone,
        ];
    }

    ksort($byyear);
    return $byyear;
}

/**
 * Calcule, pour un étudiant, la durée retenue (validée DEVE) passée à l'étranger, tous stages
 * confondus (obligatoires ou complémentaires) et quelle que soit la thématique, au regard de
 * l'obligation de mobilité internationale définie pour ce stage (stage->requiredabroaddays,
 * gérée depuis la page « Durées de stage par année », voir year_requirements.php).
 *
 * @param int $stageid
 * @param int $userid
 * @return stdClass {required, retained, done, beforeyear}
 */
function stage_get_student_abroad_progress($stageid, $userid) {
    global $DB;

    $stage = $DB->get_record('stage', ['id' => $stageid], 'requiredabroaddays, abroadbeforeyear', MUST_EXIST);
    $required = (int) $stage->requiredabroaddays;
    $retained = stage_get_student_abroad_days($stageid, $userid);

    return (object) [
        'required' => $required,
        'retained' => $retained,
        'done' => $required <= 0 || $retained >= $required,
        'beforeyear' => (int) $stage->abroadbeforeyear,
    ];
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
    $themes = stage_get_themes($stageid, true);
    $entries = stage_get_student_entries($stageid, $userid);
    $stagetypes = stage_get_entry_stagetypes(array_keys($entries));

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
        $t->requiredduration = 0;
        $t->requiredyears = [];
        $t->done = false;
        $progress->themes[$theme->id] = $t;
    }

    // Les stages complémentaires (EP) ne comptent pas dans le bilan des durées obligatoires.
    foreach ($entries as $entry) {
        if (($stagetypes[$entry->id] ?? 'obligatoire') === 'complementaire') {
            continue;
        }
        $progress->totaldeclared += $entry->declaredduration;
        if ($entry->status == STAGE_STATUS_VALIDE_DEVE) {
            $progress->totalretained += $entry->retainedduration;
        }
        if (isset($progress->themes[$entry->themeid])) {
            $t = $progress->themes[$entry->themeid];
            $t->entries[] = $entry;
            $t->declared += $entry->declaredduration;
            if ($entry->status == STAGE_STATUS_VALIDE_DEVE) {
                $t->retained += $entry->retainedduration;
            }
            // La durée requise pour la thématique est la somme des durées requises pour chacune
            // des années d'étude sur lesquelles l'étudiant y a des saisies (un stage compte
            // normalement une fois par thématique, mais rien n'empêche une redite en N-1/N+1).
            if (!in_array((int) $entry->studyyear, $t->requiredyears, true)) {
                $t->requiredyears[] = (int) $entry->studyyear;
                $t->requiredduration += stage_get_theme_duration($entry->themeid, $entry->studyyear);
            }
        }
    }

    foreach ($progress->themes as $themeid => $t) {
        if ($t->theme->mandatory) {
            if (!empty($t->theme->requiredduration)) {
                // Durée globale (unique pour toute la thématique, quel que soit le nombre
                // d'années sur lesquelles elle est saisie) : ne pas la sommer par année.
                $t->requiredduration = (int) $t->theme->requiredduration;
            } else if (empty($t->requiredyears)) {
                // Pas encore de saisie sur cette thématique : on affiche la durée requise pour
                // l'année minimale de sa plage, à titre indicatif.
                $t->requiredduration = stage_get_theme_duration($themeid, $t->theme->minstudyyear ?: $t->theme->maxstudyyear);
            }
            $progress->themes[$themeid]->requiredduration = $t->requiredduration;
            $progress->themes[$themeid]->done = ($t->retained >= $t->requiredduration) && $t->requiredduration > 0;
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
 * @param int $studyyear Année d'étude à laquelle ce stage est rattaché (N, N-1 ou N+1).
 * @param int $conventionstatus Statut de convention initial : STAGE_CONVENTION_NONE par défaut,
 *                               ou STAGE_CONVENTION_SIGNVET pour un enregistrement en masse
 *                               (stages déjà signés sur SignVet, hors circuit de gestion de
 *                               convention de ce plugin).
 * @param int $abroad Stage effectué à l'étranger (0 ou 1).
 * @param string $country Pays du stage, si $abroad.
 * @return int Id de la saisie créée.
 */
function stage_register_entry($stageid, $studentid, $themeid, $structure, $datestart, $dateend, $declaredduration,
        $studyyear = 0, $conventionstatus = STAGE_CONVENTION_NONE, $abroad = 0, $country = '') {
    global $DB;

    $record = new stdClass();
    $record->stageid = $stageid;
    $record->userid = $studentid;
    $record->themeid = $themeid;
    $record->studyyear = $studyyear;
    $record->structure = $structure;
    $record->abroad = $abroad ? 1 : 0;
    $record->country = $abroad ? $country : '';
    $record->datestart = $datestart;
    $record->dateend = $dateend;
    $record->declaredduration = $declaredduration;
    $record->retainedduration = 0;
    $record->status = STAGE_STATUS_ENREGISTRE;
    $record->conventionstatus = $conventionstatus;
    $record->timecreated = time();
    $record->timemodified = time();

    $entryid = $DB->insert_record('stage_entry', $record);

    // Toute saisie a au moins une plage de dates : les plages sont la seule saisie possible des
    // dates d'un stage (voir stage_save_entry_periods()), et les pages qui les affichent ou les
    // rééditent doivent en trouver, quel que soit le chemin de création — formulaire, création en
    // masse, import CSV ou StageVet, service web. L'appelant qui dispose de plusieurs plages les
    // remplace ensuite par la liste complète.
    if (!empty($datestart) && !empty($dateend)) {
        $DB->insert_record('stage_entry_period', (object) [
            'entryid' => $entryid,
            'datestart' => $datestart,
            'dateend' => $dateend,
            'timecreated' => time(),
        ]);
    }

    return $entryid;
}

/**
 * Met à jour les données de fond (thématique, année d'étude, structure, mobilité, dates, durée)
 * d'une saisie de stage, à l'initiative de la DEVE.
 *
 * @param stdClass $entry
 * @param int $themeid
 * @param string $structure
 * @param int $datestart
 * @param int $dateend
 * @param int $declaredduration
 * @param int $studyyear
 * @param int $abroad
 * @param string $country
 * @return void
 */
function stage_update_entry_details(stdClass $entry, $themeid, $structure, $datestart, $dateend, $declaredduration,
        $studyyear = 0, $abroad = 0, $country = '') {
    global $DB;

    $entry->themeid = $themeid;
    $entry->abroad = $abroad ? 1 : 0;
    $entry->country = $abroad ? $country : '';
    $entry->studyyear = $studyyear;
    $entry->structure = $structure;
    $entry->datestart = $datestart;
    $entry->dateend = $dateend;
    $entry->declaredduration = $declaredduration;
    $entry->timemodified = time();
    $DB->update_record('stage_entry', $entry);
}

/**
 * Retourne les plages de dates définies pour une saisie de stage, triées chronologiquement.
 *
 * @param int $entryid
 * @return array
 */
function stage_get_entry_periods($entryid) {
    global $DB;

    return $DB->get_records('stage_entry_period', ['entryid' => $entryid], 'datestart ASC');
}

/**
 * Retourne les plages de dates d'une saisie, ou une plage unique reconstituée à partir de ses
 * dates de début/fin (compatibilité avec les saisies existantes, créées avant l'introduction des
 * plages multiples) si aucune plage n'a encore été définie explicitement.
 *
 * @param stdClass $entry
 * @return array
 */
function stage_get_or_seed_entry_periods(stdClass $entry) {
    $periods = stage_get_entry_periods($entry->id);
    if (!empty($periods)) {
        return $periods;
    }
    if (empty($entry->datestart) || empty($entry->dateend)) {
        return [];
    }
    return [(object) [
        'id' => 0,
        'entryid' => $entry->id,
        'datestart' => $entry->datestart,
        'dateend' => $entry->dateend,
    ]];
}

/**
 * Remplace les plages de dates d'une saisie de stage par la liste fournie, et aligne les dates de
 * début et de fin de la saisie sur la première et la dernière date couvertes.
 *
 * Les plages sont la source de vérité des dates du stage : les dates de la saisie, affichées
 * partout ailleurs (listes, convention, exports), en sont déduites plutôt que saisies séparément,
 * ce qui garantit qu'elles ne peuvent pas les contredire. Les plages invalides (dates manquantes
 * ou fin antérieure au début) sont ignorées ; une liste vide laisse les dates de la saisie
 * inchangées, faute de quoi elles seraient effacées sans que rien ne les remplace.
 *
 * La cohérence des plages entre elles (absence de chevauchement) relève des formulaires, qui
 * refusent la saisie plutôt que de la corriger en silence : voir stage_validate_periods().
 *
 * @param int $entryid
 * @param array $periods Liste de tableaux ['datestart' => int, 'dateend' => int]
 * @return void
 */
/**
 * Indique si le stage d'une saisie n'a pas encore commencé, c'est-à-dire si sa date de début
 * (celle de sa première plage, tenue à jour par stage_save_entry_periods()) est postérieure à
 * aujourd'hui. Le jour même du début compte comme commencé : la comparaison se fait à minuit.
 *
 * Une saisie sans date de début connue (enregistrement DEVE sans dates, import) n'est jamais
 * considérée comme « pas encore commencée » : rien ne permettrait de dire quand elle commence, et
 * la bloquer sur cette base l'empêcherait d'être auto-évaluée un jour.
 *
 * @param stdClass $entry
 * @return bool
 */
function stage_entry_not_started_yet(stdClass $entry) {
    return !empty($entry->datestart) && $entry->datestart > usergetmidnight(time());
}

function stage_save_entry_periods($entryid, array $periods) {
    global $DB;

    $DB->delete_records('stage_entry_period', ['entryid' => $entryid]);

    $records = [];
    foreach ($periods as $period) {
        $start = $period['datestart'] ?? 0;
        $end = $period['dateend'] ?? 0;
        if (empty($start) || empty($end) || $start > $end) {
            continue;
        }
        $records[] = (object) [
            'entryid' => $entryid,
            'datestart' => $start,
            'dateend' => $end,
            'timecreated' => time(),
        ];
    }
    if (!$records) {
        return;
    }

    $DB->insert_records('stage_entry_period', $records);

    $starts = array_column($records, 'datestart');
    $ends = array_column($records, 'dateend');
    $DB->update_record('stage_entry', (object) [
        'id' => $entryid,
        'datestart' => min($starts),
        'dateend' => max($ends),
        'timemodified' => time(),
    ]);
}

/**
 * Vérifie la cohérence des plages de dates saisies pour un stage : au moins une plage, une fin
 * qui ne précède pas son début, et aucun chevauchement entre plages.
 *
 * Deux plages qui se recoupent feraient compter deux fois les mêmes journées dans le décompte des
 * jours de stage, et n'ont de toute façon pas de sens sur une convention : la saisie est refusée
 * plutôt que corrigée en silence. Utilisée par les formulaires de demande et de validation de
 * convention, seuls endroits où les plages se saisissent.
 *
 * @param array $periods Liste de tableaux ['datestart' => int, 'dateend' => int], telle que
 *                       produite par stage_extract_submitted_periods().
 * @param bool $nopast Refuse en plus une plage commençant avant aujourd'hui. Réservé aux
 *                     formulaires par lesquels l'étudiant demande une convention : une convention
 *                     ne peut pas couvrir un stage déjà commencé, alors que la DEVE enregistre au
 *                     contraire couramment des stages passés (reprise d'historique, saisie a
 *                     posteriori) et n'est donc pas soumise à cette règle.
 * @return string|null Message d'erreur à afficher, ou null si les plages sont cohérentes.
 */
function stage_validate_periods(array $periods, $nopast = false) {
    if (empty($periods)) {
        return get_string('periodsrequired', 'mod_stage');
    }

    foreach ($periods as $period) {
        if ($period['dateend'] < $period['datestart']) {
            return get_string('periodendbeforestart', 'mod_stage');
        }
    }

    // Une plage commençant aujourd'hui reste acceptée : c'est le jour même, pas le passé. La
    // comparaison se fait donc à minuit et non à l'heure courante, sans quoi une convention
    // demandée pour le jour même serait refusée dès la première seconde de la journée écoulée.
    if ($nopast) {
        $today = usergetmidnight(time());
        foreach ($periods as $period) {
            if ($period['datestart'] < $today) {
                return get_string('periodstartinpast', 'mod_stage');
            }
        }
    }

    // Trié par date de début, deux plages se chevauchent si l'une commence avant la fin de la
    // précédente : une seule comparaison par plage suffit alors à toutes les détecter.
    usort($periods, function($a, $b) {
        return $a['datestart'] <=> $b['datestart'];
    });
    $dateformat = get_string('strftimedate', 'langconfig');
    for ($i = 1; $i < count($periods); $i++) {
        if ($periods[$i]['datestart'] <= $periods[$i - 1]['dateend']) {
            return get_string('periodsoverlap', 'mod_stage', (object) [
                'first' => userdate($periods[$i - 1]['datestart'], $dateformat) . ' - '
                    . userdate($periods[$i - 1]['dateend'], $dateformat),
                'second' => userdate($periods[$i]['datestart'], $dateformat) . ' - '
                    . userdate($periods[$i]['dateend'], $dateformat),
            ]);
        }
    }

    return null;
}

/**
 * Extrait les plages de dates soumises par un champ répété "perioddatestart"/"perioddateend"
 * (voir stage_add_period_fields()), en ignorant les lignes vides ou incomplètes (option
 * "optional" du date_selector : valeur 0 quand la ligne n'est pas activée).
 *
 * @param stdClass $data Données soumises d'un formulaire utilisant stage_add_period_fields()
 * @return array Liste de tableaux ['datestart' => int, 'dateend' => int], prête pour
 *               stage_save_entry_periods()
 */
function stage_extract_submitted_periods(stdClass $data) {
    $periods = [];
    if (empty($data->perioddatestart) || !is_array($data->perioddatestart)) {
        return $periods;
    }
    foreach ($data->perioddatestart as $i => $start) {
        $end = $data->perioddateend[$i] ?? 0;
        if (empty($start) || empty($end)) {
            continue;
        }
        $periods[] = ['datestart' => $start, 'dateend' => $end];
    }
    return $periods;
}

/**
 * Ajoute à un formulaire les champs répétés (avec bouton "Ajouter une plage") permettant de
 * saisir plusieurs plages de dates pour un stage, directement sur la même page que les autres
 * informations de convention (voir student_register_form, convention_request_form,
 * convention_review_form). Les valeurs soumises sont récupérées avec
 * stage_extract_submitted_periods() puis enregistrées avec stage_save_entry_periods().
 *
 * @param \moodleform $form Le formulaire, pour appeler sa méthode repeat_elements().
 * @param \MoodleQuickForm $mform Son $this->_form (propriété protégée, à passer depuis la classe
 *                                appelante : ce helper ne peut pas y accéder directement).
 * @param int $initialcount Nombre de lignes affichées initialement (au moins 1).
 * @return void
 */
function stage_add_period_fields(\moodleform $form, $mform, $initialcount = 1) {
    $mform->addElement('header', 'periodsheader', get_string('periods', 'mod_stage'));
    $mform->setExpanded('periodsheader');
    $mform->addElement('static', 'periodshelp', '', get_string('periods_help', 'mod_stage'));

    $repeatarray = [
        $mform->createElement('date_selector', 'perioddatestart', get_string('periodstart', 'mod_stage'),
            ['optional' => true]),
        $mform->createElement('date_selector', 'perioddateend', get_string('periodend', 'mod_stage'),
            ['optional' => true]),
    ];
    $form->repeat_elements($repeatarray, max((int) $initialcount, 1), [], 'periodrepeats', 'periodaddfields', 1,
        get_string('addperiod', 'mod_stage'), true);

    // Au moins une plage est exigée (voir stage_validate_periods()), mais un date_selector
    // « optional » s'affiche décoché : sur une création, la première ligne serait à activer avant
    // même de pouvoir la remplir, et son oubli renverrait une erreur dont la cause n'a rien
    // d'évident. Une valeur par défaut la laisse activée ; set_data() la remplace par les dates
    // réelles lors d'une édition.
    $mform->setDefault('perioddatestart[0]', time());
    $mform->setDefault('perioddateend[0]', time());
}

/**
 * Retourne la liste des jours (timestamps à minuit, heure du serveur) compris dans une plage de
 * dates, bornes incluses.
 *
 * @param stdClass $period
 * @return array
 */
function stage_get_period_days(stdClass $period) {
    $days = [];
    $startinfo = usergetdate($period->datestart);
    $endinfo = usergetdate($period->dateend);
    $cursor = make_timestamp($startinfo['year'], $startinfo['mon'], $startinfo['mday'], 0, 0, 0);
    $end = make_timestamp($endinfo['year'], $endinfo['mon'], $endinfo['mday'], 0, 0, 0);
    while ($cursor <= $end) {
        $days[] = $cursor;
        $cursor += DAYSECS;
    }
    return $days;
}

/**
 * Retourne les jours de stage effectifs sélectionnés pour une saisie (voir
 * stage_set_entry_workdays()), triés chronologiquement.
 *
 * @param int $entryid
 * @return array int[] Timestamps (minuit, heure du serveur)
 */
function stage_get_entry_workdays($entryid) {
    global $DB;

    $dates = $DB->get_fieldset_select('stage_entry_workday', 'workdate', 'entryid = :entryid', ['entryid' => $entryid]);
    $dates = array_map('intval', $dates);
    sort($dates);
    return $dates;
}

/**
 * Enregistre les jours de stage effectifs sélectionnés par l'étudiant parmi les plages de sa
 * saisie (voir stage_get_or_seed_entry_periods()), visibles et modifiables par l'enseignant
 * référent et la DEVE lors de la validation.
 *
 * @param int $entryid
 * @param array $dates Timestamps (minuit, heure du serveur)
 * @return void
 */
function stage_set_entry_workdays($entryid, array $dates) {
    global $DB;

    $DB->delete_records('stage_entry_workday', ['entryid' => $entryid]);

    $records = [];
    $now = time();
    foreach (array_unique(array_map('intval', $dates)) as $date) {
        if (empty($date)) {
            continue;
        }
        $records[] = (object) ['entryid' => $entryid, 'workdate' => $date, 'timecreated' => $now];
    }
    if ($records) {
        $DB->insert_records('stage_entry_workday', $records);
    }
}

/**
 * Vérifie si un ensemble de jours de stage sélectionnés enfreint la règle d'au moins un jour de
 * repos par semaine : vrai si une fenêtre de 7 jours consécutifs est entièrement sélectionnée
 * (aucun jour de repos). Vérification indicative uniquement (non bloquante), affichée en
 * avertissement à l'étudiant, l'enseignant référent et la DEVE.
 *
 * @param array $dates Timestamps (minuit, heure du serveur)
 * @return bool
 */
function stage_workdays_violate_restday_rule(array $dates) {
    $set = array_flip($dates);
    foreach ($dates as $date) {
        $fullweek = true;
        for ($i = 0; $i < 7; $i++) {
            if (!isset($set[$date + $i * DAYSECS])) {
                $fullweek = false;
                break;
            }
        }
        if ($fullweek) {
            return true;
        }
    }
    return false;
}

/**
 * Produit le HTML d'un sélecteur de jours de stage effectifs, groupé par plage de dates : cases à
 * cocher si $editable, simples badges en lecture seule sinon. Inclut le rappel de la règle d'un
 * jour de repos minimum par semaine. Utilisé par l'auto-évaluation de l'étudiant (entry.php) ainsi
 * que par les pages d'évaluation enseignant (teacher.php) et de validation DEVE (deve.php), qui
 * peuvent toutes deux consulter et modifier la sélection de l'étudiant.
 *
 * @param array $periods Liste de stage_entry_period (voir stage_get_or_seed_entry_periods())
 * @param array $selected Timestamps sélectionnés
 * @param bool $editable
 * @param string $fieldname Nom du champ de formulaire (sans les crochets []) si $editable
 * @return string
 */
function stage_render_workday_picker(array $periods, array $selected, $editable, $fieldname = 'workdays') {
    if (empty($periods)) {
        return html_writer::tag('p', get_string('noperiodsdefined', 'mod_stage'), ['class' => 'text-muted']);
    }

    $dateformat = get_string('strftimedateshort', 'langconfig');
    $selectedset = array_flip($selected);

    $out = html_writer::tag('p', get_string('restdayrule', 'mod_stage'), ['class' => 'alert alert-info']);
    foreach ($periods as $period) {
        $out .= html_writer::tag('p', html_writer::tag('strong',
            userdate($period->datestart, $dateformat) . ' - ' . userdate($period->dateend, $dateformat)));
        $out .= html_writer::start_div('stage-workday-grid d-flex flex-wrap');
        foreach (stage_get_period_days($period) as $day) {
            $checked = isset($selectedset[$day]);
            if ($editable) {
                $out .= html_writer::tag('label',
                    html_writer::checkbox($fieldname . '[]', $day, $checked, ' ' . userdate($day, '%d/%m')),
                    ['class' => 'mr-3 mb-1']);
            } else {
                $out .= html_writer::span(userdate($day, '%d/%m'),
                    'badge mr-1 mb-1 ' . ($checked ? 'badge-success' : 'badge-light'));
            }
        }
        $out .= html_writer::end_div();
    }

    if (stage_workdays_violate_restday_rule($selected)) {
        $out .= html_writer::div(get_string('restdaywarning', 'mod_stage'), 'alert alert-warning mt-2');
    }

    return $out;
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
    $entry->tutorbypassed = 0;
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
 * Décrit chaque e-mail envoyé par l'activité, personnalisable par la DEVE (voir
 * notifications.php, stage_email_template) : la clé sert d'identifiant stable en base, les
 * chaînes de langue portent le texte par défaut (dans le format {$a->xxx} habituel de Moodle), et
 * "vars" liste les variables disponibles pour la personnalisation, sous la forme {{xxx}} (une
 * syntaxe volontairement différente de celle des chaînes de langue : le texte personnalisé n'est
 * pas une chaîne de langue et ne bénéficie pas de son mécanisme de substitution).
 *
 * @return array emailkey => ['label' => string, 'subjectstring' => string, 'bodystring' => string,
 *               'vars' => string[]]
 */
function stage_get_email_definitions() {
    return [
        'selfeval' => [
            'label' => get_string('emailkeyselfeval', 'mod_stage'),
            'subjectstring' => 'selfevalnotifsubject',
            'bodystring' => 'selfevalnotifbody',
            'vars' => ['student', 'stage', 'url'],
        ],
        'teacherpending' => [
            'label' => get_string('emailkeyteacherpending', 'mod_stage'),
            'subjectstring' => 'conventionteacherpendingnotifsubject',
            'bodystring' => 'conventionteacherpendingnotifbody',
            'vars' => ['stage', 'url'],
        ],
        'studentrejected' => [
            'label' => get_string('emailkeystudentrejected', 'mod_stage'),
            'subjectstring' => 'conventionrejectednotifsubject',
            'bodystring' => 'conventionrejectednotifbody',
            'vars' => ['stage', 'comment', 'url'],
        ],
        'tutorrequest' => [
            'label' => get_string('emailkeytutorrequest', 'mod_stage'),
            'subjectstring' => 'tutorevalnotifsubject',
            'bodystring' => 'tutorevalnotifbody',
            'vars' => ['student', 'stage', 'url'],
        ],
        'conventionreminder' => [
            'label' => get_string('emailkeyconventionreminder', 'mod_stage'),
            'subjectstring' => 'conventionremindernotifsubject',
            'bodystring' => 'conventionremindernotifbody',
            'vars' => ['student', 'stage', 'theme', 'datestart', 'days', 'url'],
        ],
    ];
}

/**
 * Retourne la personnalisation éventuelle (sujet/corps) d'un e-mail pour un stage donné, ou null
 * si la DEVE n'a rien personnalisé (ou a vidé les deux champs, ce qui revient à revenir au texte
 * par défaut).
 *
 * @param int $stageid
 * @param string $emailkey
 * @return stdClass|null {subject, body}, l'un ou l'autre pouvant être vide pour ne personnaliser
 *                       que l'autre.
 */
function stage_get_custom_email_template($stageid, $emailkey) {
    global $DB;

    return $DB->get_record('stage_email_template', ['stageid' => $stageid, 'emailkey' => $emailkey]) ?: null;
}

/**
 * Enregistre (ou efface, si les deux champs sont vides) la personnalisation d'un e-mail pour un
 * stage donné.
 *
 * @param int $stageid
 * @param string $emailkey
 * @param string $subject
 * @param string $body
 * @return void
 */
function stage_save_email_template($stageid, $emailkey, $subject, $body) {
    global $DB;

    $existing = $DB->get_record('stage_email_template', ['stageid' => $stageid, 'emailkey' => $emailkey]);
    if (trim($subject) === '' && trim($body) === '') {
        if ($existing) {
            $DB->delete_records('stage_email_template', ['id' => $existing->id]);
        }
        return;
    }

    if ($existing) {
        $existing->subject = $subject;
        $existing->body = $body;
        $existing->timemodified = time();
        $DB->update_record('stage_email_template', $existing);
    } else {
        $DB->insert_record('stage_email_template', (object) [
            'stageid' => $stageid,
            'emailkey' => $emailkey,
            'subject' => $subject,
            'body' => $body,
            'timemodified' => time(),
        ]);
    }
}

/**
 * Remplace dans un texte personnalisé les jetons {{cle}} par les valeurs fournies. Utilisée
 * uniquement pour un texte personnalisé par la DEVE (notifications.php) : le texte par défaut,
 * lui, est une chaîne de langue et passe par le mécanisme habituel de get_string().
 *
 * @param string $text
 * @param array $vars cle => valeur
 * @return string
 */
function stage_render_email_placeholders($text, array $vars) {
    $search = [];
    $replace = [];
    foreach ($vars as $key => $value) {
        $search[] = '{{' . $key . '}}';
        $replace[] = (string) $value;
    }
    return str_replace($search, $replace, $text);
}

/**
 * Résout le sujet et le corps d'un e-mail de l'activité : le texte personnalisé par la DEVE s'il
 * existe (voir stage_get_custom_email_template()), sinon le texte par défaut (chaîne de langue).
 *
 * @param int $stageid
 * @param string $emailkey Voir stage_get_email_definitions().
 * @param array $vars Valeurs des variables listées dans la définition de cet e-mail (voir
 *                    stage_get_email_definitions()['vars']), utilisées à la fois pour le texte
 *                    par défaut ({$a->xxx}) et pour un texte personnalisé ({{xxx}}).
 * @param string|null $lang Langue à utiliser pour le texte par défaut ('fr', 'en', ...), en
 *                    priorité sur la langue courante de la session. N'a d'effet que sur le texte
 *                    par défaut : un texte personnalisé par la DEVE reste tel quel, quelle que
 *                    soit la langue.
 * @return stdClass {subject, body}
 */
function stage_resolve_email_text($stageid, $emailkey, array $vars, $lang = null) {
    $definitions = stage_get_email_definitions();
    $definition = $definitions[$emailkey];
    $custom = stage_get_custom_email_template($stageid, $emailkey);

    if ($custom && trim((string) $custom->subject) !== '') {
        $subject = stage_render_email_placeholders($custom->subject, $vars);
    } else {
        // Les sujets par défaut existants n'utilisent qu'une seule variable (le nom du stage),
        // passée directement plutôt qu'en objet : on respecte ce format pour ne pas retoucher
        // ces chaînes de langue.
        $subject = get_string($definition['subjectstring'], 'mod_stage', $vars['stage'] ?? null, $lang);
    }

    if ($custom && trim((string) $custom->body) !== '') {
        $body = stage_render_email_placeholders($custom->body, $vars);
    } else {
        $body = get_string($definition['bodystring'], 'mod_stage', (object) $vars, $lang);
    }

    return (object) ['subject' => $subject, 'body' => $body];
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
    $text = stage_resolve_email_text($stage->id, 'selfeval', [
        'student' => fullname($student),
        'stage' => format_string($stage->name),
        'url' => $url->out(false),
    ]);
    $noreply = core_user::get_noreply_user();

    foreach ($teachers as $teacher) {
        email_to_user($teacher, $noreply, $text->subject, $text->body);
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
function stage_question_options(stdClass $question, $lang = 'fr') {
    $raw = $lang === 'en' && trim((string) ($question->optionsen ?? '')) !== ''
        ? $question->optionsen : $question->options;
    $lines = preg_split('/\r\n|\r|\n/', (string) $raw);
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
 * Intitulé d'une question dans la langue demandée : la version anglaise si elle a été saisie et
 * demandée (maître de stage dont la convention est en anglais), la version française sinon.
 *
 * @param stdClass $question
 * @param string $lang 'fr' ou 'en'
 * @return string
 */
function stage_question_name(stdClass $question, $lang = 'fr') {
    if ($lang === 'en' && trim((string) ($question->nameen ?? '')) !== '') {
        return $question->nameen;
    }
    return $question->name;
}

/**
 * Détermine la langue à utiliser pour s'adresser au maître de stage (courriel d'invitation,
 * page d'évaluation, intitulés des questions) : celle du gabarit de convention utilisé pour la
 * saisie, ou le français par défaut si la saisie n'a pas de convention ou de gabarit connu.
 *
 * @param stdClass $entry
 * @return string 'fr' ou 'en'
 */
function stage_get_entry_convention_lang(stdClass $entry) {
    global $DB;

    if (empty($entry->conventiontemplateid)) {
        return 'fr';
    }
    $lang = $DB->get_field('stage_convention_template', 'lang', ['id' => $entry->conventiontemplateid]);
    return $lang === 'en' ? 'en' : 'fr';
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
function stage_render_question_fields(array $questions, array $answers, $lang = 'fr') {
    $out = '';

    foreach ($questions as $question) {
        $current = isset($answers[$question->id]) ? $answers[$question->id]->answertext : '';
        $fieldname = 'q_' . $question->id;
        $required = $question->required ? ['required' => 'required'] : [];

        $out .= html_writer::start_tag('div', ['class' => 'form-group mb-3']);
        $out .= html_writer::tag('label', format_string(stage_question_name($question, $lang)) . ($question->required ? ' *' : ''),
            ['for' => $fieldname]);

        if ($question->qtype === 'choice') {
            foreach (stage_question_options($question, $lang) as $index => $option) {
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
 * Affiche une paire libellé/valeur, ou rien si la valeur est vide. Utilisé par la page de détail
 * d'une saisie (entrydetail.php) pour lister ses informations sans surcharger le HTML de tests.
 *
 * @param string $label
 * @param string|null $value
 * @return void
 */
function stage_detail_row($label, $value) {
    if ($value === null || $value === '') {
        return;
    }
    echo html_writer::tag('p', html_writer::tag('strong', $label . ' : ') . $value);
}

/**
 * Rend une section d'informations en lecture seule sous forme de tableau libellé/valeur, plutôt
 * qu'en suite de paragraphes (voir stage_detail_row()) : sur les pages qui en enchaînent des
 * dizaines, l'alignement des valeurs en colonne les rend nettement plus faciles à parcourir.
 *
 * Les lignes dont la valeur est vide sont omises, et la section entière disparaît s'il ne reste
 * aucune ligne à afficher : inutile d'afficher un titre au-dessus d'un tableau vide.
 *
 * @param string $heading Titre de la section.
 * @param array $rows Paires [libellé => valeur]. Une valeur null ou vide fait sauter la ligne.
 * @return string HTML, ou '' si toutes les valeurs sont vides.
 */
function stage_render_detail_section($heading, array $rows) {
    global $OUTPUT;

    $table = new html_table();
    $table->attributes['class'] = 'generaltable stage-detailtable';
    foreach ($rows as $label => $value) {
        if ($value === null || $value === '' || $value === false) {
            continue;
        }
        $table->data[] = [html_writer::tag('strong', $label), $value];
    }
    if (empty($table->data)) {
        return '';
    }

    return $OUTPUT->heading($heading, 4) . html_writer::table($table);
}

/**
 * Rend une liste d'actions en petits boutons plutôt qu'en liens séparés par des barres
 * verticales : dans une colonne « Actions » d'un tableau, les boutons restent lisibles quand il y
 * en a plusieurs, et se distinguent des liens de contenu.
 *
 * @param array $links Paires [libellé => moodle_url]. Une URL nulle fait sauter l'entrée.
 * @param string $class Classe du bouton (par défaut secondaire, discret dans un tableau).
 * @param string $empty Valeur renvoyée si aucune action n'est proposée : '-' pour remplir une
 *                      cellule de tableau, '' quand l'appelant enchaîne plusieurs groupes et
 *                      compose lui-même le rendu de l'ensemble vide.
 * @return string HTML, ou $empty si aucune action n'est proposée.
 */
function stage_render_actions(array $links, $class = 'btn btn-sm btn-secondary mr-1 mb-1', $empty = '-') {
    $out = '';
    foreach ($links as $label => $url) {
        if ($url === null) {
            continue;
        }
        $out .= html_writer::link($url, $label, ['class' => $class]);
    }

    return $out !== '' ? $out : $empty;
}

/**
 * Rend le rappel d'identité d'une saisie de stage : de quel stage il s'agit (étudiant, thématique,
 * année, structure, mobilité, dates et durées) et où il en est (statut de la saisie et de sa
 * convention.)
 *
 * Affiché en tête des pages qui portent sur une saisie précise (auto-évaluation, évaluation
 * enseignant, détail, gestion des plages), pour que l'utilisateur sache toujours sur quel stage il
 * travaille sans avoir à revenir à la liste.
 *
 * @param stdClass $entry
 * @param stdClass|null $theme Thématique de la saisie, si connue.
 * @param stdClass|null $student Étudiant concerné : à ne fournir que sur les pages où l'utilisateur
 *                               n'est pas lui-même l'étudiant (DEVE, enseignant référent).
 * @return string HTML
 */
function stage_render_entry_summary(stdClass $entry, $theme = null, $student = null) {
    $dateformat = get_string('strftimedate', 'langconfig');

    // Les plages de dates priment sur les dates de début/fin de la saisie : quand un stage est
    // découpé en plusieurs périodes non contiguës, ce sont elles l'information utile.
    $periods = stage_get_or_seed_entry_periods($entry);
    $periodlabels = array_map(function($period) use ($dateformat) {
        return userdate($period->datestart, $dateformat) . ' - ' . userdate($period->dateend, $dateformat);
    }, $periods);

    $rows = [];
    if ($student !== null) {
        $rows[get_string('student', 'mod_stage')] = fullname($student);
    }
    $rows[get_string('theme', 'mod_stage')] = $theme ? format_string($theme->name) : '-';
    $rows[get_string('studyyear', 'mod_stage')] = stage_studyyear_label($entry->studyyear);
    $rows[get_string('structure', 'mod_stage')] = s($entry->structure);
    if (!empty($entry->abroad)) {
        $rows[get_string('abroad', 'mod_stage')] = html_writer::span(
            '🌍 ' . (!empty($entry->country) ? s($entry->country) : get_string('abroad', 'mod_stage')),
            'badge badge-info');
    }
    $rows[count($periodlabels) > 1 ? get_string('periods', 'mod_stage') : get_string('dates', 'mod_stage')] =
        !empty($periodlabels) ? implode(html_writer::empty_tag('br'), $periodlabels) : null;
    $rows[get_string('declaredduration', 'mod_stage')] = $entry->declaredduration;
    $rows[get_string('retainedduration', 'mod_stage')] = $entry->retainedduration ?: null;
    $rows[get_string('status', 'mod_stage')] = html_writer::span(stage_status_label($entry->status),
        'badge ' . stage_status_badgeclass($entry->status));
    $rows[get_string('conventionstatus', 'mod_stage')] = html_writer::span(
        stage_convention_status_label($entry->conventionstatus),
        'badge ' . stage_convention_status_badgeclass($entry->conventionstatus));

    return stage_render_detail_section(get_string('stagesummary', 'mod_stage'), $rows);
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
function stage_render_answers_readonly(array $questions, array $answers, $lang = 'fr') {
    if (empty($questions)) {
        return '';
    }

    $out = html_writer::start_tag('dl', ['class' => 'stage-answers']);
    foreach ($questions as $question) {
        $current = isset($answers[$question->id]) ? trim((string) $answers[$question->id]->answertext) : '';
        $out .= html_writer::tag('dt', format_string(stage_question_name($question, $lang)));
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
        $yearprogress = stage_get_student_year_progress($stageid, $student->id);
        $abroadprogress = stage_get_student_abroad_progress($stageid, $student->id);

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

        // Les objectifs sont complétés si, pour chaque année d'étude ayant un objectif défini
        // (durée totale, durée par thématique et/ou mobilité internationale), tous les objectifs
        // de cette année sont validés (voir stage_get_student_year_progress(), qui intègre déjà
        // la mobilité internationale à l'année où elle est due).
        $yeartotal = count($yearprogress);
        $yeardone = count(array_filter($yearprogress, function($row) {
            return $row->done;
        }));

        $rows[] = (object) [
            'user' => $student,
            'progress' => $progress,
            'yearprogress' => $yearprogress,
            'abroadprogress' => $abroadprogress,
            'entrycount' => count($entries),
            'pendingcount' => $pending,
            'mandatorytotal' => $mandatorytotal,
            'mandatorydone' => $mandatorydone,
            'complete' => $yeartotal > 0 && $yeardone === $yeartotal,
        ];
    }

    return $rows;
}

/**
 * Années d'étude à retenir pour le bilan de promotion : l'année d'étude courante du stage
 * (stage->currentstudyyear) et les précédentes, ignorées au-delà.
 *
 * Les objectifs des années à venir ne sont pas encore exigibles : les faire figurer dans un bilan
 * de promotion ferait apparaître en retard toute la promotion. Tant que l'année courante n'est pas
 * renseignée, toutes les années sur lesquelles la promotion a des objectifs sont retenues.
 *
 * @param stdClass $stage
 * @param array $rows Lignes de stage_get_pilotage_overview().
 * @return array Années d'étude concernées, triées par ordre croissant.
 */
function stage_get_promotion_years(stdClass $stage, array $rows) {
    $years = [];
    foreach ($rows as $row) {
        foreach ($row->yearprogress as $yearrow) {
            if (empty($stage->currentstudyyear) || $yearrow->studyyear <= $stage->currentstudyyear) {
                $years[(int) $yearrow->studyyear] = true;
            }
        }
    }
    $years = array_keys($years);
    sort($years);

    return $years;
}

/**
 * Construit le bilan de promotion : pour chaque étudiant, la validation de chacune des années
 * d'étude échues (année courante et précédentes, voir stage_get_promotion_years()), et le constat
 * global qui en découle.
 *
 * Les étudiants qui ne valident pas au moins une de ces années sont placés en tête, du plus en
 * retard au moins en retard : c'est sur eux que la DEVE a une action à mener, et un bilan de
 * promotion est d'abord une liste de relances. Les autres suivent, par ordre alphabétique.
 *
 * @param stdClass $stage
 * @param context $context Contexte du module stage.
 * @param array|null $restrictuserids Restreint à ces étudiants (enseignant référent), ou null.
 * @return stdClass {years (années retenues), rows (lignes triées), failedcount, total}
 *                  Chaque ligne ajoute aux champs de stage_get_pilotage_overview() :
 *                  'yearsdone' [année => bool], 'failedyears' [années non validées],
 *                  'uptodate' (bool : aucune année échue en défaut).
 */
function stage_get_promotion_report(stdClass $stage, context $context, ?array $restrictuserids = null) {
    $rows = stage_get_pilotage_overview($stage->id, $context, $restrictuserids);
    $years = stage_get_promotion_years($stage, $rows);

    foreach ($rows as $row) {
        $row->yearsdone = [];
        $row->failedyears = [];
        foreach ($years as $year) {
            // Une année sans objectif pour cet étudiant n'est ni validée ni en défaut : elle
            // reste absente de yearsdone, et se lit « sans objet » à l'affichage.
            if (!isset($row->yearprogress[$year])) {
                continue;
            }
            $done = (bool) $row->yearprogress[$year]->done;
            $row->yearsdone[$year] = $done;
            if (!$done) {
                $row->failedyears[] = $year;
            }
        }
        $row->uptodate = empty($row->failedyears);
    }

    usort($rows, function($a, $b) {
        // Les étudiants en défaut d'abord ; parmi eux, ceux qui accumulent le plus d'années non
        // validées, puis ceux dont le retard est le plus ancien : c'est l'ordre d'urgence.
        if ($a->uptodate !== $b->uptodate) {
            return $a->uptodate <=> $b->uptodate;
        }
        if (!$a->uptodate) {
            $countcmp = count($b->failedyears) <=> count($a->failedyears);
            if ($countcmp !== 0) {
                return $countcmp;
            }
            $oldestcmp = reset($a->failedyears) <=> reset($b->failedyears);
            if ($oldestcmp !== 0) {
                return $oldestcmp;
            }
        }
        return core_text::strtolower(fullname($a->user)) <=> core_text::strtolower(fullname($b->user));
    });

    return (object) [
        'years' => $years,
        'rows' => $rows,
        'total' => count($rows),
        'failedcount' => count(array_filter($rows, function($row) {
            return !$row->uptodate;
        })),
    ];
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
    global $USER;

    // Les destinations sont rendues en boutons, dans l'ordre du travail quotidien (enregistrer,
    // suivre les conventions, valider, piloter) puis les usages ponctuels (export, administration)
    // en fin de barre : en simples liens séparés par des barres verticales, elles se lisaient
    // comme une seule phrase et ne se distinguaient pas du contenu de la page.
    $links = [];
    if (has_capability('mod/stage:registerstages', $context)) {
        $links[get_string('registerstages', 'mod_stage')] =
            new moodle_url('/mod/stage/register.php', ['id' => $cm->id]);
        $links[get_string('conventions', 'mod_stage')] =
            new moodle_url('/mod/stage/conventions.php', ['id' => $cm->id]);
    }
    if (has_capability('mod/stage:validatedeve', $context)) {
        $links[get_string('devevalidation', 'mod_stage')] = new moodle_url('/mod/stage/deve.php', ['id' => $cm->id]);
    }
    if (has_capability('mod/stage:evaluateteacher', $context)) {
        $links[get_string('teachervalidation', 'mod_stage')] =
            new moodle_url('/mod/stage/teacher.php', ['id' => $cm->id]);
    }
    if (has_capability('mod/stage:viewall', $context) || has_capability('mod/stage:evaluateteacher', $context)) {
        $links[get_string('pilotage', 'mod_stage')] = new moodle_url('/mod/stage/dashboard.php', ['id' => $cm->id]);
    }
    // Vue par thématique : ouverte à la DEVE et à tout enseignant responsable d'au moins une
    // thématique, y compris s'il n'est référent d'aucun étudiant. La requête n'est faite que pour
    // les enseignants (seuls affectables comme responsables), et pas à chaque page d'un étudiant.
    if (has_capability('mod/stage:viewall', $context)
            || (has_capability('mod/stage:evaluateteacher', $context)
                && stage_get_teacher_themes($cm->instance, $USER->id))) {
        $links[get_string('mythemestages', 'mod_stage')] =
            new moodle_url('/mod/stage/theme_stages.php', ['id' => $cm->id]);
    }
    if (has_capability('mod/stage:viewall', $context)) {
        $links[get_string('exportexcel', 'mod_stage')] = new moodle_url('/mod/stage/export.php', ['id' => $cm->id]);
        $links[get_string('promotionreportpdf', 'mod_stage')] =
            new moodle_url('/mod/stage/export_pdf.php', ['id' => $cm->id]);
    }
    if (has_capability('mod/stage:managethemes', $context) || has_capability('mod/stage:manageteachers', $context)) {
        $links[get_string('administration', 'mod_stage')] =
            new moodle_url('/mod/stage/administration.php', ['id' => $cm->id]);
    }

    if (empty($links)) {
        return '';
    }
    return html_writer::div(stage_render_actions($links, 'btn btn-secondary mr-1 mb-1'), 'stage-navlinks mb-3');
}

/**
 * Badge de statut « Complété » / « À compléter », utilisé partout dans le résumé de l'étudiant.
 *
 * @param bool $done
 * @return string HTML
 */
function stage_render_status_badge($done) {
    return $done
        ? html_writer::span(get_string('themedone', 'mod_stage'), 'badge badge-success')
        : html_writer::span(get_string('themetodo', 'mod_stage'), 'badge badge-warning');
}

/**
 * Construit les cellules « requis / retenu / reste à faire / statut » communes à tous les bilans
 * du résumé de l'étudiant (par thématique, par année, mobilité internationale).
 *
 * Le reste à faire est la donnée la plus utile à l'étudiant : elle évite d'avoir à soustraire
 * mentalement le retenu du requis sur chaque ligne. Sans exigence chiffrée, les trois colonnes
 * chiffrées n'ont pas de sens : seule la durée retenue est affichée.
 *
 * @param int $retained Durée retenue (validée DEVE).
 * @param int $required Durée requise, 0 si aucune exigence.
 * @param bool $done
 * @return array Quatre cellules : requis, retenu, reste, statut.
 */
function stage_render_progress_cells($retained, $required, $done) {
    if ($required <= 0) {
        return ['-', $retained, '-', '-'];
    }

    return [
        $required,
        $retained,
        $done ? '-' : ($required - $retained),
        stage_render_status_badge($done),
    ];
}

/**
 * En-têtes de colonnes communs aux tableaux de bilan du résumé de l'étudiant, à faire suivre des
 * cellules construites par stage_render_progress_cells().
 *
 * @return array
 */
function stage_progress_table_head() {
    return [
        get_string('requiredduration', 'mod_stage'),
        get_string('retainedduration', 'mod_stage'),
        get_string('remainingduration', 'mod_stage'),
        get_string('status', 'mod_stage'),
    ];
}

/**
 * Rend les actions de gestion possibles sur une saisie donnée, pour la DEVE ou l'enseignant
 * référent, dans la liste des stages du résumé d'un étudiant.
 *
 * Chaque action n'est proposée que si l'utilisateur a le droit correspondant ET que la saisie est
 * dans un état où elle a un sens : la page cible refuserait de toute façon, et proposer un bouton
 * qui mène à un refus ou à une page sans objet est pire que de ne rien proposer. Les droits sont
 * calculés une fois par étudiant par l'appelant (voir stage_print_student_dashboard()) plutôt
 * qu'ici, pour ne pas rejouer les mêmes requêtes à chaque ligne du tableau.
 *
 * @param stdClass $entry
 * @param stdClass $cm Course module.
 * @param context $context Contexte du module stage.
 * @param stdClass $rights Droits de l'utilisateur courant sur cet étudiant, tels que calculés par
 *                          stage_print_student_dashboard() : {register, validatedeve,
 *                          assignedteacher, viewdetail} (booléens).
 * @return string HTML des boutons d'action, chaîne vide s'il n'y en a aucun.
 */
function stage_render_entry_management_actions(stdClass $entry, stdClass $cm, context $context, stdClass $rights) {
    global $PAGE;

    $status = (int) $entry->status;
    $conventionstatus = (int) $entry->conventionstatus;
    $out = '';

    // Ce qui attend une action de l'utilisateur : mis en avant, c'est ce pour quoi il ouvre cette
    // page. Chaque état n'en ouvre qu'une seule. Le retour ramène sur la page courante (tableau de
    // pilotage, résumé d'un étudiant...), et non sur la liste de teacher.php/deve.php dont on ne
    // venait pas forcément.
    $out .= stage_render_actions([
        get_string('evaluate', 'mod_stage') =>
            $rights->assignedteacher && $status === STAGE_STATUS_EVAL_ETUDIANT
                ? new moodle_url('/mod/stage/teacher.php', ['id' => $cm->id, 'entryid' => $entry->id,
                    'returnurl' => $PAGE->url->out_as_local_url(false)]) : null,
        get_string('conventionteachervalidate', 'mod_stage') =>
            $rights->assignedteacher && $conventionstatus === STAGE_CONVENTION_TEACHERPENDING
                ? new moodle_url('/mod/stage/convention_teacher_validate.php', ['id' => $cm->id, 'entryid' => $entry->id,
                    'returnurl' => $PAGE->url->out_as_local_url(false)]) : null,
        get_string('validate', 'mod_stage') =>
            $rights->validatedeve && $status === STAGE_STATUS_EVAL_ENSEIGNANT
                ? new moodle_url('/mod/stage/deve.php', ['id' => $cm->id, 'entryid' => $entry->id,
                    'returnurl' => $PAGE->url->out_as_local_url(false)]) : null,
        get_string('conventionreview', 'mod_stage') =>
            $rights->register && $conventionstatus === STAGE_CONVENTION_REQUESTED
                ? new moodle_url('/mod/stage/convention_review.php', ['id' => $cm->id, 'entryid' => $entry->id,
                    'returnurl' => $PAGE->url->out_as_local_url(false)]) : null,
        get_string('conventionmarksigned', 'mod_stage') =>
            $rights->register && $conventionstatus === STAGE_CONVENTION_EDITED
                ? new moodle_url('/mod/stage/convention_sign.php', ['id' => $cm->id, 'entryid' => $entry->id,
                    'returnurl' => $PAGE->url->out_as_local_url(false)]) : null,
    ], 'btn btn-sm btn-primary mr-1 mb-1', '');

    // Actions disponibles en permanence : consultation et retouches. Le retour ramène sur la page
    // courante (tableau de pilotage, résumé d'un étudiant...), et non sur une liste fixe dont on
    // ne venait pas forcément (voir aussi generateconvention/viewconvention ci-dessous).
    $out .= stage_render_actions([
        get_string('viewdetails', 'mod_stage') => $rights->viewdetail
            ? new moodle_url('/mod/stage/entrydetail.php', ['id' => $cm->id, 'entryid' => $entry->id,
                'returnurl' => $PAGE->url->out_as_local_url(false)]) : null,
        get_string('edit') => $rights->register
            ? new moodle_url('/mod/stage/register.php', ['id' => $cm->id, 'mode' => 'single', 'entryid' => $entry->id,
                'returnurl' => $PAGE->url->out_as_local_url(false)]) : null,
        get_string('generateconvention', 'mod_stage') =>
            $rights->register && $conventionstatus >= STAGE_CONVENTION_EDITED
                ? new moodle_url('/mod/stage/convention.php', ['id' => $cm->id, 'entryid' => $entry->id,
                    'returnurl' => $PAGE->url->out_as_local_url(false)]) : null,
        // L'enseignant référent (sans le droit d'enregistrement des stages) ne fait que consulter
        // la convention déjà éditée par la DEVE, tant qu'elle n'est pas encore signée : au-delà,
        // c'est la convention signée ci-dessous qui prend le relais (voir convention.php).
        get_string('viewconvention', 'mod_stage') =>
            !$rights->register && $rights->assignedteacher && $conventionstatus === STAGE_CONVENTION_EDITED
                ? new moodle_url('/mod/stage/convention.php', ['id' => $cm->id, 'entryid' => $entry->id,
                    'returnurl' => $PAGE->url->out_as_local_url(false)]) : null,
        // Comme convention_signed.php, la convention signée est ouverte à la DEVE et à
        // l'enseignant référent de l'étudiant, mais pas à un simple droit de lecture.
        get_string('downloadsignedconvention', 'mod_stage') =>
            ($rights->register || $rights->assignedteacher)
                && $conventionstatus === STAGE_CONVENTION_SIGNED
                && stage_get_signed_convention_file($context, $entry->id)
                    ? new moodle_url('/mod/stage/convention_signed.php', ['id' => $cm->id, 'entryid' => $entry->id])
                    : null,
    ], 'btn btn-sm btn-secondary mr-1 mb-1', '');

    // Réinitialiser et annuler défont un travail déjà fait : en rouge et en dernier, comme dans la
    // liste de register.php, pour ne pas être cliquées à la place de « Modifier ». Le retour ramène
    // sur la page courante, comme les autres actions ci-dessus.
    if ($rights->register && $status !== STAGE_STATUS_ENREGISTRE) {
        $out .= html_writer::link(
            new moodle_url('/mod/stage/register.php', ['id' => $cm->id, 'mode' => 'reset', 'entryid' => $entry->id,
                'sesskey' => sesskey(), 'returnurl' => $PAGE->url->out_as_local_url(false)]),
            get_string('resetentry', 'mod_stage'), [
                'class' => 'btn btn-sm btn-outline-danger mr-1 mb-1',
                'onclick' => "return confirm('" . get_string('confirmresetentry', 'mod_stage') . "');",
            ]);
    }
    if ($rights->register && $status !== STAGE_STATUS_ANNULE) {
        $out .= html_writer::link(
            new moodle_url('/mod/stage/cancel_entry.php', ['id' => $cm->id, 'entryid' => $entry->id,
                'returnurl' => $PAGE->url->out_as_local_url(false)]),
            get_string('cancelentry', 'mod_stage'), ['class' => 'btn btn-sm btn-outline-danger mr-1 mb-1']);
    }

    return $out;
}

/**
 * Affiche l'avancement d'un étudiant (thématiques obligatoires et liste de ses saisies).
 * Utilisé par la page de l'étudiant lui-même (avec lien de saisie de l'auto-évaluation, si
 * $cm est fourni) et par le tableau de pilotage de la DEVE et des enseignants référents.
 *
 * L'information est présentée du général au particulier, en cinq sections : la synthèse (chiffres
 * clés du dossier), le bilan par année d'étude, le bilan par thématique obligatoire, l'obligation
 * de mobilité internationale, puis le détail de chaque stage saisi. Les bilans par année et par
 * thématique tiennent chacun dans un seul tableau (l'année ou la plage d'années est une colonne,
 * non un titre de section) et affichent le reste à faire plutôt que d'obliger l'étudiant à le
 * calculer lui-même.
 *
 * La colonne d'actions de la liste des stages est déduite des droits de l'utilisateur courant sur
 * l'étudiant affiché (voir stage_render_entry_management_actions()) : la DEVE et l'enseignant
 * référent peuvent agir directement sur chaque stage depuis cette page, sans repasser par les
 * listes de register.php, deve.php ou teacher.php.
 *
 * @param stdClass $stage
 * @param int $userid
 * @param stdClass|null $cm Course module, pour afficher les liens d'action.
 * @param bool $selfevallink Affiche les actions de l'étudiant sur ses propres stages (demande de
 *                            convention, auto-évaluation) : page de l'étudiant lui-même uniquement.
 * @param bool $detaillink Conservé pour la compatibilité des appels : l'accès au détail en lecture
 *                          seule de chaque saisie découle désormais des droits de l'utilisateur,
 *                          comme les autres actions de gestion.
 * @return void
 */
function stage_print_student_dashboard(stdClass $stage, $userid, $cm = null, $selfevallink = false, $detaillink = false) {
    global $OUTPUT, $PAGE, $USER;

    $progress = stage_get_student_progress($stage->id, $userid);
    $mandatorythemes = array_filter($progress->themes, function($t) {
        return $t->theme->mandatory;
    });
    // Bilan des objectifs par année d'étude (hors stages complémentaires) : les objectifs d'une
    // année sont atteints si sa durée totale, la durée de chacune de ses thématiques obligatoires
    // ET, à l'année avant laquelle elle est due le cas échéant, l'obligation de mobilité
    // internationale sont validées (voir stage_get_student_year_progress()).
    $yearprogress = stage_get_student_year_progress($stage->id, $userid);
    // Seules l'année d'étude courante et les précédentes sont présentées : les objectifs des
    // années suivantes ne sont pas encore exigibles, les afficher comme « à compléter » ne ferait
    // qu'alarmer inutilement l'étudiant. Tant que l'année courante n'est pas renseignée pour ce
    // stage (voir mod_form.php), toutes les années restent affichées.
    if (!empty($stage->currentstudyyear)) {
        $yearprogress = array_filter($yearprogress, function($row) use ($stage) {
            return $row->studyyear <= $stage->currentstudyyear;
        });
    }
    // Bilan de l'obligation de mobilité internationale, commune à tous les stages (pas liée à une
    // thématique), avec l'année avant laquelle elle doit être satisfaite le cas échéant.
    $abroadprogress = stage_get_student_abroad_progress($stage->id, $userid);
    // Total des stages complémentaires (EP), à part : ne compte pas dans le décompte obligatoire.
    $complementary = stage_get_student_complementary_days($stage->id, $userid);

    // 1. Synthèse : les chiffres clés du dossier en tête, pour situer l'avancement d'un coup
    // d'œil avant d'entrer dans le détail. Ils étaient auparavant dispersés en bas de page.
    $themesdonecount = count(array_filter($mandatorythemes, function($t) {
        return $t->done;
    }));
    $yearsdonecount = count(array_filter($yearprogress, function($row) {
        return $row->done;
    }));

    echo $OUTPUT->heading(get_string('summary', 'mod_stage'), 4);
    $summarytable = new html_table();
    $summarytable->head = [get_string('summaryitem', 'mod_stage'), get_string('summaryvalue', 'mod_stage')];
    $summarytable->data[] = [
        get_string('summarytotaldays', 'mod_stage'),
        get_string('retaineddaysonly', 'mod_stage', $progress->totalretained),
    ];
    if (!empty($yearprogress)) {
        $summarytable->data[] = [
            get_string('summaryyearsdone', 'mod_stage'),
            $yearsdonecount . ' / ' . count($yearprogress) . ' '
                . stage_render_status_badge($yearsdonecount === count($yearprogress)),
        ];
    }
    if (!empty($mandatorythemes)) {
        $summarytable->data[] = [
            get_string('summarythemesdone', 'mod_stage'),
            $themesdonecount . ' / ' . count($mandatorythemes) . ' '
                . stage_render_status_badge($themesdonecount === count($mandatorythemes)),
        ];
    }
    if ($abroadprogress->required > 0) {
        $summarytable->data[] = [
            get_string('summaryabroaddays', 'mod_stage'),
            get_string('progressofdays', 'mod_stage',
                (object) ['retained' => $abroadprogress->retained, 'required' => $abroadprogress->required])
                . ' ' . stage_render_status_badge($abroadprogress->done),
        ];
    }
    if ($complementary->total > 0) {
        $summarytable->data[] = [
            get_string('summarycomplementarydays', 'mod_stage'),
            get_string('retaineddaysonly', 'mod_stage', $complementary->total),
        ];
    }
    echo html_writer::table($summarytable);

    // 2. Bilan par année d'étude, en un seul tableau plutôt qu'un tableau et un titre par année :
    // l'année n'est rappelée que sur la première ligne de son groupe, et la colonne « Reste à
    // faire » évite d'avoir à soustraire soi-même le retenu du requis.
    if (!empty($yearprogress)) {
        echo $OUTPUT->heading(get_string('yeartotals', 'mod_stage'), 4);
        $yeartable = new html_table();
        $yeartable->head = array_merge(
            [get_string('studyyear', 'mod_stage'), get_string('objective', 'mod_stage')],
            stage_progress_table_head()
        );
        foreach ($yearprogress as $row) {
            $yearcell = html_writer::tag('strong', stage_studyyear_label($row->studyyear))
                . ' ' . stage_render_status_badge($row->done);
            $yeartable->data[] = array_merge(
                [$yearcell, html_writer::tag('strong', get_string('yeartotalobjective', 'mod_stage'))],
                stage_render_progress_cells($row->retained, $row->required, $row->totaldone)
            );
            foreach ($row->themes as $themerow) {
                $yeartable->data[] = array_merge(
                    ['', format_string($themerow->theme->name)],
                    stage_render_progress_cells($themerow->retained, $themerow->required, $themerow->done)
                );
            }
            if ($row->abroad !== null) {
                $yeartable->data[] = array_merge(
                    ['', get_string('abroadtotal', 'mod_stage')],
                    stage_render_progress_cells($row->abroad->retained, $row->abroad->required, $row->abroad->done)
                );
            }
            // Stages complémentaires (EP) de l'année : ligne à part, purement informative, ne
            // comptant pas dans le décompte des stages obligatoires (voir
            // stage_get_student_complementary_days()).
            if ($row->complementary > 0) {
                $yeartable->data[] = array_merge(
                    ['', html_writer::tag('em', get_string('complementarystages', 'mod_stage'))],
                    stage_render_progress_cells($row->complementary, 0, true)
                );
            }
        }
        echo html_writer::table($yeartable);
    }

    // 3. Bilan par thématique obligatoire, toutes années confondues : complète le bilan par année
    // ci-dessus en montrant aussi les thématiques sur lesquelles rien n'a encore été saisi. Un
    // seul tableau, avec la plage d'années en colonne plutôt qu'un titre par plage, et surtout
    // l'année limite de validation : c'est l'échéance qui compte pour l'étudiant, une thématique
    // bornée à une plage n'étant vérifiée qu'à sa dernière année (voir stage_theme_final_year()).
    echo $OUTPUT->heading(get_string('mandatorythemes', 'mod_stage'), 4);
    if (empty($mandatorythemes)) {
        echo $OUTPUT->notification(get_string('nomandatorythemes', 'mod_stage'), 'info');
    } else {
        $themetable = new html_table();
        $themetable->head = array_merge(
            [
                get_string('theme', 'mod_stage'),
                get_string('studyyear', 'mod_stage'),
                get_string('completebyyear', 'mod_stage'),
            ],
            stage_progress_table_head()
        );
        foreach ($mandatorythemes as $t) {
            $finalyear = stage_theme_final_year($t->theme);
            $themetable->data[] = array_merge(
                [
                    format_string($t->theme->name),
                    stage_studyyear_range_label($t->theme->minstudyyear, $t->theme->maxstudyyear),
                    $finalyear !== null ? stage_studyyear_label($finalyear) : '-',
                ],
                stage_render_progress_cells($t->retained, $t->requiredduration, $t->done)
            );
        }
        echo html_writer::table($themetable);
    }

    // 4. Obligation de mobilité internationale : rappelée à part, avec la consigne de la DEVE et
    // l'année avant laquelle elle doit être satisfaite (elle figure aussi dans le bilan de cette
    // année-là ci-dessus, comme condition de sa validation).
    if ($abroadprogress->required > 0) {
        echo $OUTPUT->heading(get_string('abroadtotal', 'mod_stage'), 4);
        if (!empty($stage->abroadrule)) {
            echo html_writer::tag('p', format_text($stage->abroadrule, FORMAT_PLAIN), ['class' => 'text-muted']);
        }
        $abroadtable = new html_table();
        $abroadtable->head = array_merge(
            [get_string('abroadbeforeyear', 'mod_stage')],
            stage_progress_table_head()
        );
        $abroadtable->data[] = array_merge(
            [$abroadprogress->beforeyear > 0 ? stage_studyyear_label($abroadprogress->beforeyear) : '-'],
            stage_render_progress_cells($abroadprogress->retained, $abroadprogress->required, $abroadprogress->done)
        );
        echo html_writer::table($abroadtable);
    }

    // 5. Détail de chaque stage saisi.
    echo $OUTPUT->heading(get_string('allmystages', 'mod_stage'), 4);
    $themes = stage_get_themes($stage->id);
    $entries = stage_get_student_entries($stage->id, $userid);

    // Droits de l'utilisateur courant sur les stages de cet étudiant, calculés une seule fois
    // ici : l'attribution d'un enseignant référent porte sur l'étudiant, pas sur chaque saisie,
    // et la rejouer à chaque ligne du tableau multiplierait les requêtes pour rien.
    $context = $cm ? context_module::instance($cm->id) : null;
    $rights = (object) [
        'register' => false, 'validatedeve' => false, 'assignedteacher' => false, 'viewdetail' => false,
    ];
    if ($context) {
        $rights->register = has_capability('mod/stage:registerstages', $context);
        $rights->validatedeve = has_capability('mod/stage:validatedeve', $context);
        $rights->assignedteacher = has_capability('mod/stage:evaluateteacher', $context)
            && array_key_exists($userid, stage_get_assigned_students($stage->id, $USER->id));
        // Même condition que entrydetail.php : ni la capacité d'enregistrer les stages ni celle
        // de les valider n'y donnent accès à elles seules.
        $rights->viewdetail = has_capability('mod/stage:viewall', $context) || $rights->assignedteacher;
    }
    $canmanage = $rights->register || $rights->validatedeve || $rights->assignedteacher || $rights->viewdetail;
    $showactions = $cm && ($selfevallink || $detaillink || $canmanage);

    $table = new html_table();
    $table->head = [
        get_string('theme', 'mod_stage'),
        get_string('studyyear', 'mod_stage'),
        get_string('structure', 'mod_stage'),
        get_string('abroad', 'mod_stage'),
        get_string('declaredduration', 'mod_stage'),
        get_string('retainedduration', 'mod_stage'),
        get_string('status', 'mod_stage'),
        get_string('conventionstatus', 'mod_stage'),
    ];
    if ($showactions) {
        $table->head[] = get_string('actions', 'mod_stage');
    }
    // Une ligne sur fond distinct pour chaque stage à l'étranger, pour les repérer d'un coup
    // d'œil dans la liste (voir aussi la colonne dédiée, avec le pays s'il est renseigné).
    $table->rowclasses = [];
    foreach ($entries as $entry) {
        $theme = $themes[$entry->themeid] ?? null;
        $themename = $theme ? format_string($theme->name) : '-';
        $abroadcell = !empty($entry->abroad)
            ? html_writer::span('🌍 ' . ($entry->country !== '' ? s($entry->country) : get_string('abroad', 'mod_stage')),
                'badge badge-info')
            : '-';
        $table->rowclasses[] = !empty($entry->abroad) ? 'table-info' : '';
        $badge = html_writer::span(stage_status_label($entry->status), 'badge ' . stage_status_badgeclass($entry->status));
        $conventionbadge = html_writer::span(stage_convention_status_label($entry->conventionstatus),
            'badge ' . stage_convention_status_badgeclass($entry->conventionstatus));
        $row = [
            $themename,
            stage_studyyear_label($entry->studyyear),
            $entry->structure,
            $abroadcell,
            $entry->declaredduration,
            $entry->retainedduration,
            $badge,
            $conventionbadge,
        ];
        if ($showactions) {
            // Les actions sont rendues en petits boutons plutôt qu'en liens séparés par des
            // barres verticales, pour rester lisibles quand il y en a plusieurs.
            $btn = ['class' => 'btn btn-sm btn-secondary mr-1 mb-1'];
            $actions = '';
            if ($selfevallink) {
                if ((int) $entry->conventionstatus === STAGE_CONVENTION_NONE
                        || (int) $entry->conventionstatus === STAGE_CONVENTION_REJECTED) {
                    $actions .= html_writer::link(
                        new moodle_url('/mod/stage/student_register.php', ['id' => $cm->id, 'entryid' => $entry->id]),
                        get_string('requestconvention', 'mod_stage'), $btn
                    );
                } else if (stage_convention_is_signed($entry->conventionstatus)) {
                    // Avant le début du stage, l'auto-évaluation est refusée par entry.php : la
                    // date de début est annoncée à la place du bouton, un bouton qui mène à un
                    // refus étant pire que pas de bouton du tout.
                    $actions .= stage_entry_not_started_yet($entry)
                        ? html_writer::span(get_string('selfevalfrom', 'mod_stage',
                            userdate($entry->datestart, get_string('strftimedate', 'langconfig'))),
                            'text-muted mr-1 mb-1 d-inline-block')
                        : html_writer::link(
                            new moodle_url('/mod/stage/entry.php', ['id' => $cm->id, 'entryid' => $entry->id]),
                            get_string('selfeval', 'mod_stage'), $btn
                        );
                    // Le PDF de la convention signée n'existe que pour le circuit de gestion de
                    // convention de ce plugin (STAGE_CONVENTION_SIGNED), et seulement si la DEVE
                    // en a effectivement téléversé un (facultatif, voir convention_sign.php) : les
                    // stages enregistrés en masse (SignVet) n'en ont jamais.
                    if ((int) $entry->conventionstatus === STAGE_CONVENTION_SIGNED
                            && stage_get_signed_convention_file($context, $entry->id)) {
                        $actions .= html_writer::link(
                            new moodle_url('/mod/stage/convention_signed.php', ['id' => $cm->id, 'entryid' => $entry->id]),
                            get_string('downloadsignedconvention', 'mod_stage'), $btn
                        );
                    }
                } else if ((int) $entry->conventionstatus === STAGE_CONVENTION_EDITED) {
                    // Convention éditée par la DEVE, prête à être imprimée et signée : l'étudiant
                    // peut déjà la consulter/télécharger, avant même sa signature.
                    $actions .= html_writer::link(
                        new moodle_url('/mod/stage/convention.php', ['id' => $cm->id, 'entryid' => $entry->id,
                            'returnurl' => $PAGE->url->out_as_local_url(false)]),
                        get_string('viewconvention', 'mod_stage'), $btn
                    );
                }
                // Convention seulement demandée (pas encore éditée par la DEVE) : rien à faire côté
                // étudiant pour l'instant, le badge de statut ci-dessus suffit à le renseigner.
            }
            // Actions de gestion de la DEVE et de l'enseignant référent : évaluer, valider,
            // relire la convention, modifier, annuler... selon leurs droits et l'état de la
            // saisie. Elles évitent d'avoir à repasser par les listes de register.php, deve.php
            // ou teacher.php pour agir sur le stage qu'on est justement en train de consulter.
            if ($canmanage) {
                $actions .= stage_render_entry_management_actions($entry, $cm, $context, $rights);
            }
            $row[] = $actions !== '' ? $actions : '-';
        }
        $table->data[] = $row;
    }
    if (empty($table->data)) {
        echo $OUTPUT->notification(get_string('nostages', 'mod_stage'), 'info');
    } else {
        echo html_writer::table($table);
    }

    // Les totaux (durée retenue, stages complémentaires) sont désormais présentés dans la
    // synthèse en tête de page : inutile de les répéter ici.
}

/**
 * Retourne les informations de l'établissement d'enseignement (VetAgro Sup) affichées sur la
 * page 1 de la convention, éditables par la DEVE (voir convention_templates.php). Une valeur par
 * défaut ("VetAgro Sup", pas d'autre coordonnée) est utilisée tant que la DEVE n'a rien renseigné.
 *
 * @param stdClass $stage
 * @return stdClass {name, address, representative, representativetitle, phone, email, signatory}
 */
function stage_get_establishment_info(stdClass $stage) {
    return (object) [
        'name' => $stage->establishmentname ?: 'VetAgro Sup',
        'address' => $stage->establishmentaddress ?? '',
        'representative' => $stage->establishmentrepresentative ?? '',
        'representativetitle' => $stage->establishmentrepresentativetitle ?? '',
        'phone' => $stage->establishmentphone ?? '',
        'email' => $stage->establishmentemail ?? '',
        'signatory' => $stage->establishmentsignatory ?? '',
    ];
}

/**
 * Enregistre les informations de l'établissement d'enseignement (VetAgro Sup) affichées sur la
 * page 1 de la convention.
 *
 * @param int $stageid
 * @param stdClass $data {establishmentname, establishmentaddress, establishmentrepresentative,
 *                        establishmentrepresentativetitle, establishmentphone, establishmentemail,
 *                        establishmentsignatory}
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
        'establishmentsignatory' => $data->establishmentsignatory,
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
 * Liste les autres instances de mod_stage vers lesquelles l'utilisateur courant peut transférer
 * un étudiant et ses stages (voir transfer.php) : celles où il/elle a la capacité d'enregistrer
 * les stages, sans laquelle il ne pourrait de toute façon rien y gérer ensuite.
 *
 * @param int $excludestageid Instance courante, à exclure de la liste.
 * @return array int (stageid) => string (libellé "Cours - Activité")
 */
function stage_get_transfer_target_instances($excludestageid) {
    global $DB;

    $options = [];
    $stages = $DB->get_records_select('stage', 'id != :id', ['id' => $excludestageid], 'id ASC');
    foreach ($stages as $otherstage) {
        $cm = get_coursemodule_from_instance('stage', $otherstage->id, 0, false, IGNORE_MISSING);
        if (!$cm) {
            continue;
        }
        if (!has_capability('mod/stage:registerstages', context_module::instance($cm->id))) {
            continue;
        }
        $course = get_course($cm->course);
        $options[$otherstage->id] = format_string($course->fullname) . ' - ' . format_string($otherstage->name);
    }
    return $options;
}

/**
 * Prépare le transfert d'un étudiant et de ses stages vers une autre instance de l'activité
 * (généralement dans un autre cours) : établit la correspondance des références propres à
 * l'instance source, et relève ce qui empêche ou complique le transfert.
 *
 * Les stages sont déplacés, non copiés : leurs identifiants sont conservés, si bien que tout ce
 * qui y est rattaché (plages, jours de stage, détails de convention, réponses) suit sans être
 * recopié. Doivent en revanche être retraduites les références qui appartiennent à l'instance
 * source et n'ont pas le même identifiant dans la cible : thématique, gabarit de convention, et
 * questions auxquelles répondent les évaluations. Le rapprochement se fait sur le nom, normalisé
 * comme à l'import StageVet (voir stage_normalize_name()).
 *
 * Rien n'est modifié ici : la fonction ne fait que calculer le plan, que transfer.php montre à la
 * DEVE avant confirmation et que stage_execute_student_transfer() applique tel quel.
 *
 * @param stdClass $sourcestage
 * @param stdClass $targetstage
 * @param int $userid Étudiant à transférer.
 * @return stdClass {entries, thememap, templatemap, questionmap, unmatchedthemes,
 *                  unmatchedtemplates, droppedanswers, referentteachers, blockers, warnings}
 */
function stage_plan_student_transfer(stdClass $sourcestage, stdClass $targetstage, $userid) {
    global $DB;

    $plan = (object) [
        'entries' => stage_get_student_entries($sourcestage->id, $userid),
        'thememap' => [],
        'templatemap' => [],
        'questionmap' => [],
        'unmatchedthemes' => [],
        'unmatchedtemplates' => [],
        'droppedanswers' => 0,
        'reportfiles' => 0,
        'referentteachers' => [],
        'blockers' => [],
        'warnings' => [],
    ];

    if (empty($plan->entries)) {
        $plan->blockers[] = get_string('transfernoentries', 'mod_stage');
        return $plan;
    }

    // L'étudiant doit être inscrit au cours cible : les tableaux de bord et le bilan de promotion
    // parcourent les inscrits, des stages rattachés à un non-inscrit n'y apparaîtraient nulle part.
    $targetcm = get_coursemodule_from_instance('stage', $targetstage->id, 0, false, MUST_EXIST);
    $targetcontext = context_module::instance($targetcm->id);
    $targetcourse = get_course($targetcm->course);
    if (!is_enrolled($targetcontext, $userid)) {
        $plan->blockers[] = get_string('transfernotenrolled', 'mod_stage', format_string($targetcourse->fullname));
    }

    // Thématiques : rapprochées par nom. Sans correspondance, le stage perdrait son rattachement
    // et fausserait le bilan de l'étudiant dans la cible : le transfert est refusé plutôt que
    // d'être fait à moitié.
    $targetthemesbyname = [];
    foreach (stage_get_themes($targetstage->id) as $targettheme) {
        $targetthemesbyname[stage_normalize_name($targettheme->name)] = $targettheme;
    }
    $sourcethemes = stage_get_themes($sourcestage->id);
    foreach ($plan->entries as $entry) {
        if (array_key_exists($entry->themeid, $plan->thememap)) {
            continue;
        }
        $sourcetheme = $sourcethemes[$entry->themeid] ?? null;
        $match = $sourcetheme ? ($targetthemesbyname[stage_normalize_name($sourcetheme->name)] ?? null) : null;
        $plan->thememap[$entry->themeid] = $match ? $match->id : null;
        if (!$match) {
            $plan->unmatchedthemes[] = $sourcetheme ? format_string($sourcetheme->name) : (string) $entry->themeid;
        }
    }
    if (!empty($plan->unmatchedthemes)) {
        $plan->blockers[] = get_string('transferunmatchedthemes', 'mod_stage',
            implode(', ', $plan->unmatchedthemes));
    }

    // Gabarits de convention : rapprochés par nom et langue. Sans correspondance, le gabarit est
    // simplement oublié — la convention déjà signée reste disponible, seule sa regénération en PDF
    // ne serait plus possible sans rechoisir un gabarit.
    $targettemplatesbyname = [];
    foreach (stage_get_convention_templates($targetstage->id) as $targettemplate) {
        $targettemplatesbyname[stage_normalize_name($targettemplate->name) . '|' . $targettemplate->lang] =
            $targettemplate;
    }
    $sourcetemplates = stage_get_convention_templates($sourcestage->id);
    foreach ($plan->entries as $entry) {
        if (empty($entry->conventiontemplateid) || array_key_exists($entry->conventiontemplateid, $plan->templatemap)) {
            continue;
        }
        $sourcetemplate = $sourcetemplates[$entry->conventiontemplateid] ?? null;
        $key = $sourcetemplate
            ? stage_normalize_name($sourcetemplate->name) . '|' . $sourcetemplate->lang : null;
        $match = $key !== null ? ($targettemplatesbyname[$key] ?? null) : null;
        $plan->templatemap[$entry->conventiontemplateid] = $match ? $match->id : null;
        if (!$match) {
            $plan->unmatchedtemplates[] = $sourcetemplate
                ? format_string($sourcetemplate->name) : (string) $entry->conventiontemplateid;
        }
    }
    if (!empty($plan->unmatchedtemplates)) {
        $plan->warnings[] = get_string('transferunmatchedtemplates', 'mod_stage',
            implode(', ', array_unique($plan->unmatchedtemplates)));
    }

    // Questions d'évaluation : les réponses déjà saisies pointent vers les questions de la
    // thématique source. Elles sont rapprochées, dans la thématique cible correspondante, sur le
    // type d'évaluation et le nom ; celles qui n'ont pas d'équivalent sont supprimées, faute de
    // quoi elles renverraient à des questions d'une autre instance.
    foreach ($plan->thememap as $sourcethemeid => $targetthemeid) {
        if (!$targetthemeid) {
            continue;
        }
        // Les trois types d'évaluation, le maître de stage compris : ses réponses sont des
        // réponses comme les autres et seraient sinon supprimées faute d'équivalent trouvé.
        foreach (['student', 'teacher', 'tutor'] as $evaltype) {
            $targetquestions = [];
            foreach (stage_get_questions($targetthemeid, $evaltype) as $targetquestion) {
                $targetquestions[stage_normalize_name($targetquestion->name)] = $targetquestion;
            }
            foreach (stage_get_questions($sourcethemeid, $evaltype) as $sourcequestion) {
                $match = $targetquestions[stage_normalize_name($sourcequestion->name)] ?? null;
                $plan->questionmap[$sourcequestion->id] = $match ? $match->id : null;
            }
        }
    }
    $entryids = array_keys($plan->entries);
    if (!empty($entryids)) {
        [$insql, $inparams] = $DB->get_in_or_equal($entryids, SQL_PARAMS_NAMED);
        foreach ($DB->get_records_select('stage_answer', "entryid $insql", $inparams) as $answer) {
            if (empty($plan->questionmap[$answer->questionid])) {
                $plan->droppedanswers++;
            }
        }
    }
    if ($plan->droppedanswers > 0) {
        $plan->warnings[] = get_string('transferdroppedanswers', 'mod_stage', $plan->droppedanswers);
    }

    // Rapports de stage déposés : ils suivent la saisie (leur zone de fichiers est indexée sur
    // l'identifiant de la saisie) mais changent de contexte de module, ce que fait
    // stage_execute_student_transfer(). Leur nombre est annoncé au récapitulatif, le transfert
    // n'étant pas réversible.
    $sourcecm = get_coursemodule_from_instance('stage', $sourcestage->id, 0, false, MUST_EXIST);
    $sourcecontext = context_module::instance($sourcecm->id);
    foreach ($plan->entries as $entry) {
        $plan->reportfiles += count(stage_get_report_files($sourcecontext, $entry->id));
    }

    // L'attribution des enseignants référents est propre au cours : elle n'est pas transférée,
    // les enseignants de la source n'étant en général pas inscrits au cours cible.
    $plan->referentteachers = array_map('fullname', stage_get_student_teachers($sourcestage->id, $userid));
    if (!empty($plan->referentteachers)) {
        $plan->warnings[] = get_string('transferreferentteachers', 'mod_stage',
            implode(', ', $plan->referentteachers));
    }

    return $plan;
}

/**
 * Exécute le transfert préparé par stage_plan_student_transfer() : rattache les stages de
 * l'étudiant à l'instance cible, retraduit les références propres à l'instance et déplace les
 * conventions signées vers le contexte du module cible.
 *
 * Le tout dans une transaction : un transfert à moitié appliqué laisserait des stages rattachés à
 * une instance mais pointant vers les thématiques d'une autre.
 *
 * @param stdClass $sourcestage
 * @param context $sourcecontext Contexte du module source.
 * @param stdClass $targetstage
 * @param context $targetcontext Contexte du module cible.
 * @param int $userid
 * @param stdClass $plan Plan calculé par stage_plan_student_transfer(), sans bloquant.
 * @return int Nombre de stages transférés.
 */
function stage_execute_student_transfer(stdClass $sourcestage, context $sourcecontext, stdClass $targetstage,
        context $targetcontext, $userid, stdClass $plan) {
    global $DB;

    $transaction = $DB->start_delegated_transaction();
    $fs = get_file_storage();
    $now = time();

    foreach ($plan->entries as $entry) {
        $update = (object) [
            'id' => $entry->id,
            'stageid' => $targetstage->id,
            'themeid' => $plan->thememap[$entry->themeid],
            'timemodified' => $now,
        ];
        if (!empty($entry->conventiontemplateid)) {
            $update->conventiontemplateid = $plan->templatemap[$entry->conventiontemplateid] ?: null;
        }
        $DB->update_record('stage_entry', $update);

        // Les réponses suivent le stage (même entryid) mais doivent désigner les questions de
        // l'instance cible ; celles sans équivalent sont supprimées.
        foreach ($DB->get_records('stage_answer', ['entryid' => $entry->id]) as $answer) {
            $targetquestionid = $plan->questionmap[$answer->questionid] ?? null;
            if ($targetquestionid) {
                $DB->update_record('stage_answer', (object) [
                    'id' => $answer->id, 'questionid' => $targetquestionid, 'timemodified' => $now,
                ]);
            } else {
                $DB->delete_records('stage_answer', ['id' => $answer->id]);
            }
        }

        // La convention signée et le rapport de stage sont stockés dans le contexte du module :
        // ils deviendraient inaccessibles depuis la cible s'ils restaient dans celui de la source.
        foreach (['signedconvention', STAGE_REPORT_FILEAREA] as $filearea) {
            foreach ($fs->get_area_files($sourcecontext->id, 'mod_stage', $filearea, $entry->id, 'itemid', false)
                    as $file) {
                $fs->create_file_from_storedfile(['contextid' => $targetcontext->id], $file);
                $file->delete();
            }
        }
    }

    // L'attribution des enseignants référents est propre au cours : elle reste dans la source,
    // où elle continue de décrire l'année passée, et n'est pas recréée dans la cible.
    $DB->delete_records('stage_entry_teacher', ['stageid' => $sourcestage->id, 'studentid' => $userid]);

    $transaction->allow_commit();

    return count($plan->entries);
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
        $newthemeid = $DB->insert_record('stage_theme', (object) [
            'stageid' => $targetstageid,
            'name' => $theme->name,
            'description' => $theme->description,
            'mandatory' => $theme->mandatory,
            'requiredduration' => $theme->requiredduration,
            'minstudyyear' => $theme->minstudyyear,
            'maxstudyyear' => $theme->maxstudyyear,
            'sortorder' => $theme->sortorder,
            'visible' => $theme->visible,
            // Les réglages d'évaluation par le maître de stage et de dépôt du rapport font partie
            // de la définition de la thématique : sans eux, la copie repartirait des valeurs par
            // défaut (évaluation réactivée, rapport plus demandé) sans que rien ne le signale.
            // Les enseignants responsables, eux, ne sont pas copiés : comme les enseignants
            // référents, ils sont propres au cours (voir stage_execute_student_transfer()).
            'tutorevaluationenabled' => $theme->tutorevaluationenabled,
            'reportmode' => $theme->reportmode,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        foreach (stage_get_theme_durations($theme->id) as $studyyear => $requiredduration) {
            stage_set_theme_duration($newthemeid, $studyyear, $requiredduration);
        }
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
 * Copie les textes personnalisés des courriels (voir stage_get_email_definitions()) d'une
 * instance source vers une instance cible.
 *
 * Contrairement aux thématiques et aux gabarits, ces textes ne s'ajoutent pas : il n'existe qu'un
 * texte par courriel et par instance. Chaque courriel personnalisé dans la source remplace donc
 * celui de la cible ; ceux que la source n'a pas personnalisés sont laissés tels quels dans la
 * cible plutôt que d'y effacer une personnalisation existante.
 *
 * @param int $sourcestageid
 * @param int $targetstageid
 * @return int Nombre de courriels personnalisés copiés.
 */
function stage_import_email_templates($sourcestageid, $targetstageid) {
    global $DB;

    $definitions = stage_get_email_definitions();
    $copied = 0;
    foreach ($DB->get_records('stage_email_template', ['stageid' => $sourcestageid]) as $template) {
        // Une clé inconnue est ignorée : elle proviendrait d'une version du plugin définissant un
        // courriel que celle-ci ne connaît pas, et n'aurait aucun effet dans la cible.
        if (!isset($definitions[$template->emailkey])) {
            continue;
        }
        // Une ligne source vide des deux côtés ne personnalise rien : la recopier effacerait la
        // personnalisation de la cible (stage_save_email_template() supprime alors la ligne),
        // c'est-à-dire l'inverse de ce que l'import annonce.
        if (trim((string) $template->subject) === '' && trim((string) $template->body) === '') {
            continue;
        }
        stage_save_email_template($targetstageid, $template->emailkey, (string) $template->subject,
            (string) $template->body);
        $copied++;
    }

    return $copied;
}

/**
 * Importe, selon les options choisies, les thématiques, gabarits de convention, logos, textes
 * de courriels et/ou informations d'établissement d'une autre instance de mod_stage vers
 * l'instance courante (voir administration_import.php). L'appelant est responsable de vérifier au
 * préalable que l'utilisateur a la capacité de gérer les thématiques sur les deux instances.
 *
 * @param stdClass $sourcestage
 * @param context $sourcecontext
 * @param stdClass $targetstage
 * @param context $targetcontext
 * @param array $options ['themes' => bool, 'templates' => bool, 'logos' => bool, 'emails' => bool,
 *                        'establishment' => bool]
 * @return stdClass Résumé : {themes: int, templates: int, logos: int, emails: int,
 *                  establishment: bool}
 */
function stage_import_from_stage(stdClass $sourcestage, context $sourcecontext, stdClass $targetstage,
        context $targetcontext, array $options) {
    $result = (object) ['themes' => 0, 'templates' => 0, 'logos' => 0, 'emails' => 0, 'establishment' => false];

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
    if (!empty($options['emails'])) {
        $result->emails = stage_import_email_templates($sourcestage->id, $targetstage->id);
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
 * Définit le type (obligatoire ou complémentaire) d'une saisie de stage, à l'initiative de la
 * DEVE lors de son enregistrement ou de son édition (voir register.php). Crée un enregistrement
 * minimal dans stage_convention_detail si la saisie n'en a pas encore (cas courant d'un stage créé
 * directement par la DEVE, sans demande de convention par l'étudiant), sans toucher aux autres
 * champs s'il en existe déjà un.
 *
 * @param int $entryid
 * @param string $stagetype 'obligatoire' ou 'complementaire'
 * @return void
 */
function stage_set_entry_stagetype($entryid, $stagetype) {
    global $DB;

    $existing = stage_get_convention_detail($entryid);
    if ($existing) {
        if ($existing->stagetype === $stagetype) {
            return;
        }
        $existing->stagetype = $stagetype;
        $existing->timemodified = time();
        $DB->update_record('stage_convention_detail', $existing);
    } else {
        $DB->insert_record('stage_convention_detail', (object) [
            'entryid' => $entryid,
            'stagetype' => $stagetype,
            'yearsituation' => 'normal',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }
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
 * Retourne le type (obligatoire ou complementaire) de chaque saisie, indexé par id de saisie.
 * Une saisie sans information de convention (par exemple enregistrée directement par la DEVE) est
 * considérée obligatoire par défaut. Les stages complémentaires ne comptent pas dans le bilan des
 * durées obligatoires (par thématique et par année).
 *
 * @param array $entryids
 * @return array int => 'obligatoire'|'complementaire'
 */
function stage_get_entry_stagetypes(array $entryids) {
    global $DB;

    $stagetypes = [];
    foreach ($entryids as $entryid) {
        $stagetypes[$entryid] = 'obligatoire';
    }
    if (empty($entryids)) {
        return $stagetypes;
    }
    [$insql, $inparams] = $DB->get_in_or_equal($entryids);
    $details = $DB->get_records_select('stage_convention_detail', "entryid $insql", $inparams, '', 'entryid, stagetype');
    foreach ($details as $detail) {
        $stagetypes[$detail->entryid] = $detail->stagetype;
    }
    return $stagetypes;
}

/**
 * Calcule, pour un étudiant, la durée retenue (validée DEVE) des stages complémentaires (EP),
 * globale et par année d'étude. Les stages complémentaires ne comptent pas dans le bilan des
 * durées obligatoires (voir stage_get_student_year_progress()), mais sont affichés à part, à
 * titre informatif, dans le bilan par année et le total final de l'étudiant.
 *
 * @param int $stageid
 * @param int $userid
 * @return stdClass {total, byyear} 'byyear' est un tableau année => jours
 */
function stage_get_student_complementary_days($stageid, $userid) {
    $entries = stage_get_student_entries($stageid, $userid);
    $stagetypes = stage_get_entry_stagetypes(array_keys($entries));

    $total = 0;
    $byyear = [];
    foreach ($entries as $entry) {
        if (($stagetypes[$entry->id] ?? 'obligatoire') !== 'complementaire') {
            continue;
        }
        if ($entry->status != STAGE_STATUS_VALIDE_DEVE) {
            continue;
        }
        $year = (int) $entry->studyyear;
        $byyear[$year] = ($byyear[$year] ?? 0) + $entry->retainedduration;
        $total += $entry->retainedduration;
    }

    return (object) ['total' => $total, 'byyear' => $byyear];
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
 * Applique ou retire la dispense de convention d'une saisie de stage, à l'initiative de la DEVE
 * lors de son enregistrement ou de son édition (voir register.php, case à cocher "Dispenser de
 * convention"). N'a d'effet que si aucune demande de convention réelle n'est en cours ou aboutie
 * (statut "aucune", "dispensée" ou "refusée") : ne touche pas au statut d'un stage dont la
 * convention a déjà été demandée, éditée, signée ou signée sur SignVet, pour ne pas écraser
 * silencieusement un circuit de convention en cours.
 *
 * @param stdClass $entry
 * @param bool $exempt
 * @return void
 */
function stage_set_entry_convention_exempt(stdClass $entry, $exempt) {
    global $DB;

    $status = (int) $entry->conventionstatus;
    if (!in_array($status, [STAGE_CONVENTION_NONE, STAGE_CONVENTION_EXEMPT, STAGE_CONVENTION_REJECTED], true)) {
        return;
    }

    $newstatus = $exempt ? STAGE_CONVENTION_EXEMPT : STAGE_CONVENTION_NONE;
    if ($status === $newstatus) {
        return;
    }

    $entry->conventionstatus = $newstatus;
    $entry->timemodified = time();
    $DB->update_record('stage_entry', $entry);
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
    $text = stage_resolve_email_text($stage->id, 'teacherpending', [
        'stage' => format_string($stage->name),
        'url' => $url->out(false),
    ]);
    $noreply = core_user::get_noreply_user();
    foreach ($teachers as $teacher) {
        email_to_user($teacher, $noreply, $text->subject, $text->body);
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

    $url = new moodle_url('/mod/stage/student_register.php', ['id' => $cm->id, 'entryid' => $entry->id]);
    $text = stage_resolve_email_text($stage->id, 'studentrejected', [
        'stage' => format_string($stage->name),
        'comment' => $comment,
        'url' => $url->out(false),
    ]);
    email_to_user($student, core_user::get_noreply_user(), $text->subject, $text->body);
}

/**
 * Saisies dont le stage commence dans les jours qui viennent alors que la convention n'est
 * toujours pas signée, et dont l'étudiant n'a pas encore été relancé.
 *
 * Les stages annulés ou déjà commencés sont exclus (relancer pour un stage commencé n'aiderait
 * plus), ainsi que les conventions signées ou dispensées (voir stage_convention_is_signed()).
 *
 * @param int $days Fenêtre en jours avant le début du stage.
 * @return array Saisies (stage_entry) indexées par id.
 */
function stage_get_entries_needing_convention_reminder($days = STAGE_CONVENTION_REMINDER_DAYS) {
    global $DB;

    // La fenêtre va de maintenant à la fin du jour situé $days plus tard : un stage commençant
    // dans 7 jours doit être relancé quelle que soit l'heure de passage du cron ce jour-là.
    $from = time();
    $until = usergetmidnight($from) + ($days + 1) * DAYSECS - 1;

    [$signedsql, $signedparams] = $DB->get_in_or_equal(
        [STAGE_CONVENTION_SIGNED, STAGE_CONVENTION_SIGNVET, STAGE_CONVENTION_EXEMPT],
        SQL_PARAMS_NAMED, 'cs', false);

    $params = $signedparams + [
        'from' => $from, 'until' => $until, 'cancelled' => STAGE_STATUS_ANNULE,
    ];

    return $DB->get_records_select('stage_entry',
        "datestart > 0 AND datestart >= :from AND datestart <= :until
         AND conventionstatus $signedsql
         AND status <> :cancelled
         AND conventionremindertime IS NULL", $params, 'datestart ASC');
}

/**
 * Envoie à l'étudiant le rappel que son stage commence bientôt alors que sa convention n'est
 * toujours pas signée. Appelée par la tâche planifiée
 * \mod_stage\task\send_convention_reminders ; l'envoi n'est marqué que si le courriel part, pour
 * qu'un échec ponctuel soit retenté au passage suivant.
 *
 * @param stdClass $stage
 * @param stdClass $cm Course module.
 * @param stdClass $entry
 * @param stdClass $student
 * @param stdClass|null $theme Thématique du stage, pour la variable {{theme}}.
 * @return bool True si le courriel est parti.
 */
function stage_notify_student_convention_reminder(stdClass $stage, stdClass $cm, stdClass $entry,
        stdClass $student, ?stdClass $theme) {
    global $DB;

    // Le lien mène là où l'étudiant peut agir : sa demande de convention, à faire ou à corriger.
    // S'il en existe déjà une bien avancée (en cours de validation, signée...), la page redirige
    // simplement vers la vue d'ensemble : ce lien ne fait rien de plus que convention_request.php
    // ne le faisait déjà dans ce cas.
    $url = new moodle_url('/mod/stage/student_register.php', ['id' => $cm->id, 'entryid' => $entry->id]);
    $text = stage_resolve_email_text($stage->id, 'conventionreminder', [
        'student' => fullname($student),
        'stage' => format_string($stage->name),
        'theme' => $theme ? format_string($theme->name) : '',
        'datestart' => userdate($entry->datestart, get_string('strftimedate', 'langconfig')),
        'days' => STAGE_CONVENTION_REMINDER_DAYS,
        'url' => $url->out(false),
    ]);

    if (!email_to_user($student, core_user::get_noreply_user(), $text->subject, $text->body)) {
        return false;
    }

    $DB->set_field('stage_entry', 'conventionremindertime', time(), ['id' => $entry->id]);
    return true;
}

/**
 * Envoie au maître de stage (personne extérieure, sans compte Moodle) le lien à jeton lui
 * permettant de répondre au questionnaire d'évaluation de la thématique.
 *
 * @param stdClass $stage
 * @param stdClass $cm Course module.
 * @param stdClass $entry
 * @param string $tutorname
 * @param string $tutoremail
 * @return void
 */
function stage_notify_tutor_evaluation_request(stdClass $stage, stdClass $cm, stdClass $entry, $tutorname,
        $tutoremail) {
    global $DB;

    $student = $DB->get_record('user', ['id' => $entry->userid]);
    if (!$student) {
        return;
    }

    $lang = stage_get_entry_convention_lang($entry);
    $url = new moodle_url('/mod/stage/tutor_eval.php', ['token' => $entry->tutortoken]);
    $text = stage_resolve_email_text($stage->id, 'tutorrequest', [
        'student' => fullname($student),
        'stage' => format_string($stage->name),
        'url' => $url->out(false),
    ], $lang);

    $names = explode(' ', trim($tutorname), 2);
    $tutoruser = (object) [
        'id' => -1,
        'email' => $tutoremail,
        'firstname' => $names[0] !== '' ? $names[0] : $tutorname,
        'lastname' => $names[1] ?? '',
        'maildisplay' => true,
        'mailformat' => 1,
        'auth' => 'manual',
        'confirmed' => 1,
        'suspended' => 0,
        'deleted' => 0,
        'emailstop' => 0,
        'lang' => $lang,
    ];

    email_to_user($tutoruser, core_user::get_noreply_user(), $text->subject, $text->body);
}

/**
 * Indique si l'évaluation par le maître de stage est active pour une thématique donnée :
 * l'option doit être activée à la fois globalement pour l'activité (notifications.php) et pour
 * cette thématique (themes.php), la seconde n'ayant de sens que si la première l'est aussi.
 *
 * @param stdClass $stage
 * @param stdClass|null $theme Thématique de la saisie concernée ; null (thématique supprimée
 *                              entretemps, par exemple) est traité comme désactivé.
 * @return bool
 */
function stage_tutor_evaluation_enabled(stdClass $stage, ?stdClass $theme) {
    return !empty($stage->tutorevaluationenabled) && $theme !== null && !empty($theme->tutorevaluationenabled);
}

/**
 * Liste les saisies dont le stage a commencé et dont le maître de stage n'a pas encore reçu son
 * invitation à évaluer, filtrée directement en SQL sur les mêmes conditions que
 * stage_maybe_request_tutor_evaluation() (évaluation activée, coordonnées connues) pour ne pas
 * représenter indéfiniment, à chaque passage du cron, les saisies qui n'y sont pas éligibles.
 *
 * @return array stdClass[] Saisies (stage_entry), triées par date de début.
 */
function stage_get_entries_needing_tutor_request() {
    global $DB;

    $sql = "SELECT e.*
              FROM {stage_entry} e
              JOIN {stage} s ON s.id = e.stageid
              JOIN {stage_theme} t ON t.id = e.themeid
              JOIN {stage_convention_detail} d ON d.entryid = e.id
             WHERE e.datestart > 0 AND e.datestart <= :now
               AND e.status <> :cancelled
               AND e.tutortoken IS NULL
               AND s.tutorevaluationenabled = 1
               AND t.tutorevaluationenabled = 1
               AND d.tutoremail IS NOT NULL AND " . $DB->sql_compare_text('d.tutoremail') . " <> ''
          ORDER BY e.datestart ASC";

    return $DB->get_records_sql($sql, ['now' => time(), 'cancelled' => STAGE_STATUS_ANNULE]);
}

/**
 * Si l'évaluation par le maître de stage est activée pour l'activité et le stage a commencé,
 * génère (si besoin) un jeton d'accès et envoie l'invitation par courriel au maître de stage.
 * N'envoie jamais deux fois l'invitation pour une même saisie (un jeton déjà présent est laissé
 * tel quel). Appelée par la tâche planifiée \mod_stage\task\send_tutor_evaluation_requests, qui
 * s'appuie sur stage_get_entries_needing_tutor_request() pour ne cibler que les saisies dont le
 * premier jour de stage est arrivé : envoyer l'invitation dès l'auto-évaluation de l'étudiant (qui
 * peut être saisie bien avant le début du stage) enverrait le questionnaire au maître de stage
 * avant même que le stage ait commencé.
 *
 * @param stdClass $stage
 * @param stdClass $cm Course module.
 * @param stdClass $entry
 * @return void
 */
function stage_maybe_request_tutor_evaluation(stdClass $stage, stdClass $cm, stdClass $entry) {
    global $DB;

    if (empty($entry->datestart) || $entry->datestart > time()) {
        return;
    }

    $theme = $DB->get_record('stage_theme', ['id' => $entry->themeid]);
    if (!stage_tutor_evaluation_enabled($stage, $theme) || !empty($entry->tutortoken)) {
        return;
    }

    $detail = stage_get_convention_detail($entry->id);
    if (!$detail || empty($detail->tutoremail)) {
        return;
    }

    $token = bin2hex(random_bytes(32));
    $DB->set_field('stage_entry', 'tutortoken', $token, ['id' => $entry->id]);
    $entry->tutortoken = $token;

    stage_notify_tutor_evaluation_request($stage, $cm, $entry, $detail->tutorname, $detail->tutoremail);
}

/**
 * Récupère une saisie de stage à partir du jeton d'accès du maître de stage.
 *
 * @param string $token
 * @return stdClass|false
 */
function stage_get_entry_by_tutor_token($token) {
    global $DB;
    if (empty($token)) {
        return false;
    }
    return $DB->get_record('stage_entry', ['tutortoken' => $token]);
}

/**
 * URL à jeton permettant au maître de stage de répondre au questionnaire d'évaluation, à
 * l'usage de la DEVE (récupération du lien pour le transmettre par un autre moyen que le
 * courriel automatique, par exemple). Génère le jeton si besoin, comme
 * stage_maybe_request_tutor_evaluation() mais sans envoyer de courriel.
 *
 * @param stdClass $entry
 * @return moodle_url|null Null si l'évaluation par le maître de stage n'est pas activée ou si
 *                          les coordonnées du maître de stage sont inconnues.
 */
function stage_get_tutor_eval_url(stdClass $stage, stdClass $entry) {
    global $DB;

    $theme = $DB->get_record('stage_theme', ['id' => $entry->themeid]);
    if (!stage_tutor_evaluation_enabled($stage, $theme)) {
        return null;
    }

    if (empty($entry->tutortoken)) {
        $detail = stage_get_convention_detail($entry->id);
        if (!$detail || empty($detail->tutoremail)) {
            return null;
        }
        $token = bin2hex(random_bytes(32));
        $DB->set_field('stage_entry', 'tutortoken', $token, ['id' => $entry->id]);
        $entry->tutortoken = $token;
    }

    return new moodle_url('/mod/stage/tutor_eval.php', ['token' => $entry->tutortoken]);
}

/**
 * Renvoie (relance) au maître de stage le courriel d'invitation à évaluer le stage, en
 * réutilisant le jeton déjà généré (ou en le générant si besoin). Contrairement à
 * stage_maybe_request_tutor_evaluation(), envoie systématiquement le courriel : à l'usage de la
 * DEVE, qui décide elle-même de relancer.
 *
 * @param stdClass $stage
 * @param stdClass $cm Course module.
 * @param stdClass $entry
 * @return bool True si le courriel a pu être envoyé.
 */
function stage_resend_tutor_evaluation_request(stdClass $stage, stdClass $cm, stdClass $entry) {
    $detail = stage_get_convention_detail($entry->id);
    if (!$detail || empty($detail->tutoremail)) {
        return false;
    }

    if (!stage_get_tutor_eval_url($stage, $entry)) {
        return false;
    }

    stage_notify_tutor_evaluation_request($stage, $cm, $entry, $detail->tutorname, $detail->tutoremail);
    return true;
}

/**
 * Ignore l'évaluation du maître de stage pour cette saisie (« shunt » DEVE) : marque la saisie
 * comme telle pour que son absence n'empêche plus la validation finale. Réversible en
 * réinitialisant la saisie (stage_reset_entry), qui remet tutorbypassed à 0.
 *
 * @param stdClass $entry
 * @return void
 */
function stage_bypass_tutor_eval(stdClass $entry) {
    global $DB;

    $DB->update_record('stage_entry', (object) [
        'id' => $entry->id,
        'tutorbypassed' => 1,
        'timemodified' => time(),
    ]);
}

/**
 * Enregistre l'évaluation en texte libre du maître de stage (utilisée quand la thématique ne
 * définit aucune question de type "tutor" : les réponses au questionnaire, elles, sont déjà
 * enregistrées via stage_save_answers()).
 *
 * @param stdClass $entry
 * @param string|null $comment
 * @return void
 */
function stage_apply_tutor_eval(stdClass $entry, $comment = null) {
    global $DB;

    $update = (object) [
        'id' => $entry->id,
        'tutortime' => time(),
        'timemodified' => time(),
    ];
    if ($comment !== null) {
        $update->tutoreval = $comment;
    }
    $DB->update_record('stage_entry', $update);
}

/**
 * Libellé lisible d'un type d'évaluation (student/teacher/tutor), pour l'affichage dans
 * questions.php.
 *
 * @param string $evaltype
 * @return string
 */
function stage_evaltype_label($evaltype) {
    switch ($evaltype) {
        case 'student':
            return get_string('evaltype_student', 'mod_stage');
        case 'teacher':
            return get_string('evaltype_teacher', 'mod_stage');
        case 'tutor':
            return get_string('evaltype_tutor', 'mod_stage');
        default:
            return $evaltype;
    }
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
 * Rapports de stage (documents déposés par l'étudiant lors de son auto-évaluation) pour une
 * saisie donnée.
 *
 * @param context $context Contexte du module stage.
 * @param int $entryid
 * @return \stored_file[] Indexés par pathnamehash, éventuellement vide.
 */
function stage_get_report_files(context $context, $entryid) {
    $fs = get_file_storage();
    return $fs->get_area_files($context->id, 'mod_stage', STAGE_REPORT_FILEAREA, $entryid, 'filename', false);
}

/**
 * Mode de dépôt du rapport de stage défini pour une thématique (aucun / facultatif / obligatoire).
 *
 * @param stdClass|null $theme
 * @return int Une des constantes STAGE_REPORT_*.
 */
function stage_theme_report_mode(?stdClass $theme) {
    return $theme === null ? STAGE_REPORT_NONE : (int) $theme->reportmode;
}

/**
 * Libellés des modes de dépôt du rapport de stage, pour les listes déroulantes de themes.php.
 *
 * @return array int => libellé
 */
function stage_report_mode_options() {
    return [
        STAGE_REPORT_NONE => get_string('reportmode_none', 'mod_stage'),
        STAGE_REPORT_OPTIONAL => get_string('reportmode_optional', 'mod_stage'),
        STAGE_REPORT_REQUIRED => get_string('reportmode_required', 'mod_stage'),
    ];
}

/**
 * Enseignants responsables d'une thématique (distincts des enseignants référents d'un étudiant) :
 * ils accèdent aux stages faits sur leur thématique et aux rapports qui y sont déposés.
 *
 * @param int $themeid
 * @return array userid => objet utilisateur, trié par nom.
 */
function stage_get_theme_teachers($themeid) {
    global $DB;

    $userfields = 'u.' . implode(', u.', \core_user\fields::get_name_fields());
    return $DB->get_records_sql(
        "SELECT u.id, $userfields
           FROM {stage_theme_teacher} tt
           JOIN {user} u ON u.id = tt.teacherid
          WHERE tt.themeid = :themeid
       ORDER BY u.lastname ASC, u.firstname ASC", ['themeid' => $themeid]);
}

/**
 * Enregistre la liste des enseignants responsables d'une thématique, en remplacement de la
 * précédente. Comme stage_set_student_teachers(), ne réécrit rien si la liste est inchangée.
 *
 * @param int $themeid
 * @param array $teacherids
 * @return void
 */
function stage_set_theme_teachers($themeid, array $teacherids) {
    global $DB;

    $teacherids = array_filter(array_unique(array_map('intval', $teacherids)));

    $existing = array_map('intval',
        $DB->get_fieldset_select('stage_theme_teacher', 'teacherid', 'themeid = ?', [$themeid]));
    sort($existing);
    $wanted = array_values($teacherids);
    sort($wanted);
    if ($existing === $wanted) {
        return;
    }

    $DB->delete_records('stage_theme_teacher', ['themeid' => $themeid]);
    foreach ($teacherids as $teacherid) {
        $DB->insert_record('stage_theme_teacher', (object) [
            'themeid' => $themeid,
            'teacherid' => $teacherid,
            'timecreated' => time(),
        ]);
    }
}

/**
 * Thématiques d'une activité dont un utilisateur est enseignant responsable.
 *
 * @param int $stageid
 * @param int $userid
 * @return array themeid => objet thématique, dans l'ordre d'affichage habituel.
 */
function stage_get_teacher_themes($stageid, $userid) {
    global $DB;

    return $DB->get_records_sql(
        "SELECT t.*
           FROM {stage_theme} t
           JOIN {stage_theme_teacher} tt ON tt.themeid = t.id
          WHERE t.stageid = :stageid AND tt.teacherid = :userid
       ORDER BY t.minstudyyear ASC, t.maxstudyyear ASC, t.sortorder ASC, t.name ASC",
        ['stageid' => $stageid, 'userid' => $userid]);
}

/**
 * Indique si un utilisateur est enseignant responsable d'une thématique donnée.
 *
 * @param int $themeid
 * @param int $userid
 * @return bool
 */
function stage_is_theme_teacher($themeid, $userid) {
    global $DB;

    return $DB->record_exists('stage_theme_teacher', ['themeid' => $themeid, 'teacherid' => $userid]);
}

/**
 * Indique si un utilisateur a le droit de consulter les rapports de stage déposés pour une
 * saisie : l'étudiant qui les a déposés, la DEVE, l'enseignant référent de l'étudiant et les
 * enseignants responsables de la thématique du stage.
 *
 * @param stdClass $stage
 * @param stdClass $entry
 * @param context $context Contexte du module stage.
 * @param int|null $userid Utilisateur concerné, l'utilisateur courant par défaut.
 * @return bool
 */
function stage_can_access_reports(stdClass $stage, stdClass $entry, context $context, $userid = null) {
    global $USER;

    $userid = $userid ?: $USER->id;

    if ((int) $entry->userid === (int) $userid && has_capability('mod/stage:submit', $context, $userid)) {
        return true;
    }
    if (has_capability('mod/stage:viewall', $context, $userid)) {
        return true;
    }
    if (has_capability('mod/stage:evaluateteacher', $context, $userid)
            && array_key_exists($entry->userid, stage_get_assigned_students($stage->id, $userid))) {
        return true;
    }

    return stage_is_theme_teacher($entry->themeid, $userid);
}

/**
 * Liens de téléchargement des rapports de stage d'une saisie, pour les pages de consultation
 * (détail d'une saisie, évaluation enseignant, validation DEVE). Chaîne vide si aucun document
 * n'a été déposé.
 *
 * @param stdClass $cm Course module.
 * @param context $context Contexte du module stage.
 * @param stdClass $entry
 * @return string HTML
 */
function stage_render_report_links(stdClass $cm, context $context, stdClass $entry) {
    $files = stage_get_report_files($context, $entry->id);
    if (empty($files)) {
        return '';
    }

    $items = [];
    foreach ($files as $file) {
        $url = new moodle_url('/mod/stage/report_file.php', [
            'id' => $cm->id, 'entryid' => $entry->id, 'pathnamehash' => $file->get_pathnamehash(),
        ]);
        $items[] = html_writer::link($url, $file->get_filename())
            . html_writer::span(' (' . display_size($file->get_filesize()) . ')', 'text-muted');
    }

    return html_writer::alist($items);
}

/**
 * Section « Rapport de stage » des pages de consultation d'une saisie (détail, évaluation
 * enseignant, validation DEVE) : titre et liens de téléchargement, ou mention qu'aucun document
 * n'a été déposé.
 *
 * La section est affichée dès qu'un document existe, même si la thématique ne demande plus de
 * rapport : la DEVE peut avoir changé la thématique de la saisie (register.php) ou le mode de
 * dépôt de la thématique depuis le dépôt, et des documents déjà déposés ne doivent pas
 * disparaître de la vue pour autant.
 *
 * @param stdClass $cm Course module.
 * @param context $context Contexte du module stage.
 * @param stdClass $entry
 * @param stdClass|null $theme Thématique de la saisie.
 * @return string HTML, chaîne vide si la thématique ne demande aucun rapport et qu'aucun
 *                document n'a été déposé.
 */
function stage_render_report_section(stdClass $cm, context $context, stdClass $entry, ?stdClass $theme) {
    global $OUTPUT;

    $links = stage_render_report_links($cm, $context, $entry);
    if ($links === '' && stage_theme_report_mode($theme) == STAGE_REPORT_NONE) {
        return '';
    }

    return $OUTPUT->heading(get_string('reportfiles', 'mod_stage'), 4)
        . ($links !== '' ? $links : $OUTPUT->notification(get_string('noreportfiles', 'mod_stage'), 'info'));
}

/**
 * Construit et envoie au navigateur une archive ZIP des rapports de stage des saisies données,
 * un dossier par étudiant pour que les fichiers de même nom ne s'écrasent pas. Ne revient pas :
 * termine la requête en envoyant le fichier.
 *
 * @param context $context Contexte du module stage.
 * @param array $entries Saisies concernées (objets stage_entry).
 * @param array $students Étudiants indexés par id, tels que renvoyés par stage_get_entry_users().
 * @param string $zipname Nom de l'archive envoyée (sans extension).
 * @return void
 */
function stage_send_reports_zip(context $context, array $entries, array $students, $zipname) {
    $filesforzipping = [];
    foreach ($entries as $entry) {
        $student = $students[$entry->userid] ?? null;
        $foldername = clean_filename(($student ? fullname($student) : 'etudiant-' . $entry->userid)
            . '-' . $entry->id);
        foreach (stage_get_report_files($context, $entry->id) as $file) {
            $filesforzipping[$foldername . '/' . clean_filename($file->get_filename())] = $file;
        }
    }

    if (empty($filesforzipping)) {
        throw new moodle_exception('noreportstozip', 'mod_stage');
    }

    $tmpfile = tempnam(make_temp_directory('mod_stage'), 'stagereports_');
    $packer = get_file_packer('application/zip');
    if (!$packer->archive_to_pathname($filesforzipping, $tmpfile)) {
        @unlink($tmpfile);
        throw new moodle_exception('reportszipfailed', 'mod_stage');
    }

    send_temp_file($tmpfile, clean_filename($zipname) . '.zip');
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
 * Vérifie ce qui empêcherait de construire le PDF d'une convention (gabarit non choisi, fichier
 * de gabarit absent, bibliothèque FPDI non installée), sans rien construire.
 *
 * Ces contrôles sont ceux de stage_build_convention_pdf(), isolés pour pouvoir être faits avant
 * de lancer un téléchargement : une erreur survenant pendant le téléchargement lui-même
 * (convention.php, cadre invisible) ne serait jamais montrée à l'utilisateur.
 *
 * @param stdClass $entry
 * @param context $context Contexte du module stage.
 * @return string|null Clé de chaîne de langue mod_stage décrivant l'empêchement, ou null si le
 *                     PDF peut être construit.
 */
function stage_check_convention_pdf_prerequisites(stdClass $entry, context $context) {
    global $CFG;

    if (empty($entry->conventiontemplateid)) {
        return 'conventionnotemplatechosen';
    }
    if (!stage_get_convention_template_file($context, $entry->conventiontemplateid)) {
        return 'conventiontemplatemissing';
    }
    if (!is_readable($CFG->dirroot . '/mod/stage/thirdparty/vendor/autoload.php')) {
        return 'conventionfpdimissing';
    }

    return null;
}

/**
 * Page intermédiaire qui lance un téléchargement puis ramène l'utilisateur là d'où il venait.
 *
 * Un fichier envoyé en pièce jointe ne fait pas changer de page : sans cela, l'utilisateur reste
 * sur le formulaire qui a déclenché le téléchargement. Le téléchargement est donc lancé dans un
 * cadre invisible, ce qui laisse la page courante libre de naviguer ensuite vers l'écran de
 * retour. Les deux liens restent proposés en clair : ils servent de recours si le navigateur
 * bloque le cadre, si le script est désactivé, ou si le téléchargement tarde.
 *
 * @param moodle_url $downloadurl URL renvoyant le fichier en pièce jointe.
 * @param moodle_url $returnurl Écran sur lequel revenir une fois le téléchargement lancé.
 * @param int $delay Délai avant le retour, en secondes : le téléchargement doit avoir démarré,
 *                   un retour trop prompt interromprait une génération encore en cours.
 * @return string HTML
 */
function stage_render_download_and_return(moodle_url $downloadurl, moodle_url $returnurl, $delay = 5) {
    global $OUTPUT;

    $out = $OUTPUT->notification(get_string('downloadstarting', 'mod_stage'),
        \core\output\notification::NOTIFY_INFO);

    $out .= html_writer::tag('iframe', '', [
        'src' => $downloadurl->out(false), 'title' => get_string('downloadstarting', 'mod_stage'),
        'width' => '0', 'height' => '0', 'style' => 'border:0;position:absolute;visibility:hidden;',
    ]);

    $out .= html_writer::div(
        html_writer::link($downloadurl, get_string('downloadrestart', 'mod_stage'),
            ['class' => 'btn btn-secondary mr-2'])
        . html_writer::link($returnurl, get_string('backtolist', 'mod_stage'),
            ['class' => 'btn btn-primary']));

    $out .= html_writer::script(
        'setTimeout(function() { window.location.href = ' . json_encode($returnurl->out(false)) . '; }, '
        . ((int) $delay * 1000) . ');');

    return $out;
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
 * @param bool $withsignatures Ajoute un cadre de signatures (stagiaire, maître de stage,
 *                              responsable de l'organisme d'accueil, enseignant.e référent.e,
 *                              établissement) en bas de la page 1, pour une convention imprimée
 *                              destinée à être signée à la main.
 * @return array ['error' => string|null (clé de chaîne de langue mod_stage), 'pdf' => objet FPDI
 *               prêt pour Output(), ou null en cas d'erreur, 'filename' => string|null]
 */
function stage_build_convention_pdf(stdClass $stage, stdClass $entry, context $context, $withsignatures = false) {
    global $DB, $CFG;

    $error = stage_check_convention_pdf_prerequisites($entry, $context);
    if ($error !== null) {
        return ['error' => $error, 'pdf' => null, 'filename' => null];
    }

    $conventiontemplate = $DB->get_record('stage_convention_template', ['id' => $entry->conventiontemplateid]);
    $conventionlang = $conventiontemplate ? $conventiontemplate->lang : 'fr';
    $templatefile = stage_get_convention_template_file($context, $entry->conventiontemplateid);

    $fpdiautoload = $CFG->dirroot . '/mod/stage/thirdparty/vendor/autoload.php';
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
            'signatory' => $establishmentinfo->signatory,
        ],
        'hoststructure' => (string) $entry->structure,
        'yearlabel' => $theme ? stage_convention_year_label($theme->minstudyyear, $detail->yearsituation, $conventionlang)
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
        'periods' => array_map(function($period) use ($dateformat) {
            return userdate($period->datestart, $dateformat) . ' - ' . userdate($period->dateend, $dateformat);
        }, stage_get_or_seed_entry_periods($entry)),
        // Ni la durée retenue ni le statut de la saisie ne figurent sur la convention : elle se
        // signe avant toute évaluation, l'une est donc encore à zéro et l'autre à son état
        // initial, deux informations qui n'ont pas leur place sur ce document.
        'duration' => [
            'declared' => $entry->declaredduration,
        ],
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
    $page1->generate_page1($stagedata, $logoleftpath, $logorightpath, $conventionlang, $withsignatures);
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

/**
 * Supprime définitivement des saisies de stage et tout ce qui leur est rattaché : périodes, jours
 * ouvrés, détail de convention, réponses aux questionnaires, et — si le contexte du module est
 * fourni — la convention signée et le rapport de stage stockés dans ses zones de fichiers.
 *
 * Factorisé ici pour que les deux appelants (suppression de l'instance dans stage_delete_instance()
 * et suppression des données personnelles d'un étudiant dans \mod_stage\privacy\provider) ne
 * puissent pas diverger : une table rattachée ajoutée plus tard n'a ainsi qu'un seul endroit à
 * mettre à jour pour être purgée dans les deux cas.
 *
 * @param int[] $entryids Identifiants de saisies. Un tableau vide ne fait rien.
 * @param context|null $context Contexte du module, pour purger aussi les fichiers. Peut être omis
 *        lorsque Moodle s'apprête de toute façon à supprimer le contexte et ses fichiers (cas de
 *        la suppression de l'instance).
 * @return void
 */
function stage_delete_entries(array $entryids, ?context $context = null) {
    global $DB;

    $entryids = array_values(array_filter(array_unique(array_map('intval', $entryids))));
    if (empty($entryids)) {
        return;
    }

    if ($context) {
        $fs = get_file_storage();
        foreach ($entryids as $entryid) {
            foreach (['signedconvention', STAGE_REPORT_FILEAREA] as $filearea) {
                $fs->delete_area_files($context->id, 'mod_stage', $filearea, $entryid);
            }
        }
    }

    [$insql, $inparams] = $DB->get_in_or_equal($entryids);
    foreach (['stage_answer', 'stage_convention_detail', 'stage_entry_period', 'stage_entry_workday'] as $table) {
        $DB->delete_records_select($table, "entryid $insql", $inparams);
    }
    $DB->delete_records_select('stage_entry', "id $insql", $inparams);
}
