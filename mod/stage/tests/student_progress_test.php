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
 * Tests du bilan global d'un étudiant (stage_get_student_progress()), en particulier de la
 * durée requise affichée pour une thématique définissant une durée globale unique
 * (stage_theme.requiredduration) plutôt qu'une durée par année.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_get_student_progress
 */
final class student_progress_test extends \advanced_testcase {
    /**
     * Une thématique bornée sur plusieurs années avec une durée globale (ex : 30 jours, quelle que
     * soit l'année) ne doit pas voir cette durée sommée une fois par année sur laquelle l'étudiant a
     * des saisies : le total requis doit rester 30, pas 30 * nombre d'années.
     */
    public function test_flat_duration_is_not_multiplied_by_number_of_years(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $stage = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        /** @var \mod_stage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $theme = $generator->create_theme($stage, [
            'name' => 'Thématique pluriannuelle', 'mandatory' => 1,
            'minstudyyear' => 2, 'maxstudyyear' => 4, 'requiredduration' => 30,
        ]);
        $student = $this->getDataGenerator()->create_user();

        foreach ([2, 3, 4] as $year) {
            $entry = $generator->create_entry(
                $stage,
                $student->id,
                $theme,
                ['studyyear' => $year, 'declaredduration' => 10]
            );
            stage_apply_deve_validation($entry, 2, 10);
        }

        $progress = stage_get_student_progress($stage->id, $student->id);
        $themerow = $progress->themes[$theme->id];

        $this->assertSame(30, $themerow->retained);
        $this->assertSame(30, $themerow->requiredduration);
        $this->assertTrue($themerow->done);
    }

    /**
     * Régression : un stage annulé ou non validé ajoutait à la thématique l'exigence de son année
     * d'étude (la thématique n'était alors jamais « faite »), gonflait la durée déclarée, et
     * comptait comme stage en cours dans le tableau de pilotage.
     *
     * @covers ::stage_get_pilotage_overview
     */
    public function test_cancelled_and_rejected_entries_do_not_count(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $stage = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        /** @var \mod_stage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $theme = $generator->create_theme($stage, ['name' => 'Clinique', 'mandatory' => 1]);
        stage_set_theme_duration($theme->id, 2, 10);
        stage_set_theme_duration($theme->id, 3, 10);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $cancelled = $generator->create_entry($stage, $student->id, $theme, ['studyyear' => 2, 'declaredduration' => 7]);
        stage_cancel_entry($cancelled, 2, 'Abandon');
        $rejected = $generator->create_entry($stage, $student->id, $theme, [
            'studyyear' => 2, 'declaredduration' => 4,
            'datestart' => make_timestamp(2026, 5, 1), 'dateend' => make_timestamp(2026, 5, 4),
        ]);
        stage_reject_by_deve($rejected, 2, 'Hors délai');
        $valid = $generator->create_entry($stage, $student->id, $theme, [
            'studyyear' => 3, 'declaredduration' => 10,
            'datestart' => make_timestamp(2026, 6, 1), 'dateend' => make_timestamp(2026, 6, 10),
        ]);
        stage_apply_deve_validation($valid, 2, 10);

        $progress = stage_get_student_progress($stage->id, $student->id);
        $themerow = $progress->themes[$theme->id];
        $this->assertSame(10, $themerow->requiredduration);
        $this->assertSame([3], $themerow->requiredyears);
        $this->assertTrue($themerow->done);
        $this->assertSame(10, $progress->totaldeclared);

        $years = array_map(fn($row) => $row->studyyear, stage_get_student_year_progress($stage->id, $student->id));
        $this->assertNotContains(2, $years);

        $overview = stage_get_pilotage_overview($stage->id, \context_module::instance($stage->cmid));
        $this->assertSame(0, reset($overview)->pendingcount);
    }

    /**
     * Le tableau de pilotage précharge les données de toute la promotion : il donne exactement le
     * même bilan que le calcul étudiant par étudiant, en un nombre de requêtes qui ne dépend plus
     * du nombre d'étudiants (plus de 16 000 requêtes auparavant pour 180 étudiants).
     *
     * @covers ::stage_get_pilotage_overview
     * @covers \mod_stage\local\progress_cache
     */
    public function test_pilotage_overview_is_batched_and_identical(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $stage = $gen->create_module('stage', ['course' => $course]);
        $DB->update_record('stage', (object) ['id' => $stage->id, 'requiredabroaddays' => 10, 'abroadbeforeyear' => 4]);
        /** @var \mod_stage_generator $generator */
        $generator = $gen->get_plugin_generator('mod_stage');
        $themes = [];
        foreach ([[0, 0, 0], [2, 4, 0], [0, 0, 15]] as $i => [$min, $max, $flat]) {
            $theme = $generator->create_theme($stage, [
                'name' => "Thématique $i", 'mandatory' => 1, 'minstudyyear' => $min, 'maxstudyyear' => $max,
                'requiredduration' => $flat,
            ]);
            stage_set_theme_duration($theme->id, 0, 4);
            stage_set_theme_duration($theme->id, 3, 8);
            $themes[] = $theme;
        }
        stage_set_year_requirement($stage->id, 3, 20);
        $context = \context_module::instance($stage->cmid);

        $count = 0;
        foreach ([5, 15] as $size) {
            for (; $count < $size; $count++) {
                $student = $gen->create_user();
                $gen->enrol_user($student->id, $course->id, 'student');
                foreach ($themes as $t => $theme) {
                    $entry = $generator->create_entry($stage, $student->id, $theme, [
                        'studyyear' => 2 + ($count + $t) % 3, 'declaredduration' => 6,
                        'abroad' => $t === 1 ? 1 : 0, 'datestart' => make_timestamp(2026, 1 + $t, 1),
                        'dateend' => make_timestamp(2026, 1 + $t, 6),
                    ]);
                    if (($count + $t) % 4) {
                        stage_apply_deve_validation($entry, 2, 6);
                    }
                    if ($t === 2) {
                        stage_set_entry_stagetype($entry->id, 'complementaire');
                    }
                }
            }
            $before = $DB->perf_get_queries();
            $rows = stage_get_pilotage_overview($stage->id, $context);
            $queries[$size] = $DB->perf_get_queries() - $before;

            $reference = stage_build_pilotage_rows($stage->id, stage_get_enrolled_students($context));
            $this->assertSame(json_encode(array_values($reference)), json_encode(array_values($rows)));
        }
        // Le nombre de requêtes ne croît pas avec la taille de la promotion (à une ou deux
        // requêtes près, selon les caches internes de Moodle sur les inscriptions).
        $this->assertLessThanOrEqual($queries[5] + 2, $queries[15]);
        $this->assertLessThan(20, $queries[15]);
        // Le cache est vidé : un calcul isolé interroge de nouveau la base.
        $this->assertFalse(\mod_stage\local\progress_cache::covers($stage->id));
    }
}
