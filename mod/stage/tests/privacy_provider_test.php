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

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use mod_stage\privacy\provider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/stage/locallib.php');

/**
 * Tests du fournisseur de données personnelles, et notamment de la règle retenue à la suppression :
 * supprimer un étudiant efface tous ses stages et évaluations, tandis que supprimer un membre du
 * personnel laisse intacts les stages des étudiants dont il n'est que le référent.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_stage\privacy\provider
 */
final class privacy_provider_test extends \advanced_testcase {

    /**
     * Monte une activité avec un étudiant, un enseignant référent, et une saisie complète :
     * périodes, détail de convention, réponse à un questionnaire et évaluations.
     *
     * @return array [stdClass $stage, \context_module $context, stdClass $student, stdClass $teacher,
     *                stdClass $entry]
     */
    private function prepare(): array {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $stage = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        $context = \context_module::instance($stage->cmid);

        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $theme = $generator->create_theme($stage);
        $entry = $generator->create_entry($stage, $student->id, $theme);
        $generator->assign_teacher($stage, $student->id, $teacher->id);

        // L'enseignant référent évalue le stage : ses données à lui, sur la saisie de l'étudiant.
        $DB->update_record('stage_entry', (object) [
            'id' => $entry->id,
            'teacherid' => $teacher->id,
            'teachereval' => 'Appréciation du référent',
            'teachertime' => time(),
            'studentselfeval' => 'Auto-évaluation',
        ]);

        $DB->insert_record('stage_convention_detail', (object) [
            'entryid' => $entry->id,
            'referentteacherid' => $teacher->id,
            'studentaddress' => '1 rue de Test',
            'studentphone' => '0102030405',
            'tutorname' => 'Maître de stage',
            'tutoremail' => 'tuteur@example.com',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $question = $DB->insert_record('stage_question', (object) [
            'stageid' => $stage->id, 'themeid' => $theme->id, 'evaltype' => 'student', 'qtype' => 'text',
            'name' => 'Question', 'nameen' => '', 'options' => '', 'optionsen' => '', 'required' => 0,
            'sortorder' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('stage_answer', (object) [
            'entryid' => $entry->id, 'questionid' => $question, 'answertext' => 'Réponse',
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        $entry = $DB->get_record('stage_entry', ['id' => $entry->id], '*', MUST_EXIST);

        return [$stage, $context, $student, $teacher, $entry];
    }

    /**
     * Supprimer un étudiant efface son stage et tout ce qui en dépend : périodes, détail de
     * convention, réponses, et les évaluations portées par la saisie.
     */
    public function test_delete_student_removes_all_their_internships(): void {
        global $DB;

        [$stage, $context, $student, , $entry] = $this->prepare();

        $this->assertTrue($DB->record_exists('stage_entry_period', ['entryid' => $entry->id]));

        provider::delete_data_for_user(new approved_contextlist($student, 'mod_stage', [$context->id]));

        $this->assertFalse($DB->record_exists('stage_entry', ['id' => $entry->id]));
        $this->assertFalse($DB->record_exists('stage_entry_period', ['entryid' => $entry->id]));
        $this->assertFalse($DB->record_exists('stage_entry_workday', ['entryid' => $entry->id]));
        $this->assertFalse($DB->record_exists('stage_convention_detail', ['entryid' => $entry->id]));
        $this->assertFalse($DB->record_exists('stage_answer', ['entryid' => $entry->id]));
        $this->assertFalse($DB->record_exists('stage_entry_teacher', ['studentid' => $student->id]));
    }

    /**
     * Supprimer l'enseignant référent ne doit pas emporter le stage de l'étudiant, qui ne lui
     * appartient pas : la saisie survit, dissociée de lui, et l'appréciation qu'il avait rédigée
     * disparaît avec la référence.
     */
    public function test_delete_teacher_keeps_the_student_internship(): void {
        global $DB;

        [$stage, $context, $student, $teacher, $entry] = $this->prepare();

        provider::delete_data_for_user(new approved_contextlist($teacher, 'mod_stage', [$context->id]));

        $reloaded = $DB->get_record('stage_entry', ['id' => $entry->id]);
        $this->assertNotFalse($reloaded, 'Le stage de l\'étudiant doit survivre à la suppression du référent.');
        $this->assertEquals($student->id, (int) $reloaded->userid);
        $this->assertEquals('Auto-évaluation', $reloaded->studentselfeval);
        $this->assertNull($reloaded->teacherid);
        $this->assertNull($reloaded->teachereval);

        $detail = $DB->get_record('stage_convention_detail', ['entryid' => $entry->id]);
        $this->assertNotFalse($detail);
        $this->assertNull($detail->referentteacherid);
        $this->assertEquals('1 rue de Test', $detail->studentaddress);

        $this->assertFalse($DB->record_exists('stage_entry_teacher', ['teacherid' => $teacher->id]));
    }

    /**
     * La liste des utilisateurs d'un contexte doit contenir aussi bien l'étudiant que le personnel
     * cité dans sa saisie, sans quoi la suppression par contexte les manquerait.
     */
    public function test_get_users_in_context_covers_student_and_teacher(): void {
        [$stage, $context, $student, $teacher] = $this->prepare();

        $userlist = new userlist($context, 'mod_stage');
        provider::get_users_in_context($userlist);
        $found = $userlist->get_userids();

        $this->assertContains((int) $student->id, $found);
        $this->assertContains((int) $teacher->id, $found);
    }

    /**
     * Suppression ciblée d'un lot d'utilisateurs dans un contexte : mêmes règles que la
     * suppression individuelle, appliquées d'un coup.
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        [$stage, $context, $student, $teacher, $entry] = $this->prepare();

        provider::delete_data_for_users(new approved_userlist($context, 'mod_stage', [$student->id]));

        $this->assertFalse($DB->record_exists('stage_entry', ['id' => $entry->id]));
        // L'enseignant n'était pas visé : ses autres rattachements ne bougent pas.
        $this->assertTrue($DB->record_exists('user', ['id' => $teacher->id]));
    }

    /**
     * Vider un contexte efface les stages de tous ses étudiants ainsi que les attributions de
     * référents propres à l'activité.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        [$stage, $context, , , $entry] = $this->prepare();

        provider::delete_data_for_all_users_in_context($context);

        $this->assertFalse($DB->record_exists('stage_entry', ['stageid' => $stage->id]));
        $this->assertFalse($DB->record_exists('stage_answer', ['entryid' => $entry->id]));
        $this->assertFalse($DB->record_exists('stage_entry_teacher', ['stageid' => $stage->id]));
    }

    /**
     * Les contextes remontés doivent couvrir l'étudiant comme le référent.
     */
    public function test_get_contexts_for_userid(): void {
        [, $context, $student, $teacher] = $this->prepare();

        // L'étudiant est atteint par plusieurs des requêtes du fournisseur (saisie et attribution
        // de référent) : seule compte l'unicité du contexte remonté, pas le nombre de fois.
        $this->assertEquals([$context->id],
            array_values(array_unique(provider::get_contexts_for_userid($student->id)->get_contextids())));
        $this->assertEquals([$context->id],
            array_values(array_unique(provider::get_contexts_for_userid($teacher->id)->get_contextids())));
    }
}
