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

namespace mod_stagesynthesis;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/stagesynthesis/locallib.php');

/**
 * Vérifie qui voit le lien de gestion des liens : lier une activité désigne des cours et des
 * promotions extérieurs à celui-ci, c'est donc à qui administre l'activité (enseignant éditeur,
 * gestionnaire) de l'arbitrer. L'enseignant non éditeur, lui, ne doit ni pouvoir le faire ni même
 * voir le lien, tout en gardant l'accès à la synthèse de ses propres étudiants.
 *
 * @package    mod_stagesynthesis
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stagesynthesis_render_managelinks_notice
 */
final class managelinks_access_test extends \advanced_testcase {

    /**
     * Crée une activité de synthèse et renvoie de quoi tester les capacités dans son contexte.
     *
     * @return array [stdClass $synthesis, stdClass $cm, \context_module $context, stdClass $course]
     */
    private function prepare(): array {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $synthesis = $this->getDataGenerator()->create_module('stagesynthesis', ['course' => $course]);
        $cm = get_coursemodule_from_id('stagesynthesis', $synthesis->cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        return [$synthesis, $cm, $context, $course];
    }

    /**
     * L'enseignant non éditeur n'a pas la capacité et ne voit pas le lien qu'elle commande, tout en
     * conservant l'accès à la synthèse elle-même : c'est bien son usage normal.
     *
     * @return void
     */
    public function test_non_editing_teacher_cannot_manage_links(): void {
        [$synthesis, $cm, $context, $course] = $this->prepare();

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'teacher');
        $this->setUser($user);

        $this->assertFalse(has_capability('mod/stagesynthesis:managelinks', $context));
        $this->assertSame('', stagesynthesis_render_managelinks_notice($synthesis, $cm, $context));
        $this->assertTrue(has_capability('mod/stagesynthesis:view', $context));
    }

    /**
     * L'enseignant éditeur administre l'activité dans son cours : il conserve la capacité et voit
     * le lien.
     *
     * @return void
     */
    public function test_editing_teacher_can_manage_links(): void {
        [$synthesis, $cm, $context, $course] = $this->prepare();

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);

        $this->assertTrue(has_capability('mod/stagesynthesis:managelinks', $context));
        $this->assertStringContainsString('/mod/stagesynthesis/administration.php',
            stagesynthesis_render_managelinks_notice($synthesis, $cm, $context));
    }

    /**
     * Le gestionnaire, lui aussi, conserve la capacité et voit le lien.
     *
     * @return void
     */
    public function test_manager_can_manage_links(): void {
        global $DB;

        [$synthesis, $cm, $context] = $this->prepare();

        // Le rôle « manager » de l'installation standard est utilisé plutôt qu'un rôle créé pour
        // l'occasion : c'est lui qui reçoit les capacités déclarées par archétype dans access.php,
        // et donc lui qui reflète réellement le comportement en production.
        $manager = $this->getDataGenerator()->create_user();
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerroleid, $manager->id, \context_system::instance()->id);
        $this->setUser($manager);

        $this->assertTrue(has_capability('mod/stagesynthesis:managelinks', $context));
        $this->assertStringContainsString('/mod/stagesynthesis/administration.php',
            stagesynthesis_render_managelinks_notice($synthesis, $cm, $context));
    }
}
