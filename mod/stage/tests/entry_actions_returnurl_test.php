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
 * Tests que les liens d'action rendus par stage_render_entry_management_actions() (utilisée aussi
 * bien sur le bilan de l'étudiant, view.php, que sur le tableau de pilotage, dashboard.php)
 * transmettent bien la page appelante en "returnurl", pour que les pages cibles (entrydetail.php,
 * register.php, cancel_entry.php, teacher.php, deve.php, convention_*.php) ramènent l'utilisateur
 * là d'où il vient plutôt que sur une liste fixe.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_render_entry_management_actions
 */
final class entry_actions_returnurl_test extends \advanced_testcase {

    /**
     * Chaque lien d'action porte bien un paramètre returnurl encodant la page appelante ($PAGE->url,
     * telle que fixée par view.php ou dashboard.php avant d'appeler stage_print_student_dashboard()).
     */
    public function test_action_links_carry_the_calling_page_as_returnurl(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $stage = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        $cm = get_coursemodule_from_instance('stage', $stage->id);
        $context = \context_module::instance($cm->id);
        /** @var \mod_stage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $theme = $generator->create_theme($stage);
        $student = $this->getDataGenerator()->create_user();
        $entry = $generator->create_entry($stage, $student->id, $theme);

        // La page appelante : le résumé de l'étudiant dans le tableau de pilotage, pour laquelle
        // aucune des pages ciblées n'a de secours pertinent codé en dur.
        $callingurl = new \moodle_url('/mod/stage/dashboard.php', ['id' => $cm->id, 'studentid' => $student->id]);
        $PAGE->set_url($callingurl);
        $expectedreturnurl = 'returnurl=' . urlencode($callingurl->out_as_local_url(false));

        $rights = (object) [
            'register' => true, 'validatedeve' => true, 'assignedteacher' => true, 'viewdetail' => true,
        ];

        // Détail en lecture seule.
        $html = stage_render_entry_management_actions($entry, $cm, $context, $rights);
        $this->assertStringContainsString('entrydetail.php', $html);
        $this->assertStringContainsString($expectedreturnurl, $html);

        // Modifier (register.php).
        $this->assertStringContainsString('register.php', $html);

        // "Évaluer" (teacher.php) : en attente d'auto-évaluation de l'étudiant.
        $DB->set_field('stage_entry', 'status', STAGE_STATUS_EVAL_ETUDIANT, ['id' => $entry->id]);
        $entry->status = STAGE_STATUS_EVAL_ETUDIANT;
        $html = stage_render_entry_management_actions($entry, $cm, $context, $rights);
        $this->assertStringContainsString('teacher.php', $html);
        $this->assertStringContainsString('mode=reset', $html);
        $this->assertStringContainsString('cancel_entry.php', $html);
        // Toutes les occurrences de returnurl dans ce rendu pointent vers la même page appelante.
        $this->assertGreaterThan(1, substr_count($html, $expectedreturnurl));

        // "Valider" (deve.php) : en attente de validation DEVE.
        $DB->set_field('stage_entry', 'status', STAGE_STATUS_EVAL_ENSEIGNANT, ['id' => $entry->id]);
        $entry->status = STAGE_STATUS_EVAL_ENSEIGNANT;
        $html = stage_render_entry_management_actions($entry, $cm, $context, $rights);
        $this->assertStringContainsString('deve.php', $html);
        $this->assertGreaterThan(1, substr_count($html, $expectedreturnurl));
    }
}
