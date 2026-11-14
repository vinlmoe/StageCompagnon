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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/stage/locallib.php');

/**
 * Export des réponses au questionnaire de demande (pré-stage) d'une thématique : une ligne par
 * stage ayant répondu, une colonne par question, réponses en clair ou en valeur numérique.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_get_request_answers_export
 * @covers     ::stage_theme_has_request_answers
 */
final class request_answers_export_test extends \advanced_testcase {
    /**
     * Crée une question du questionnaire de demande.
     *
     * @param \stdClass $stage
     * @param string $qtype
     * @param string $name
     * @param int $sortorder
     * @param string|null $options
     * @return \stdClass
     */
    private function question(\stdClass $stage, string $qtype, string $name, int $sortorder, ?string $options = null): \stdClass {
        global $DB;
        $question = (object) [
            'stageid' => $stage->id, 'themeid' => 0, 'evaltype' => 'request', 'qtype' => $qtype,
            'name' => $name, 'options' => $options, 'required' => 0, 'sortorder' => $sortorder,
            'timecreated' => time(), 'timemodified' => time(),
        ];
        $question->id = $DB->insert_record('stage_question', $question);
        return $question;
    }

    /**
     * Une ligne par stage ayant répondu, une colonne par question, dans l'ordre de la liste ; une
     * question retirée de la liste depuis reste exportée.
     */
    public function test_export_table(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $stage = $this->getDataGenerator()->create_module('stage', ['course' => $this->getDataGenerator()->create_course()]);
        $theme = $gen->create_theme($stage, ['name' => 'Canine']);
        $other = $gen->create_theme($stage, ['name' => 'Équine']);

        $this->assertFalse(stage_theme_has_request_answers($theme));

        $motivation = $this->question($stage, 'shorttext', 'Motivation', 2);
        $rating = $this->question($stage, 'rating', 'Intérêt', 1);
        $domains = $this->question($stage, 'checkbox', 'Domaines', 3, "Chirurgie\nMédecine");
        $yesno = $this->question($stage, 'yesno', 'Permis', 4);
        $removed = $this->question($stage, 'number', 'Ancienne question', 5);
        $gen->create_evallist($stage, 'request', [$motivation->id, $rating->id, $domains->id, $yesno->id], [$theme]);
        $this->assertTrue(stage_theme_has_request_answers($theme));
        $this->assertFalse(stage_theme_has_request_answers($other));

        $alice = $this->getDataGenerator()->create_user(['firstname' => 'Alice', 'lastname' => 'A', 'email' => 'a@example.com']);
        $bob = $this->getDataGenerator()->create_user(['firstname' => 'Bob', 'lastname' => 'B']);
        $carol = $this->getDataGenerator()->create_user(['firstname' => 'Carol', 'lastname' => 'C']);
        $entrya = $gen->create_entry($stage, $alice->id, $theme);
        $entryb = $gen->create_entry($stage, $bob->id, $theme);
        $gen->create_entry($stage, $carol->id, $theme);

        stage_save_answers($entrya->id, [$motivation, $rating, $domains, $yesno, $removed], [
            $motivation->id => 'Passion', $rating->id => '4', $domains->id => ['Chirurgie', 'Médecine'],
            $yesno->id => 'yes', $removed->id => '3.5',
        ]);
        stage_save_answers($entryb->id, [$motivation], [$motivation->id => 'Curiosité']);

        $entries = stage_get_filtered_entries($stage->id, ['themeid' => $theme->id], 'student', 'ASC');
        $export = stage_get_request_answers_export($theme, $entries);

        $this->assertSame(
            ['q' . $rating->id, 'q' . $motivation->id, 'q' . $domains->id, 'q' . $yesno->id, 'q' . $removed->id],
            array_slice(array_keys($export['columns']), 7)
        );
        $this->assertSame('Intérêt', $export['columns']['q' . $rating->id]);

        // Carol n'a pas répondu : pas de ligne.
        $this->assertCount(2, $export['rows']);
        [$rowa, $rowb] = $export['rows'];
        $this->assertSame((int) $entrya->id, $rowa['entryid']);
        $this->assertSame(fullname($alice), $rowa['student']);
        $this->assertSame('a@example.com', $rowa['email']);
        $this->assertSame(4.0, $rowa['q' . $rating->id]);
        $this->assertSame('Passion', $rowa['q' . $motivation->id]);
        $this->assertSame('Chirurgie; Médecine', $rowa['q' . $domains->id]);
        $this->assertSame(get_string('yes'), $rowa['q' . $yesno->id]);
        $this->assertSame(3.5, $rowa['q' . $removed->id]);

        $this->assertSame(userdate($entrya->datestart, '%Y-%m-%d', 99, false), $rowa['datestart']);

        $this->assertSame((int) $entryb->id, $rowb['entryid']);
        $this->assertSame('Curiosité', $rowb['q' . $motivation->id]);
        $this->assertSame('', $rowb['q' . $rating->id]);
        $this->assertSame(array_keys($export['columns']), array_keys($rowb));

        // Sans le courriel (responsable de thématique) : ni colonne ni valeur.
        $export = stage_get_request_answers_export($theme, $entries, false);
        $this->assertArrayNotHasKey('email', $export['columns']);
        $this->assertArrayNotHasKey('email', $export['rows'][0]);
        $this->assertSame(array_keys($export['columns']), array_keys($export['rows'][0]));
    }
}
