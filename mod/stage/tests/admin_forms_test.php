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
require_once($CFG->dirroot . '/mod/stage/classes/form/conventions_admin_form.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/notifications_form.php');

/**
 * Tests des formulaires uniques d'administration : conventions (convention_templates.php) et
 * notifications (notifications.php).
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_stage\form\conventions_admin_form
 * @covers     \mod_stage\form\notifications_form
 */
final class admin_forms_test extends \advanced_testcase {
    /**
     * Gabarit fictif, tel que le reçoit le formulaire des conventions.
     *
     * @param int $id
     * @return \stdClass
     */
    private function template(int $id): \stdClass {
        return (object) ['id' => $id, 'name' => 'Gabarit ' . $id, 'lang' => 'fr'];
    }

    /**
     * Un gabarit utilisé n'a pas de case de suppression, un gabarit libre en a une.
     */
    public function test_conventions_form_delete_only_unused_templates(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = new class (null, [
            'templates' => [3 => $this->template(3), 4 => $this->template(4)],
            'inuse' => [3 => 2],
        ]) extends \mod_stage\form\conventions_admin_form {
            /**
             * Accès au formulaire QuickForm sous-jacent.
             *
             * @return \MoodleQuickForm
             */
            public function get_mform(): \MoodleQuickForm {
                return $this->_form;
            }
        };
        $mform = $form->get_mform();

        $this->assertFalse($mform->elementExists('templatedelete_3'));
        $this->assertTrue($mform->elementExists('templateinuse_3'));
        $this->assertTrue($mform->elementExists('templatedelete_4'));
    }

    /**
     * Un nouveau gabarit exige un nom et un PDF ensemble ; un gabarit existant garde son nom,
     * sauf s'il est supprimé.
     */
    public function test_conventions_form_validation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = new \mod_stage\form\conventions_admin_form(null, [
            'templates' => [3 => $this->template(3)],
            'inuse' => [],
        ]);

        $errors = $form->validation(['newtemplatename' => 'Nouveau', 'newtemplatefile' => 0, 'templatename_3' => 'A'], []);
        $this->assertArrayHasKey('newtemplatefile', $errors);

        $errors = $form->validation(['newtemplatename' => '', 'newtemplatefile' => 0, 'templatename_3' => ' '], []);
        $this->assertArrayHasKey('templatename_3', $errors);

        $errors = $form->validation([
            'newtemplatename' => '',
            'newtemplatefile' => 0,
            'templatename_3' => '',
            'templatedelete_3' => 1,
        ], []);
        $this->assertSame([], $errors);

        // Un PDF sans nom est refusé.
        $draftitemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance(get_admin()->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => 'g.pdf',
        ], '%PDF-1.4');
        $this->assertTrue(\mod_stage\form\conventions_admin_form::draft_has_file($draftitemid));
        $errors = $form->validation(['newtemplatename' => '', 'newtemplatefile' => $draftitemid, 'templatename_3' => 'A'], []);
        $this->assertArrayHasKey('newtemplatename', $errors);
    }

    /**
     * Le formulaire des notifications porte un sujet et un corps par e-mail défini.
     */
    public function test_notifications_form_has_one_section_per_email(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $definitions = stage_get_email_definitions();
        $form = new class (null, ['definitions' => $definitions, 'customized' => []]) extends \mod_stage\form\notifications_form {
            /**
             * Accès au formulaire QuickForm sous-jacent.
             *
             * @return \MoodleQuickForm
             */
            public function get_mform(): \MoodleQuickForm {
                return $this->_form;
            }
        };
        $mform = $form->get_mform();

        $this->assertTrue($mform->elementExists('tutorevaluationenabled'));
        foreach (array_keys($definitions) as $key) {
            $this->assertTrue($mform->elementExists('subject_' . $key), $key);
            $this->assertTrue($mform->elementExists('body_' . $key), $key);
        }
    }
}
