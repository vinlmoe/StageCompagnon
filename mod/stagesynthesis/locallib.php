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
 * Fonctions métier internes pour mod_stagesynthesis.
 *
 * @package   mod_stagesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/stagesynthesis/lib.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

/**
 * Rend, pour les utilisateurs qui ont le droit de les modifier, le rappel du nombre d'activités
 * liées et le lien vers leur gestion : affiché identiquement en tête de dashboard.php et
 * entries.php, factorisé ici pour que les deux ne divergent pas.
 *
 * @param stdClass $stagesynthesis
 * @param stdClass $cm
 * @param context $context
 * @return string HTML, ou '' si l'utilisateur n'a pas la capacité de gérer les liens.
 */
function stagesynthesis_render_managelinks_notice(stdClass $stagesynthesis, stdClass $cm, context $context) {
    if (!has_capability('mod/stagesynthesis:managelinks', $context)) {
        return '';
    }

    $links = stagesynthesis_get_links($stagesynthesis->id);
    return html_writer::div(
        get_string('linkedcount', 'mod_stagesynthesis', count($links)) . ' ' .
        html_writer::link(new moodle_url('/mod/stagesynthesis/administration.php', ['id' => $cm->id]),
            get_string('managelinks', 'mod_stagesynthesis')),
        'mb-3'
    );
}

/**
 * Liste des course-modules d'activités "Gestion des stages" actuellement liées à une instance de
 * Suivi des stages, dans l'ordre où ils ont été ajoutés.
 *
 * @param int $synthesisid
 * @return array cmid => stdClass{linkid, stagecmid, coursename, stagename, visible}
 */
function stagesynthesis_get_links($synthesisid) {
    global $DB;

    $sql = "SELECT l.id AS linkid, l.stagecmid, cm.visible, s.name AS stagename, c.id AS courseid,
                   c.fullname AS coursename, c.visible AS coursevisible
              FROM {stagesynthesis_link} l
              JOIN {course_modules} cm ON cm.id = l.stagecmid
              JOIN {stage} s ON s.id = cm.instance
              JOIN {course} c ON c.id = cm.course
             WHERE l.synthesisid = :synthesisid
          ORDER BY c.fullname ASC, s.name ASC";

    $rows = $DB->get_records_sql($sql, ['synthesisid' => $synthesisid]);

    $links = [];
    foreach ($rows as $row) {
        $links[(int) $row->stagecmid] = $row;
    }
    return $links;
}

/**
 * Liste des activités "Gestion des stages" que l'utilisateur donné a le droit de lier, pour le
 * formulaire d'administration des liens : uniquement celles où il a lui-même un rôle de gestion
 * (mod/stage:manageteachers, déjà requis pour attribuer des référents dans l'activité d'origine),
 * pour ne jamais exposer les noms de cours/activités d'une promotion à laquelle il n'a par
 * ailleurs aucun accès. Inclut les activités masquées et celles dans des cours masqués parmi ces
 * dernières : c'est justement ce qui permet d'exclure explicitement une promotion qui n'est plus
 * suivie plutôt que de devoir supprimer son activité d'origine.
 *
 * @param int $userid
 * @return array cmid => stdClass{stagecmid, coursename, stagename, visible, coursevisible}
 */
function stagesynthesis_get_available_stage_activities($userid) {
    global $DB;

    $sql = "SELECT cm.id AS stagecmid, cm.visible, s.name AS stagename, c.id AS courseid,
                   c.fullname AS coursename, c.visible AS coursevisible
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module AND m.name = 'stage'
              JOIN {stage} s ON s.id = cm.instance
              JOIN {course} c ON c.id = cm.course
          ORDER BY c.fullname ASC, s.name ASC";

    $candidates = $DB->get_records_sql($sql);

    $available = [];
    foreach ($candidates as $candidate) {
        $modcontext = context_module::instance($candidate->stagecmid, IGNORE_MISSING);
        if ($modcontext && has_capability('mod/stage:manageteachers', $modcontext, $userid)) {
            $available[(int) $candidate->stagecmid] = $candidate;
        }
    }
    return $available;
}

/**
 * Remplace la liste des activités "Gestion des stages" liées à une instance de Suivi des stages.
 *
 * @param int $synthesisid
 * @param int[] $stagecmids
 * @return void
 */
function stagesynthesis_set_links($synthesisid, array $stagecmids) {
    global $DB;

    $stagecmids = array_filter(array_unique(array_map('intval', $stagecmids)));

    $DB->delete_records('stagesynthesis_link', ['synthesisid' => $synthesisid]);

    $now = time();
    foreach ($stagecmids as $stagecmid) {
        $DB->insert_record('stagesynthesis_link', (object) [
            'synthesisid' => $synthesisid,
            'stagecmid' => $stagecmid,
            'timecreated' => $now,
        ]);
    }
}

/**
 * Détermine, pour l'utilisateur donné, le sous-ensemble des activités liées sur lesquelles il est
 * effectivement enseignant référent d'au moins un étudiant : une activité liée n'entre dans le
 * périmètre que si l'utilisateur a toujours la capacité mod/stage:evaluateteacher sur l'instance
 * d'origine (retrait de rôle, promotion archivée...) et qu'au moins un étudiant lui est attribué.
 * La synthèse ne fait ainsi que refléter les droits déjà accordés dans chaque cours, elle n'en
 * accorde aucun de plus.
 *
 * @param int $synthesisid
 * @param int $userid
 * @return array stagecmid => stdClass{cm, context, stage, assignedids, coursename, stagename}
 */
function stagesynthesis_get_active_links($synthesisid, $userid) {
    global $DB;

    $active = [];
    foreach (stagesynthesis_get_links($synthesisid) as $stagecmid => $link) {
        if (!$link->visible || !$link->coursevisible) {
            continue;
        }

        try {
            $cm = get_coursemodule_from_id('stage', $stagecmid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }
            $context = context_module::instance($cm->id);
        } catch (Exception $e) {
            // Lien orphelin (contexte manquant/corrompu...) : ignoré plutôt que de faire échouer
            // toute la synthèse pour les autres activités liées, saines.
            continue;
        }

        if (!has_capability('mod/stage:evaluateteacher', $context, $userid)) {
            continue;
        }

        $stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);
        $assignedids = array_keys(stage_get_assigned_students($stage->id, $userid));
        if (empty($assignedids)) {
            continue;
        }

        $active[$stagecmid] = (object) [
            'cm' => $cm,
            'context' => $context,
            'stage' => $stage,
            'assignedids' => $assignedids,
            'coursename' => $link->coursename,
            'stagename' => $link->stagename,
            'themes' => stage_get_themes($stage->id),
        ];
    }
    return $active;
}

/**
 * Thématiques proposées dans le filtre, toutes activités actives confondues : chaque thématique
 * est propre à une instance mod_stage, donc identifiée dans le filtre par la paire
 * "stagecmid:themeid" (deux instances peuvent avoir un même id de thématique sans rapport entre
 * elles) et étiquetée avec son cours pour rester compréhensible dans une liste combinée.
 *
 * @param array $activelinks Voir stagesynthesis_get_active_links().
 * @return array "stagecmid:themeid" => libellé
 */
function stagesynthesis_get_theme_options(array $activelinks) {
    $options = [];
    foreach ($activelinks as $stagecmid => $link) {
        foreach ($link->themes as $theme) {
            $options[$stagecmid . ':' . $theme->id] = format_string($link->coursename) . ' – ' . format_string($theme->name);
        }
    }
    return $options;
}

/**
 * Décompose une valeur de filtre de thématique combiné ("stagecmid:themeid") si elle correspond
 * bien à une des options proposées ; sinon, ignore silencieusement une valeur invalide ou forgée
 * plutôt que de la répercuter dans une requête SQL.
 *
 * @param string $value
 * @param array $themeoptions Voir stagesynthesis_get_theme_options().
 * @return array{0:int,1:int}|null [stagecmid, themeid], ou null si absent/invalide.
 */
function stagesynthesis_parse_theme_filter($value, array $themeoptions) {
    if ($value === '' || !isset($themeoptions[$value])) {
        return null;
    }
    [$stagecmid, $themeid] = explode(':', $value, 2);
    return [(int) $stagecmid, (int) $themeid];
}

/**
 * Rend le formulaire de recherche/filtre au-dessus de la liste combinée : mêmes filtres que
 * stage_render_list_filters() (nom étudiant, thématique, statut), la thématique étant ici une
 * valeur combinée "stagecmid:themeid" (voir stagesynthesis_get_theme_options()).
 *
 * @param moodle_url $baseurl
 * @param array $themeoptions
 * @param string $search
 * @param string $themekey
 * @param string $status
 * @return string
 */
function stagesynthesis_render_list_filters(moodle_url $baseurl, array $themeoptions, $search, $themekey, $status) {
    $formurl = new moodle_url($baseurl);
    $formurl->remove_params('search', 'themekey', 'status', 'tsort', 'tdir');

    $out = html_writer::start_tag('form',
        ['method' => 'get', 'action' => $formurl, 'class' => 'form-inline stage-filters mb-3']);
    foreach ($formurl->params() as $key => $value) {
        $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $key, 'value' => $value]);
    }
    $out .= html_writer::empty_tag('input', [
        'type' => 'text', 'name' => 'search', 'value' => s($search),
        'placeholder' => get_string('searchstudent', 'mod_stage'), 'class' => 'form-control mr-2',
    ]);

    $out .= html_writer::select(['' => get_string('allthemes', 'mod_stage')] + $themeoptions, 'themekey', $themekey,
        false, ['class' => 'form-control mr-2']);

    $statusoptions = ['' => get_string('allstatuses', 'mod_stage')];
    foreach ([STAGE_STATUS_ANNULE, STAGE_STATUS_NON_VALIDE, STAGE_STATUS_ENREGISTRE, STAGE_STATUS_EVAL_ETUDIANT,
            STAGE_STATUS_EVAL_ENSEIGNANT, STAGE_STATUS_VALIDE_DEVE] as $statuscode) {
        $statusoptions[$statuscode] = stage_status_label($statuscode);
    }
    $out .= html_writer::select($statusoptions, 'status', $status, false, ['class' => 'form-control mr-2']);

    $out .= html_writer::empty_tag('input', [
        'type' => 'submit', 'value' => get_string('search'), 'class' => 'btn btn-secondary mr-2',
    ]);
    $out .= html_writer::link($formurl, get_string('resetfilters', 'mod_stage'), ['class' => 'btn btn-link']);
    $out .= html_writer::end_tag('form');

    return $out;
}

/**
 * Construit la liste combinée des saisies à afficher, toutes activités actives confondues, avec
 * les mêmes filtres que teacher.php (recherche, thématique, statut) et triée globalement selon la
 * même clé/sens que stage_get_filtered_entries(). Chaque saisie est enrichie des informations de
 * son activité d'origine nécessaires à l'affichage (cours, année d'étude courante, thématique...).
 *
 * @param array $activelinks Voir stagesynthesis_get_active_links().
 * @param array $filters ['search' => string, 'themefilter' => [stagecmid, themeid]|null, 'status' => string]
 * @param string $sort
 * @param string $dir
 * @return array Saisies enrichies, triées.
 */
function stagesynthesis_get_filtered_entries(array $activelinks, array $filters, $sort, $dir) {
    global $DB;

    $themefilter = $filters['themefilter'] ?? null;

    $rows = [];
    foreach ($activelinks as $stagecmid => $link) {
        // Une thématique donnée n'existe que dans son instance d'origine : si le filtre en cible
        // une dans une autre activité, celle-ci ne peut rien avoir à proposer.
        if ($themefilter !== null && $themefilter[0] !== $stagecmid) {
            continue;
        }

        $entryfilters = ['search' => $filters['search'], 'status' => $filters['status']];
        if ($themefilter !== null) {
            $entryfilters['themeid'] = $themefilter[1];
        }

        $entries = stage_get_filtered_entries($link->stage->id, $entryfilters, $sort, $dir, $link->assignedids);
        if (empty($entries)) {
            continue;
        }

        $themes = $link->themes;
        $students = stage_get_entry_users($entries);

        foreach ($entries as $entry) {
            $entry->cmid = $link->cm->id;
            $entry->coursename = $link->coursename;
            $entry->currentstudyyear = (int) $link->stage->currentstudyyear;
            $entry->themename = isset($themes[$entry->themeid]) ? format_string($themes[$entry->themeid]->name) : '-';
            $student = $students[$entry->userid] ?? null;
            $entry->studentfullname = $student ? fullname($student) : '-';
            $rows[] = $entry;
        }
    }

    $sortmap = [
        'student' => 'studentfullname',
        'theme' => 'themename',
        'status' => 'status',
        'duration' => 'declaredduration',
        'course' => 'coursename',
        'timecreated' => 'timecreated',
    ];
    $sortfield = $sortmap[$sort] ?? $sortmap['timecreated'];
    $reverse = strtoupper($dir) !== 'ASC';

    usort($rows, function($a, $b) use ($sortfield, $reverse) {
        $result = $a->$sortfield <=> $b->$sortfield;
        return $reverse ? -$result : $result;
    });

    return $rows;
}

/**
 * Demandes de convention en attente de validation par l'enseignant référent (voir
 * stage_get_teacher_pending_convention_entries()), agrégées sur toutes les activités actives.
 *
 * @param array $activelinks Voir stagesynthesis_get_active_links().
 * @param int $userid
 * @return array Saisies enrichies (cmid, coursename, themename, studentfullname).
 */
function stagesynthesis_get_pending_convention_entries(array $activelinks, $userid) {
    $rows = [];
    foreach ($activelinks as $stagecmid => $link) {
        $entries = stage_get_teacher_pending_convention_entries($link->stage->id, $userid);
        if (empty($entries)) {
            continue;
        }

        $themes = $link->themes;
        $students = stage_get_entry_users($entries);

        foreach ($entries as $entry) {
            $entry->cmid = $link->cm->id;
            $entry->coursename = $link->coursename;
            $entry->themename = isset($themes[$entry->themeid]) ? format_string($themes[$entry->themeid]->name) : '-';
            $student = $students[$entry->userid] ?? null;
            $entry->studentfullname = $student ? fullname($student) : '-';
            $rows[] = $entry;
        }
    }

    usort($rows, function($a, $b) {
        return $a->conventionrequesttime <=> $b->conventionrequesttime;
    });

    return $rows;
}

/**
 * Construit la vue d'ensemble par étudiant (voir stage_get_pilotage_overview()), agrégée sur
 * toutes les activités actives : une ligne par étudiant attribué, enrichie de son activité
 * d'origine (cmid, cours, année d'étude courante) pour permettre le lien vers son détail.
 *
 * @param array $activelinks Voir stagesynthesis_get_active_links().
 * @return array Lignes enrichies, non triées (le tri se fait à l'affichage, comme dashboard.php).
 */
function stagesynthesis_get_pilotage_rows(array $activelinks) {
    $rows = [];
    foreach ($activelinks as $stagecmid => $link) {
        $overview = stage_get_pilotage_overview($link->stage->id, $link->context, $link->assignedids);
        foreach ($overview as $row) {
            $row->cmid = $link->cm->id;
            $row->coursename = $link->coursename;
            $row->currentstudyyear = (int) $link->stage->currentstudyyear;
            $rows[] = $row;
        }
    }
    return $rows;
}
