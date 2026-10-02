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

use mod_stage\local\csv_importer;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/stage/locallib.php');

/**
 * Doublons probables entre imports : un stage StageVet qui ressemble à un stage déjà enregistré
 * (suivi historique sans dates, autre thématique aux mêmes dates) n'est plus créé en silence, la
 * validation existante n'est jamais touchée, et l'enseignant n'est pas sollicité pour un stage
 * déjà validé.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_find_probable_duplicate_entries
 * @covers     ::stage_has_validated_probable_duplicate
 * @covers     \mod_stage\local\csv_importer
 */
final class import_duplicates_test extends \advanced_testcase {
    /**
     * Activité, étudiant avec référent, deux thématiques, et un stage historique validé (comme le
     * crée import_historical.php).
     *
     * @param int|null $start
     * @param int|null $end
     * @param string $theme Thématique du stage historique.
     * @return array [stage, context, historical entry id]
     */
    private function fixture(?int $start, ?int $end, string $theme = 'Clinique'): array {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $stage = $gen->create_module('stage', ['course' => $course]);
        $student = $gen->create_user(['email' => 'camille@example.test']);
        $teacher = $gen->create_user();
        $gen->enrol_user($student->id, $course->id, 'student');
        $gen->enrol_user($teacher->id, $course->id, 'teacher');
        $sg = $gen->get_plugin_generator('mod_stage');
        $themes = [
            'Clinique' => $sg->create_theme($stage, ['name' => 'Clinique']),
            'Autre' => $sg->create_theme($stage, ['name' => 'Autre']),
        ];
        $sg->assign_teacher($stage, $student->id, $teacher->id);
        $entryid = stage_register_entry(
            $stage->id,
            $student->id,
            $themes[$theme]->id,
            'Clinique du lac',
            $start,
            $end,
            5,
            3,
            STAGE_CONVENTION_NONE
        );
        stage_apply_deve_validation($DB->get_record('stage_entry', ['id' => $entryid]), $USER->id, 5, 'Import historique');
        return [$stage, \context_module::instance($stage->cmid), $entryid];
    }

    /**
     * Ligne StageVet du même étudiant, thématique « Clinique », avec ses deux évaluations.
     *
     * @return string
     */
    private function csv(): string {
        return "\xEF\xBB\xBF\"Email étudiant\";\"Thème\";\"Début stage\";\"Fin stage\";\"Année d'étude\";"
            . "\"Évaluation par le maître de stage\";\"Évaluation par l’étudiant\"\r\n"
            . "\"camille@example.test\";\"Clinique\";\"01/02/2026\";\"05/02/2026\";\"3\";\"Avis global : 4/5\";"
            . "\"Avis global : 5/5\"\r\n";
    }

    /**
     * Un stage historique sans dates n'est plus doublé : la ligne est soumise à la DEVE.
     */
    public function test_undated_historical_entry_is_reported_not_duplicated(): void {
        global $DB;
        [$stage, $context, $histid] = $this->fixture(null, null);
        $sink = $this->redirectEmails();

        $result = csv_importer::stagevet($stage, $context, $this->csv());
        $this->assertSame(0, $result['results']->created);
        $this->assertSame([2], array_keys($result['results']->probableduplicates));
        $this->assertSame([$histid], array_map('intval', array_keys($result['results']->probableduplicates[2]->candidates)));
        $this->assertEquals(1, $DB->count_records('stage_entry'));

        // Rattachement : les évaluations rejoignent le stage historique, sa validation reste.
        $attach = csv_importer::stagevet($stage, $context, $this->csv(), [], [2 => (string) $histid]);
        $this->assertSame(1, $attach['results']->updated);
        $hist = $DB->get_record('stage_entry', ['id' => $histid]);
        $this->assertEquals(STAGE_STATUS_VALIDE_DEVE, $hist->status);
        $this->assertEquals(5, $hist->retainedduration);
        $this->assertSame('Import historique', $hist->devecomment);
        $this->assertSame('Avis global : 4/5', $hist->tutoreval);
        $this->assertEquals(1, $DB->count_records('stage_entry'));
        $this->assertCount(0, $sink->get_messages());
    }

    /**
     * Créer malgré tout un nouveau stage reste possible, sans solliciter l'enseignant puisque le
     * même stage est déjà validé ; « Ne pas importer » ne crée rien.
     */
    public function test_new_and_skip_decisions(): void {
        global $DB;
        [$stage, $context, $histid] = $this->fixture(null, null);
        $sink = $this->redirectEmails();

        $skip = csv_importer::stagevet($stage, $context, $this->csv(), [], [2 => 'skip']);
        $this->assertSame(0, $skip['results']->created);
        $this->assertEquals(1, $DB->count_records('stage_entry'));

        $new = csv_importer::stagevet($stage, $context, $this->csv(), [], [2 => 'new']);
        $this->assertSame(1, $new['results']->created);
        $this->assertSame(0, $new['results']->notified);
        $this->assertSame(1, $new['results']->notifyskipped);
        $this->assertEquals(2, $DB->count_records('stage_entry'));
        $this->assertCount(0, $sink->get_messages());
    }

    /**
     * Un stage d'une autre thématique aux dates qui se recoupent est lui aussi signalé.
     */
    public function test_overlapping_entry_of_other_theme_is_reported(): void {
        [$stage, $context, $histid] = $this->fixture(make_timestamp(2026, 2, 2), make_timestamp(2026, 2, 6), 'Autre');
        $result = csv_importer::stagevet($stage, $context, $this->csv());
        $this->assertSame(0, $result['results']->created);
        $this->assertArrayHasKey($histid, $result['results']->probableduplicates[2]->candidates);
    }

    /**
     * Règles de rapprochement : dates qui se recoupent quelle que soit la thématique ; sans dates,
     * même thématique et même année ; stages annulés ignorés.
     */
    public function test_find_probable_duplicates_rules(): void {
        global $DB;
        [$stage, , $histid] = $this->fixture(null, null);
        $hist = $DB->get_record('stage_entry', ['id' => $histid]);
        $other = $DB->get_field('stage_theme', 'id', ['stageid' => $stage->id, 'name' => 'Autre']);
        $feb1 = make_timestamp(2026, 2, 1);
        $feb5 = make_timestamp(2026, 2, 5);

        $find = fn($themeid, $year)
            => stage_find_probable_duplicate_entries($stage->id, $hist->userid, $themeid, $feb1, $feb5, $year);
        $this->assertArrayHasKey($histid, $find($hist->themeid, 3));
        $this->assertArrayHasKey($histid, $find($hist->themeid, 0));
        $this->assertSame([], $find($hist->themeid, 4));
        $this->assertSame([], $find($other, 3));

        $DB->set_field('stage_entry', 'status', STAGE_STATUS_ANNULE, ['id' => $histid]);
        $this->assertSame([], $find($hist->themeid, 3));
    }
}
