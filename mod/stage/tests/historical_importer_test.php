<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_stage;

use mod_stage\local\historical_importer;

/**
 * Tests de transformation du suivi Excel historique en une ligne par stage.
 *
 * @package mod_stage
 * @covers \mod_stage\local\historical_importer
 */
final class historical_importer_test extends \advanced_testcase {

    /**
     * La colonne Email est trouvée par son intitulé, même si son ajout décale les blocs.
     */
    public function test_mandatory_blocks_are_expanded_with_email_and_year(): void {
        $rows = [
            0 => [5 => "Clinique début de cursus\n2-5 jours", 9 => "Élevage laitier\n10 jours"],
            1 => [0 => 'NOM', 1 => 'Prénom', 2 => 'Email', 3 => 'NOM', 4 => 'Prénom',
                5 => 'Lieu et date', 6 => 'Validation par l’ER', 7 => 'Durée (en jours)', 8 => 'Année d’études',
                9 => 'Lieu et date', 10 => 'Validation par l’ER', 11 => 'Durée (en jours)', 12 => 'Année d’études'],
            2 => [0 => 'DUPONT', 1 => 'Zoé', 2 => 'zoe@example.test',
                5 => '10 au 14/04/2023 Clinique A', 6 => 1, 7 => 5, 8 => 2,
                9 => 'Ferme B', 10 => 0, 11 => 10, 12 => 2],
        ];

        $result = historical_importer::parse_rows($rows, false, 'Stages - validation ER');

        $this->assertCount(1, $result['records']);
        $record = reset($result['records']);
        $this->assertSame('zoe@example.test', $record->email);
        $this->assertSame('Clinique début de cursus', $record->themename);
        $this->assertSame(5, $record->duration);
        $this->assertSame(2, $record->studyyear);
        $this->assertSame('obligatoire', $record->stagetype);
        $this->assertNotEmpty($record->datestart);
        $this->assertNotEmpty($record->dateend);
    }

    /**
     * Le suivi 2025-26 intitule la première colonne de chaque bloc « Convention enregistrée ».
     */
    public function test_mandatory_blocks_with_convention_header_are_imported(): void {
        $rows = [
            0 => [5 => "Clinique début de cursus\n2j min - 5j max / en A2"],
            1 => [0 => 'NOM', 1 => 'Prénom', 2 => 'Email', 3 => 'NOM', 4 => 'Prénom',
                5 => 'Convention enregistrée', 6 => 'Validation par l’ER',
                7 => 'Durée (en jours)', 8 => 'Année d’études'],
            2 => [0 => 'DUPONT', 1 => 'Zoé', 2 => 'zoe@example.test',
                5 => 'Cabinet vétérinaire - du 10 au 14/04/2025', 6 => 1, 7 => 5, 8 => 2],
        ];

        $result = historical_importer::parse_rows($rows, false, 'STAGE - Validation ER');

        $this->assertCount(1, $result['records']);
        $record = reset($result['records']);
        $this->assertSame('Clinique début de cursus', $record->themename);
        $this->assertSame('Cabinet vétérinaire - du 10 au 14/04/2025', $record->structure);
        $this->assertSame(5, $record->duration);
        $this->assertSame('obligatoire', $record->stagetype);
    }

    /**
     * Les stages EP validés deviennent complémentaires et les EP non validés restent ignorés.
     */
    public function test_ep_stage_is_imported_as_complementary(): void {
        $rows = [
            0 => [5 => 'EP Stages - n°1'],
            1 => [0 => 'NOM', 1 => 'Prénom', 2 => 'Email', 5 => 'Lieu du stage',
                6 => 'Validé par Enseignant Référent', 7 => 'Nombre de jours de stage',
                8 => 'Nombre crédits', 9 => 'Année d’études'],
            2 => [0 => 'DUPONT', 1 => 'Zoé', 2 => 'zoe@example.test', 5 => 'Clinique A',
                6 => 1, 7 => 8, 8 => 2.5, 9 => 4],
        ];

        $result = historical_importer::parse_rows($rows, true, 'Stage EP - validation ER');

        $this->assertCount(1, $result['records']);
        $record = reset($result['records']);
        $this->assertSame('EP Stages', $record->themename);
        $this->assertSame('complementaire', $record->stagetype);
        $this->assertSame(8, $record->duration);
        $this->assertSame(4, $record->studyyear);
    }

    /**
     * Sans colonne Email, aucune donnée ne peut être associée silencieusement par le seul nom.
     */
    public function test_missing_email_column_blocks_sheet(): void {
        $rows = [
            0 => [4 => 'Clinique'],
            1 => [0 => 'NOM', 1 => 'Prénom', 4 => 'Lieu et date', 5 => 'Validation par l’ER',
                6 => 'Durée (en jours)', 7 => 'Année d’études'],
        ];

        $result = historical_importer::parse_rows($rows, false, 'Stages - validation ER');

        $this->assertEmpty($result['records']);
        $this->assertNotEmpty($result['warnings']);
    }
}
