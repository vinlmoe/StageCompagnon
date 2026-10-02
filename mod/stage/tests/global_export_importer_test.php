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
        $this->assertSame(make_timestamp(2026, 3, 1), $record->datestart);
        $this->assertSame(make_timestamp(2026, 3, 10), $record->dateend);
        $this->assertSame(2, $record->line);
        $this->assertNull($record->studentbirthdate);
    }

    /**
     * Régression : dans l'export anglais, la colonne « Email » du maître de stage, placée après
     * l'adresse de l'étudiant, prenait sa place ; toutes les lignes étaient alors ignorées.
     */
    public function test_first_matching_column_wins(): void {
        $path = $this->workbook([
            ['Internship ID', 'Student', 'Email address', 'Theme', 'Workplace tutor', 'Email'],
            [7, 'Zoé Dupont', 'student@example.com', 'Clinic', 'Dr X', ''],
        ]);
        $result = global_export_importer::read($path);
        $this->assertCount(1, $result['records']);
        $this->assertSame('student@example.com', $result['records'][0]->email);
    }

    /**
     * Régression : l'export global écrivait une colonne de courriel vide, ce qui rendait sa
     * restauration impossible (les étudiants y sont retrouvés par leur courriel).
     *
     * @covers ::stage_get_entry_users
     */
    public function test_entry_users_include_email(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/stage/locallib.php');
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['email' => 'zoe@example.com']);
        $users = stage_get_entry_users([(object) ['userid' => $user->id]]);
        $this->assertSame('zoe@example.com', $users[$user->id]->email);
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
     * Les dates Excel décrivent une heure locale (l'export les écrit dans le fuseau de
     * l'utilisateur) : relues dans ce même fuseau, elles redonnent les horodatages d'origine.
     */
    public function test_excel_dates_are_read_in_user_timezone(): void {
        $this->resetAfterTest();
        $this->setTimezone('Europe/Paris');
        $path = $this->workbook([
            ['Internship id', 'Email', 'Theme', 'Start date', 'End date', 'Last modified'],
            // 46082 = 01/03/2026, 46133.5 = 21/04/2026 12:00 (heure d'été).
            [1, 'student@example.com', 'Clinic', 46082, 46133, 46133.5],
        ]);
        $record = global_export_importer::read($path)['records'][0];
        $this->assertSame(make_timestamp(2026, 3, 1), $record->datestart);
        $this->assertSame(make_timestamp(2026, 4, 21), $record->dateend);
        $this->assertSame(make_timestamp(2026, 4, 21, 12), $record->timemodified);
    }

    /**
     * La colonne des plages de dates est relue (format numérique actuel, ou mois en toutes lettres
     * des exports antérieurs) ; une valeur illisible est ignorée en bloc.
     */
    public function test_periods_column_is_parsed(): void {
        $this->resetAfterTest();
        $this->assertSame([
            ['datestart' => make_timestamp(2026, 3, 2), 'dateend' => make_timestamp(2026, 3, 6)],
            ['datestart' => make_timestamp(2026, 3, 16), 'dateend' => make_timestamp(2026, 3, 20)],
        ], global_export_importer::periods('2/03/2026 - 6/03/2026 ; 16/03/2026 - 20/03/2026'));
        $this->assertSame([
            ['datestart' => make_timestamp(2026, 2, 2), 'dateend' => make_timestamp(2026, 2, 6)],
            ['datestart' => make_timestamp(2026, 8, 3), 'dateend' => make_timestamp(2026, 8, 7)],
        ], global_export_importer::periods('2 février 2026 - 6 février 2026 ; 3 August 2026 - 7 August 2026'));
        $this->assertSame([], global_export_importer::periods(''));
        $this->assertSame([], global_export_importer::periods('2/03/2026 - 6/03/2026 ; n\'importe quoi'));
        $this->assertSame([], global_export_importer::periods('10/03/2026 - 6/03/2026'));

        $path = $this->workbook([
            ['Internship id', 'Email', 'Theme', 'Start date', 'End date', 'Date ranges'],
            [1, 'student@example.com', 'Clinic', 46083, 46101, '2/03/2026 - 6/03/2026 ; 16/03/2026 - 20/03/2026'],
        ]);
        $this->assertCount(2, global_export_importer::read($path)['records'][0]->periods);
    }

    /**
     * Un stage en plusieurs plages est restauré avec ses plages, et non en une plage continue.
     */
    public function test_restore_keeps_every_period(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/stage/locallib.php');
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $stage = $gen->create_module('stage', ['course' => $gen->create_course()]);
        $theme = $gen->get_plugin_generator('mod_stage')->create_theme($stage);
        $user = $gen->create_user();
        $periods = global_export_importer::periods('2/03/2026 - 6/03/2026 ; 16/03/2026 - 20/03/2026');
        global_export_importer::restore($stage->id, [[
            'entry' => [
                'themeid' => $theme->id, 'userid' => $user->id, 'status' => STAGE_STATUS_ENREGISTRE,
                'conventionstatus' => STAGE_CONVENTION_NONE, 'declaredduration' => 10, 'retainedduration' => 0,
                'datestart' => make_timestamp(2026, 3, 2), 'dateend' => make_timestamp(2026, 3, 20),
                'timecreated' => 0, 'timemodified' => 0,
            ],
            'periods' => $periods,
        ]]);
        $entry = $DB->get_record('stage_entry', ['stageid' => $stage->id], '*', MUST_EXIST);
        $restored = array_values(stage_get_entry_periods($entry->id));
        $this->assertCount(2, $restored);
        $this->assertEquals(make_timestamp(2026, 3, 6), $restored[0]->dateend);
        $this->assertEquals(make_timestamp(2026, 3, 16), $restored[1]->datestart);
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
