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
require_once($CFG->dirroot . '/mod/stage/classes/form/deve_entry_form.php');

use mod_stage\form\deve_entry_form;

/**
 * Test de régression pour un incident réel : la DEVE ne pouvait pas mettre à jour une saisie
 * existante sans modifier ses dates, register.php rejetant l'édition comme un doublon d'elle-même.
 *
 * La cause : le contrôle de doublon excluait la saisie en cours d'édition à partir du champ caché
 * "entryid" soumis par le formulaire, plutôt que de la valeur connue côté serveur dès l'URL avant
 * même de construire le formulaire (voir register.php, deve_entry_form::validation()).
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_stage\form\deve_entry_form
 */
final class deve_entry_form_test extends \advanced_testcase {

    /**
     * Éditer une saisie sans changer ni sa thématique ni ses dates ne doit pas être rejeté comme
     * un doublon d'elle-même.
     */
    public function test_editing_entry_without_changes_is_not_flagged_as_duplicate(): void {
        $this->resetAfterTest();
        /** @var \mod_stage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $course = $this->getDataGenerator()->create_course();
        $stage = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        $theme = $generator->create_theme($stage);
        $student = $this->getDataGenerator()->create_user();
        $entry = $generator->create_entry($stage, $student->id, $theme, [
            'datestart' => make_timestamp(2026, 3, 1),
            'dateend' => make_timestamp(2026, 3, 15),
        ]);

        $mform = new deve_entry_form(null, [
            'themes' => [$theme],
            'students' => [$student],
            'lockstudent' => true,
            'studentname' => fullname($student),
            'stageid' => $stage->id,
            'periods' => [],
            'entryid' => $entry->id,
        ]);

        // Simule la resoumission du formulaire tel qu'affiché pour éditer $entry, sans rien
        // modifier : mêmes thématique, dates et durée déclarée.
        $submitted = [
            'id' => 0,
            'entryid' => $entry->id,
            'userid' => $student->id,
            'themeid' => $theme->id,
            'studyyear' => 0,
            'structure' => $entry->structure,
            'stagetype' => 'obligatoire',
            'abroad' => 0,
            'country' => '',
            'declaredduration' => $entry->declaredduration,
            'exemptfromconvention' => 0,
            'perioddatestart' => [$entry->datestart],
            'perioddateend' => [$entry->dateend],
        ];

        $errors = $mform->validation($submitted, []);

        $this->assertArrayNotHasKey('themeid', $errors,
            'La mise à jour d\'une saisie sans changement ne doit pas être signalée comme un doublon d\'elle-même.');
    }

    /**
     * À l'inverse, une réelle seconde saisie sur la même thématique et les mêmes dates pour le
     * même étudiant doit toujours être refusée : le contrat de stage_entry_is_duplicate() n'est
     * pas affaibli par le correctif ci-dessus.
     */
    public function test_genuine_duplicate_is_still_rejected(): void {
        $this->resetAfterTest();
        /** @var \mod_stage_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $course = $this->getDataGenerator()->create_course();
        $stage = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        $theme = $generator->create_theme($stage);
        $student = $this->getDataGenerator()->create_user();
        $existing = $generator->create_entry($stage, $student->id, $theme, [
            'datestart' => make_timestamp(2026, 3, 1),
            'dateend' => make_timestamp(2026, 3, 15),
        ]);

        // Formulaire de création (pas d'édition en cours : entryid absent du customdata).
        $mform = new deve_entry_form(null, [
            'themes' => [$theme],
            'students' => [$student],
            'lockstudent' => false,
            'stageid' => $stage->id,
            'periods' => [],
        ]);

        $submitted = [
            'id' => 0,
            'entryid' => 0,
            'userid' => $student->id,
            'themeid' => $theme->id,
            'studyyear' => 0,
            'structure' => 'Autre structure',
            'stagetype' => 'obligatoire',
            'abroad' => 0,
            'country' => '',
            'declaredduration' => 10,
            'exemptfromconvention' => 0,
            'perioddatestart' => [$existing->datestart],
            'perioddateend' => [$existing->dateend],
        ];

        $errors = $mform->validation($submitted, []);

        $this->assertArrayHasKey('themeid', $errors);
    }
}
