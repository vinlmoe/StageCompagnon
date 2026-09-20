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

use mod_stage\local\csv_importer;

/**
 * Tests des imports CSV des stages et des enseignants.
 *
 * @package mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_stage\local\csv_importer
 */
final class csv_importer_test extends \advanced_testcase {
    /**
     * Prépare les utilisateurs inscrits et une thématique.
     *
     * @return array
     */
    private function fixture(): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/stage/locallib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $stage = $gen->create_module('stage', ['course' => $course]);
        $context = \context_module::instance($stage->cmid);
        $student = $gen->create_user(['email' => 'student@example.com', 'firstname' => 'Zoé', 'lastname' => 'Dupont']);
        $teacher = $gen->create_user(['email' => 'teacher@example.com']);
        $gen->enrol_user($student->id, $course->id, 'student');
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        $theme = $gen->get_plugin_generator('mod_stage')->create_theme($stage, ['name' => 'Clinique']);
        return [$stage, $context, $student, $teacher, $theme];
    }

    /**
     * Les deux séparateurs, la casse et les champs enregistrés sont couverts.
     */
    public function test_entries_accept_csv_delimiters_and_case(): void {
        global $DB;
        [$stage, $context, $student, , $theme] = $this->fixture();
        foreach ([';', ','] as $index => $separator) {
            $day = $index + 1;
            $csv = implode($separator, ['email', 'theme', 'structure', 'datestart', 'dateend', 'duration']) . "\n";
            $csv .= implode($separator, ['STUDENT@EXAMPLE.COM', 'CLINIQUE', 'Clinique A', "2026-03-0$day", '2026-03-10', 5]);
            $result = csv_importer::entries($stage, $context, $csv);
            $this->assertNull($result['error']);
            $this->assertSame(1, $result['results']->created);
            $this->assertEmpty($result['results']->errors);
            $entry = $DB->get_record('stage_entry', ['stageid' => $stage->id, 'datestart' => strtotime("2026-03-0$day")]);
            $this->assertEquals($student->id, $entry->userid);
            $this->assertEquals($theme->id, $entry->themeid);
            $this->assertSame('Clinique A', $entry->structure);
            $this->assertEquals(5, $entry->declaredduration);
            $this->assertEquals(STAGE_STATUS_ENREGISTRE, $entry->status);
        }
    }

    /**
     * Doublons dans le fichier et en base, avec poursuite après erreur.
     */
    public function test_entries_report_unknown_values_and_duplicates(): void {
        global $DB;
        [$stage, $context] = $this->fixture();
        $header = "email;theme;structure;datestart;dateend;duration\n";
        $row = "student@example.com;Clinique;A;2026-03-01;2026-03-10;5\n";
        $result = csv_importer::entries($stage, $context, $header . $row . $row
            . "unknown@example.com;Clinique;A;;;5\nstudent@example.com;Absent;A;;;5\n");
        $this->assertSame(1, $result['results']->created);
        $this->assertCount(3, $result['results']->errors);
        $this->assertSame(get_string('importerrorduplicate', 'mod_stage', (object) [
            'line' => 3, 'email' => 'student@example.com', 'theme' => 'Clinique',
        ]), $result['results']->errors[0]);
        $again = csv_importer::entries($stage, $context, $header . $row);
        $this->assertSame(0, $again['results']->created);
        $this->assertCount(1, $again['results']->errors);
        $this->assertEquals(1, $DB->count_records('stage_entry', ['stageid' => $stage->id]));
    }

    /**
     * Les dates facultatives restent nulles, les lignes sans identité sont ignorées.
     */
    public function test_entries_optional_dates_and_repeated_header(): void {
        global $DB;
        [$stage, $context] = $this->fixture();
        $header = "email;theme;structure;datestart;dateend;duration\n";
        $result = csv_importer::entries($stage, $context, $header . $header . ";;;;;\n"
            . "student@example.com;Clinique;;;;3\n");
        $this->assertSame(1, $result['results']->created);
        $this->assertEmpty($result['results']->errors);
        $entry = $DB->get_record('stage_entry', ['stageid' => $stage->id]);
        $this->assertNull($entry->datestart);
        $this->assertNull($entry->dateend);
    }

    /**
     * Le remplacement dédoublonne les référents et permet une suppression explicite.
     */
    public function test_teachers_replace_deduplicate_and_clear_assignments(): void {
        [$stage, $context, $student, $teacher] = $this->fixture();
        $old = $this->getDataGenerator()->create_user();
        stage_set_student_teachers($stage->id, $student->id, [$old->id]);
        $header = "studentemail;teacher1email;teacher2email\n";
        $result = csv_importer::teachers($stage, $context, $header
            . "STUDENT@EXAMPLE.COM;TEACHER@EXAMPLE.COM;teacher@example.com\n");
        $this->assertNull($result['error']);
        $this->assertSame(1, $result['results']->assigned);
        $this->assertEmpty($result['results']->errors);
        $assigned = stage_get_student_teachers($stage->id, $student->id);
        $this->assertCount(1, $assigned);
        $this->assertArrayHasKey($teacher->id, $assigned);
        csv_importer::teachers($stage, $context, $header . "student@example.com;;\n");
        $this->assertEmpty(stage_get_student_teachers($stage->id, $student->id));
    }

    /**
     * Les comptes non inscrits sont refusés, un référent reconnu reste attribué.
     */
    public function test_teachers_report_unenrolled_users_and_keep_recognized_teacher(): void {
        [$stage, $context, $student, $teacher] = $this->fixture();
        $this->getDataGenerator()->create_user(['email' => 'outsider@example.com']);
        $result = csv_importer::teachers(
            $stage,
            $context,
            "studentemail,teacher1email,teacher2email\n"
            . "outsider@example.com,teacher@example.com,\n"
            . "student@example.com,teacher@example.com,outsider@example.com\n"
        );
        $this->assertSame(1, $result['results']->assigned);
        $this->assertCount(2, $result['results']->errors);
        $this->assertArrayHasKey($teacher->id, stage_get_student_teachers($stage->id, $student->id));
    }

    /**
     * Les colonnes convention priment et le référent reste distinct du maître de stage.
     */
    public function test_stagevet_imports_convention_fields_and_period(): void {
        global $DB;
        [$stage, $context, $student, $teacher] = $this->fixture();
        $csv = "Email étudiant;Thème;Début stage;Fin stage;Début (convention);Fin (convention);"
            . "Jours effectifs;Jours déclarés;Année étudiant (convention);Année d'étude;"
            . "Organisme;Organisme (convention);Email tuteur;Nom maître de stage;Présence de nuit\n"
            . "STUDENT@EXAMPLE.COM;Clinique;01/01/2026;05/01/2026;01/03/2026;10/03/2026;"
            . "6;9;3ème année;2;Ancien;Clinique A;TEACHER@EXAMPLE.COM;Maître Test;Oui\n";
        $result = csv_importer::stagevet($stage, $context, $csv);
        $this->assertNull($result['error']);
        $this->assertSame(1, $result['results']->created);
        $this->assertEmpty($result['results']->errors);
        $entry = $DB->get_record('stage_entry', ['stageid' => $stage->id]);
        $this->assertEquals($student->id, $entry->userid);
        $this->assertEquals(3, $entry->studyyear);
        $this->assertEquals(6, $entry->declaredduration);
        $this->assertSame('Clinique A', $entry->structure);
        $this->assertEquals(STAGE_CONVENTION_SIGNVET, $entry->conventionstatus);
        $periods = stage_get_entry_periods($entry->id);
        $this->assertCount(1, $periods);
        $this->assertEquals(strtotime('2026-03-01'), reset($periods)->datestart);
        $this->assertEquals(strtotime('2026-03-10'), reset($periods)->dateend);
        $detail = stage_get_convention_detail($entry->id);
        $this->assertEquals($teacher->id, $detail->referentteacherid);
        $this->assertSame('Maître Test', $detail->tutorname);
        $this->assertEquals(1, $detail->nightpresence);
        $again = csv_importer::stagevet($stage, $context, $csv);
        $this->assertSame(0, $again['results']->created);
        $this->assertCount(1, $again['results']->errors);
    }

    /**
     * Les noms normalisés et les anciennes colonnes restent utilisables.
     */
    public function test_stagevet_falls_back_to_name_text_duration_and_studyyear(): void {
        global $DB;
        [$stage, $context, $student] = $this->fixture();
        $result = csv_importer::stagevet(
            $stage,
            $context,
            "Nom étudiant;Prénom étudiant;Thème (convention);Début stage;Fin stage;Durée (convention);Année d'étude\n"
            . "DUPONT;Zoe;Clinique;01/03/2026;10/03/2026;7 jours effectifs;4ème année\n"
        );
        $this->assertSame(1, $result['results']->created);
        $entry = $DB->get_record('stage_entry', ['stageid' => $stage->id]);
        $this->assertEquals($student->id, $entry->userid);
        $this->assertEquals(7, $entry->declaredduration);
        $this->assertEquals(4, $entry->studyyear);
    }

    /**
     * Les erreurs de dates et les valeurs inconnues ne créent aucune saisie.
     */
    public function test_stagevet_rejects_missing_reversed_dates_and_unknown_values(): void {
        global $DB;
        [$stage, $context] = $this->fixture();
        $result = csv_importer::stagevet(
            $stage,
            $context,
            "Email étudiant;Thème;Début stage;Fin stage\n"
            . "student@example.com;Clinique;;10/03/2026\n"
            . "student@example.com;Clinique;10/03/2026;01/03/2026\n"
            . "student@example.com;Clinique;invalide;10/03/2026\n"
            . "unknown@example.com;Clinique;01/03/2026;10/03/2026\n"
            . "unknown@example.com;Clinique;01/03/2026;10/03/2026\n"
            . "student@example.com;Absent;01/03/2026;10/03/2026\n"
        );
        $this->assertSame(0, $result['results']->created);
        $this->assertCount(3, $result['results']->errors);
        $this->assertSame(['unknown@example.com' => [5, 6]], $result['results']->unknownstudents);
        $this->assertSame(['Absent' => [7]], $result['results']->unknownthemes);
        $this->assertEquals(0, $DB->count_records('stage_entry'));
        $this->assertEquals(0, $DB->count_records('stage_convention_detail'));
    }

    /**
     * Les trois lecteurs signalent une entrée vide sans insertion.
     */
    public function test_empty_csv_returns_read_error(): void {
        global $DB;
        [$stage, $context] = $this->fixture();
        foreach (['entries', 'teachers', 'stagevet'] as $method) {
            $result = csv_importer::$method($stage, $context, '');
            $this->assertNotEmpty($result['error']);
            $this->assertNull($result['results']);
        }
        $this->assertEquals(0, $DB->count_records('stage_entry'));
    }
}
