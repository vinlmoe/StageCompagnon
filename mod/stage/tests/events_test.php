<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_stage;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/stage/locallib.php');

/**
 * Événements du circuit d'un stage : chaque transition laisse dans les journaux Moodle une trace
 * rattachée au module, à la saisie et à l'étudiant, avec les statuts résultants.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_trigger_entry_event
 * @covers     \mod_stage\event\entry_event_base
 * @covers     \mod_stage\event\course_module_viewed
 */
final class events_test extends \advanced_testcase {
    /**
     * Prépare une activité, un étudiant, un enseignant et une thématique.
     *
     * @return array Activité, module, étudiant, enseignant, thématique, générateur.
     */
    private function prepare(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $stage = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        $cm = get_coursemodule_from_instance('stage', $stage->id);
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $theme = $generator->create_theme($stage);
        return [$stage, $cm, $student, $teacher, $theme, $generator];
    }

    /**
     * Vérifie ce que tout événement du circuit doit porter : la saisie, l'étudiant, le module,
     * un nom, une description et une URL exploitables.
     *
     * @param \core\event\base[] $events
     * @param \stdClass $entry
     * @param int $studentid
     * @param int $cmid
     */
    private function assert_entry_events(array $events, \stdClass $entry, int $studentid, int $cmid): void {
        foreach ($events as $event) {
            $this->assertInstanceOf(event\entry_event_base::class, $event);
            $this->assertSame((int) $entry->id, (int) $event->objectid);
            $this->assertSame('stage_entry', $event->objecttable);
            $this->assertSame($studentid, (int) $event->relateduserid);
            $this->assertSame($cmid, (int) $event->contextinstanceid);
            $this->assertNotEmpty($event->get_name());
            $this->assertStringContainsString("#{$entry->id}", $event->get_description());
            $this->assertSame($cmid, (int) $event->get_url()->get_param('id'));
            $this->assertSame((int) $entry->id, (int) $event->get_url()->get_param('entryid'));
            $this->assertArrayHasKey('status', $event->other);
            $this->assertArrayHasKey('conventionstatus', $event->other);
        }
    }

    /**
     * Enregistrement, évaluations, validation, réinitialisation, non-validations et annulation
     * déclenchent chacun leur événement, dans l'ordre, avec le statut résultant.
     */
    public function test_entry_transitions_trigger_events(): void {
        [$stage, $cm, $student, $teacher, $theme, $generator] = $this->prepare();
        $deve = get_admin();

        $sink = $this->redirectEvents();
        $entry = $generator->create_entry($stage, $student->id, $theme);
        stage_apply_student_eval($entry, 'Mon auto-évaluation');
        stage_apply_teacher_eval($entry, $teacher->id, 'Très bien');
        stage_apply_deve_validation($entry, $deve->id, 12);
        stage_reset_entry($entry);
        stage_reject_by_teacher($entry, $teacher->id, 'Rapport manquant');
        stage_reset_entry($entry);
        stage_reject_by_deve($entry, $deve->id, 'Convention absente');
        stage_cancel_entry($entry, $deve->id, 'Stage interrompu');
        $events = $sink->get_events();
        $sink->close();

        $this->assertSame([
            event\entry_created::class,
            event\entry_student_evaluated::class,
            event\entry_teacher_evaluated::class,
            event\entry_validated::class,
            event\entry_reset::class,
            event\entry_rejected::class,
            event\entry_reset::class,
            event\entry_rejected::class,
            event\entry_cancelled::class,
        ], array_map('get_class', $events));
        $this->assert_entry_events($events, $entry, (int) $student->id, (int) $cm->id);

        $this->assertSame(STAGE_STATUS_ENREGISTRE, $events[0]->other['status']);
        $this->assertSame('c', $events[0]->crud);
        $this->assertSame(STAGE_STATUS_EVAL_ETUDIANT, $events[1]->other['status']);
        $this->assertSame(STAGE_STATUS_EVAL_ENSEIGNANT, $events[2]->other['status']);
        $this->assertSame(STAGE_STATUS_VALIDE_DEVE, $events[3]->other['status']);
        $this->assertSame(12, $events[3]->other['retainedduration']);
        $this->assertStringContainsString('12 jour', $events[3]->get_description());
        $this->assertSame(STAGE_STATUS_ENREGISTRE, $events[4]->other['status']);
        $this->assertSame('teacher', $events[5]->other['role']);
        $this->assertStringContainsString('enseignant référent', $events[5]->get_description());
        $this->assertSame('deve', $events[7]->other['role']);
        $this->assertStringContainsString('DEVE', $events[7]->get_description());
        $this->assertSame(STAGE_STATUS_ANNULE, $events[8]->other['status']);
    }

    /**
     * Le circuit de la convention est tracé de la demande à la signature ou au refus, dispense
     * comprise ; une dispense inchangée ne produit rien.
     */
    public function test_convention_transitions_trigger_events(): void {
        [$stage, $cm, $student, $teacher, $theme, $generator] = $this->prepare();
        $deve = get_admin();
        $entry = $generator->create_entry($stage, $student->id, $theme);

        $sink = $this->redirectEvents();
        stage_request_convention($entry, 0, true);
        stage_teacher_validate_convention($entry, $teacher->id);
        stage_convention_mark_edited($entry, $deve->id);
        stage_convention_mark_signed($entry, $deve->id);
        stage_reject_convention($entry, $deve->id, 'Dates incohérentes');
        stage_set_entry_convention_exempt($entry, true);
        stage_set_entry_convention_exempt($entry, true);
        stage_set_entry_convention_exempt($entry, false);
        $events = $sink->get_events();
        $sink->close();

        $this->assertSame([
            event\convention_requested::class,
            event\convention_teacher_validated::class,
            event\convention_edited::class,
            event\convention_signed::class,
            event\convention_rejected::class,
            event\convention_exemption_changed::class,
            event\convention_exemption_changed::class,
        ], array_map('get_class', $events));
        $this->assert_entry_events($events, $entry, (int) $student->id, (int) $cm->id);

        $this->assertSame(1, $events[0]->other['teacherpending']);
        $this->assertSame(STAGE_CONVENTION_TEACHERPENDING, $events[0]->other['conventionstatus']);
        $this->assertStringContainsString('enseignant référent', $events[0]->get_description());
        $this->assertSame(STAGE_CONVENTION_REQUESTED, $events[1]->other['conventionstatus']);
        $this->assertSame(STAGE_CONVENTION_EDITED, $events[2]->other['conventionstatus']);
        $this->assertSame(STAGE_CONVENTION_SIGNED, $events[3]->other['conventionstatus']);
        $this->assertSame(STAGE_CONVENTION_REJECTED, $events[4]->other['conventionstatus']);
        $this->assertSame(1, $events[5]->other['exempt']);
        $this->assertSame(STAGE_CONVENTION_EXEMPT, $events[5]->other['conventionstatus']);
        $this->assertSame(0, $events[6]->other['exempt']);
        $this->assertSame(STAGE_CONVENTION_NONE, $events[6]->other['conventionstatus']);
    }

    /**
     * Le maître de stage n'a pas de compte Moodle : son évaluation est tracée sans utilisateur
     * (0), mais toujours rattachée au stage et à l'étudiant.
     */
    public function test_tutor_evaluation_is_traced_without_moodle_user(): void {
        [$stage, $cm, $student, $teacher, $theme, $generator] = $this->prepare();
        $entry = $generator->create_entry($stage, $student->id, $theme);
        $this->setUser();

        $sink = $this->redirectEvents();
        $error = stage_submit_tutor_eval($entry, [], [], 'Stagiaire sérieux et autonome.');
        $events = $sink->get_events();
        $sink->close();

        $this->assertNull($error);
        $this->assertCount(1, $events);
        $this->assertInstanceOf(event\entry_tutor_evaluated::class, $events[0]);
        $this->assertSame(0, (int) $events[0]->userid);
        $this->assertStringContainsString('maître de stage', $events[0]->get_description());
        $this->assert_entry_events($events, $entry, (int) $student->id, (int) $cm->id);
    }

    /**
     * La consultation de l'activité est l'événement standard de Moodle, rattaché à l'instance.
     */
    public function test_course_module_viewed_event(): void {
        [$stage, $cm] = $this->prepare();
        $context = \context_module::instance($cm->id);

        $sink = $this->redirectEvents();
        $event = event\course_module_viewed::create(['objectid' => $stage->id, 'context' => $context]);
        $event->trigger();
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(\core\event\course_module_viewed::class, $events[0]);
        $this->assertSame('stage', $events[0]->objecttable);
        $this->assertSame((int) $stage->id, (int) $events[0]->objectid);
        $this->assertSame((int) $cm->id, (int) $events[0]->contextinstanceid);
        $this->assertNotEmpty($events[0]->get_name());
    }
}
