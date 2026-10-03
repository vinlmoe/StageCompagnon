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
 * Tests du courriel annonçant à l'étudiant que sa convention est téléchargeable, envoyé lorsque
 * la DEVE génère la convention avec son cadre de signatures (voir convention.php).
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_notify_student_convention_ready
 * @covers     ::stage_convention_validate_from_review
 * @covers     ::stage_get_email_definitions
 * @covers     ::stage_resolve_email_text
 */
final class convention_ready_notification_test extends \advanced_testcase {
    /**
     * Prépare une activité, un étudiant inscrit et sa saisie de stage.
     *
     * @return array [stdClass $stage, stdClass $cm, stdClass $entry, stdClass $student]
     */
    private function prepare(): array {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $stage = $this->getDataGenerator()->create_module('stage', ['course' => $course, 'name' => 'Stages A3']);
        $cm = get_coursemodule_from_instance('stage', $stage->id, 0, false, MUST_EXIST);

        $student = $this->getDataGenerator()->create_user(['email' => 'etudiant@example.com']);
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        /** @var \mod_stage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $theme = $generator->create_theme($stage, ['name' => 'Animaux de compagnie']);
        $entry = $generator->create_entry($stage, $student->id, $theme);

        return [$stage, $cm, $entry, $student];
    }

    /**
     * Le courriel est bien proposé à la personnalisation par la DEVE : sans entrée dans
     * stage_get_email_definitions(), il n'apparaîtrait pas sur la page « Notifications ».
     */
    public function test_the_email_is_customisable_from_the_administration(): void {
        $definitions = stage_get_email_definitions();

        $this->assertArrayHasKey('conventionready', $definitions);
        $this->assertSame(
            ['student', 'stage', 'theme', 'url'],
            $definitions['conventionready']['vars']
        );
    }

    /**
     * Le texte par défaut nomme la thématique et porte le lien vers l'espace de l'étudiant.
     */
    public function test_default_text_names_the_theme_and_carries_the_link(): void {
        [$stage, $cm, $entry, $student] = $this->prepare();

        $sink = $this->redirectEmails();
        $this->assertTrue(stage_notify_student_convention_ready($stage, $cm, $entry));
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertSame($student->email, $message->to);
        $this->assertStringContainsString('Stages A3', $message->subject);
        $body = quoted_printable_decode($message->body);
        $this->assertStringContainsString('Animaux de compagnie', $body);
        $this->assertStringContainsString('/mod/stage/view.php?id=' . $cm->id, $body);
    }

    /**
     * Un texte personnalisé par la DEVE remplace le texte par défaut, jetons {{...}} substitués.
     */
    public function test_custom_text_replaces_the_default_one(): void {
        [$stage, $cm, $entry] = $this->prepare();

        stage_save_email_template(
            $stage->id,
            'conventionready',
            'Convention disponible pour {{student}}',
            'Bonjour {{student}}, votre convention « {{theme}} » vous attend ici : {{url}}'
        );

        $sink = $this->redirectEmails();
        stage_notify_student_convention_ready($stage, $cm, $entry);
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertStringContainsString('Convention disponible pour', $message->subject);
        $body = quoted_printable_decode($message->body);
        $this->assertStringContainsString('votre convention', $body);
        $this->assertStringContainsString('Animaux de compagnie', $body);
        $this->assertStringNotContainsString('{{', $body);
    }

    /**
     * Régression : depuis que la page de téléchargement n'envoie plus le courriel sur une simple
     * requête GET, la validation par la DEVE depuis sa revue ne prévenait plus l'étudiant. Le
     * courriel part désormais à la validation elle-même, seulement pour l'exemplaire à signer et
     * si le PDF peut être produit.
     */
    public function test_review_validation_notifies_only_for_printable_signed_copy(): void {
        global $DB, $USER;
        [$stage, $cm, $entry] = $this->prepare();
        $this->setAdminUser();
        $context = \context_module::instance($cm->id);
        $DB->set_field('stage_entry', 'conventionstatus', STAGE_CONVENTION_REQUESTED, ['id' => $entry->id]);
        $entry = $DB->get_record('stage_entry', ['id' => $entry->id]);
        $sink = $this->redirectEmails();

        // Sans gabarit, le PDF ne peut pas être produit : pas de courriel.
        $outcome = stage_convention_validate_from_review($stage, $cm, $context, $entry, $USER->id, true);
        $this->assertSame('conventionnotemplatechosen', $outcome['error']);
        $this->assertNull($outcome['notified']);
        $this->assertEquals(STAGE_CONVENTION_EDITED, $DB->get_field('stage_entry', 'conventionstatus', ['id' => $entry->id]));
        $this->assertCount(0, $sink->get_messages());

        $templateid = $DB->insert_record('stage_convention_template', (object) [
            'stageid' => $stage->id, 'name' => 'Standard', 'lang' => 'fr', 'timecreated' => time(), 'timemodified' => time(),
        ]);
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => 'mod_stage', 'filearea' => 'conventiontemplate',
            'itemid' => $templateid, 'filepath' => '/', 'filename' => 'gabarit.pdf',
        ], '%PDF-1.4');
        $entry->conventiontemplateid = $templateid;
        $DB->set_field('stage_entry', 'conventiontemplateid', $templateid, ['id' => $entry->id]);

        $outcome = stage_convention_validate_from_review($stage, $cm, $context, $entry, $USER->id, false);
        $this->assertNull($outcome['error']);
        $this->assertNull($outcome['notified']);
        $this->assertCount(0, $sink->get_messages());

        $outcome = stage_convention_validate_from_review($stage, $cm, $context, $entry, $USER->id, true);
        $this->assertNull($outcome['error']);
        $this->assertTrue($outcome['notified']);
        $this->assertCount(1, $sink->get_messages());
        $sink->close();
    }

    /**
     * Une saisie dont l'étudiant n'existe plus ne fait pas échouer la génération de la convention :
     * il n'y a simplement personne à prévenir.
     */
    public function test_a_missing_student_is_not_an_error(): void {
        [$stage, $cm, $entry] = $this->prepare();
        $entry->userid = -1;

        $sink = $this->redirectEmails();
        $this->assertFalse(stage_notify_student_convention_ready($stage, $cm, $entry));
        $this->assertCount(0, $sink->get_messages());
        $sink->close();
    }
}
