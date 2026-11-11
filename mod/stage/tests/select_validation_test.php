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

use mod_stage\form\convention_request_form;
use mod_stage\form\student_register_form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/stage/locallib.php');

/**
 * Listes déroulantes des formulaires de demande de convention : une valeur absente des options
 * (formulaire falsifié) est refusée par la validation, sur son champ, au lieu de disparaître des
 * données et de faire échouer l'enregistrement sur une contrainte NOT NULL.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_validate_select_fields
 * @covers     \mod_stage\form\convention_request_form
 * @covers     \mod_stage\form\student_register_form
 */
final class select_validation_test extends \advanced_testcase {
    /**
     * Activité, étudiant connecté avec un référent attribué, thématique, saisie et gabarit français.
     *
     * @return array [\stdClass $stage, \stdClass $student, \stdClass $referent, \stdClass $theme,
     *               \stdClass $entry, \stdClass $template]
     */
    private function fixture(): array {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $stage = $gen->create_module('stage', ['course' => $course]);
        // Année d'étude courante fixée : l'étudiant choisit alors parmi N-1, N et N+1.
        $DB->set_field('stage', 'currentstudyyear', 3, ['id' => $stage->id]);
        $stage = $DB->get_record('stage', ['id' => $stage->id]);
        $student = $gen->create_user();
        $referent = $gen->create_user();
        $gen->enrol_user($student->id, $course->id, 'student');
        $gen->enrol_user($referent->id, $course->id, 'teacher');
        /** @var \mod_stage_generator $generator */
        $generator = $gen->get_plugin_generator('mod_stage');
        $theme = $generator->create_theme($stage, ['name' => 'Clinique']);
        $generator->assign_teacher($stage, $student->id, $referent->id);
        $entry = $generator->create_entry($stage, $student->id, $theme);
        $template = $generator->create_convention_template($stage, ['lang' => 'fr']);
        $this->setUser($student);
        return [$stage, $student, $referent, $theme, $entry, $template];
    }

    /**
     * Date à soumettre pour un sélecteur de date, $days jours après aujourd'hui.
     *
     * @param int $days
     * @return array
     */
    private function date_field(int $days): array {
        $time = time() + $days * DAYSECS;
        return [
            'day' => (int) userdate($time, '%d'),
            'month' => (int) userdate($time, '%m'),
            'year' => (int) userdate($time, '%Y'),
        ];
    }

    /**
     * Valeurs d'une demande de convention complète et valide, communes aux deux formulaires.
     *
     * @param \stdClass $referent
     * @param \stdClass $template
     * @return array
     */
    private function valid_request(\stdClass $referent, \stdClass $template): array {
        return [
            'conventionlang' => 'fr',
            'conventiontemplateid' => $template->id,
            'paperrequestedbystudent' => 0,
            'referentteacherid' => $referent->id,
            'yearsituation' => 'normal',
            'stagetype' => 'obligatoire',
            'periodrepeats' => 1,
            'perioddatestart' => [0 => $this->date_field(30)],
            'perioddateend' => [0 => $this->date_field(40)],
            'studentbirthdate' => ['day' => 1, 'month' => 1, 'year' => 2000],
            'studentaddress' => '1 rue de la Gare',
            'studentphone' => '0600000000',
            'hostaddress' => '2 avenue des Champs',
            'hostrepresentative' => 'Dr Martin',
            'hostrepresentativetitle' => 'Gérant',
            'hostservice' => 'Canine',
            'hostphone' => '0478000000',
            'hostemail' => 'clinique@example.com',
            'siret' => '',
            'hostlocation' => '',
            'tutorname' => 'Dr Durand',
            'tutorfunction' => 'Vétérinaire',
            'tutorphone' => '0478000001',
            'tutoremail' => 'tuteur@example.com',
            'nightpresence' => 0,
            'sundaypresence' => 0,
            'holidaypresence' => 0,
            'homebased' => 0,
            'othermodality' => '',
            'gratificationamount' => '0',
            'hasleave' => 0,
            'leavedays' => 0,
            'leavemodalities' => '',
        ];
    }

    /**
     * Construit le formulaire de demande de convention après avoir simulé l'envoi de $submitted.
     *
     * @param array $submitted
     * @param \stdClass $entry
     * @param \stdClass $referent
     * @param \stdClass $template
     * @return convention_request_form
     */
    private function request_form(array $submitted, \stdClass $entry, \stdClass $referent, \stdClass $template) {
        convention_request_form::mock_submit($submitted);
        return new convention_request_form(null, [
            'templates' => [$template->id => $template],
            'referentteachers' => [$referent->id => $referent],
            'periods' => [],
            'themeid' => (int) $entry->themeid,
            'checklistitems' => [],
            'requestquestions' => [],
        ]);
    }

    /**
     * Erreur de validation portée par un champ d'un formulaire déjà soumis.
     *
     * @param \moodleform $form
     * @param string $field
     * @return string|null
     */
    private function field_error(\moodleform $form, string $field): ?string {
        $property = new \ReflectionProperty(\moodleform::class, '_form');
        $property->setAccessible(true);
        return $property->getValue($form)->getElementError($field);
    }

    /**
     * Une situation d'année ou un type de stage hors liste est refusé sur son champ, et la demande
     * n'est pas enregistrée ; la même demande avec des valeurs de la liste est acceptée.
     */
    public function test_request_form_rejects_value_outside_options(): void {
        global $DB;
        [, , $referent, , $entry, $template] = $this->fixture();
        $base = ['id' => 0, 'entryid' => $entry->id] + $this->valid_request($referent, $template);

        // Témoin : sans valeur falsifiée, la demande passe la validation.
        $form = $this->request_form($base, $entry, $referent, $template);
        $this->assertNotNull($form->get_data());

        foreach (['yearsituation' => 'inconnue', 'stagetype' => 'facultatif', 'referentteacherid' => -1] as $field => $bogus) {
            $form = $this->request_form([$field => $bogus] + $base, $entry, $referent, $template);
            $data = $form->get_data();
            $this->assertNull($data, $field);
            $this->assertSame(get_string('required'), $this->field_error($form, $field), $field);

            // Le traitement de convention_request.php n'enregistre que des données validées.
            if ($data) {
                stage_save_convention_detail($entry->id, $data);
            }
            $this->assertFalse($DB->record_exists('stage_convention_detail', ['entryid' => $entry->id]), $field);
        }
    }

    /**
     * Une langue hors liste est signalée sur la langue, sans erreur de cohérence avec le gabarit.
     */
    public function test_request_form_rejects_language_outside_options(): void {
        [, , $referent, , $entry, $template] = $this->fixture();
        $submitted = ['id' => 0, 'entryid' => $entry->id, 'conventionlang' => 'de']
            + $this->valid_request($referent, $template);

        $form = $this->request_form($submitted, $entry, $referent, $template);

        $this->assertNull($form->get_data());
        $this->assertSame(get_string('required'), $this->field_error($form, 'conventionlang'));
        $this->assertNull($this->field_error($form, 'conventiontemplateid'));
    }

    /**
     * Le formulaire d'enregistrement de stage par l'étudiant (student_register.php) refuse lui
     * aussi une valeur hors liste, y compris pour la thématique, sans enregistrer de saisie.
     */
    public function test_register_form_rejects_value_outside_options(): void {
        global $DB;
        [$stage, $student, $referent, $theme, , $template] = $this->fixture();
        $entriesbefore = $DB->count_records('stage_entry', ['stageid' => $stage->id]);
        $base = [
            'id' => 0,
            'themeid' => $theme->id,
            'studyyear' => 3,
            'structure' => 'Clinique du Parc',
            'abroad' => 0,
            'country' => '',
            'declaredduration' => 2,
        ] + $this->valid_request($referent, $template);
        $make = function (array $submitted) use ($stage, $student, $referent, $theme, $template) {
            student_register_form::mock_submit($submitted);
            return new student_register_form(null, [
                'themes' => [$theme->id => $theme],
                'templates' => [$template->id => $template],
                'referentteachers' => [$referent->id => $referent],
                'stageid' => $stage->id,
                'userid' => $student->id,
                'stage' => $stage,
                'editing' => false,
                'excludeentryid' => 0,
                'periods' => [],
                'checklistsbytheme' => [],
                'requestquestionsbytheme' => [],
            ]);
        };

        $this->assertNotNull($make($base)->get_data());

        $bogusvalues = ['yearsituation' => 'inconnue', 'stagetype' => 'facultatif', 'themeid' => -1, 'studyyear' => 6];
        foreach ($bogusvalues as $field => $bogus) {
            $form = $make([$field => $bogus] + $base);
            $this->assertNull($form->get_data(), $field);
            $this->assertSame(get_string('required'), $this->field_error($form, $field), $field);
        }
        $this->assertSame($entriesbefore, $DB->count_records('stage_entry', ['stageid' => $stage->id]));
    }
}
