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
 * Tests du bilan annuel d'un étudiant (stage_get_student_year_progress()) : validation d'une
 * thématique bornée à une plage d'années, mobilité internationale intégrée à l'année où elle est
 * due, et stages complémentaires exclus du décompte.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_get_student_year_progress
 * @covers     ::stage_get_student_abroad_progress
 */
final class year_progress_test extends \advanced_testcase {

    /**
     * Prépare un stage, une thématique obligatoire bornée de A2 à A4 (30 jours requis au total)
     * et un étudiant.
     *
     * @return array [stdClass $stage, stdClass $theme, stdClass $student]
     */
    private function prepare(): array {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $stage = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        /** @var \mod_stage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $theme = $generator->create_theme($stage, [
            'name' => 'Thématique pluriannuelle', 'mandatory' => 1, 'minstudyyear' => 2, 'maxstudyyear' => 4,
        ]);
        $student = $this->getDataGenerator()->create_user();
        stage_set_theme_duration($theme->id, 4, 30);

        return [$stage, $theme, $student];
    }

    /**
     * Une thématique bornée à une plage d'années n'est vérifiée qu'à sa dernière année, de façon
     * cumulative sur l'ensemble de la plage : un stage fait en A2 ne suffit pas seul, mais compte
     * pour le cumul vérifié en A4.
     */
    public function test_ranged_theme_checked_cumulatively_at_final_year(): void {
        [$stage, $theme, $student] = $this->prepare();
        /** @var \mod_stage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');

        $entry = $generator->create_entry($stage, $student->id, $theme, ['studyyear' => 2, 'declaredduration' => 15]);
        stage_apply_deve_validation($entry, 2, 15);

        $progress = stage_get_student_year_progress($stage->id, $student->id);

        // A2 figure dans le bilan (l'étudiant y a une saisie), mais la thématique n'y apparaît pas
        // comme un objectif de cette année : bornée à une plage, elle n'est due qu'à sa dernière
        // année (A4), pas aux années intermédiaires.
        $this->assertArrayHasKey(2, $progress);
        $this->assertCount(0, $progress[2]->themes);

        $entry2 = $generator->create_entry($stage, $student->id, $theme, ['studyyear' => 4, 'declaredduration' => 10]);
        stage_apply_deve_validation($entry2, 2, 10);

        $progress = stage_get_student_year_progress($stage->id, $student->id);
        $this->assertArrayHasKey(4, $progress);
        $themerow = $progress[4]->themes[0];
        // 15 (A2) + 10 (A4) = 25 sur 30 requis : pas encore atteint.
        $this->assertSame(25, $themerow->retained);
        $this->assertFalse($themerow->done);
        $this->assertFalse($progress[4]->done);

        $entry3 = $generator->create_entry($stage, $student->id, $theme, ['studyyear' => 4, 'declaredduration' => 5]);
        stage_apply_deve_validation($entry3, 2, 5);

        $progress = stage_get_student_year_progress($stage->id, $student->id);
        $this->assertSame(30, $progress[4]->themes[0]->retained);
        $this->assertTrue($progress[4]->themes[0]->done);
        $this->assertTrue($progress[4]->done);
    }

    /**
     * L'obligation de mobilité internationale n'est intégrée au bilan qu'à l'année avant laquelle
     * elle doit être satisfaite (stage->abroadbeforeyear), et sa non-satisfaction empêche la
     * validation globale de cette année-là même si le reste des objectifs de l'année est atteint.
     */
    public function test_abroad_requirement_gates_its_target_year(): void {
        global $DB;
        [$stage, $theme, $student] = $this->prepare();
        /** @var \mod_stage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');

        $DB->update_record('stage', (object) [
            'id' => $stage->id, 'requiredabroaddays' => 20, 'abroadbeforeyear' => 4,
        ]);
        stage_set_year_requirement($stage->id, 4, 0);

        // Le cumul de la thématique est complet (30/30), mais aucun jour à l'étranger.
        $entry = $generator->create_entry($stage, $student->id, $theme, ['studyyear' => 4, 'declaredduration' => 30]);
        stage_apply_deve_validation($entry, 2, 30);

        $progress = stage_get_student_year_progress($stage->id, $student->id);
        $this->assertNotNull($progress[4]->abroad);
        $this->assertFalse($progress[4]->abroad->done);
        $this->assertFalse($progress[4]->done, 'La mobilité manquante doit bloquer la validation de son année cible');

        $abroadentry = $generator->create_entry($stage, $student->id, $theme, [
            'studyyear' => 4, 'declaredduration' => 20, 'abroad' => 1, 'country' => 'Belgique',
        ]);
        stage_apply_deve_validation($abroadentry, 2, 20);

        $progress = stage_get_student_year_progress($stage->id, $student->id);
        $this->assertTrue($progress[4]->abroad->done);
        $this->assertTrue($progress[4]->done);
    }

    /**
     * Un stage complémentaire (EP) apparaît à titre informatif dans le bilan de son année mais ne
     * compte ni dans la durée retenue de la thématique ni dans la validation de l'année.
     */
    public function test_complementary_stage_excluded_from_mandatory_count(): void {
        [$stage, $theme, $student] = $this->prepare();
        /** @var \mod_stage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');

        $entry = $generator->create_entry($stage, $student->id, $theme, ['studyyear' => 4, 'declaredduration' => 30]);
        stage_apply_deve_validation($entry, 2, 30);

        $comp = $generator->create_entry($stage, $student->id, $theme, ['studyyear' => 4, 'declaredduration' => 5]);
        stage_set_entry_stagetype($comp->id, 'complementaire');
        stage_apply_deve_validation($comp, 2, 5);

        $progress = stage_get_student_year_progress($stage->id, $student->id);
        $this->assertSame(30, $progress[4]->themes[0]->retained);
        $this->assertTrue($progress[4]->done);
        $this->assertSame(5, $progress[4]->complementary);
    }
}
