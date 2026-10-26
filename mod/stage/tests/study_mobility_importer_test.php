<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_stage;

use mod_stage\local\study_mobility_importer;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/stage/locallib.php');

/**
 * Import Excel des mobilités d'études : lecture du classeur, validation et retrait, erreurs ligne
 * par ligne, et effet sur l'obligation de mobilité internationale.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_stage\local\study_mobility_importer
 */
final class study_mobility_importer_test extends \advanced_testcase {
    /** @var \stdClass Activité. */
    private $stage;

    /** @var \context_module Contexte du module. */
    private $context;

    /** @var \stdClass[] Étudiants inscrits, indexés par prénom. */
    private $students = [];

    /** @var \stdClass Membre de la DEVE. */
    private $deve;

    /**
     * Activité exigeant 20 jours à l'étranger avant la 5e année, et trois étudiants inscrits.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $this->stage = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        $DB->update_record('stage', (object) [
            'id' => $this->stage->id, 'requiredabroaddays' => 20, 'abroadbeforeyear' => 5,
        ]);
        $this->context = \context_module::instance($this->stage->cmid);
        foreach (['lea', 'hugo', 'ines'] as $name) {
            $this->students[$name] = $this->getDataGenerator()->create_user([
                'firstname' => ucfirst($name), 'email' => $name . '@example.com',
            ]);
            $this->getDataGenerator()->enrol_user($this->students[$name]->id, $course->id, 'student');
        }
        $this->deve = $this->getDataGenerator()->create_user();
    }

    /**
     * Enregistre un classeur temporaire dont la première feuille contient les lignes données.
     *
     * @param array $rows
     * @return string Chemin du classeur.
     */
    private function workbook(array $rows): string {
        $book = new Spreadsheet();
        $book->getActiveSheet()->setTitle('Mobilités')->fromArray($rows);
        $book->createSheet()->setTitle('Autre feuille')->fromArray([['Email', 'Lieu'], ['hugo@example.com', 'Ignoré']]);
        $path = make_request_directory() . '/mobilites.xlsx';
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
        return $path;
    }

    /**
     * Lit et importe un classeur.
     *
     * @param array $rows
     * @return \stdClass Résultat de study_mobility_importer::import().
     */
    private function import_rows(array $rows): \stdClass {
        $records = study_mobility_importer::read($this->workbook($rows));
        return study_mobility_importer::import($this->stage, $this->context, $records, $this->deve->id);
    }

    /**
     * Mobilité d'études enregistrée pour un étudiant, ou null.
     *
     * @param string $name
     * @return \stdClass|null
     */
    private function mobility(string $name): ?\stdClass {
        return stage_get_student_study_mobility($this->stage->id, $this->students[$name]->id);
    }

    /**
     * Intitulés français accentués, dans le désordre, adresse en majuscules et lignes vides :
     * seule la première feuille est lue, et chaque mobilité validée remplit l'obligation.
     */
    public function test_reads_first_sheet_and_validates_mobilities(): void {
        $result = $this->import_rows([
            ['Nom', 'Lieu de la mobilité', 'Adresse mail', "Mobilité d'études"],
            ['Martin', '  Université de Liège  ', 'LEA@example.com', 'Oui'],
            [null, null, null, null],
            ['Durand', 'Montréal', 'ines@example.com', 'x'],
        ]);

        $this->assertSame(2, $result->validated);
        $this->assertSame(0, $result->removed);
        $this->assertSame([], $result->errors);
        $this->assertSame('Université de Liège', $this->mobility('lea')->location);
        $this->assertEquals($this->deve->id, $this->mobility('lea')->validatedby);
        $this->assertSame('Montréal', $this->mobility('ines')->location);
        // La seconde feuille, qui cite Hugo, est ignorée.
        $this->assertNull($this->mobility('hugo'));

        $abroad = stage_get_student_abroad_progress($this->stage->id, $this->students['lea']->id);
        $this->assertTrue($abroad->done);
        $this->assertSame(0, $abroad->retained);
        $this->assertFalse(stage_get_student_abroad_progress($this->stage->id, $this->students['hugo']->id)->done);
    }

    /**
     * Les numéros de ligne affichés sont ceux du classeur, en tenant compte des lignes vides.
     */
    public function test_records_keep_workbook_line_numbers(): void {
        $records = study_mobility_importer::read($this->workbook([
            ['Email', 'Lieu'],
            [null, null],
            ['lea@example.com', 'Liège'],
        ]));

        $this->assertCount(1, $records);
        $this->assertSame(3, $records[0]->line);
        $this->assertSame('lea@example.com', $records[0]->email);
        $this->assertSame('Liège', $records[0]->location);
        $this->assertNull($records[0]->validated);
    }

    /**
     * « non » retire une validation existante ; un nouvel import d'une mobilité déjà validée en
     * met le lieu à jour sans créer de doublon.
     */
    public function test_no_removes_and_reimport_updates_location(): void {
        global $DB;
        stage_set_student_study_mobility($this->stage->id, $this->students['hugo']->id, true, 'Madrid', $this->deve->id);
        stage_set_student_study_mobility($this->stage->id, $this->students['lea']->id, true, 'Liège', $this->deve->id);

        $result = $this->import_rows([
            ['Email', 'Mobilité d’études', 'Lieu'],
            ['hugo@example.com', 'non', ''],
            ['lea@example.com', 'oui', 'Université de Liège (Belgique)'],
        ]);

        $this->assertSame(1, $result->validated);
        $this->assertSame(1, $result->removed);
        $this->assertNull($this->mobility('hugo'));
        $this->assertSame('Université de Liège (Belgique)', $this->mobility('lea')->location);
        $this->assertSame(1, $DB->count_records('stage_study_mobility', ['stageid' => $this->stage->id]));
    }

    /**
     * Sans colonne « Mobilité d'études », toute ligne portant un lieu valide la mobilité ; une
     * ligne sans lieu est ignorée, sans erreur ni retrait d'une validation existante.
     */
    public function test_without_validation_column_location_validates(): void {
        stage_set_student_study_mobility($this->stage->id, $this->students['ines']->id, true, 'Madrid', $this->deve->id);

        $result = $this->import_rows([
            ['Email', 'Lieu'],
            ['lea@example.com', 'Liège'],
            ['hugo@example.com', ''],
            ['ines@example.com', ''],
        ]);

        $this->assertSame(1, $result->validated);
        $this->assertSame(2, $result->skipped);
        $this->assertSame([], $result->errors);
        $this->assertNotNull($this->mobility('lea'));
        $this->assertNull($this->mobility('hugo'));
        $this->assertSame('Madrid', $this->mobility('ines')->location);
    }

    /**
     * « oui » sans lieu est signalé : la mobilité n'est pas validée.
     */
    public function test_yes_without_location_is_an_error(): void {
        $result = $this->import_rows([
            ['Email', 'Mobilité d’études', 'Lieu'],
            ['hugo@example.com', 'oui', ''],
        ]);

        $this->assertSame(0, $result->validated);
        $this->assertSame(
            [get_string('studymobilityimportnolocation', 'mod_stage', (object) [
                'line' => 2, 'email' => 'hugo@example.com', 'value' => 'oui',
            ])],
            $result->errors
        );
        $this->assertNull($this->mobility('hugo'));
    }

    /**
     * Chaque ligne fautive est signalée avec son numéro, sans empêcher l'import des autres :
     * étudiant inconnu ou non inscrit, adresse vide, valeur illisible, lieu manquant.
     */
    public function test_errors_are_reported_per_line(): void {
        $outsider = $this->getDataGenerator()->create_user(['email' => 'outsider@example.com']);

        $result = $this->import_rows([
            ['Email', 'Lieu', 'Mobilité'],
            ['inconnu@example.com', 'Rome', 'oui'],
            ['outsider@example.com', 'Rome', 'oui'],
            ['', 'Rome', 'oui'],
            ['hugo@example.com', 'Rome', 'peut-être'],
            ['ines@example.com', '', 'oui'],
            ['lea@example.com', 'Liège', ''],
        ]);

        $this->assertSame(1, $result->validated);
        $this->assertCount(5, $result->errors);
        $this->assertStringContainsString('inconnu@example.com', $result->errors[0]);
        $this->assertStringContainsString('outsider@example.com', $result->errors[1]);
        $this->assertSame(
            get_string('studymobilityimportbadvalue', 'mod_stage', (object) [
                'line' => 5, 'email' => 'hugo@example.com', 'value' => 'peut-être',
            ]),
            $result->errors[3]
        );
        $this->assertStringContainsString('ines@example.com', $result->errors[4]);
        $this->assertNull(stage_get_student_study_mobility($this->stage->id, $outsider->id));
        $this->assertNull($this->mobility('hugo'));
        $this->assertNull($this->mobility('ines'));
        $this->assertSame('Liège', $this->mobility('lea')->location);
    }

    /**
     * Le modèle proposé au téléchargement (intitulés de la langue courante) est relu tel quel.
     */
    public function test_template_headers_are_recognised(): void {
        $records = study_mobility_importer::parse_rows([
            [
                get_string('lastname'), get_string('firstname'), get_string('email'),
                get_string('studymobility', 'mod_stage'), get_string('studymobilityimportlocationheader', 'mod_stage'),
            ],
            ['Martin', 'Léa', 'lea@example.com', get_string('yes'), 'Liège'],
            ['Durand', 'Hugo', 'hugo@example.com', '', ''],
        ]);

        $result = study_mobility_importer::import($this->stage, $this->context, $records, $this->deve->id);
        $this->assertSame(1, $result->validated);
        $this->assertSame(1, $result->skipped);
        $this->assertSame([], $result->errors);
    }

    /**
     * Un classeur sans colonne Email est refusé.
     */
    public function test_rejects_workbook_without_email_column(): void {
        $path = $this->workbook([['Nom', 'Lieu'], ['Martin', 'Liège']]);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('studymobilityimportnoemail', 'mod_stage'));
        study_mobility_importer::read($path);
    }

    /**
     * Un fichier qui n'est pas un classeur XLSX est refusé.
     */
    public function test_rejects_non_xlsx_file(): void {
        $path = make_request_directory() . '/mobilites.xlsx';
        file_put_contents($path, 'pas un classeur');
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('historicalimportinvalidfile', 'mod_stage'));
        study_mobility_importer::read($path);
    }
}
