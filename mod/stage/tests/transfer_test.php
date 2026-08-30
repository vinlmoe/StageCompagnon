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
 * Tests du transfert d'un étudiant et de ses stages vers une autre instance de l'activité
 * (stage_plan_student_transfer(), stage_execute_student_transfer()) : rapprochement tolérant des
 * thématiques par nom, blocages attendus, et effets réels du transfert.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_plan_student_transfer
 * @covers     ::stage_execute_student_transfer
 */
final class transfer_test extends \advanced_testcase {

    /**
     * Crée deux instances de l'activité, dans deux cours distincts, chacune avec un étudiant
     * inscrit correspondant.
     *
     * @return array [stdClass $sourcestage, stdClass $targetstage, stdClass $student]
     */
    private function prepare_two_stages(): array {
        $this->resetAfterTest();
        $sourcecourse = $this->getDataGenerator()->create_course();
        $targetcourse = $this->getDataGenerator()->create_course();
        $sourcestage = $this->getDataGenerator()->create_module('stage', ['course' => $sourcecourse]);
        $targetstage = $this->getDataGenerator()->create_module('stage', ['course' => $targetcourse]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $sourcecourse->id, 'student');

        return [$sourcestage, $targetstage, $student];
    }

    /**
     * Un étudiant sans aucun stage dans l'instance source n'a rien à transférer.
     */
    public function test_blocks_when_no_entries(): void {
        [$sourcestage, $targetstage, $student] = $this->prepare_two_stages();
        $this->getDataGenerator()->enrol_user($student->id, $targetstage->course, 'student');

        $plan = stage_plan_student_transfer($sourcestage, $targetstage, $student->id);

        $this->assertNotEmpty($plan->blockers);
    }

    /**
     * Sans inscription au cours de destination, le transfert est bloqué : les stages transférés
     * n'apparaîtraient dans aucun tableau de bord de la destination.
     */
    public function test_blocks_when_student_not_enrolled_in_target(): void {
        [$sourcestage, $targetstage, $student] = $this->prepare_two_stages();
        /** @var \mod_stage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $theme = $generator->create_theme($sourcestage);
        $generator->create_entry($sourcestage, $student->id, $theme);
        // Volontairement pas d'inscription de l'étudiant au cours cible.

        $plan = stage_plan_student_transfer($sourcestage, $targetstage, $student->id);

        $this->assertNotEmpty($plan->blockers);
    }

    /**
     * Une thématique de la source sans équivalent dans la destination bloque le transfert : sans
     * rapprochement, le stage perdrait son rattachement et fausserait le bilan de l'étudiant.
     */
    public function test_blocks_when_theme_has_no_match_in_target(): void {
        [$sourcestage, $targetstage, $student] = $this->prepare_two_stages();
        $this->getDataGenerator()->enrol_user($student->id, $targetstage->course, 'student');
        /** @var \mod_stage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $theme = $generator->create_theme($sourcestage, ['name' => 'Filière avicole']);
        $generator->create_entry($sourcestage, $student->id, $theme);
        // Le stage cible n'a aucune thématique du même nom.
        $generator->create_theme($targetstage, ['name' => 'Autre thématique']);

        $plan = stage_plan_student_transfer($sourcestage, $targetstage, $student->id);

        $this->assertNotEmpty($plan->blockers);
    }

    /**
     * Les thématiques sont rapprochées par nom normalisé (accents, casse, espaces ignorés) : une
     * thématique cible dont le nom ne diffère que par ces détails est reconnue comme équivalente.
     */
    public function test_theme_matched_by_normalized_name(): void {
        [$sourcestage, $targetstage, $student] = $this->prepare_two_stages();
        $this->getDataGenerator()->enrol_user($student->id, $targetstage->course, 'student');
        /** @var \mod_stage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $sourcetheme = $generator->create_theme($sourcestage, ['name' => 'Animaux de compagnie']);
        $generator->create_entry($sourcestage, $student->id, $sourcetheme);
        $generator->create_theme($targetstage, ['name' => '  ANIMAUX   de   Compagnie ']);

        $plan = stage_plan_student_transfer($sourcestage, $targetstage, $student->id);

        $this->assertEmpty($plan->blockers);
        $this->assertNotEmpty($plan->thememap[$sourcetheme->id]);
    }

    /**
     * L'exécution rattache la saisie à l'instance cible avec sa thématique retraduite, sans
     * modifier ses dates, et retire l'attribution d'enseignant référent (propre au cours) de la
     * source sans la recréer dans la destination.
     */
    public function test_execute_moves_entry_and_drops_teacher_assignment(): void {
        global $DB;
        [$sourcestage, $targetstage, $student] = $this->prepare_two_stages();
        $targetcourseid = $targetstage->course;
        $this->getDataGenerator()->enrol_user($student->id, $targetcourseid, 'student');
        /** @var \mod_stage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $sourcetheme = $generator->create_theme($sourcestage, ['name' => 'Ruminants']);
        $targettheme = $generator->create_theme($targetstage, ['name' => 'Ruminants']);
        $entry = $generator->create_entry($sourcestage, $student->id, $sourcetheme, [
            'datestart' => make_timestamp(2026, 3, 1), 'dateend' => make_timestamp(2026, 3, 15),
        ]);

        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $sourcestage->course, 'editingteacher');
        $generator->assign_teacher($sourcestage, $student->id, $teacher->id);

        $sourcecm = get_coursemodule_from_instance('stage', $sourcestage->id);
        $targetcm = get_coursemodule_from_instance('stage', $targetstage->id);
        $sourcecontext = \context_module::instance($sourcecm->id);
        $targetcontext = \context_module::instance($targetcm->id);

        $plan = stage_plan_student_transfer($sourcestage, $targetstage, $student->id);
        $this->assertEmpty($plan->blockers);

        $count = stage_execute_student_transfer(
            $sourcestage, $sourcecontext, $targetstage, $targetcontext, $student->id, $plan);

        $this->assertSame(1, $count);

        $moved = $DB->get_record('stage_entry', ['id' => $entry->id], '*', MUST_EXIST);
        $this->assertEquals($targetstage->id, $moved->stageid);
        $this->assertEquals($targettheme->id, $moved->themeid);
        $this->assertEquals(make_timestamp(2026, 3, 1), $moved->datestart);
        $this->assertEquals(make_timestamp(2026, 3, 15), $moved->dateend);

        $this->assertEmpty(stage_get_student_teachers($sourcestage->id, $student->id));
        $this->assertEmpty(stage_get_student_teachers($targetstage->id, $student->id));
    }
}
