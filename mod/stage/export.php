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
 * - « Stages » : une ligne par saisie, le détail complet pour retravailler les données.
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

// L'évaluateur (stage_entry.teacherid) et les enseignants référents attribués à l'étudiant sont
// deux informations distinctes : la colonne unique de l'ancien export était intitulée
// « enseignants référents » mais contenait l'évaluateur. Les deux figurent désormais.
$evaluatorids = array_filter(array_unique(array_map(function($entry) {
    return $entry->teacherid;
}, $entries)));
$evaluators = $evaluatorids ? $DB->get_records_list('user', 'id', $evaluatorids, '', 'id, ' . implode(', ',
    \core_user\fields::get_name_fields())) : [];
$referentsbyuser = [];

$sheet = $workbook->add_worksheet(get_string('allstages', 'mod_stage'));

$headers = [
    get_string('student', 'mod_stage'),
    get_string('email'),
    get_string('studyyear', 'mod_stage'),
    get_string('theme', 'mod_stage'),
    get_string('mandatory', 'mod_stage'),
    get_string('conventionstagetype', 'mod_stage'),
    get_string('structure', 'mod_stage'),
    get_string('abroad', 'mod_stage'),
    get_string('country', 'mod_stage'),
    get_string('datestart', 'mod_stage'),
    get_string('dateend', 'mod_stage'),
    get_string('periods', 'mod_stage'),
    get_string('declaredduration', 'mod_stage'),
    get_string('retainedduration', 'mod_stage'),
    get_string('status', 'mod_stage'),
    get_string('conventionstatus', 'mod_stage'),
    get_string('referentteachers', 'mod_stage'),
    get_string('evaluatedby', 'mod_stage'),
    get_string('teachereval', 'mod_stage'),
    get_string('devecomment', 'mod_stage'),
];
foreach ($headers as $col => $header) {
    $sheet->write_string(0, $col, $header, $headerformat);
}

$exportdateformat = get_string('strftimedate', 'langconfig');
$stagetypeoptions = stage_convention_stagetype_options();

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

    $col = 0;
    $sheet->write_string($row, $col++, $student ? fullname($student) : '');
    $sheet->write_string($row, $col++, $student ? $student->email : '');
    $sheet->write_string($row, $col++, stage_studyyear_label($entry->studyyear));
    $sheet->write_string($row, $col++, $theme ? format_string($theme->name) : '');
    $sheet->write_string($row, $col++, ($theme && $theme->mandatory) ? get_string('yes') : get_string('no'));
    $sheet->write_string($row, $col++, $stagetypeoptions[$stagetype] ?? $stagetype);
    $sheet->write_string($row, $col++, (string) $entry->structure);
    $sheet->write_string($row, $col++, !empty($entry->abroad) ? get_string('yes') : get_string('no'));
    $sheet->write_string($row, $col++, (string) $entry->country);

    if ($entry->datestart) {
        $sheet->write_date($row, $col, $entry->datestart, $dateformat);
    } else {
        $sheet->write_string($row, $col, '');
    }
    $col++;
    if ($entry->dateend) {
        $sheet->write_date($row, $col, $entry->dateend, $dateformat);
    } else {
        $sheet->write_string($row, $col, '');
    }
    $col++;

    // Les plages ne sont détaillées que quand il y en a plusieurs : sinon elles répètent
    // exactement les deux colonnes de dates qui précèdent.
    $sheet->write_string($row, $col++, count($periodlabels) > 1 ? implode(' ; ', $periodlabels) : '');

    $sheet->write_number($row, $col++, (int) $entry->declaredduration);
    $sheet->write_number($row, $col++, (int) $entry->retainedduration);
    $sheet->write_string($row, $col++, stage_status_label($entry->status));
    $sheet->write_string($row, $col++, stage_convention_status_label($entry->conventionstatus));
    $sheet->write_string($row, $col++, $referentsbyuser[$entry->userid]);
    $sheet->write_string($row, $col++, $evaluator ? fullname($evaluator) : '');
    $sheet->write_string($row, $col++, (string) $entry->teachereval);
    $sheet->write_string($row, $col++, (string) $entry->devecomment);

    $row++;
}

$workbook->close();
