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
 * Tests de l'accès à la convention générée (non signée) depuis le bilan : la DEVE conserve son
 * lien « Générer la convention », tandis que l'enseignant référent (sans le droit d'enregistrement
 * des stages) obtient un lien « Consulter la convention » distinct, uniquement une fois la
 * convention éditée par la DEVE et tant qu'elle n'est pas encore signée.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_render_entry_management_actions
 */
final class convention_access_test extends \advanced_testcase {

    /**
     * Prépare un stage, une thématique, une saisie et son contexte de module.
     *
     * @return array [stdClass $cm, context $context, stdClass $entry]
     */
    private function prepare_entry(): array {
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

        return [$cm, $context, $entry];
    }

    /**
     * L'enseignant référent (sans mod/stage:registerstages) obtient un lien de consultation de la
     * convention une fois qu'elle est éditée par la DEVE, mais pas avant (encore seulement
     * demandée) ni après (déjà signée, où seule la convention signée reste accessible).
     */
    public function test_referent_teacher_can_view_edited_convention_only(): void {
        global $DB;
        [$cm, $context, $entry] = $this->prepare_entry();

        $rights = (object) ['register' => false, 'validatedeve' => false, 'assignedteacher' => true, 'viewdetail' => true];

        $DB->set_field('stage_entry', 'conventionstatus', STAGE_CONVENTION_REQUESTED, ['id' => $entry->id]);
        $entry->conventionstatus = STAGE_CONVENTION_REQUESTED;
        $html = stage_render_entry_management_actions($entry, $cm, $context, $rights);
        $this->assertStringNotContainsString(get_string('viewconvention', 'mod_stage'), $html);

        $DB->set_field('stage_entry', 'conventionstatus', STAGE_CONVENTION_EDITED, ['id' => $entry->id]);
        $entry->conventionstatus = STAGE_CONVENTION_EDITED;
        $html = stage_render_entry_management_actions($entry, $cm, $context, $rights);
        $this->assertStringContainsString(get_string('viewconvention', 'mod_stage'), $html);
        $this->assertStringContainsString('convention.php', $html);
        // Le lien de la DEVE ("Générer la convention") ne lui est pas destiné : il n'a pas ce droit.
        $this->assertStringNotContainsString(get_string('generateconvention', 'mod_stage'), $html);
    }

    /**
     * La DEVE conserve son lien "Générer la convention" habituel (droit d'enregistrement des
     * stages) : le nouveau lien de consultation, réservé à l'enseignant référent, ne s'y substitue
     * pas.
     */
    public function test_deve_keeps_generate_link_instead_of_view_link(): void {
        [$cm, $context, $entry] = $this->prepare_entry();

        $rights = (object) ['register' => true, 'validatedeve' => false, 'assignedteacher' => false, 'viewdetail' => true];
        $entry->conventionstatus = STAGE_CONVENTION_EDITED;

        $html = stage_render_entry_management_actions($entry, $cm, $context, $rights);
        $this->assertStringContainsString(get_string('generateconvention', 'mod_stage'), $html);
        $this->assertStringNotContainsString(get_string('viewconvention', 'mod_stage'), $html);
    }
}
