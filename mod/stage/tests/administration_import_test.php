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

/**
 * Tests de copie des paramètres entre activités.
 *
 * @package mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers ::stage_import_from_stage
 * @covers ::stage_import_email_templates
 * @covers ::stage_import_convention_templates
 * @covers ::stage_import_convention_logos
 * @covers ::stage_import_establishment_info
 * @covers ::stage_import_themes
 */
final class administration_import_test extends \advanced_testcase {
    /**
     * Prépare deux activités indépendantes.
     *
     * @return array
     */
    private function fixture(): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/stage/locallib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $source = $gen->create_module('stage', ['course' => $gen->create_course()]);
        $target = $gen->create_module('stage', ['course' => $gen->create_course()]);
        return [$source, \context_module::instance($source->cmid), $target, \context_module::instance($target->cmid)];
    }

    /**
     * Sans option sélectionnée, la cible reste intacte.
     */
    public function test_no_options_does_not_copy_data(): void {
        global $DB;
        [$source, $sourcecontext, $target, $targetcontext] = $this->fixture();
        $this->getDataGenerator()->get_plugin_generator('mod_stage')->create_theme($source);
        stage_save_email_template($source->id, 'selfeval', 'Source', 'Corps');
        stage_save_email_template($target->id, 'selfeval', 'Cible', 'À garder');
        $result = stage_import_from_stage($source, $sourcecontext, $target, $targetcontext, []);
        $this->assertEquals((object) [
            'themes' => 0, 'templates' => 0, 'logos' => 0, 'emails' => 0, 'establishment' => false,
        ], $result);
        $this->assertEquals(0, $DB->count_records('stage_theme', ['stageid' => $target->id]));
        $this->assertSame('Cible', stage_get_custom_email_template($target->id, 'selfeval')->subject);
    }

    /**
     * Les thématiques s'ajoutent avec durées et objectifs, sans toucher à la source.
     */
    public function test_theme_import_preserves_settings_durations_and_checklist(): void {
        global $DB;
        [$source, $sourcecontext, $target, $targetcontext] = $this->fixture();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $theme = $gen->create_theme($source, [
            'name' => 'Clinique', 'mandatory' => 0, 'minstudyyear' => 2, 'maxstudyyear' => 4,
            'tutorevaluationenabled' => 0, 'reportmode' => STAGE_REPORT_REQUIRED,
        ]);
        $gen->create_checklist_item($theme, ['name' => 'Objectif clinique', 'description' => 'Description']);
        stage_set_theme_duration($theme->id, 2, 7);
        $existing = $gen->create_theme($target, ['name' => 'Existante']);
        $result = stage_import_from_stage($source, $sourcecontext, $target, $targetcontext, ['themes' => true]);
        $this->assertSame(1, $result->themes);
        $this->assertEquals(2, $DB->count_records('stage_theme', ['stageid' => $target->id]));
        $copy = $DB->get_record('stage_theme', ['stageid' => $target->id, 'name' => 'Clinique']);
        $this->assertNotEquals($theme->id, $copy->id);
        foreach (['mandatory', 'minstudyyear', 'maxstudyyear', 'tutorevaluationenabled', 'reportmode'] as $field) {
            $this->assertEquals($theme->$field, $copy->$field);
        }
        $this->assertEquals([2 => 7], stage_get_theme_durations($copy->id));
        $items = stage_get_theme_checklist($copy->id);
        $this->assertCount(1, $items);
        $this->assertSame('Objectif clinique', reset($items)->name);
        $this->assertTrue($DB->record_exists('stage_theme', ['id' => $existing->id]));
        $this->assertCount(1, stage_get_theme_checklist($theme->id));
    }

    /**
     * Une personnalisation absente, vide ou inconnue ne remplace pas celle de la cible.
     */
    public function test_email_import_overwrites_only_known_nonempty_templates(): void {
        global $DB;
        [$source, $sourcecontext, $target, $targetcontext] = $this->fixture();
        stage_save_email_template($source->id, 'selfeval', 'Nouveau', 'Nouveau corps');
        stage_save_email_template($target->id, 'selfeval', 'Ancien', 'Ancien corps');
        stage_save_email_template($target->id, 'teacherpending', 'À garder', 'Corps');
        stage_save_email_template($target->id, 'tutorrequest', 'Absent source', 'Corps');
        foreach (['teacherpending', 'unknownkey'] as $key) {
            $DB->insert_record('stage_email_template', (object) [
                'stageid' => $source->id, 'emailkey' => $key,
                'subject' => $key === 'unknownkey' ? 'Inconnu' : ' ', 'body' => '',
                'timecreated' => time(), 'timemodified' => time(),
            ]);
        }
        $result = stage_import_from_stage($source, $sourcecontext, $target, $targetcontext, ['emails' => true]);
        $this->assertSame(1, $result->emails);
        $this->assertSame('Nouveau', stage_get_custom_email_template($target->id, 'selfeval')->subject);
        $this->assertSame('Nouveau corps', stage_get_custom_email_template($target->id, 'selfeval')->body);
        $this->assertSame('À garder', stage_get_custom_email_template($target->id, 'teacherpending')->subject);
        $this->assertSame('Absent source', stage_get_custom_email_template($target->id, 'tutorrequest')->subject);
        $this->assertNull(stage_get_custom_email_template($target->id, 'unknownkey'));
    }

    /**
     * Les PDF sont copiés avec un nouvel identifiant et les logos remplacés par côté.
     */
    public function test_copies_template_file_and_replaces_only_available_logo(): void {
        global $DB;
        [$source, $sourcecontext, $target, $targetcontext] = $this->fixture();
        $templateid = $DB->insert_record('stage_convention_template', (object) [
            'stageid' => $source->id, 'name' => 'Convention', 'lang' => 'fr',
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $fs = get_file_storage();
        foreach (
            [
            [$sourcecontext, 'conventiontemplate', $templateid, 'template.pdf', '%PDF-test'],
            [$sourcecontext, 'conventionlogoleft', 0, 'new.png', 'new logo'],
            [$targetcontext, 'conventionlogoleft', 0, 'old.png', 'old logo'],
            [$targetcontext, 'conventionlogoright', 0, 'right.png', 'keep logo'],
            ] as [$context, $area, $itemid, $filename, $content]
        ) {
            $fs->create_file_from_string([
                'contextid' => $context->id, 'component' => 'mod_stage', 'filearea' => $area,
                'itemid' => $itemid, 'filepath' => '/', 'filename' => $filename,
            ], $content);
        }
        $result = stage_import_from_stage($source, $sourcecontext, $target, $targetcontext, [
            'templates' => true, 'logos' => true,
        ]);
        $this->assertSame(1, $result->templates);
        $this->assertSame(1, $result->logos);
        $copy = $DB->get_record('stage_convention_template', ['stageid' => $target->id]);
        $this->assertNotEquals($templateid, $copy->id);
        $this->assertSame('fr', $copy->lang);
        $this->assertSame('%PDF-test', stage_get_convention_template_file($targetcontext, $copy->id)->get_content());
        $this->assertSame('new logo', stage_get_convention_logo_file($targetcontext, 'left')->get_content());
        $this->assertSame('keep logo', stage_get_convention_logo_file($targetcontext, 'right')->get_content());
        $this->assertCount(1, $fs->get_area_files($targetcontext->id, 'mod_stage', 'conventionlogoleft', 0, 'id', false));
        $this->assertSame('%PDF-test', stage_get_convention_template_file($sourcecontext, $templateid)->get_content());
    }

    /**
     * Les informations établissement remplacent celles de la cible sur demande.
     */
    public function test_establishment_import_replaces_target_fields(): void {
        global $DB;
        [$source, $sourcecontext, $target, $targetcontext] = $this->fixture();
        $fields = (object) [
            'establishmentname' => 'École source', 'establishmentaddress' => '1 rue École',
            'establishmentrepresentative' => 'Direction', 'establishmentrepresentativetitle' => 'Directrice',
            'establishmentphone' => '0102030405', 'establishmentemail' => 'school@example.com',
            'establishmentsignatory' => 'Signataire source',
        ];
        stage_save_establishment_info($source->id, $fields);
        $result = stage_import_from_stage($source, $sourcecontext, $target, $targetcontext, ['establishment' => true]);
        $this->assertTrue($result->establishment);
        $saved = $DB->get_record('stage', ['id' => $target->id]);
        foreach ($fields as $field => $value) {
            $this->assertSame($value, $saved->$field);
        }
    }
}
