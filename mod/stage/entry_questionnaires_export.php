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
 * Export de tous les questionnaires d'un stage (demande, auto-évaluation, évaluation de
 * l'enseignant référent, évaluation du maître de stage) en Excel ou en PDF.
 *
 * Ouvert à la DEVE, à l'enseignant référent de l'étudiant et à l'enseignant responsable de la
 * thématique du stage (voir stage_can_export_entry_questionnaires()), depuis la page de détail
 * du stage.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$id = required_param('id', PARAM_INT);
$entryid = required_param('entryid', PARAM_INT);
$format = optional_param('format', 'excel', PARAM_ALPHA);
if (!in_array($format, ['excel', 'pdf'], true)) {
    throw new moodle_exception('invalidparameter', 'debug');
}

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

$entry = $DB->get_record('stage_entry', ['id' => $entryid, 'stageid' => $stage->id], '*', MUST_EXIST);
if (!stage_can_export_entry_questionnaires($stage, $entry, $context, $USER->id)) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('entryquestionnairesexport', 'mod_stage'));
}

$theme = $DB->get_record('stage_theme', ['id' => $entry->themeid]);
$student = $DB->get_record('user', ['id' => $entry->userid], '*', MUST_EXIST);
$sections = stage_get_entry_questionnaires($stage, $entry, $theme);
$identity = stage_get_entry_questionnaires_identity($entry, $theme);
$filename = clean_filename(get_string('entryquestionnairesfilename', 'mod_stage', (object) [
    'student' => stage_plain_string(fullname($student)),
    'entryid' => $entry->id,
]));

if ($format === 'pdf') {
    require_once($CFG->dirroot . '/mod/stage/classes/pdf/questionnaires_pdf.php');
    $pdf = new \mod_stage\pdf\questionnaires_pdf('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->generate([
        'title' => get_string('entryquestionnairestitle', 'mod_stage', fullname($student)),
        'generatedon' => get_string('entryquestionnairesgeneratedon', 'mod_stage', userdate(time())),
        'identity' => $identity,
        'sections' => $sections,
        'labels' => [
            'question' => get_string('entryquestionnairequestion', 'mod_stage'),
            'answer' => get_string('entryquestionnaireanswer', 'mod_stage'),
            'comment' => get_string('entryquestionnairecomment', 'mod_stage'),
            'respondent' => get_string('entryquestionnairerespondent', 'mod_stage'),
            'noanswer' => get_string('noanswer', 'mod_stage'),
            'empty' => get_string('entryquestionnairesnone', 'mod_stage'),
        ],
    ]);
    $pdf->Output($filename . '.pdf', 'D');
    exit;
}

// Excel : écrit en flux par OpenSpout, cellules typées (nombres en cellules numériques, textes en
// cellules texte, jamais interprétées comme des formules), comme request_answers_export.php.
\core\session\manager::write_close();
$writer = new \OpenSpout\Writer\XLSX\Writer();
if (method_exists($writer->getOptions(), 'setTempFolder')) {
    $writer->getOptions()->setTempFolder(make_request_directory());
}
$writer->openToBrowser($filename . '.xlsx');
$bold = (new \OpenSpout\Common\Entity\Style\Style())->setFontBold();
$cells = function (array $values, $style = null) {
    return new \OpenSpout\Common\Entity\Row(array_map(function ($value) use ($style) {
        if (is_int($value) || is_float($value)) {
            return new \OpenSpout\Common\Entity\Cell\NumericCell($value, $style);
        }
        if ($value === null || $value === '') {
            return new \OpenSpout\Common\Entity\Cell\EmptyCell(null, $style);
        }
        return new \OpenSpout\Common\Entity\Cell\StringCell((string) $value, $style);
    }, array_values($values)));
};

// Identité du stage en tête, puis le tableau des réponses.
foreach ($identity as $label => $value) {
    $writer->addRow($cells([$label, $value]));
}
$writer->addRow($cells(['']));
$table = stage_get_entry_questionnaires_table($sections);
$writer->addRow($cells($table['columns'], $bold));
foreach ($table['rows'] as $row) {
    $writer->addRow($cells($row));
}
$writer->close();
exit;
