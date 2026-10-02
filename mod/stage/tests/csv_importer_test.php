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
     * Les comptes non inscrits sont refusés, et une ligne dont un référent n'est pas reconnu n'est
     * pas appliquée à moitié : l'attribution existante de l'étudiant est conservée.
     */
    public function test_teachers_unknown_teacher_keeps_existing_assignment(): void {
        [$stage, $context, $student, $teacher] = $this->fixture();
        $this->getDataGenerator()->create_user(['email' => 'outsider@example.com']);
        stage_set_student_teachers($stage->id, $student->id, [$teacher->id]);
        $result = csv_importer::teachers(
            $stage,
            $context,
            "studentemail,teacher1email,teacher2email\n"
            . "outsider@example.com,teacher@example.com,\n"
            . "student@example.com,typo@example.com,\n"
            . "student@example.com,teacher@example.com,outsider@example.com\n"
        );
        $this->assertSame(0, $result['results']->assigned);
        $this->assertCount(3, $result['results']->errors);
        $this->assertSame([(int) $teacher->id], array_map('intval', array_keys(
            stage_get_student_teachers($stage->id, $student->id)
        )));
    }

    /**
     * Les dates réécrites par Excel francophone (JJ/MM/AAAA) sont lues comme telles, jamais à
     * l'américaine ; une date illisible ou une plage inversée est signalée ; la plage et l'année
     * d'étude sont enregistrées.
     */
    public function test_entries_read_french_dates_and_report_invalid_ones(): void {
        global $DB;
        [$stage, $context, $student, , $theme] = $this->fixture();
        $result = csv_importer::entries($stage, $context, "email;theme;structure;datestart;dateend;duration;studyyear\n"
            . "student@example.com;Clinique;A;03/04/2026;15/04/2026;5;3\n"
            . "student@example.com;Clinique;B;31/02/2026;10/03/2026;5;\n"
            . "student@example.com;Clinique;C;15/03/26;20/03/2026;5;\n"
            . "student@example.com;Clinique;D;2026-05-10;2026-05-01;5;\n");
        $this->assertSame(1, $result['results']->created);
        $this->assertSame([
            get_string('importerrordate', 'mod_stage', (object) ['line' => 3, 'value' => '31/02/2026']),
            get_string('importerrordate', 'mod_stage', (object) ['line' => 4, 'value' => '15/03/26']),
            get_string('importerrordaterange', 'mod_stage', 5),
        ], $result['results']->errors);
        $entry = $DB->get_record('stage_entry', ['stageid' => $stage->id], '*', MUST_EXIST);
        $this->assertEquals(make_timestamp(2026, 4, 3), $entry->datestart);
        $this->assertEquals(make_timestamp(2026, 4, 15), $entry->dateend);
        $this->assertEquals(3, $entry->studyyear);
        $this->assertEquals($student->id, $entry->userid);
        $this->assertEquals($theme->id, $entry->themeid);
        $periods = stage_get_entry_periods($entry->id);
        $this->assertCount(1, $periods);
        $this->assertEquals(make_timestamp(2026, 4, 3), reset($periods)->datestart);
    }

    /**
     * Lecture stricte des dates : année sur quatre chiffres, date existante, heure ignorée.
     */
    public function test_parse_date_is_strict(): void {
        $this->resetAfterTest();
        $this->assertSame(make_timestamp(2026, 3, 15), csv_importer::parse_date('15/03/2026'));
        $this->assertSame(make_timestamp(2026, 3, 1), csv_importer::parse_date('1/3/2026'));
        $this->assertSame(make_timestamp(2026, 3, 15), csv_importer::parse_date('2026-03-15'));
        $this->assertSame(make_timestamp(2026, 3, 15), csv_importer::parse_date('15/03/2026 00:00'));
        $this->assertNull(csv_importer::parse_date('15/03/26'));
        $this->assertNull(csv_importer::parse_date('31/02/2026'));
        $this->assertNull(csv_importer::parse_date('03/15/2026'));
        $this->assertNull(csv_importer::parse_date('mars 2026'));
        $this->assertNull(csv_importer::parse_date(''));
    }

    /**
     * Le séparateur est déterminé sur l'en-tête : un « ; » dans un texte libre d'un fichier à
     * virgules ne fausse plus la lecture.
     */
    public function test_delimiter_is_detected_on_header_line(): void {
        global $DB;
        [$stage, $context, $student] = $this->fixture();
        $this->assertSame('comma', csv_importer::detect_delimiter("a,b,c\n\"x;y;z\",2,3\n"));
        $this->assertSame('semicolon', csv_importer::detect_delimiter("\xEF\xBB\xBFa;b;c\n1,5;2;3\n"));
        $this->assertSame('tab', csv_importer::detect_delimiter("a\tb\tc\n"));
        $this->assertSame('comma', csv_importer::detect_delimiter("seule\n"));

        $result = csv_importer::stagevet(
            $stage,
            $context,
            "Email étudiant,Thème,Début stage,Fin stage,Évaluation par l’étudiant\n"
            . "student@example.com,Clinique,01/03/2026,10/03/2026,\"Très bien ; à refaire ; merci\"\n"
        );
        $this->assertNull($result['error']);
        $this->assertSame(1, $result['results']->created);
        $this->assertSame(
            'Très bien ; à refaire ; merci',
            $DB->get_field('stage_entry', 'studentselfeval', ['userid' => $student->id])
        );
    }

    /**
     * Un CSV enregistré par Excel francophone (Windows-1252) garde ses accents : en-têtes et
     * thématiques accentuées restent reconnus.
     */
    public function test_windows_1252_file_is_converted(): void {
        global $DB;
        [$stage, $context, $student] = $this->fixture();
        $this->getDataGenerator()->get_plugin_generator('mod_stage')->create_theme($stage, ['name' => 'Équine']);
        $utf8 = "Email étudiant;Thème;Début stage;Fin stage\nstudent@example.com;Équine;01/03/2026;10/03/2026\n";
        $cp1252 = mb_convert_encoding($utf8, 'Windows-1252', 'UTF-8');
        $this->assertSame('WINDOWS-1252', csv_importer::detect_encoding($cp1252));
        $this->assertSame('UTF-8', csv_importer::detect_encoding($utf8));

        $result = csv_importer::stagevet($stage, $context, $cp1252);
        $this->assertSame(1, $result['results']->created);
        $this->assertEmpty($result['results']->unknownthemes);
        $this->assertEquals(1, $DB->count_records('stage_entry', ['userid' => $student->id]));

        $entries = csv_importer::entries($stage, $context, mb_convert_encoding(
            "email;theme;structure;datestart;dateend;duration\nstudent@example.com;Équine;Écurie;01/05/2026;02/05/2026;2\n",
            'Windows-1252',
            'UTF-8'
        ));
        $this->assertSame(1, $entries['results']->created);
        $this->assertTrue($DB->record_exists('stage_entry', ['structure' => 'Écurie']));
    }

    /**
     * Deux inscrits homonymes : la ligne n'est rattachée à aucun d'eux d'office, la DEVE choisit.
     * Un enseignant référent homonyme n'est pas non plus désigné au hasard.
     */
    public function test_stagevet_homonyms_are_left_to_deve(): void {
        global $DB;
        [$stage, $context, $student] = $this->fixture();
        $gen = $this->getDataGenerator();
        $twin = $gen->create_user(['email' => 'twin@example.com', 'firstname' => 'Zoé', 'lastname' => 'Dupont']);
        $gen->enrol_user($twin->id, $stage->course, 'student');
        foreach (['a', 'b'] as $suffix) {
            $teacher = $gen->create_user(['email' => "prof$suffix@example.com", 'firstname' => 'Paul', 'lastname' => 'Martin']);
            $gen->enrol_user($teacher->id, $stage->course, 'editingteacher');
        }
        $csv = "Étudiant;Thème;Début stage;Fin stage;Nom tuteur\n"
            . "DUPONT Zoé;Clinique;01/03/2026;10/03/2026;Paul Martin\n";

        $first = csv_importer::stagevet($stage, $context, $csv);
        $this->assertSame(0, $first['results']->created);
        $this->assertSame(['DUPONT Zoé' => [2]], $first['results']->unknownstudents);

        $second = csv_importer::stagevet($stage, $context, $csv, ['DUPONT Zoé' => $twin->id], [], [2]);
        $this->assertSame(1, $second['results']->created);
        $entry = $DB->get_record('stage_entry', ['stageid' => $stage->id], '*', MUST_EXIST);
        $this->assertEquals($twin->id, $entry->userid);
        $this->assertNull(stage_get_convention_detail($entry->id)->referentteacherid);
        $this->assertFalse($DB->record_exists('stage_entry', ['userid' => $student->id]));
    }

    /**
     * Deux lignes du même stage aux plages qui se recoupent (dates de convention, dates du tableau
     * de bord) ne créent qu'un stage.
     */
    public function test_stagevet_overlapping_rows_in_file_create_one_entry(): void {
        global $DB;
        [$stage, $context, $student] = $this->fixture();
        $result = csv_importer::stagevet(
            $stage,
            $context,
            "Email étudiant;Thème;Début stage;Fin stage\n"
            . "student@example.com;Clinique;01/03/2026;10/03/2026\n"
            . "student@example.com;Clinique;02/03/2026;12/03/2026\n"
            . "student@example.com;Clinique;01/06/2026;10/06/2026\n"
        );
        $this->assertSame(2, $result['results']->created);
        $this->assertCount(1, $result['results']->errors);
        $this->assertEquals(2, $DB->count_records('stage_entry', ['userid' => $student->id]));
    }

    /**
     * Les deux arbitrages en attente (étudiant non rapproché, doublon probable) survivent l'un à
     * l'autre, quel que soit l'ordre dans lequel la DEVE les tranche.
     */
    public function test_stagevet_pending_arbitrations_survive_each_other(): void {
        global $DB;
        [$stage, $context, $student, , $theme] = $this->fixture();
        $other = $this->getDataGenerator()->create_user(['firstname' => 'Louise', 'lastname' => 'Martineau']);
        $this->getDataGenerator()->enrol_user($other->id, $stage->course, 'student');
        // Stage historique sans dates : la ligne 2 lui ressemble.
        $histid = stage_register_entry($stage->id, $student->id, $theme->id, 'Ancien', null, null, 5);
        $csv = "Étudiant;Email étudiant;Thème;Début stage;Fin stage\n"
            . ";student@example.com;Clinique;01/03/2026;10/03/2026\n"
            . "MARTIN Lou;;Clinique;01/04/2026;10/04/2026\n";

        $first = csv_importer::stagevet($stage, $context, $csv);
        $this->assertSame(0, $first['results']->created);
        $this->assertSame([2], array_keys($first['results']->probableduplicates));
        $this->assertSame(['MARTIN Lou' => [3]], $first['results']->unknownstudents);
        $pending = csv_importer::pending_lines($first['results']);
        $this->assertSame([2, 3], $pending);

        // Doublon tranché d'abord : l'étudiant non rapproché reste en attente.
        $second = csv_importer::stagevet($stage, $context, $csv, [], [2 => (string) $histid], $pending);
        $this->assertSame(1, $second['results']->updated + $second['results']->unchanged);
        $this->assertSame(['MARTIN Lou' => [3]], $second['results']->unknownstudents);
        $pending = csv_importer::pending_lines($second['results']);
        $this->assertSame([3], $pending);

        // Puis l'étudiant : la ligne 3 est créée, la ligne 2 n'est pas rejouée.
        $third = csv_importer::stagevet(
            $stage,
            $context,
            $csv,
            ['MARTIN Lou' => $other->id],
            [2 => (string) $histid],
            $pending
        );
        $this->assertSame(1, $third['results']->created);
        $this->assertSame(0, $third['results']->updated + $third['results']->unchanged);
        $this->assertSame([], csv_importer::pending_lines($third['results']));
        $this->assertEquals(1, $DB->count_records('stage_entry', ['userid' => $student->id]));
        $this->assertEquals(1, $DB->count_records('stage_entry', ['userid' => $other->id]));
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
        // Réimporter le même fichier ne crée pas de doublon : le stage est reconnu, et laissé
        // inchangé faute d'information nouvelle.
        $again = csv_importer::stagevet($stage, $context, $csv);
        $this->assertSame(0, $again['results']->created);
        $this->assertSame(1, $again['results']->unchanged);
        $this->assertEmpty($again['results']->errors);
        $this->assertEquals(1, $DB->count_records('stage_entry', ['stageid' => $stage->id]));
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
     * Sans convention PDF analysée, la colonne « Étudiant » du tableau de bord suffit.
     */
    public function test_stagevet_falls_back_to_dashboard_student_column(): void {
        global $DB;
        [$stage, $context, $student] = $this->fixture();
        // Export réalisé avant analyse des conventions : les colonnes issues du PDF sont vides et
        // « Étudiant » porte le nom dans l'ordre « Nom Prénom ».
        $result = csv_importer::stagevet(
            $stage,
            $context,
            "Étudiant;Nom étudiant;Prénom étudiant;Email étudiant;Thème;Début stage;Fin stage;Durée (convention)\n"
            . "DUPONT Zoe;;;;Clinique;01/03/2026;10/03/2026;7 jours effectifs\n"
        );
        $this->assertSame(1, $result['results']->created);
        $this->assertEmpty($result['results']->unknownstudents);
        $this->assertEquals($student->id, $DB->get_field('stage_entry', 'userid', ['stageid' => $stage->id]));
    }

    /**
     * Une ligne sans étudiant identifiable est signalée, jamais ignorée en silence.
     */
    public function test_stagevet_reports_lines_without_any_student_identifier(): void {
        global $DB;
        [$stage, $context] = $this->fixture();
        $result = csv_importer::stagevet(
            $stage,
            $context,
            "Étudiant;Nom étudiant;Prénom étudiant;Email étudiant;Thème;Début stage;Fin stage\n"
            . ";;;;Clinique;01/03/2026;10/03/2026\n"
            . "\n"
        );
        $this->assertSame(0, $result['results']->created);
        // La ligne vide finale reste ignorée ; celle sans identifiant est remontée avec son numéro.
        $this->assertSame(
            [get_string('importstagevetunnamedstudent', 'mod_stage', 2) => [2]],
            $result['results']->unknownstudents
        );
        $this->assertEquals(0, $DB->count_records('stage_entry'));
    }

    /**
     * La DEVE rattache elle-même un libellé introuvable, sans réimporter les lignes déjà traitées.
     */
    public function test_stagevet_imports_lines_resolved_by_deve(): void {
        global $DB;
        [$stage, $context, $student] = $this->fixture();
        $csv = "Étudiant;Thème;Début stage;Fin stage\n"
            . "DUPONT Zoe;Clinique;01/03/2026;10/03/2026\n"
            . "MARTIN Lou;Clinique;01/04/2026;10/04/2026\n";

        $first = csv_importer::stagevet($stage, $context, $csv);
        $this->assertSame(1, $first['results']->created);
        $this->assertSame(['MARTIN Lou' => [3]], $first['results']->unknownstudents);

        $second = csv_importer::stagevet($stage, $context, $csv, ['MARTIN Lou' => $student->id]);
        $this->assertSame(1, $second['results']->created);
        $this->assertEmpty($second['results']->unknownstudents);
        // La ligne 2, déjà importée, n'est ni recréée ni signalée comme doublon.
        $this->assertEmpty($second['results']->errors);
        $this->assertEquals(2, $DB->count_records('stage_entry', ['userid' => $student->id]));
    }

    /**
     * Un identifiant désignant quelqu'un qui n'est pas inscrit au cours est refusé.
     */
    public function test_stagevet_rejects_resolution_to_a_non_enrolled_user(): void {
        global $DB;
        [$stage, $context] = $this->fixture();
        $outsider = $this->getDataGenerator()->create_user();
        $result = csv_importer::stagevet(
            $stage,
            $context,
            "Étudiant;Thème;Début stage;Fin stage\nMARTIN Lou;Clinique;01/04/2026;10/04/2026\n",
            ['MARTIN Lou' => $outsider->id]
        );
        $this->assertSame(0, $result['results']->created);
        $this->assertSame(['MARTIN Lou' => [2]], $result['results']->unknownstudents);
        $this->assertEquals(0, $DB->count_records('stage_entry'));
    }

    /**
     * La durée retombe sur la plage de dates quand aucun libellé n'est exprimé en jours.
     */
    public function test_stagevet_duration_falls_back_from_label_to_period(): void {
        global $DB;
        [$stage, $context, $student] = $this->fixture();
        // « 4 semaines » est le libellé du tableau de bord : le convertir supposerait de trancher
        // entre jours calendaires et jours ouvrés, la plage de dates prend donc le relais.
        $result = csv_importer::stagevet(
            $stage,
            $context,
            "Étudiant;Thème;Début stage;Fin stage;Durée\n"
            . "DUPONT Zoe;Clinique;01/03/2026;12/03/2026;4 semaines\n"
        );
        $this->assertSame(1, $result['results']->created);
        $this->assertEquals(12, $DB->get_field('stage_entry', 'declaredduration', ['userid' => $student->id]));

        // Un libellé déjà exprimé en jours est en revanche retenu tel quel.
        $this->assertSame(10, csv_importer::parse_duration('10 jours'));
        $this->assertSame(0, csv_importer::parse_duration('4 semaines'));
        $this->assertSame(0, csv_importer::parse_duration('2 mois'));
        $this->assertSame(0, csv_importer::count_period_days(null, null));
    }

    /**
     * Les compteurs en jours de la convention restent prioritaires sur ce repli.
     */
    public function test_stagevet_duration_prefers_convention_day_counts(): void {
        global $DB;
        [$stage, $context, $student] = $this->fixture();
        $result = csv_importer::stagevet(
            $stage,
            $context,
            "Étudiant;Thème;Début stage;Fin stage;Durée;Jours effectifs\n"
            . "DUPONT Zoe;Clinique;01/03/2026;12/03/2026;4 semaines;6\n"
        );
        $this->assertSame(1, $result['results']->created);
        $this->assertEquals(6, $DB->get_field('stage_entry', 'declaredduration', ['userid' => $student->id]));
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
