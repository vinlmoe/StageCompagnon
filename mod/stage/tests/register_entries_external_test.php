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
 * Contrôle métier et absence d'import partiel via l'API.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_stage\external\register_entries
 * @runTestsInSeparateProcesses
 */
final class register_entries_external_test extends \advanced_testcase {
    /**
     * Prépare les données d'une saisie importable.
     *
     * @return array Module et paramètres de saisie.
     */
    private function prepare(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $stage = $this->getDataGenerator()->create_module('stage', ['course' => $course]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $theme = $generator->create_theme($stage);
        return [$stage->cmid, [
            'userid' => $student->id, 'themeid' => $theme->id, 'declaredduration' => 10,
            'datestart' => make_timestamp(2026, 3, 1), 'dateend' => make_timestamp(2026, 3, 15),
        ]];
    }

    /**
     * Un lot invalide ne laisse aucune saisie, même si la première ligne est valide.
     */
    public function test_invalid_batch_creates_no_entries(): void {
        global $DB;
        [$cmid, $entry] = $this->prepare();
        $invalidvalues = [
            ['declaredduration' => -1], ['studyyear' => 7], ['studyyear' => -1],
            ['abroad' => 2], ['datestart' => -1], ['datestart' => 0],
            ['dateend' => 0], ['dateend' => $entry['datestart'] - DAYSECS],
        ];
        foreach ($invalidvalues as $values) {
            try {
                \mod_stage\external\register_entries::execute($cmid, [$entry, array_merge($entry, $values)]);
                $this->fail('An invalid batch must be rejected.');
            } catch (\invalid_parameter_exception $e) {
                $this->assertEquals(0, $DB->count_records('stage_entry'));
                $this->assertEquals(0, $DB->count_records('stage_entry_period'));
            }
        }
    }

    /**
     * Les dates entièrement absentes restent acceptées, ainsi qu'une plage valide.
     */
    public function test_valid_entries_and_duplicates(): void {
        global $DB;
        [$cmid, $entry] = $this->prepare();
        $undated = array_merge($entry, ['datestart' => 0, 'dateend' => 0]);
        $result = \mod_stage\external\register_entries::execute($cmid, [$entry, $undated, $entry]);
        $this->assertEquals(2, $result['createdcount']);
        $this->assertEquals([$entry['userid']], $result['duplicateuserids']);
        $this->assertEquals(2, $DB->count_records('stage_entry'));
        $this->assertEquals(1, $DB->count_records('stage_entry_period'));
    }
}
