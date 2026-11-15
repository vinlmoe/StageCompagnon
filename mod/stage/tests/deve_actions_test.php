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
 * Tests de la liste des stages qui attendent une action de la DEVE (deve_actions.php).
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_get_deve_action_entries
 * @covers     ::stage_get_deve_entry_actions
 */
final class deve_actions_test extends \advanced_testcase {
    /** @var \stdClass Activité. */
    private $stage;

    /** @var \stdClass Thématique. */
    private $theme;

    /** @var \mod_stage_generator Générateur du module. */
    private $generator;

    /**
     * Prépare une activité et une thématique.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->stage = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $this->theme = $this->generator->create_theme($this->stage);
    }

    /**
     * Crée un stage pour un nouvel étudiant, puis force les champs donnés.
     *
     * @param array $fields
     * @return \stdClass La saisie, relue en base.
     */
    private function entry(array $fields = []): \stdClass {
        global $DB;

        $student = $this->getDataGenerator()->create_user();
        $entry = $this->generator->create_entry($this->stage, $student->id, $this->theme);
        foreach ($fields as $field => $value) {
            $DB->set_field('stage_entry', $field, $value, ['id' => $entry->id]);
        }
        return $DB->get_record('stage_entry', ['id' => $entry->id], '*', MUST_EXIST);
    }

    /**
     * Chaque situation qui attend la DEVE figure dans la liste, avec la bonne action ; les autres
     * (rien à faire, stage déjà validé, stage annulé) n'y figurent pas.
     */
    public function test_lists_only_entries_awaiting_the_deve(): void {
        $requested = $this->entry(['conventionstatus' => STAGE_CONVENTION_REQUESTED]);
        $edited = $this->entry(['conventionstatus' => STAGE_CONVENTION_EDITED]);
        $tovalidate = $this->entry([
            'status' => STAGE_STATUS_EVAL_ENSEIGNANT, 'conventionstatus' => STAGE_CONVENTION_SIGNED,
        ]);
        $both = $this->entry(['status' => STAGE_STATUS_EVAL_ENSEIGNANT, 'conventionstatus' => STAGE_CONVENTION_EDITED]);

        // Rien à faire pour la DEVE : en attente de l'étudiant, de l'enseignant, déjà validé, annulé.
        $this->entry(['conventionstatus' => STAGE_CONVENTION_NONE]);
        $this->entry(['conventionstatus' => STAGE_CONVENTION_TEACHERPENDING]);
        $this->entry(['status' => STAGE_STATUS_EVAL_ETUDIANT, 'conventionstatus' => STAGE_CONVENTION_SIGNED]);
        $this->entry(['status' => STAGE_STATUS_VALIDE_DEVE, 'conventionstatus' => STAGE_CONVENTION_SIGNED]);
        $this->entry(['status' => STAGE_STATUS_ANNULE, 'conventionstatus' => STAGE_CONVENTION_REQUESTED]);

        $entries = stage_get_deve_action_entries($this->stage);

        $this->assertEqualsCanonicalizing(
            [$requested->id, $edited->id, $tovalidate->id, $both->id],
            array_keys($entries)
        );
        $this->assertSame(['conventionreview'], $entries[$requested->id]->deveactions);
        $this->assertSame(['conventionsign'], $entries[$edited->id]->deveactions);
        $this->assertSame(['validate'], $entries[$tovalidate->id]->deveactions);
        $this->assertSame(['conventionsign', 'validate'], $entries[$both->id]->deveactions);
    }

    /**
     * Le filtre par type d'action ne garde que les stages qui attendent cette action.
     */
    public function test_filters_by_action(): void {
        $this->entry(['conventionstatus' => STAGE_CONVENTION_REQUESTED]);
        $tovalidate = $this->entry(['status' => STAGE_STATUS_EVAL_ENSEIGNANT, 'conventionstatus' => STAGE_CONVENTION_SIGNED]);

        $entries = stage_get_deve_action_entries($this->stage, '', 0, 'validate');

        $this->assertSame([(int) $tovalidate->id], array_map('intval', array_keys($entries)));
    }

    /**
     * Une invitation du maître de stage en échec n'attend la DEVE que si l'évaluation par le maître
     * de stage est toujours activée, et tant qu'elle n'a été ni faite ni contournée.
     */
    public function test_failed_tutor_invitation(): void {
        global $DB;

        $failed = $this->entry([
            'status' => STAGE_STATUS_EVAL_ETUDIANT, 'conventionstatus' => STAGE_CONVENTION_SIGNED,
            'tutorrequestfailtime' => time(),
        ]);
        $bypassed = $this->entry([
            'status' => STAGE_STATUS_EVAL_ETUDIANT, 'conventionstatus' => STAGE_CONVENTION_SIGNED,
            'tutorrequestfailtime' => time(), 'tutorbypassed' => 1,
        ]);

        // Évaluation du maître de stage désactivée : rien à relancer.
        $this->assertSame([], stage_get_deve_action_entries($this->stage));

        $DB->set_field('stage', 'tutorevaluationenabled', 1, ['id' => $this->stage->id]);
        $DB->set_field('stage_theme', 'tutorevaluationenabled', 1, ['id' => $this->theme->id]);
        $stage = $DB->get_record('stage', ['id' => $this->stage->id], '*', MUST_EXIST);

        $entries = stage_get_deve_action_entries($stage);
        $this->assertSame([(int) $failed->id], array_map('intval', array_keys($entries)));
        $this->assertSame(['tutorrequestfailed'], $entries[$failed->id]->deveactions);
        $this->assertArrayNotHasKey($bypassed->id, $entries);
    }

    /**
     * La recherche porte sur le nom de l'étudiant, et le filtre de thématique sur la thématique.
     */
    public function test_search_and_theme_filters(): void {
        global $DB;

        $entry = $this->entry(['conventionstatus' => STAGE_CONVENTION_REQUESTED]);
        $DB->set_field('user', 'lastname', 'Zorglub', ['id' => $entry->userid]);
        $this->entry(['conventionstatus' => STAGE_CONVENTION_REQUESTED]);
        $othertheme = $this->generator->create_theme($this->stage);

        $this->assertSame(
            [(int) $entry->id],
            array_map('intval', array_keys(stage_get_deve_action_entries($this->stage, 'zorglub')))
        );
        $this->assertCount(2, stage_get_deve_action_entries($this->stage, '', $this->theme->id));
        $this->assertSame([], stage_get_deve_action_entries($this->stage, '', $othertheme->id));
    }
}
