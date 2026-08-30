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
 * Bilan de promotion en PDF, pour la DEVE : la validation, par étudiant, de l'année d'étude
 * courante du stage et des précédentes, les étudiants en défaut en tête (voir
 * stage_get_promotion_report()). Destiné à être imprimé ou diffusé en commission, là où l'export
 * Excel (export.php) sert à retravailler les données.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/classes/pdf/promotion_pdf.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:viewall', $context);

$report = stage_get_promotion_report($stage, $context);

// Une ligne par étudiant : nom, validation de chaque année retenue, puis les totaux.
$buildrows = function(array $rows) use ($report) {
    $out = [];
    foreach ($rows as $row) {
        $cells = [fullname($row->user)];
        foreach ($report->years as $year) {
            if (!isset($row->yearsdone[$year])) {
                // Aucun objectif défini pour cet étudiant cette année-là : ni validée, ni en
                // défaut. Un tiret, plutôt qu'une croix qui se lirait comme un manquement.
                $cells[] = '-';
            } else {
                $cells[] = $row->yearsdone[$year]
                    ? get_string('promotionyeardone', 'mod_stage')
                    : get_string('promotionyearfailed', 'mod_stage');
            }
        }
        $cells[] = (string) $row->progress->totalretained;
        $cells[] = $row->mandatorytotal > 0 ? "{$row->mandatorydone} / {$row->mandatorytotal}" : '-';
        // La colonne s'intitule « Années non validées » : les années y suffisent, un préfixe
        // « Manque : » répété à chaque ligne ne ferait que déborder de la colonne.
        $cells[] = $row->uptodate
            ? get_string('promotionuptodate', 'mod_stage')
            : implode(', ', array_map(function($year) {
                return stage_studyyear_label($year);
            }, $row->failedyears));
        $out[] = $cells;
    }
    return $out;
};

$failedrows = array_filter($report->rows, function($row) {
    return !$row->uptodate;
});
$uptodaterows = array_filter($report->rows, function($row) {
    return $row->uptodate;
});

$data = [
    'title' => get_string('promotionreport', 'mod_stage'),
    'subtitle' => format_string($course->fullname) . ' - ' . format_string($stage->name),
    'generatedon' => get_string('promotiongeneratedon', 'mod_stage',
        userdate(time(), get_string('strftimedatetime', 'langconfig'))),
    'summary' => get_string('promotionsummary', 'mod_stage', (object) [
        'total' => $report->total,
        'failed' => $report->failedcount,
        'uptodate' => $report->total - $report->failedcount,
    ]),
    'yearlabels' => array_map(function($year) {
        return stage_studyyear_label($year);
    }, $report->years),
    // Libellés courts : les colonnes chiffrées sont étroites, les intitulés complets employés
    // ailleurs dans le plugin y déborderaient.
    'columns' => [
        'student' => get_string('student', 'mod_stage'),
        'days' => get_string('promotioncoldays', 'mod_stage'),
        'themes' => get_string('promotioncolthemes', 'mod_stage'),
        'status' => get_string('promotionfailedyears', 'mod_stage'),
    ],
    'sections' => [
        [
            'heading' => get_string('promotionfailedheading', 'mod_stage'),
            'note' => get_string('promotionfailedheading_help', 'mod_stage'),
            'rows' => $buildrows($failedrows),
            'empty' => get_string('promotionnofailed', 'mod_stage'),
            'failed' => true,
        ],
        [
            'heading' => get_string('promotionuptodateheading', 'mod_stage'),
            'note' => '',
            'rows' => $buildrows($uptodaterows),
            'empty' => get_string('promotionnouptodate', 'mod_stage'),
            'failed' => false,
        ],
    ],
    'legend' => get_string('promotionlegend', 'mod_stage'),
];

$pdf = new \mod_stage\pdf\promotion_pdf('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->generate($data);

$filename = clean_filename(format_string($course->shortname) . '_' . format_string($stage->name) . '_bilan.pdf');
$pdf->Output($filename, 'D');
