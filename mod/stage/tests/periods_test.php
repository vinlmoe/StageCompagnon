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

namespace mod_stage;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/stage/locallib.php');

/**
 * Tests des plages de dates : contrôle de cohérence (stage_validate_periods()) et statut de
 * source de vérité des dates du stage (stage_save_entry_periods(), stage_register_entry()).
 *
 * Ces règles ont été ajoutées après un incident réel où un chemin de création de saisie (le
 * formulaire d'enregistrement DEVE) gardait des champs de date sans jamais créer de plage
 * correspondante, laissant la base dans un état que le reste du code ne prévoyait plus : ces
 * tests fixent le comportement attendu pour empêcher une régression du même genre.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_validate_periods
 * @covers     ::stage_save_entry_periods
 * @covers     ::stage_register_entry
 * @covers     ::stage_extract_submitted_periods
 */
final class periods_test extends \advanced_testcase {

    /**
     * Une liste vide est refusée : les dates du stage en dépendent, il en faut au moins une.
     */
    public function test_validate_periods_requires_at_least_one(): void {
        $this->assertNotNull(stage_validate_periods([]));
    }

    /**
     * Une plage dont la fin précède le début est refusée.
     */
    public function test_validate_periods_rejects_end_before_start(): void {
        $error = stage_validate_periods([
            ['datestart' => make_timestamp(2026, 3, 20), 'dateend' => make_timestamp(2026, 3, 10)],
        ]);
        $this->assertNotNull($error);
    }

    /**
     * Deux plages disjointes, ou jointives (la seconde débute le lendemain de la fin de la
     * première), sont acceptées : elles ne partagent aucun jour.
     */
    public function test_validate_periods_accepts_disjoint_and_adjacent_periods(): void {
        $disjoint = [
            ['datestart' => make_timestamp(2026, 3, 1), 'dateend' => make_timestamp(2026, 3, 15)],
            ['datestart' => make_timestamp(2026, 4, 1), 'dateend' => make_timestamp(2026, 4, 10)],
        ];
        $this->assertNull(stage_validate_periods($disjoint));

        $adjacent = [
            ['datestart' => make_timestamp(2026, 3, 1), 'dateend' => make_timestamp(2026, 3, 15)],
            ['datestart' => make_timestamp(2026, 3, 16), 'dateend' => make_timestamp(2026, 3, 20)],
        ];
        $this->assertNull(stage_validate_periods($adjacent));
    }

    /**
     * Deux plages qui partagent ne serait-ce qu'un jour sont refusées, y compris quand l'une est
     * entièrement incluse dans l'autre, et quel que soit l'ordre de saisie.
     */
    public function test_validate_periods_rejects_any_overlap(): void {
        $sharedday = [
            ['datestart' => make_timestamp(2026, 3, 1), 'dateend' => make_timestamp(2026, 3, 15)],
            ['datestart' => make_timestamp(2026, 3, 15), 'dateend' => make_timestamp(2026, 3, 20)],
        ];
        $this->assertNotNull(stage_validate_periods($sharedday));

        $contained = [
            ['datestart' => make_timestamp(2026, 3, 1), 'dateend' => make_timestamp(2026, 3, 31)],
            ['datestart' => make_timestamp(2026, 3, 10), 'dateend' => make_timestamp(2026, 3, 12)],
        ];
        $this->assertNotNull(stage_validate_periods($contained));

        $unsorted = [
            ['datestart' => make_timestamp(2026, 5, 1), 'dateend' => make_timestamp(2026, 5, 10)],
            ['datestart' => make_timestamp(2026, 3, 1), 'dateend' => make_timestamp(2026, 3, 20)],
            ['datestart' => make_timestamp(2026, 3, 15), 'dateend' => make_timestamp(2026, 3, 25)],
        ];
        $this->assertNotNull(stage_validate_periods($unsorted));
    }

    /**
     * stage_extract_submitted_periods() ignore les lignes incomplètes (date_selector "optional"
     * non activé, qui soumet 0) plutôt que de produire une plage à moitié renseignée.
     */
    public function test_extract_submitted_periods_ignores_incomplete_rows(): void {
        $data = (object) [
            'perioddatestart' => [make_timestamp(2026, 3, 1), 0, make_timestamp(2026, 5, 1)],
            'perioddateend' => [make_timestamp(2026, 3, 15), make_timestamp(2026, 4, 10), 0],
        ];
        $periods = stage_extract_submitted_periods($data);

        $this->assertCount(1, $periods);
        $this->assertSame(make_timestamp(2026, 3, 1), $periods[0]['datestart']);
        $this->assertSame(make_timestamp(2026, 3, 15), $periods[0]['dateend']);
    }

    /**
     * stage_register_entry() crée systématiquement la plage correspondant aux dates reçues :
     * c'est ce qui garantit qu'une saisie créée par n'importe quel chemin (formulaire, import,
     * service web) a toujours au moins une plage, condition que le reste du code suppose acquise.
     */
    public function test_register_entry_creates_matching_period(): void {
        $this->resetAfterTest();
        /** @var \mod_stage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $course = $this->getDataGenerator()->create_course();
        $stage = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        $theme = $generator->create_theme($stage);
        $student = $this->getDataGenerator()->create_user();

        $entry = $generator->create_entry($stage, $student->id, $theme, [
            'datestart' => make_timestamp(2026, 3, 1),
            'dateend' => make_timestamp(2026, 3, 15),
        ]);

        $periods = stage_get_or_seed_entry_periods($entry);
        $this->assertCount(1, $periods);
        $this->assertEquals(make_timestamp(2026, 3, 1), $periods[0]->datestart);
        $this->assertEquals(make_timestamp(2026, 3, 15), $periods[0]->dateend);
    }

    /**
     * stage_save_entry_periods() aligne les dates de la saisie sur la première et la dernière
     * date couverte par les plages fournies, quel que soit leur ordre de saisie : les plages sont
     * la source de vérité des dates de la saisie, pas l'inverse.
     */
    public function test_save_entry_periods_derives_entry_dates(): void {
        $this->resetAfterTest();
        /** @var \mod_stage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $course = $this->getDataGenerator()->create_course();
        $stage = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        $theme = $generator->create_theme($stage);
        $student = $this->getDataGenerator()->create_user();
        $entry = $generator->create_entry($stage, $student->id, $theme);

        stage_save_entry_periods($entry->id, [
            ['datestart' => make_timestamp(2026, 4, 1), 'dateend' => make_timestamp(2026, 4, 10)],
            ['datestart' => make_timestamp(2026, 2, 1), 'dateend' => make_timestamp(2026, 2, 5)],
            ['datestart' => make_timestamp(2026, 6, 1), 'dateend' => make_timestamp(2026, 6, 20)],
        ]);

        global $DB;
        $updated = $DB->get_record('stage_entry', ['id' => $entry->id], '*', MUST_EXIST);
        $this->assertEquals(make_timestamp(2026, 2, 1), $updated->datestart);
        $this->assertEquals(make_timestamp(2026, 6, 20), $updated->dateend);
    }

    /**
     * Une liste de plages vide ne doit rien effacer : les dates existantes de la saisie sont
     * préservées plutôt que remises à zéro faute de remplacement.
     */
    public function test_save_entry_periods_with_empty_list_preserves_dates(): void {
        $this->resetAfterTest();
        /** @var \mod_stage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $course = $this->getDataGenerator()->create_course();
        $stage = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        $theme = $generator->create_theme($stage);
        $student = $this->getDataGenerator()->create_user();
        $entry = $generator->create_entry($stage, $student->id, $theme, [
            'datestart' => make_timestamp(2026, 10, 1),
            'dateend' => make_timestamp(2026, 10, 10),
        ]);

        stage_save_entry_periods($entry->id, []);

        global $DB;
        $updated = $DB->get_record('stage_entry', ['id' => $entry->id], '*', MUST_EXIST);
        $this->assertEquals(make_timestamp(2026, 10, 1), $updated->datestart);
        $this->assertEquals(make_timestamp(2026, 10, 10), $updated->dateend);
    }

    /**
     * Une plage invalide (fin avant début) au sein d'une liste par ailleurs valide est ignorée
     * silencieusement par stage_save_entry_periods(), qui ne fait que filtrer : le contrôle
     * bloquant relève de stage_validate_periods(), appelé séparément par les formulaires.
     */
    public function test_save_entry_periods_filters_invalid_rows(): void {
        $this->resetAfterTest();
        /** @var \mod_stage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $course = $this->getDataGenerator()->create_course();
        $stage = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        $theme = $generator->create_theme($stage);
        $student = $this->getDataGenerator()->create_user();
        $entry = $generator->create_entry($stage, $student->id, $theme);

        stage_save_entry_periods($entry->id, [
            ['datestart' => make_timestamp(2026, 10, 1), 'dateend' => make_timestamp(2026, 10, 10)],
            ['datestart' => make_timestamp(2026, 11, 20), 'dateend' => make_timestamp(2026, 11, 10)],
        ]);

        $periods = stage_get_entry_periods($entry->id);
        $this->assertCount(1, $periods);
    }
}
