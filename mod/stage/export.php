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
 * Export au format Excel (xlsx), pour la DEVE, en deux feuilles :
 *
 * - « Bilan de promotion » : une ligne par étudiant inscrit, avec la validation de l'année
 *   d'étude courante et des précédentes, les étudiants en défaut en tête (voir
 *   stage_get_promotion_report()). Les étudiants sans aucune saisie y figurent aussi, alors
 *   qu'ils étaient absents de l'export : ce sont précisément ceux à relancer en premier.
 * - « Stages » : une ligne par saisie, le détail complet pour retravailler les données. Toutes
 *   les informations de la saisie y figurent : caractéristiques du stage, convention (statut,
 *   étapes datées, et la totalité des informations saisies pour sa page 1), évaluations
 *   (étudiant, enseignant référent, maître de stage), rapport de stage déposé, validation DEVE et
 *   annulation.
 * - « Réponses aux questionnaires » : une ligne par réponse, seul moyen d'exporter les
 *   questionnaires définis par thématique, dont les réponses ne tiendraient pas en colonnes
 *   fixes (les questions diffèrent d'une thématique à l'autre).
 *
 * Le bilan destiné à être imprimé ou diffusé est produit en PDF par export_pdf.php.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/excellib.class.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:viewall', $context);

$filename = clean_filename(format_string($course->shortname) . '_' . format_string($stage->name) . '_stages.xlsx');

$workbook = new MoodleExcelWorkbook('-');
$workbook->send($filename);

$headerformat = new MoodleExcelFormat(['bold' => 1]);
$dateformat = new MoodleExcelFormat(['num_format' => 'dd/mm/yyyy']);

// --- Feuille 1 : bilan de promotion ---------------------------------------------------------

$report = stage_get_promotion_report($stage, $context);

$sheet = $workbook->add_worksheet(get_string('promotionreport', 'mod_stage'));

$headers = [get_string('student', 'mod_stage'), get_string('email')];
foreach ($report->years as $year) {
    $headers[] = stage_studyyear_label($year);
}
$headers = array_merge($headers, [
    get_string('promotionfailedyears', 'mod_stage'),
    get_string('totalretainedshort', 'mod_stage'),
    get_string('mandatorythemes', 'mod_stage'),
    get_string('summaryabroaddays', 'mod_stage'),
    get_string('pendingstages', 'mod_stage'),
    get_string('status', 'mod_stage'),
]);
foreach ($headers as $col => $header) {
    $sheet->write_string(0, $col, $header, $headerformat);
}

$row = 1;
foreach ($report->rows as $reportrow) {
    $col = 0;
    $sheet->write_string($row, $col++, fullname($reportrow->user));
    $sheet->write_string($row, $col++, (string) $reportrow->user->email);
    foreach ($report->years as $year) {
        // Une année sans objectif pour cet étudiant n'est ni validée ni en défaut : un tiret,
        // plutôt qu'un « non » qui se lirait comme un manquement.
        $sheet->write_string($row, $col++, !isset($reportrow->yearsdone[$year])
            ? '-'
            : ($reportrow->yearsdone[$year] ? get_string('yes') : get_string('no')));
    }
    $sheet->write_string($row, $col++, implode(', ', array_map(function($year) {
        return stage_studyyear_label($year);
    }, $reportrow->failedyears)));
    $sheet->write_number($row, $col++, (int) $reportrow->progress->totalretained);
    $sheet->write_string($row, $col++, $reportrow->mandatorytotal > 0
        ? "{$reportrow->mandatorydone} / {$reportrow->mandatorytotal}" : '-');
    $sheet->write_string($row, $col++, $reportrow->abroadprogress->required > 0
        ? "{$reportrow->abroadprogress->retained} / {$reportrow->abroadprogress->required}" : '-');
    $sheet->write_number($row, $col++, (int) $reportrow->pendingcount);
    $sheet->write_string($row, $col++, $reportrow->uptodate
        ? get_string('promotionuptodate', 'mod_stage') : get_string('themetodo', 'mod_stage'));
    $row++;
}

// --- Feuille 2 : détail des saisies ----------------------------------------------------------

$entries = $DB->get_records('stage_entry', ['stageid' => $stage->id], 'timecreated ASC');
$students = stage_get_entry_users($entries);
$themes = stage_get_themes($stage->id);
$stagetypes = stage_get_entry_stagetypes(array_keys($entries));
$entryids = array_keys($entries);

// Toutes les données rattachées aux saisies sont résolues en une requête chacune plutôt qu'une
// par saisie : sur une promotion entière (plusieurs centaines de saisies), une requête par saisie
// et par information suffisait à faire expirer l'export.
$conventiondetails = [];
$workdaycounts = [];
$reportfilenames = [];
if (!empty($entryids)) {
    [$insql, $inparams] = $DB->get_in_or_equal($entryids, SQL_PARAMS_NAMED);

    $conventiondetails = $DB->get_records_select('stage_convention_detail', "entryid $insql", $inparams,
        '', '*');
    $conventiondetails = array_combine(array_map(function($detail) {
        return $detail->entryid;
    }, $conventiondetails), $conventiondetails);

    $workdaycounts = $DB->get_records_sql_menu(
        "SELECT entryid, COUNT(1) FROM {stage_entry_workday} WHERE entryid $insql GROUP BY entryid", $inparams);

    // Les rapports de stage sont interrogés directement dans la table des fichiers : l'API
    // fichiers ne sait lire qu'une zone (donc une saisie) à la fois.
    $fileparams = $inparams + [
        'contextid' => $context->id, 'component' => 'mod_stage', 'filearea' => STAGE_REPORT_FILEAREA,
    ];
    $filerecords = $DB->get_records_sql(
        "SELECT id, itemid, filename
           FROM {files}
          WHERE contextid = :contextid AND component = :component AND filearea = :filearea
                AND filename <> '.' AND itemid $insql
       ORDER BY filename ASC", $fileparams);
    foreach ($filerecords as $filerecord) {
        $reportfilenames[$filerecord->itemid][] = $filerecord->filename;
    }
}

// Les enseignants responsables sont attribués par thématique : une seule résolution par
// thématique, même si elle porte des dizaines de saisies.
$themeteachers = [];
foreach ($themes as $exportedtheme) {
    $themeteachers[$exportedtheme->id] = implode(', ',
        array_map('fullname', stage_get_theme_teachers($exportedtheme->id)));
}

// L'évaluateur (stage_entry.teacherid) et les enseignants référents attribués à l'étudiant sont
// deux informations distinctes : la colonne unique de l'ancien export était intitulée
// « enseignants référents » mais contenait l'évaluateur. Les deux figurent désormais.
// Les autres intervenants (DEVE, convention, annulation) sont résolus dans le même lot.
$actorids = [];
foreach ($entries as $entry) {
    foreach ([$entry->teacherid, $entry->deveuserid, $entry->cancelledby, $entry->conventionrejectedby,
            $entry->conventionteachervalidatedby, $entry->conventioneditedby, $entry->conventionsignedby] as $actorid) {
        if (!empty($actorid)) {
            $actorids[(int) $actorid] = (int) $actorid;
        }
    }
}
foreach ($conventiondetails as $detail) {
    if (!empty($detail->referentteacherid)) {
        $actorids[(int) $detail->referentteacherid] = (int) $detail->referentteacherid;
    }
}
$actors = $actorids ? $DB->get_records_list('user', 'id', $actorids, '', 'id, ' . implode(', ',
    \core_user\fields::get_name_fields())) : [];
$referentsbyuser = [];

// Nom d'un intervenant à partir de son id, vide s'il n'y en a pas (colonne laissée vide plutôt
// qu'un identifiant numérique, illisible dans un tableur).
$actorname = function($userid) use ($actors) {
    return !empty($userid) && isset($actors[$userid]) ? fullname($actors[$userid]) : '';
};

// Date au format du tableur, vide si l'étape n'a pas eu lieu : une cellule vide se filtre et se
// trie, contrairement à un « - » ou à un horodatage à 0 affiché comme 01/01/1970.
$writedate = function($sheet, $row, $col, $timestamp) use ($dateformat) {
    if (!empty($timestamp)) {
        $sheet->write_date($row, $col, $timestamp, $dateformat);
    } else {
        $sheet->write_string($row, $col, '');
    }
};

$yesno = function($value) {
    return !empty($value) ? get_string('yes') : get_string('no');
};

$sheet = $workbook->add_worksheet(get_string('allstages', 'mod_stage'));

$headers = [
    // Identifiant de la saisie : sans lui, les réponses de la feuille 3 ne pourraient pas être
    // rattachées à leur stage (un étudiant peut avoir plusieurs stages sur la même thématique).
    get_string('exportentryid', 'mod_stage'),
    // Étudiant et thématique.
    get_string('student', 'mod_stage'),
    get_string('email'),
    get_string('studyyear', 'mod_stage'),
    get_string('conventionyearsituation', 'mod_stage'),
    get_string('theme', 'mod_stage'),
    get_string('mandatory', 'mod_stage'),
    get_string('themeteachers', 'mod_stage'),
    // Caractéristiques du stage.
    get_string('conventionstagetype', 'mod_stage'),
    get_string('structure', 'mod_stage'),
    get_string('abroad', 'mod_stage'),
    get_string('country', 'mod_stage'),
    get_string('datestart', 'mod_stage'),
    get_string('dateend', 'mod_stage'),
    get_string('periods', 'mod_stage'),
    get_string('workdayscount', 'mod_stage'),
    get_string('declaredduration', 'mod_stage'),
    get_string('retainedduration', 'mod_stage'),
    get_string('status', 'mod_stage'),
    // Convention : statut et étapes datées.
    get_string('conventionstatus', 'mod_stage'),
    get_string('conventiontemplatename', 'mod_stage'),
    get_string('conventionreferentteacher', 'mod_stage'),
    get_string('conventionrequesttime', 'mod_stage'),
    get_string('conventionteachervalidatedby', 'mod_stage'),
    get_string('conventionteachervalidatetime', 'mod_stage'),
    get_string('conventioneditedby', 'mod_stage'),
    get_string('conventionedittime', 'mod_stage'),
    get_string('conventionsignedby', 'mod_stage'),
    get_string('conventionsigntime', 'mod_stage'),
    get_string('conventionrejectedby', 'mod_stage'),
    get_string('conventionrejecttime', 'mod_stage'),
    get_string('conventionrejectcomment', 'mod_stage'),
    // Convention : informations saisies pour sa page 1.
    get_string('conventionbirthdate', 'mod_stage'),
    get_string('conventionstudentaddress', 'mod_stage'),
    get_string('conventionstudentphone', 'mod_stage'),
    get_string('conventionhostaddress', 'mod_stage'),
    get_string('conventionhostrepresentative', 'mod_stage'),
    get_string('conventionhostrepresentativetitle', 'mod_stage'),
    get_string('conventionhostservice', 'mod_stage'),
    get_string('conventionhostphone', 'mod_stage'),
    get_string('conventionhostemail', 'mod_stage'),
    get_string('conventionhostlocation', 'mod_stage'),
    get_string('conventiontutorname', 'mod_stage'),
    get_string('conventiontutorfunction', 'mod_stage'),
    get_string('conventiontutorphone', 'mod_stage'),
    get_string('conventiontutoremail', 'mod_stage'),
    get_string('conventionnightpresence', 'mod_stage'),
    get_string('conventionsundaypresence', 'mod_stage'),
    get_string('conventionholidaypresence', 'mod_stage'),
    get_string('conventionhomebased', 'mod_stage'),
    get_string('conventionothermodality', 'mod_stage'),
    get_string('conventiongratification', 'mod_stage'),
    get_string('conventionhasleave', 'mod_stage'),
    get_string('conventionleavedays', 'mod_stage'),
    get_string('conventionleavemodalities', 'mod_stage'),
    // Évaluations.
    get_string('studentselfeval', 'mod_stage'),
    get_string('referentteachers', 'mod_stage'),
    get_string('evaluatedby', 'mod_stage'),
    get_string('teachervalidationtime', 'mod_stage'),
    get_string('teachereval', 'mod_stage'),
    get_string('tutorevalheading', 'mod_stage'),
    get_string('tutorevaltime', 'mod_stage'),
    get_string('tutorevalbypassedcolumn', 'mod_stage'),
    // Rapport de stage déposé.
    get_string('reportmode', 'mod_stage'),
    get_string('reportfilescount', 'mod_stage'),
    get_string('reportfiles', 'mod_stage'),
    // Validation DEVE, annulation et traçabilité.
    get_string('devevalidatedby', 'mod_stage'),
    get_string('devevalidationtime', 'mod_stage'),
    get_string('devecomment', 'mod_stage'),
    get_string('cancelledby', 'mod_stage'),
    get_string('canceltime', 'mod_stage'),
    get_string('cancelcomment', 'mod_stage'),
    get_string('timecreated', 'mod_stage'),
    get_string('lastmodified'),
];
foreach ($headers as $col => $header) {
    $sheet->write_string(0, $col, $header, $headerformat);
}

$exportdateformat = get_string('strftimedate', 'langconfig');
$stagetypeoptions = stage_convention_stagetype_options();
$yearsituationoptions = stage_convention_yearsituation_options();
$reportmodeoptions = stage_report_mode_options();
$conventiontemplates = stage_get_convention_templates($stage->id);

$row = 1;
foreach ($entries as $entry) {
    $student = $students[$entry->userid] ?? null;
    $theme = $themes[$entry->themeid] ?? null;
    $evaluator = $entry->teacherid && isset($evaluators[$entry->teacherid]) ? $evaluators[$entry->teacherid] : null;

    // Les référents sont attribués par étudiant : une seule résolution par étudiant, même s'il a
    // plusieurs saisies.
    if (!array_key_exists($entry->userid, $referentsbyuser)) {
        $referentsbyuser[$entry->userid] = implode(', ', array_map('fullname',
            stage_get_student_teachers($stage->id, $entry->userid)));
    }

    $periods = stage_get_or_seed_entry_periods($entry);
    $periodlabels = array_map(function($period) use ($exportdateformat) {
        return userdate($period->datestart, $exportdateformat) . ' - ' . userdate($period->dateend, $exportdateformat);
    }, $periods);
    $stagetype = $stagetypes[$entry->id] ?? 'obligatoire';

    $detail = $conventiondetails[$entry->id] ?? null;
    $reportfiles = $reportfilenames[$entry->id] ?? [];
    // Le gabarit est porté par la saisie elle-même : une saisie enregistrée par la DEVE peut en
    // avoir un sans que l'étudiant ait rempli le détail de sa convention.
    $template = !empty($entry->conventiontemplateid)
        ? ($conventiontemplates[$entry->conventiontemplateid] ?? null) : null;

    // Une valeur du détail de convention, vide tant que l'étudiant n'a pas rempli sa demande.
    $detailvalue = function($field) use ($detail) {
        return $detail !== null ? (string) $detail->$field : '';
    };

    $col = 0;
    $sheet->write_number($row, $col++, (int) $entry->id);
    $sheet->write_string($row, $col++, $student ? fullname($student) : '');
    $sheet->write_string($row, $col++, $student ? $student->email : '');
    $sheet->write_string($row, $col++, stage_studyyear_label($entry->studyyear));
    $sheet->write_string($row, $col++, $detail !== null
        ? ($yearsituationoptions[$detail->yearsituation] ?? $detail->yearsituation) : '');
    $sheet->write_string($row, $col++, $theme ? format_string($theme->name) : '');
    $sheet->write_string($row, $col++, $yesno($theme && $theme->mandatory));
    $sheet->write_string($row, $col++, $theme ? ($themeteachers[$theme->id] ?? '') : '');

    $sheet->write_string($row, $col++, $stagetypeoptions[$stagetype] ?? $stagetype);
    $sheet->write_string($row, $col++, (string) $entry->structure);
    $sheet->write_string($row, $col++, $yesno($entry->abroad));
    $sheet->write_string($row, $col++, (string) $entry->country);
    $writedate($sheet, $row, $col++, $entry->datestart);
    $writedate($sheet, $row, $col++, $entry->dateend);

    // Les plages ne sont détaillées que quand il y en a plusieurs : sinon elles répètent
    // exactement les deux colonnes de dates qui précèdent.
    $sheet->write_string($row, $col++, count($periodlabels) > 1 ? implode(' ; ', $periodlabels) : '');

    $sheet->write_number($row, $col++, (int) ($workdaycounts[$entry->id] ?? 0));
    $sheet->write_number($row, $col++, (int) $entry->declaredduration);
    $sheet->write_number($row, $col++, (int) $entry->retainedduration);
    $sheet->write_string($row, $col++, stage_status_label($entry->status));

    $sheet->write_string($row, $col++, stage_convention_status_label($entry->conventionstatus));
    $sheet->write_string($row, $col++, $template ? format_string($template->name) : '');
    $sheet->write_string($row, $col++, $detail !== null ? $actorname($detail->referentteacherid) : '');
    $writedate($sheet, $row, $col++, $entry->conventionrequesttime);
    $sheet->write_string($row, $col++, $actorname($entry->conventionteachervalidatedby));
    $writedate($sheet, $row, $col++, $entry->conventionteachervalidatetime);
    $sheet->write_string($row, $col++, $actorname($entry->conventioneditedby));
    $writedate($sheet, $row, $col++, $entry->conventionedittime);
    $sheet->write_string($row, $col++, $actorname($entry->conventionsignedby));
    $writedate($sheet, $row, $col++, $entry->conventionsigntime);
    $sheet->write_string($row, $col++, $actorname($entry->conventionrejectedby));
    $writedate($sheet, $row, $col++, $entry->conventionrejecttime);
    $sheet->write_string($row, $col++, (string) $entry->conventionrejectcomment);

    $writedate($sheet, $row, $col++, $detail !== null ? $detail->studentbirthdate : null);
    $sheet->write_string($row, $col++, $detailvalue('studentaddress'));
    $sheet->write_string($row, $col++, $detailvalue('studentphone'));
    $sheet->write_string($row, $col++, $detailvalue('hostaddress'));
    $sheet->write_string($row, $col++, $detailvalue('hostrepresentative'));
    $sheet->write_string($row, $col++, $detailvalue('hostrepresentativetitle'));
    $sheet->write_string($row, $col++, $detailvalue('hostservice'));
    $sheet->write_string($row, $col++, $detailvalue('hostphone'));
    $sheet->write_string($row, $col++, $detailvalue('hostemail'));
    $sheet->write_string($row, $col++, $detailvalue('hostlocation'));
    $sheet->write_string($row, $col++, $detailvalue('tutorname'));
    $sheet->write_string($row, $col++, $detailvalue('tutorfunction'));
    $sheet->write_string($row, $col++, $detailvalue('tutorphone'));
    $sheet->write_string($row, $col++, $detailvalue('tutoremail'));
    $sheet->write_string($row, $col++, $detail !== null ? $yesno($detail->nightpresence) : '');
    $sheet->write_string($row, $col++, $detail !== null ? $yesno($detail->sundaypresence) : '');
    $sheet->write_string($row, $col++, $detail !== null ? $yesno($detail->holidaypresence) : '');
    $sheet->write_string($row, $col++, $detail !== null ? $yesno($detail->homebased) : '');
    $sheet->write_string($row, $col++, $detailvalue('othermodality'));
    $sheet->write_string($row, $col++, $detailvalue('gratificationamount'));
    $sheet->write_string($row, $col++, $detail !== null ? $yesno($detail->hasleave) : '');
    $sheet->write_string($row, $col++, $detailvalue('leavedays'));
    $sheet->write_string($row, $col++, $detailvalue('leavemodalities'));

    // Les évaluations sont exportées en texte : les réponses aux questionnaires définis par
    // thématique, elles, ne tiennent pas en colonnes fixes et font l'objet de la feuille 3.
    $sheet->write_string($row, $col++, html_to_text((string) $entry->studentselfeval, 0, false));
    $sheet->write_string($row, $col++, $referentsbyuser[$entry->userid]);
    $sheet->write_string($row, $col++, $evaluator ? fullname($evaluator) : '');
    $writedate($sheet, $row, $col++, $entry->teachertime);
    $sheet->write_string($row, $col++, (string) $entry->teachereval);
    $sheet->write_string($row, $col++, (string) $entry->tutoreval);
    $writedate($sheet, $row, $col++, $entry->tutortime);
    $sheet->write_string($row, $col++, $yesno($entry->tutorbypassed));

    $sheet->write_string($row, $col++, $theme
        ? ($reportmodeoptions[stage_theme_report_mode($theme)] ?? '') : '');
    $sheet->write_number($row, $col++, count($reportfiles));
    $sheet->write_string($row, $col++, implode(' ; ', $reportfiles));

    $sheet->write_string($row, $col++, $actorname($entry->deveuserid));
    $writedate($sheet, $row, $col++, $entry->devetime);
    $sheet->write_string($row, $col++, (string) $entry->devecomment);
    $sheet->write_string($row, $col++, $actorname($entry->cancelledby));
    $writedate($sheet, $row, $col++, $entry->canceltime);
    $sheet->write_string($row, $col++, (string) $entry->cancelcomment);
    $writedate($sheet, $row, $col++, $entry->timecreated);
    $writedate($sheet, $row, $col++, $entry->timemodified);

    $row++;
}

// --- Feuille 3 : réponses aux questionnaires -------------------------------------------------

// Les questions étant définies par thématique, leurs réponses ne peuvent pas tenir en colonnes
// fixes de la feuille 2 : une ligne par réponse, avec de quoi la rattacher à sa saisie et la
// filtrer par thématique ou par type d'évaluation.
$sheet = $workbook->add_worksheet(get_string('exportanswers', 'mod_stage'));

$headers = [
    get_string('exportentryid', 'mod_stage'),
    get_string('student', 'mod_stage'),
    get_string('theme', 'mod_stage'),
    get_string('datestart', 'mod_stage'),
    get_string('dateend', 'mod_stage'),
    get_string('evaltype', 'mod_stage'),
    get_string('questionlabel', 'mod_stage'),
    get_string('answer', 'mod_stage'),
];
foreach ($headers as $col => $header) {
    $sheet->write_string(0, $col, $header, $headerformat);
}

$row = 1;
if (!empty($entryids)) {
    [$insql, $inparams] = $DB->get_in_or_equal($entryids, SQL_PARAMS_NAMED);
    $answerrecords = $DB->get_records_sql(
        "SELECT a.id, a.entryid, a.answertext, q.name AS questionname, q.evaltype, q.sortorder
           FROM {stage_answer} a
           JOIN {stage_question} q ON q.id = a.questionid
          WHERE a.entryid $insql
       ORDER BY a.entryid ASC, q.evaltype ASC, q.sortorder ASC", $inparams);

    foreach ($answerrecords as $answer) {
        $entry = $entries[$answer->entryid] ?? null;
        if (!$entry) {
            continue;
        }
        $student = $students[$entry->userid] ?? null;
        $theme = $themes[$entry->themeid] ?? null;

        $col = 0;
        $sheet->write_number($row, $col++, (int) $entry->id);
        $sheet->write_string($row, $col++, $student ? fullname($student) : '');
        $sheet->write_string($row, $col++, $theme ? format_string($theme->name) : '');
        $writedate($sheet, $row, $col++, $entry->datestart);
        $writedate($sheet, $row, $col++, $entry->dateend);
        $sheet->write_string($row, $col++, stage_evaltype_label($answer->evaltype));
        $sheet->write_string($row, $col++, format_string($answer->questionname));
        $sheet->write_string($row, $col++, (string) $answer->answertext);
        $row++;
    }
}

$workbook->close();
