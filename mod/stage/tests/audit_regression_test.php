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
 * Régressions reproduites lors de l'audit des imports, transferts, dates et accès.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_get_period_days
 * @covers     ::stage_set_entry_workdays
 * @covers     ::stage_save_entry_periods
 * @covers     ::stage_workdays_violate_restday_rule
 * @covers     ::stage_plan_student_transfer
 * @covers     ::stage_execute_student_transfer
 * @covers     ::stage_can_access_reports
 * @covers     ::stage_get_entry_by_tutor_token
 * @covers     ::stage_cancel_entry
 * @covers     ::stage_status_label
 * @covers     \mod_stage\local\global_export_importer
 */
final class audit_regression_test extends \advanced_testcase {
    /**
     * Prépare une activité et une saisie pour les scénarios de l'audit.
     *
     * @return array Cours, activité, étudiant, générateur, thématique, saisie.
     */
    private function fixture(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $stage = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $theme = $gen->create_theme($stage, ['name' => 'Ruminants']);
        $entry = $gen->create_entry($stage, $student->id, $theme);
        return [$course, $stage, $student, $gen, $theme, $entry];
    }

    /**
     * La confirmation d'un import global restaure les stages datés et leur détail.
     */
    public function test_global_import_period_argument(): void {
        global $DB;
        [, $stage, , , , $entry] = $this->fixture();
        $data = (array) $entry;
        unset($data['id']);
        $count = \mod_stage\local\global_export_importer::restore($stage->id, [[
            'entry' => $data, 'detail' => ['tutorname' => 'Tutor Test'],
        ]]);
        $this->assertEquals(1, $count);
        $restored = $DB->get_record_select('stage_entry', 'stageid = ? AND id <> ?', [$stage->id, $entry->id]);
        $periods = stage_get_entry_periods($restored->id);
        $this->assertCount(1, $periods);
        $this->assertEquals($entry->datestart, reset($periods)->datestart);
        $this->assertSame('Tutor Test', stage_get_convention_detail($restored->id)->tutorname);
    }

    /**
     * Le passage à l'heure d'été ne supprime pas le dernier jour de la période.
     */
    public function test_spring_dst_includes_last_day(): void {
        $this->resetAfterTest();
        $this->setTimezone('Europe/Paris');
        $this->setUser($this->getDataGenerator()->create_user(['timezone' => 'Europe/Paris']));
        $period = (object) ['datestart' => make_timestamp(2026, 3, 28), 'dateend' => make_timestamp(2026, 3, 30)];
        $this->assertCount(3, stage_get_period_days($period));
    }

    /**
     * Les jours d'automne restent distincts et la semaine sans repos est détectée.
     */
    public function test_autumn_dst_and_rest_day_rule(): void {
        $this->resetAfterTest();
        $this->setTimezone('Europe/Paris');
        $this->setUser($this->getDataGenerator()->create_user(['timezone' => 'Europe/Paris']));
        $period = (object) ['datestart' => make_timestamp(2026, 10, 22), 'dateend' => make_timestamp(2026, 10, 28)];
        $days = stage_get_period_days($period);
        $this->assertCount(7, $days);
        $this->assertEquals(make_timestamp(2026, 10, 28), end($days));
        foreach ($days as $day) {
            $this->assertEquals(0, usergetdate($day)['hours']);
        }
        $this->assertTrue(stage_workdays_violate_restday_rule($days));
        unset($days[3]);
        $this->assertFalse(stage_workdays_violate_restday_rule($days));
    }

    /**
     * Une langue explicite n'est plus passée au paramètre obsolète de get_string().
     */
    public function test_explicit_language_does_not_raise_debugging(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user(['lang' => 'en']));
        $actual = stage_status_label(STAGE_STATUS_ENREGISTRE, 'en');
        $expected = get_string_manager()->get_string('status_enregistre', 'mod_stage', null, 'en');
        $this->assertSame($expected, $actual);
        $this->assertNotEmpty(stage_studyyear_options('en'));
        $this->assertNotEmpty(stage_convention_yearsituation_options('en'));
        $this->assertNotEmpty(stage_convention_stagetype_options('en'));
    }

    /**
     * Une requête forgée ne permet pas d'ajouter une journée hors période.
     */
    public function test_workdays_cannot_be_outside_period(): void {
        [, , , , , $entry] = $this->fixture();
        stage_set_entry_workdays($entry->id, [$entry->datestart, make_timestamp(2030, 1, 1)]);
        $this->assertSame([(int) $entry->datestart], stage_get_entry_workdays($entry->id));
    }

    /**
     * Réduire une période supprime les jours précédemment cochés devenus hors période.
     */
    public function test_changing_periods_prunes_workdays(): void {
        [, , , , , $entry] = $this->fixture();
        stage_set_entry_workdays($entry->id, [$entry->datestart, $entry->dateend]);
        stage_save_entry_periods($entry->id, [[
            'datestart' => $entry->datestart, 'dateend' => $entry->datestart,
        ]]);
        $this->assertSame([(int) $entry->datestart], stage_get_entry_workdays($entry->id));
    }

    /**
     * Une réponse sans objectif correspondant bloque le transfert au lieu de disparaître.
     */
    public function test_transfer_blocks_unmatched_checklist(): void {
        [, $stage, $student, $gen, $theme, $entry] = $this->fixture();
        $targetcourse = $this->getDataGenerator()->create_course();
        $target = $this->getDataGenerator()->create_module('stage', ['course' => $targetcourse]);
        $this->getDataGenerator()->enrol_user($student->id, $targetcourse->id, 'student');
        $gen->create_theme($target, ['name' => 'Ruminants']);
        $item = $gen->create_checklist_item($theme);
        stage_save_entry_checklist($entry->id, [$item], [$item->id => (object) ['checked' => 1, 'explanation' => '']]);
        $plan = stage_plan_student_transfer($stage, $target, $student->id);
        $this->assertNotEmpty($plan->blockers);
        $this->expectException(\moodle_exception::class);
        stage_execute_student_transfer(
            $stage,
            \context_module::instance($stage->cmid),
            $target,
            \context_module::instance($target->cmid),
            $student->id,
            $plan
        );
    }

    /**
     * Les réponses suivent les objectifs de la thématique cible après transfert.
     */
    public function test_checklist_follows_transfer(): void {
        [$course, $stage, $student, $gen, $theme, $entry] = $this->fixture();
        $targetcourse = $this->getDataGenerator()->create_course();
        $target = $this->getDataGenerator()->create_module('stage', ['course' => $targetcourse]);
        $this->getDataGenerator()->enrol_user($student->id, $targetcourse->id, 'student');
        $targettheme = $gen->create_theme($target, ['name' => 'Ruminants']);
        $sourceitem = $gen->create_checklist_item($theme, ['name' => 'Examen clinique']);
        $targetitem = $gen->create_checklist_item($targettheme, ['name' => 'Examen clinique']);
        stage_save_entry_checklist($entry->id, [$sourceitem], [$sourceitem->id => (object) ['checked' => 1, 'explanation' => '']]);
        $plan = stage_plan_student_transfer($stage, $target, $student->id);
        $this->assertEmpty($plan->blockers);
        stage_execute_student_transfer(
            $stage,
            \context_module::instance($stage->cmid),
            $target,
            \context_module::instance($target->cmid),
            $student->id,
            $plan
        );
        $this->assertArrayHasKey($targetitem->id, stage_get_entry_checklist($entry->id));
        stage_delete_theme_checklist($theme->id);
        $this->assertArrayHasKey($targetitem->id, stage_get_entry_checklist($entry->id));
    }

    /**
     * Une attribution ancienne ne supplée pas les droits d'enseignant retirés.
     */
    public function test_revoked_teacher_cannot_read_reports(): void {
        global $DB;
        [$course, $stage, , , $theme, $entry] = $this->fixture();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'teacher');
        stage_set_theme_teachers($theme->id, [$teacher->id]);
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'student');
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'teacher']);
        role_unassign($roleid, $teacher->id, \context_course::instance($course->id)->id);
        $this->setUser($teacher);
        $context = \context_module::instance($stage->cmid);
        $this->assertFalse(has_capability('mod/stage:evaluateteacher', $context));
        $this->assertFalse(stage_can_access_reports($stage, $entry, $context));
        $this->assertFalse(stage_is_theme_teacher($theme->id, $teacher->id));
        $this->assertEmpty(stage_get_teacher_themes($stage->id, $teacher->id));
    }

    /**
     * Une question partagée est remappée selon la thématique de chaque saisie.
     */
    public function test_shared_question_transfer_uses_each_entry_theme(): void {
        global $DB;
        [, $stage, $student, $gen, $theme, $entry] = $this->fixture();
        $source2 = $gen->create_theme($stage, ['name' => 'Equins']);
        $entry2 = $gen->create_entry($stage, $student->id, $source2);
        $course = $this->getDataGenerator()->create_course();
        $target = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $target1 = $gen->create_theme($target, ['name' => 'Ruminants']);
        $target2 = $gen->create_theme($target, ['name' => 'Equins']);
        $question = function ($activity, $themes) use ($DB) {
            $qid = $DB->insert_record('stage_question', (object) [
                'stageid' => $activity->id, 'themeid' => $themes[0], 'name' => 'Bilan',
                'evaltype' => 'student', 'qtype' => 'text', 'required' => 0, 'sortorder' => 0,
                'timecreated' => time(), 'timemodified' => time(),
            ]);
            stage_set_question_themes($qid, $themes);
            return $qid;
        };
        $sourceq = $question($stage, [$theme->id, $source2->id]);
        $targetq1 = $question($target, [$target1->id]);
        $targetq2 = $question($target, [$target2->id]);
        stage_save_answers($entry->id, [(object) ['id' => $sourceq]], [$sourceq => 'Bilan ruminants']);
        stage_save_answers($entry2->id, [(object) ['id' => $sourceq]], [$sourceq => 'Bilan équins']);
        $plan = stage_plan_student_transfer($stage, $target, $student->id);
        stage_execute_student_transfer(
            $stage,
            \context_module::instance($stage->cmid),
            $target,
            \context_module::instance($target->cmid),
            $student->id,
            $plan
        );
        $this->assertArrayHasKey($targetq1, stage_get_answers($entry->id));
        $this->assertArrayHasKey($targetq2, stage_get_answers($entry2->id));
    }

    /**
     * Annuler le stage révoque le jeton, même si la saisie est réinitialisée ensuite.
     */
    public function test_cancelled_entry_token_is_revoked(): void {
        global $DB;
        [, , , , , $entry] = $this->fixture();
        $token = bin2hex(random_bytes(32));
        $entry->tutortoken = $token;
        $DB->set_field('stage_entry', 'tutortoken', $token, ['id' => $entry->id]);
        stage_cancel_entry($entry, 2, 'Annulation');
        $this->assertFalse(stage_get_entry_by_tutor_token($token));
        stage_reset_entry($entry);
        $this->assertFalse(stage_get_entry_by_tutor_token($token));
    }

    /**
     * Un jeton historique laissé en base sur un stage annulé est également refusé.
     */
    public function test_legacy_cancelled_token_is_rejected(): void {
        global $DB;
        [, $stage, , , , $entry] = $this->fixture();
        $token = bin2hex(random_bytes(32));
        $DB->update_record('stage_entry', (object) [
            'id' => $entry->id, 'status' => STAGE_STATUS_ANNULE, 'tutortoken' => $token,
        ]);
        $this->assertFalse(stage_get_entry_by_tutor_token($token));
        $this->assertNull(stage_get_tutor_eval_url($stage, $entry));
        $cancelled = $DB->get_record('stage_entry', ['id' => $entry->id], '*', MUST_EXIST);
        stage_reset_entry($cancelled);
        $this->assertFalse(stage_get_entry_by_tutor_token($token));
    }
}
