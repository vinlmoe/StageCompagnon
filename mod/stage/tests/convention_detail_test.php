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

use mod_stage\local\convention_detail;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/tests/fixtures/convention_detail_test_form.php');

/**
 * Longueurs des informations complémentaires de convention : une valeur trop longue est signalée
 * par le formulaire, et tronquée plutôt que de faire échouer l'écriture en base.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_stage\local\convention_detail
 * @covers     ::stage_save_convention_detail
 */
final class convention_detail_test extends \advanced_testcase {
    /**
     * Les longueurs sont celles de la table, y compris les champs courts.
     */
    public function test_max_lengths_follow_the_table(): void {
        $lengths = convention_detail::max_lengths();

        $this->assertSame(20, $lengths['gratificationamount']);
        $this->assertSame(30, $lengths['studentphone']);
        $this->assertSame(30, $lengths['tutorphone']);
        $this->assertSame(255, $lengths['hostaddress']);
        $this->assertArrayNotHasKey('leavemodalities', $lengths);
    }

    /**
     * La troncature compte les caractères et non les octets, et ne touche pas aux valeurs courtes.
     */
    public function test_fit_truncates_by_characters(): void {
        $data = (object) [
            'gratificationamount' => str_repeat('é', 25),
            'hostphone' => '04 78 00 00 00',
            'leavemodalities' => str_repeat('x', 400),
        ];

        convention_detail::fit($data);

        $this->assertSame(str_repeat('é', 20), $data->gratificationamount);
        $this->assertSame('04 78 00 00 00', $data->hostphone);
        $this->assertSame(400, \core_text::strlen($data->leavemodalities));
    }

    /**
     * Une gratification ou un téléphone trop long ne fait plus échouer l'enregistrement.
     */
    public function test_save_convention_detail_accepts_long_values(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $stage = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        $student = $this->getDataGenerator()->create_user();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $entry = $generator->create_entry($stage, $student->id, $generator->create_theme($stage));

        stage_save_convention_detail($entry->id, (object) [
            'gratificationamount' => '600 € par mois environ, versés en fin de stage',
            'studentphone' => '+33 (0)6 12 34 56 78 poste 1234 ou domicile',
        ]);

        $detail = $DB->get_record('stage_convention_detail', ['entryid' => $entry->id], '*', MUST_EXIST);
        $this->assertSame('600 € par mois envir', $detail->gratificationamount);
        $this->assertSame(30, \core_text::strlen($detail->studentphone));
    }

    /**
     * Le formulaire refuse une valeur trop longue et accepte une valeur à la limite.
     */
    public function test_maxlength_rules_reject_too_long_values(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $make = function (array $submitted) {
            fixtures\convention_detail_test_form::mock_submit($submitted);
            return new fixtures\convention_detail_test_form();
        };

        $form = $make(['gratificationamount' => str_repeat('1', 21), 'hostphone' => '04 78 00 00 00']);
        $this->assertNull($form->get_data());

        $form = $make(['gratificationamount' => str_repeat('1', 20), 'hostphone' => '04 78 00 00 00']);
        $this->assertNotNull($form->get_data());
    }
}
