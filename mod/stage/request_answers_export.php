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
 * Export des réponses au questionnaire de demande de stage (pré-stage) d'une thématique, pour la
 * DEVE et les enseignants responsables de cette thématique : une ligne par stage, une colonne par
 * question (voir stage_get_request_answers_export()), dans le format choisi (Excel, CSV, ODS...).
 *
 * Les filtres passés en paramètre sont ceux de la liste des stages de la thématique
 * (theme_stages.php) : l'export porte sur les stages que la liste affiche.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$id = required_param('id', PARAM_INT);
$themeid = required_param('themeid', PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$filterstatus = stage_optional_status_param('status');
$dataformat = optional_param('dataformat', 'excel', PARAM_ALPHA);

if (!array_key_exists($dataformat, core_plugin_manager::instance()->get_enabled_plugins('dataformat') ?? [])) {
    throw new moodle_exception('invalidparameter', 'debug');
}

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:view', $context);

$theme = $DB->get_record('stage_theme', ['id' => $themeid, 'stageid' => $stage->id], '*', MUST_EXIST);

// Mêmes droits que la liste des stages de la thématique : la DEVE, ou un responsable de la
// thématique, qui voit déjà ces réponses en validant les demandes.
if (!has_capability('mod/stage:viewall', $context) && !stage_is_theme_teacher($theme->id, $USER->id)) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('requestanswersexport', 'mod_stage'));
}

$entries = stage_get_filtered_entries(
    $stage->id,
    ['search' => $search, 'themeid' => $theme->id, 'status' => $filterstatus],
    'student',
    'ASC'
);
// Le courriel des étudiants n'apparaît sur aucune page du responsable de thématique : il n'est
// exporté que pour qui voit tous les stages (DEVE).
$export = stage_get_request_answers_export($theme, $entries, has_capability('mod/stage:viewall', $context));

$filename = clean_filename(get_string('requestanswersfilename', 'mod_stage', stage_plain_string($theme->name)));

// Excel : classeur écrit en flux par OpenSpout, comme le fait l'API dataformat, mais en typant
// chaque cellule. L'API dataformat convertit toute valeur en texte (en neutralisant les formules) :
// une note y deviendrait « 3 » en texte, à convertir à la main. Ici, nombres en cellules
// numériques, textes en cellules texte, jamais interprétées comme des formules même si elles
// commencent par « = ». L'écriture en flux tient dans la mémoire d'une page ordinaire même pour
// des dizaines de milliers de lignes, ce que la construction du classeur en mémoire
// (MoodleExcelWorkbook) ne permet pas.
if ($dataformat === 'excel') {
    core_php_time_limit::raise();
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
    $writer->addRow($cells($export['columns'], $bold));
    foreach ($export['rows'] as $row) {
        $writer->addRow($cells($row));
    }
    $writer->close();
    exit;
}

// Formats HTML et PDF : les cellules y sont écrites sans échappement par Moodle. Tableurs (CSV,
// ODS...) : une réponse commençant par « = », « + », « - » ou « @ » serait lue comme une
// formule ; les versions récentes de Moodle la neutralisent, pas les plus anciennes (4.3).
$supportshtml = \core\dataformat::get_format_instance($dataformat)->supports_html();
$coreescapes = method_exists(\core\dataformat::class, 'escape_spreadsheet_formula');
$escape = function ($value) use ($supportshtml, $coreescapes) {
    if (!is_string($value)) {
        return $value;
    }
    if ($supportshtml) {
        return s($value);
    }
    return !$coreescapes && preg_match('/^\s*[=+\-@]/', $value) ? "'" . $value : $value;
};
\core\dataformat::download_data(
    $filename,
    $dataformat,
    array_map($escape, array_values($export['columns'])),
    array_map(fn($row) => array_map($escape, array_values($row)), $export['rows'])
);
