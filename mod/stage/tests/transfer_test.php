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
 * @covers     ::stage_get_transfer_students
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
     * Régression : des thématiques numérotées étaient toutes rattachées à la dernière d'entre elles.
     */
    public function test_numbered_themes_keep_their_own_match(): void {
        global $DB;
        [$sourcestage, $targetstage, $student] = $this->prepare_two_stages();
        $this->getDataGenerator()->enrol_user($student->id, $targetstage->course, 'student');
        /** @var \mod_stage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $source1 = $generator->create_theme($sourcestage, ['name' => 'Clinique 1']);
        $source2 = $generator->create_theme($sourcestage, ['name' => 'Clinique 2']);
        $target1 = $generator->create_theme($targetstage, ['name' => 'Clinique 1']);
        $target2 = $generator->create_theme($targetstage, ['name' => 'Clinique 2']);
        $entry1 = $generator->create_entry($sourcestage, $student->id, $source1);
        $entry2 = $generator->create_entry($sourcestage, $student->id, $source2, [
            'datestart' => make_timestamp(2026, 5, 1), 'dateend' => make_timestamp(2026, 5, 10),
        ]);

        $plan = stage_plan_student_transfer($sourcestage, $targetstage, $student->id);
        $this->assertEmpty($plan->blockers);
        $this->assertEquals($target1->id, $plan->thememap[$source1->id]);
        $this->assertEquals($target2->id, $plan->thememap[$source2->id]);

        [$sourcecontext, $targetcontext] = $this->contexts($sourcestage, $targetstage);
        stage_execute_student_transfer($sourcestage, $sourcecontext, $targetstage, $targetcontext, $student->id, $plan);
        $this->assertEquals($target1->id, $DB->get_field('stage_entry', 'themeid', ['id' => $entry1->id]));
        $this->assertEquals($target2->id, $DB->get_field('stage_entry', 'themeid', ['id' => $entry2->id]));
    }

    /**
     * Deux thématiques de même nom dans la cible : aucune n'est choisie au hasard, le transfert
     * est bloqué.
     */
    public function test_blocks_when_target_theme_name_is_ambiguous(): void {
        [$sourcestage, $targetstage, $student] = $this->prepare_two_stages();
        $this->getDataGenerator()->enrol_user($student->id, $targetstage->course, 'student');
        /** @var \mod_stage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $sourcetheme = $generator->create_theme($sourcestage, ['name' => 'Ruminants']);
        $generator->create_theme($targetstage, ['name' => 'Ruminants']);
        $generator->create_theme($targetstage, ['name' => 'RUMINANTS']);
        $generator->create_entry($sourcestage, $student->id, $sourcetheme);

        $plan = stage_plan_student_transfer($sourcestage, $targetstage, $student->id);
        $this->assertNotEmpty($plan->blockers);
        $this->assertNull($plan->thememap[$sourcetheme->id]);
    }

    /**
     * Régression : deux questions source rapprochées de la même question cible faisaient échouer
     * le transfert sur l'index unique des réponses. Seules les correspondances uniques sont
     * reportées, les autres réponses sont annoncées puis supprimées.
     */
    public function test_ambiguous_questions_do_not_collide(): void {
        global $DB;
        [$sourcestage, $targetstage, $student] = $this->prepare_two_stages();
        $this->getDataGenerator()->enrol_user($student->id, $targetstage->course, 'student');
        /** @var \mod_stage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $sourcetheme = $generator->create_theme($sourcestage, ['name' => 'Ruminants']);
        $targettheme = $generator->create_theme($targetstage, ['name' => 'Ruminants']);
        $question = function ($stage, $name) use ($DB) {
            return $DB->get_record('stage_question', ['id' => $DB->insert_record('stage_question', (object) [
                'stageid' => $stage->id, 'themeid' => 0, 'evaltype' => 'student', 'qtype' => 'text', 'name' => $name,
                'required' => 0, 'sortorder' => 0, 'timecreated' => time(), 'timemodified' => time(),
            ])]);
        };
        $s1 = $question($sourcestage, 'Compétence 1');
        $s2 = $question($sourcestage, 'Compétence 2');
        $s3 = $question($sourcestage, 'Bilan');
        $s4 = $question($sourcestage, 'bilan');
        $t1 = $question($targetstage, 'Compétence 1');
        $t2 = $question($targetstage, 'Compétence 2');
        $t3 = $question($targetstage, 'Bilan');
        $generator->create_evallist($sourcestage, 'student', [$s1->id, $s2->id, $s3->id, $s4->id], [$sourcetheme]);
        $generator->create_evallist($targetstage, 'student', [$t1->id, $t2->id, $t3->id], [$targettheme]);
        $entry = $generator->create_entry($sourcestage, $student->id, $sourcetheme);
        stage_save_answers($entry->id, [$s1, $s2, $s3, $s4], [
            $s1->id => 'un', $s2->id => 'deux', $s3->id => 'trois', $s4->id => 'quatre',
        ]);

        $plan = stage_plan_student_transfer($sourcestage, $targetstage, $student->id);
        $this->assertEmpty($plan->blockers);
        $this->assertEquals($t1->id, $plan->questionmap[$sourcetheme->id][$s1->id]);
        $this->assertEquals($t2->id, $plan->questionmap[$sourcetheme->id][$s2->id]);
        $this->assertNull($plan->questionmap[$sourcetheme->id][$s3->id]);
        $this->assertNull($plan->questionmap[$sourcetheme->id][$s4->id]);
        $this->assertSame(2, $plan->droppedanswers);

        [$sourcecontext, $targetcontext] = $this->contexts($sourcestage, $targetstage);
        stage_execute_student_transfer($sourcestage, $sourcecontext, $targetstage, $targetcontext, $student->id, $plan);
        $answers = $DB->get_records_menu('stage_answer', ['entryid' => $entry->id], '', 'questionid, answertext');
        $this->assertEquals([$t1->id => 'un', $t2->id => 'deux'], $answers);
    }

    /**
     * Un étudiant désinscrit du cours source mais qui y a encore des stages reste transférable.
     */
    public function test_unenrolled_student_with_entries_is_offered(): void {
        global $DB;
        [$sourcestage, , $student] = $this->prepare_two_stages();
        /** @var \mod_stage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $generator->create_entry($sourcestage, $student->id, $generator->create_theme($sourcestage));
        $enrolled = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($enrolled->id, $sourcestage->course, 'student');
        [$sourcecontext] = $this->contexts($sourcestage, $sourcestage);

        $manual = $DB->get_record('enrol', ['courseid' => $sourcestage->course, 'enrol' => 'manual'], '*', MUST_EXIST);
        enrol_get_plugin('manual')->unenrol_user($manual, $student->id);

        $students = stage_get_transfer_students($sourcestage->id, $sourcecontext);
        $this->assertArrayHasKey($enrolled->id, $students);
        $this->assertSame(
            get_string('transferformerstudent', 'mod_stage', fullname($student)),
            $students[$student->id]
        );
    }

    /**
     * Contextes de module des deux instances.
     *
     * @param \stdClass $sourcestage
     * @param \stdClass $targetstage
     * @return \context_module[]
     */
    private function contexts(\stdClass $sourcestage, \stdClass $targetstage): array {
        return [
            \context_module::instance(get_coursemodule_from_instance('stage', $sourcestage->id)->id),
            \context_module::instance(get_coursemodule_from_instance('stage', $targetstage->id)->id),
        ];
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
            $sourcestage,
            $sourcecontext,
            $targetstage,
            $targetcontext,
            $student->id,
            $plan
        );

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
