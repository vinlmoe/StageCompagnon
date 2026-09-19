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
 * Tests des objectifs de stage rattachés à une thématique : documents à télécharger et check-list
 * renseignée par l'étudiant lors de sa demande de convention.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_get_theme_checklist
 * @covers     ::stage_get_theme_checklists
 * @covers     ::stage_save_entry_checklist
 * @covers     ::stage_extract_submitted_checklist
 * @covers     ::stage_validate_checklist
 * @covers     ::stage_checklist_form_data
 * @covers     ::stage_can_edit_entry_checklist
 * @covers     ::stage_delete_theme_checklist
 * @covers     ::stage_get_theme_objective_files
 * @covers     ::stage_import_themes
 */
final class theme_objectives_test extends \advanced_testcase {
    /**
     * Prépare une activité, une thématique dotée de deux objectifs, un étudiant et sa saisie.
     *
     * @return array [stdClass $stage, \context_module $context, stdClass $theme, stdClass $entry,
     *                stdClass $student, array $items]
     */
    private function prepare(): array {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $stage = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        $context = \context_module::instance($stage->cmid);

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        /** @var \mod_stage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $theme = $generator->create_theme($stage);
        $generator->create_checklist_item($theme, ['name' => 'Consultation en autonomie', 'sortorder' => 1]);
        $generator->create_checklist_item($theme, ['name' => 'Chirurgie observée', 'sortorder' => 0]);
        $entry = $generator->create_entry($stage, $student->id, $theme);

        return [$stage, $context, $theme, $entry, $student, stage_get_theme_checklist($theme->id)];
    }

    /**
     * La check-list d'une thématique est rendue dans l'ordre d'affichage défini par la DEVE, et
     * non dans l'ordre de création.
     */
    public function test_checklist_is_sorted_by_sortorder(): void {
        [, , , , , $items] = $this->prepare();

        $names = array_values(array_map(function ($item) {
            return $item->name;
        }, $items));
        $this->assertSame(['Chirurgie observée', 'Consultation en autonomie'], $names);
    }

    /**
     * Le chargement groupé renvoie une entrée par thématique demandée, y compris pour celles qui
     * n'ont aucun objectif : le formulaire de demande de convention s'appuie dessus pour n'avoir à
     * traiter qu'un seul format.
     */
    public function test_checklists_by_theme_includes_empty_themes(): void {
        [$stage, , $theme] = $this->prepare();

        /** @var \mod_stage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $othertheme = $generator->create_theme($stage);

        $lists = stage_get_theme_checklists([$theme->id, $othertheme->id]);
        $this->assertCount(2, $lists);
        $this->assertCount(2, $lists[$theme->id]);
        $this->assertSame([], $lists[$othertheme->id]);
    }

    /**
     * Un objectif laissé décoché sans justification est refusé : c'est tout l'objet du champ
     * libre, qui accompagne la case décochée.
     */
    public function test_unchecked_item_requires_an_explanation(): void {
        [, , , , , $items] = $this->prepare();
        $first = reset($items);
        $second = end($items);

        $data = [
            stage_checklist_check_field($first->id) => 1,
            stage_checklist_comment_field($first->id) => '',
            stage_checklist_check_field($second->id) => 0,
            stage_checklist_comment_field($second->id) => '',
        ];
        $errors = stage_validate_checklist($data, $items);
        $this->assertArrayNotHasKey(stage_checklist_comment_field($first->id), $errors);
        $this->assertArrayHasKey(stage_checklist_comment_field($second->id), $errors);

        $data[stage_checklist_comment_field($second->id)] = 'Aucune chirurgie pendant la période.';
        $this->assertSame([], stage_validate_checklist($data, $items));
    }

    /**
     * Les réponses sont enregistrées puis relues telles quelles, et la justification d'un objectif
     * finalement coché est effacée plutôt que laissée orpheline à côté d'une case cochée.
     */
    public function test_answers_are_saved_and_comment_dropped_once_checked(): void {
        [, , , $entry, , $items] = $this->prepare();
        $first = reset($items);
        $second = end($items);

        $data = [
            stage_checklist_check_field($first->id) => 0,
            stage_checklist_comment_field($first->id) => 'Pas de matériel sur place.',
            stage_checklist_check_field($second->id) => 1,
            stage_checklist_comment_field($second->id) => 'Texte sans objet.',
        ];
        stage_save_entry_checklist($entry->id, $items, stage_extract_submitted_checklist($data, $items));

        $saved = stage_get_entry_checklist($entry->id);
        $this->assertCount(2, $saved);
        $this->assertEquals(0, $saved[$first->id]->checked);
        $this->assertSame('Pas de matériel sur place.', $saved[$first->id]->explanation);
        $this->assertEquals(1, $saved[$second->id]->checked);
        $this->assertSame('', $saved[$second->id]->explanation);

        // Les valeurs enregistrées reviennent dans le formulaire de correction.
        $formdata = stage_checklist_form_data($items, $saved);
        $this->assertSame(0, $formdata[stage_checklist_check_field($first->id)]);
        $this->assertSame('Pas de matériel sur place.', $formdata[stage_checklist_comment_field($first->id)]);
    }

    /**
     * Changer la thématique d'une saisie avant l'édition de sa convention emporte les réponses de
     * l'ancienne check-list : elles ne se rapportent plus à rien.
     */
    public function test_answers_of_another_theme_are_dropped(): void {
        [$stage, , , $entry, , $items] = $this->prepare();

        $data = [];
        foreach ($items as $item) {
            $data[stage_checklist_check_field($item->id)] = 1;
        }
        stage_save_entry_checklist($entry->id, $items, stage_extract_submitted_checklist($data, $items));
        $this->assertCount(2, stage_get_entry_checklist($entry->id));

        /** @var \mod_stage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $othertheme = $generator->create_theme($stage);
        $otheritem = $generator->create_checklist_item($othertheme);
        $otheritems = stage_get_theme_checklist($othertheme->id);

        stage_save_entry_checklist($entry->id, $otheritems, stage_extract_submitted_checklist(
            [stage_checklist_check_field($otheritem->id) => 1],
            $otheritems
        ));

        $saved = stage_get_entry_checklist($entry->id);
        $this->assertCount(1, $saved);
        $this->assertArrayHasKey((int) $otheritem->id, $saved);
    }

    /**
     * Supprimer la saisie d'un étudiant emporte ses réponses à la check-list.
     */
    public function test_deleting_an_entry_removes_its_answers(): void {
        global $DB;

        [, $context, , $entry, , $items] = $this->prepare();
        $data = [];
        foreach ($items as $item) {
            $data[stage_checklist_check_field($item->id)] = 1;
        }
        stage_save_entry_checklist($entry->id, $items, stage_extract_submitted_checklist($data, $items));

        stage_delete_entries([$entry->id], $context);
        $this->assertSame(0, $DB->count_records('stage_entry_checklist', ['entryid' => $entry->id]));
    }

    /**
     * Supprimer la check-list d'une thématique emporte aussi les réponses qui s'y rattachaient.
     */
    public function test_deleting_a_theme_checklist_removes_the_answers(): void {
        global $DB;

        [, , $theme, $entry, , $items] = $this->prepare();
        $data = [];
        foreach ($items as $item) {
            $data[stage_checklist_check_field($item->id)] = 1;
        }
        stage_save_entry_checklist($entry->id, $items, stage_extract_submitted_checklist($data, $items));

        stage_delete_theme_checklist($theme->id);
        $this->assertSame(0, $DB->count_records('stage_theme_checklist', ['themeid' => $theme->id]));
        $this->assertSame(0, $DB->count_records('stage_entry_checklist', ['entryid' => $entry->id]));
    }

    /**
     * La correction après coup est ouverte à la DEVE et à l'enseignant référent de l'étudiant, et
     * refusée à un enseignant qui n'est référent que d'un autre étudiant.
     */
    public function test_only_deve_and_referent_teacher_may_edit(): void {
        [$stage, $context, , $entry, , ] = $this->prepare();

        $deve = $this->getDataGenerator()->create_user();
        $referent = $this->getDataGenerator()->create_user();
        $otherteacher = $this->getDataGenerator()->create_user();
        // Le rôle « enseignant non éditeur » porte evaluateteacher sans viewall : c'est
        // exactement le profil de l'enseignant référent, à distinguer de celui de la DEVE.
        $this->getDataGenerator()->enrol_user($deve->id, $stage->course, 'manager');
        $this->getDataGenerator()->enrol_user($referent->id, $stage->course, 'teacher');
        $this->getDataGenerator()->enrol_user($otherteacher->id, $stage->course, 'teacher');

        /** @var \mod_stage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $generator->assign_teacher($stage, $entry->userid, $referent->id);

        $this->assertTrue(stage_can_edit_entry_checklist($stage, $entry, $context, $deve->id));
        $this->assertTrue(stage_can_edit_entry_checklist($stage, $entry, $context, $referent->id));
        $this->assertFalse(stage_can_edit_entry_checklist($stage, $entry, $context, $otherteacher->id));
        $this->assertFalse(stage_can_edit_entry_checklist($stage, $entry, $context, $entry->userid));
    }

    /**
     * L'import d'une autre instance recopie les objectifs avec la thématique : la check-list, et
     * les documents dès lors que les deux contextes sont connus.
     */
    public function test_import_copies_checklist_and_objective_files(): void {
        [$stage, $context, $theme] = $this->prepare();

        get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => 'mod_stage',
            'filearea' => STAGE_THEME_OBJECTIVE_FILEAREA, 'itemid' => $theme->id,
            'filepath' => '/', 'filename' => 'objectifs.pdf',
        ], 'objectifs');

        $targetcourse = $this->getDataGenerator()->create_course();
        $targetstage = $this->getDataGenerator()->create_module('stage', ['course' => $targetcourse]);
        $targetcontext = \context_module::instance($targetstage->cmid);

        $copied = stage_import_themes($stage->id, $targetstage->id, $context, $targetcontext);
        $this->assertSame(1, $copied);

        $newthemes = stage_get_themes($targetstage->id);
        $this->assertCount(1, $newthemes);
        $newtheme = reset($newthemes);

        $newitems = stage_get_theme_checklist($newtheme->id);
        $this->assertCount(2, $newitems);
        $firstitem = reset($newitems);
        $this->assertSame('Chirurgie observée', $firstitem->name);

        $this->assertCount(1, stage_get_theme_objective_files($targetcontext, $newtheme->id));
        // Les documents de l'original restent en place : l'import copie, il ne déplace pas.
        $this->assertCount(1, stage_get_theme_objective_files($context, $theme->id));
    }
}
