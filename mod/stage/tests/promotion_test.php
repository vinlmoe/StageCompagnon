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
 * Tests du bilan de promotion (stage_get_promotion_report()) : ne retient que les années échues,
 * et classe les étudiants en défaut en tête, du plus en retard au moins en retard.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_get_promotion_years
 * @covers     ::stage_get_promotion_report
 */
final class promotion_test extends \advanced_testcase {

    /**
     * Seule l'année d'étude courante du stage et les précédentes sont retenues dans le bilan :
     * les objectifs des années à venir ne sont pas encore exigibles.
     */
    public function test_only_elapsed_years_are_kept(): void {
        $this->resetAfterTest();
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $stage = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        $DB->update_record('stage', (object) ['id' => $stage->id, 'currentstudyyear' => 3]);

        $rows = [
            (object) ['yearprogress' => [
                2 => (object) ['studyyear' => 2, 'done' => true],
                3 => (object) ['studyyear' => 3, 'done' => true],
                5 => (object) ['studyyear' => 5, 'done' => false],
            ]],
        ];

        $years = stage_get_promotion_years($stage, $rows);

        $this->assertSame([2, 3], $years);
    }

    /**
     * Tant que l'année courante n'est pas renseignée (0), toutes les années sur lesquelles la
     * promotion a des objectifs sont retenues : c'est le repli, sans quoi le bilan serait vide.
     */
    public function test_all_years_kept_when_current_year_unset(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $stage = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        // currentstudyyear vaut 0 par défaut à la création.

        $rows = [
            (object) ['yearprogress' => [
                2 => (object) ['studyyear' => 2, 'done' => true],
                6 => (object) ['studyyear' => 6, 'done' => false],
            ]],
        ];

        $this->assertSame([2, 6], stage_get_promotion_years($stage, $rows));
    }

    /**
     * Les étudiants en défaut sont en tête, classés du plus en retard (le plus d'années non
     * validées, puis le retard le plus ancien) au moins en retard ; les étudiants à jour suivent,
     * par ordre alphabétique.
     */
    public function test_failed_students_ranked_first_by_severity(): void {
        $this->resetAfterTest();
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $stage = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        $cm = get_coursemodule_from_instance('stage', $stage->id);
        $context = \context_module::instance($cm->id);
        $DB->update_record('stage', (object) ['id' => $stage->id, 'currentstudyyear' => 4]);
        /** @var \mod_stage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $theme = $generator->create_theme($stage, ['mandatory' => 1, 'minstudyyear' => 0, 'maxstudyyear' => 0]);
        stage_set_theme_duration($theme->id, 0, 0);

        // Zoe Alpha : validera les trois années (10 jours requis chacune, stage-wide).
        $alpha = $this->getDataGenerator()->create_user(['firstname' => 'Zoe', 'lastname' => 'Alpha']);
        // Bob Beta : en défaut sur les trois années.
        $beta = $this->getDataGenerator()->create_user(['firstname' => 'Bob', 'lastname' => 'Beta']);
        // Ivy Zeta : en défaut sur une seule année, la plus ancienne (A2).
        $zeta = $this->getDataGenerator()->create_user(['firstname' => 'Ivy', 'lastname' => 'Zeta']);
        // Eve Epsilon : en défaut sur une seule année, plus récente (A4) -> après Zeta.
        $epsilon = $this->getDataGenerator()->create_user(['firstname' => 'Eve', 'lastname' => 'Epsilon']);

        foreach ([$alpha, $beta, $zeta, $epsilon] as $user) {
            $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        }
        foreach ([2, 3, 4] as $year) {
            stage_set_year_requirement($stage->id, $year, 10);
        }

        // Alpha : les trois années complètes -> à jour.
        foreach ([2, 3, 4] as $year) {
            $generator->create_entry($stage, $alpha->id, $theme, ['studyyear' => $year, 'declaredduration' => 10]);
            stage_apply_deve_validation(
                $DB->get_record('stage_entry', ['userid' => $alpha->id, 'studyyear' => $year]), 2, 10);
        }

        // Beta : rien de validé nulle part (aucune saisie).
        // Zeta : en défaut seulement en A2.
        $generator->create_entry($stage, $zeta->id, $theme, ['studyyear' => 3, 'declaredduration' => 10]);
        stage_apply_deve_validation(
            $DB->get_record('stage_entry', ['userid' => $zeta->id, 'studyyear' => 3]), 2, 10);
        $generator->create_entry($stage, $zeta->id, $theme, ['studyyear' => 4, 'declaredduration' => 10]);
        stage_apply_deve_validation(
            $DB->get_record('stage_entry', ['userid' => $zeta->id, 'studyyear' => 4]), 2, 10);

        // Epsilon : en défaut seulement en A4.
        $generator->create_entry($stage, $epsilon->id, $theme, ['studyyear' => 2, 'declaredduration' => 10]);
        stage_apply_deve_validation(
            $DB->get_record('stage_entry', ['userid' => $epsilon->id, 'studyyear' => 2]), 2, 10);
        $generator->create_entry($stage, $epsilon->id, $theme, ['studyyear' => 3, 'declaredduration' => 10]);
        stage_apply_deve_validation(
            $DB->get_record('stage_entry', ['userid' => $epsilon->id, 'studyyear' => 3]), 2, 10);

        $report = stage_get_promotion_report($stage, $context);

        $names = array_map(fn($row) => fullname($row->user), $report->rows);

        $this->assertSame(4, $report->total);
        $this->assertSame(3, $report->failedcount);
        // Beta (3 années en défaut) d'abord, puis Zeta (en défaut dès A2, avant Epsilon dont le
        // seul défaut est plus tardif en A4), puis Epsilon, puis Alpha (à jour) en dernier.
        $this->assertSame(['Bob Beta', 'Ivy Zeta', 'Eve Epsilon', 'Zoe Alpha'], $names);
    }
}
