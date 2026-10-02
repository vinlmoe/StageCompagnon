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
 * Listes d'évaluation nommées : questions d'une thématique tirées de la liste qu'elle a choisie,
 * partage d'une question entre listes, retrait et suppression sans perte de réponses inattendue.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_get_questions
 * @covers     ::stage_get_evallist_questions
 * @covers     ::stage_remove_evallist_question
 * @covers     ::stage_delete_evallist
 * @covers     ::stage_count_evallist_exclusive_answers
 * @covers     ::stage_get_reusable_evallist_questions
 * @covers     ::stage_get_evallist_themes
 */
final class evallists_test extends \advanced_testcase {
    /**
     * Crée une question d'évaluation.
     *
     * @param \stdClass $stage
     * @param string $name
     * @param string $evaltype
     * @param int $sortorder
     * @return int
     */
    private function question(\stdClass $stage, string $name, string $evaltype = 'student', int $sortorder = 0): int {
        global $DB;
        return (int) $DB->insert_record('stage_question', (object) [
            'stageid' => $stage->id, 'themeid' => 0, 'evaltype' => $evaltype, 'qtype' => 'text', 'name' => $name,
            'required' => 0, 'sortorder' => $sortorder, 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Les questions d'une thématique sont celles de la liste choisie pour chaque formulaire.
     */
    public function test_theme_questions_come_from_chosen_list(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $stage = $this->getDataGenerator()->create_module('stage', ['course' => $this->getDataGenerator()->create_course()]);
        $theme1 = $gen->create_theme($stage, ['name' => 'A']);
        $theme2 = $gen->create_theme($stage, ['name' => 'B']);
        $q1 = $this->question($stage, 'Q1', 'student', 2);
        $q2 = $this->question($stage, 'Q2', 'student', 1);
        $qt = $this->question($stage, 'QT', 'teacher');
        $list = $gen->create_evallist($stage, 'student', [$q1, $q2], [$theme1, $theme2]);
        $gen->create_evallist($stage, 'teacher', [$qt], [$theme1]);

        $this->assertSame([$q2, $q1], array_map('intval', array_keys(stage_get_questions($theme1->id, 'student'))));
        $this->assertSame([$q2, $q1], array_map('intval', array_keys(stage_get_questions($theme2->id, 'student'))));
        $this->assertSame([$qt], array_map('intval', array_keys(stage_get_questions($theme1->id, 'teacher'))));
        $this->assertSame([], stage_get_questions($theme2->id, 'teacher'));
        $this->assertSame([], stage_get_questions($theme1->id, 'tutor'));
        $this->assertCount(2, stage_get_evallist_themes($list));
    }

    /**
     * Une question partagée survit à son retrait d'une liste ; seule, elle disparaît avec ses
     * réponses. La suppression d'une liste renvoie ses thématiques au commentaire libre.
     */
    public function test_remove_and_delete_keep_shared_questions(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $course = $this->getDataGenerator()->create_course();
        $stage = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $theme = $gen->create_theme($stage, ['name' => 'A']);
        $entry = $gen->create_entry($stage, $student->id, $theme);
        $shared = $this->question($stage, 'Partagée');
        $own = $this->question($stage, 'Propre');
        $other = $this->question($stage, 'Autre');
        $list1 = $gen->create_evallist($stage, 'student', [$shared, $own], [$theme], 'L1');
        $list2 = $gen->create_evallist($stage, 'student', [$shared, $other], [], 'L2');
        stage_save_answers($entry->id, [(object) ['id' => $own], (object) ['id' => $shared]], [$own => 'x', $shared => 'y']);

        // Seules les réponses aux questions propres à la liste comptent.
        $this->assertSame(1, stage_count_evallist_exclusive_answers($list1->id));
        $this->assertSame(0, stage_count_evallist_exclusive_answers($list2->id));
        $this->assertSame([$own], array_map('intval', array_keys(stage_get_reusable_evallist_questions($list2))));

        stage_remove_evallist_question($list1->id, $shared);
        $this->assertTrue($DB->record_exists('stage_question', ['id' => $shared]));
        $this->assertTrue($DB->record_exists('stage_answer', ['questionid' => $shared]));

        stage_remove_evallist_question($list1->id, $own);
        $this->assertFalse($DB->record_exists('stage_question', ['id' => $own]));
        $this->assertFalse($DB->record_exists('stage_answer', ['questionid' => $own]));

        stage_delete_evallist($list1);
        $this->assertFalse($DB->record_exists('stage_evallist', ['id' => $list1->id]));
        $this->assertEquals(0, $DB->get_field('stage_theme', 'studentlistid', ['id' => $theme->id]));
        $this->assertTrue($DB->record_exists('stage_question', ['id' => $shared]));
    }
}
