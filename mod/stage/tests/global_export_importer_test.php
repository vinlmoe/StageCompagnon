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

use mod_stage\local\global_export_importer;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Lecture de vrais classeurs d'export global.
 *
 * @package mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_stage\local\global_export_importer
 */
final class global_export_importer_test extends \advanced_testcase {
    /**
     * Crée un classeur temporaire avec une feuille sans rapport avant les stages.
     *
     * @param array $rows
     * @return string
     */
    private function workbook(array $rows): string {
        $this->resetAfterTest();
        $book = new Spreadsheet();
        $book->getActiveSheet()->setTitle('Résumé')->fromArray([['Sans rapport'], ['Autre feuille']]);
        $book->createSheet()->setTitle('Stages')->fromArray($rows);
        $path = make_request_directory() . '/global.xlsx';
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
        return $path;
    }

    /**
     * Les alias français, les accents et les dates Excel sont reconnus.
     */
    public function test_reads_french_headers_and_excel_dates(): void {
        $path = $this->workbook([
            ['N° de stage', 'Adresse de courriel', 'Thématique', 'Date de début', 'Date de fin', 'Structure d’accueil'],
            [42, ' student@example.com ', 'Clinique', 46082, 46091, 'Clinique A'],
            [43, '', 'Clinique', 46082, 46091, 'Ignorée'],
        ]);
        $result = global_export_importer::read($path);
        $this->assertEmpty($result['warnings']);
        $this->assertCount(1, $result['records']);
        $record = $result['records'][0];
        $this->assertSame('42', $record->entryid);
        $this->assertSame('student@example.com', $record->email);
        $this->assertSame('Clinique', $record->theme);
        $this->assertSame('Clinique A', $record->structure);
        $this->assertSame(gmmktime(0, 0, 0, 3, 1, 2026), $record->datestart);
        $this->assertSame(gmmktime(0, 0, 0, 3, 10, 2026), $record->dateend);
        $this->assertSame(2, $record->line);
        $this->assertNull($record->studentbirthdate);
    }

    /**
     * Les colonnes anglaises réordonnées et les dates textuelles sont reconnues.
     */
    public function test_reads_reordered_english_headers_and_text_dates(): void {
        $path = $this->workbook([
            ['Unknown column', 'Theme', 'Email', 'Internship id', 'Start date', 'End date', 'Birth date'],
            ['Ignored', 'Clinic', 'student@example.com', 5, '2026-03-01', 'invalid', ''],
        ]);
        $record = global_export_importer::read($path)['records'][0];
        $this->assertSame('Clinic', $record->theme);
        $this->assertSame('5', $record->entryid);
        $this->assertSame(strtotime('2026-03-01'), $record->datestart);
        $this->assertNull($record->dateend);
        $this->assertNull($record->studentbirthdate);
    }

    /**
     * Un classeur sans les colonnes d'identification est refusé.
     */
    public function test_rejects_missing_required_header(): void {
        $path = $this->workbook([['Email', 'Theme'], ['student@example.com', 'Clinic']]);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('globalimportinvalid', 'mod_stage'));
        global_export_importer::read($path);
    }

    /**
     * Une feuille valide sans données produit une liste vide.
     */
    public function test_header_only_returns_empty_records(): void {
        $path = $this->workbook([['Internship id', 'Email', 'Theme']]);
        $this->assertSame(['records' => [], 'warnings' => []], global_export_importer::read($path));
    }

    /**
     * Les fichiers qui ne sont pas des classeurs sont refusés.
     */
    public function test_rejects_non_xlsx_file(): void {
        $this->resetAfterTest();
        $path = make_request_directory() . '/invalid.xlsx';
        file_put_contents($path, 'not a workbook');
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('historicalimportinvalidfile', 'mod_stage'));
        global_export_importer::read($path);
    }
}
