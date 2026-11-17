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

use mod_stage\form\student_register_form;
use mod_stage\local\placement_offer_importer;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/student_register_form.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/convention_request_form.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/theme_edit_form.php');
require_once($CFG->dirroot . '/mod/stage/classes/form/placement_offer_form.php');

/**
 * Tests des thématiques à organismes proposés : vœux des étudiants, appariement, affectation
 * décidée par la DEVE, demande de convention préremplie et règle du SIRET hors liste, import Excel
 * et reprise de stages précédents.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_placement_state
 * @covers     ::stage_placement_save_wishes
 * @covers     ::stage_placement_run_matching
 * @covers     ::stage_placement_set_assignment
 * @covers     ::stage_placement_request_errors
 * @covers     ::stage_placement_prefill
 * @covers     ::stage_placement_get_past_organisations
 * @covers     ::stage_placement_add_offers
 * @covers     ::stage_placement_notify_results
 * @covers     \mod_stage\local\placement_offer_importer
 * @covers     \mod_stage\task\run_placement_matching
 * @covers     \mod_stage\form\student_register_form
 * @covers     \mod_stage\form\convention_request_form
 * @covers     \mod_stage\form\theme_edit_form
 * @covers     \mod_stage\form\placement_offer_form
 * @covers     ::stage_placement_clean_offer
 * @covers     ::stage_placement_offer_key
 * @covers     ::stage_placement_remaining_places
 * @covers     ::stage_placement_get_export
 * @covers     ::stage_placement_render_student_section
 * @covers     ::stage_placement_get_themes_to_match
 * @covers     ::stage_placement_delete_users_data
 */
final class placement_test extends \advanced_testcase {
    /** @var string SIRET valide (clé de Luhn juste). */
    const SIRET = '73282932000074';

    /**
     * Activité avec une thématique à organismes proposés, trois organismes et des étudiants.
     *
     * @param int $students Nombre d'étudiants inscrits.
     * @param array $themerecord Réglages de la thématique à surcharger.
     * @return array [$stage, $theme, $offers (A, B, C), $students, $course]
     */
    private function fixture(int $students = 3, array $themerecord = []): array {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $stage = $gen->create_module('stage', ['course' => $course->id, 'currentstudyyear' => 3]);
        /** @var \mod_stage_generator $stagegen */
        $stagegen = $gen->get_plugin_generator('mod_stage');
        $theme = $stagegen->create_theme($stage, $themerecord + [
            'name' => 'Clinique rurale',
            'placementenabled' => 1,
            'placementchoices' => 2,
            'placementdeadline' => time() + WEEKSECS,
        ]);
        $offers = [
            $stagegen->create_placement_offer($theme, ['name' => 'Clinique A', 'places' => 1]),
            $stagegen->create_placement_offer($theme, ['name' => 'Clinique B', 'places' => 1]),
            $stagegen->create_placement_offer($theme, ['name' => 'Clinique C', 'places' => 2]),
        ];
        $users = [];
        for ($i = 0; $i < $students; $i++) {
            $users[] = $gen->create_and_enrol($course, 'student');
        }
        return [$stage, $theme, $offers, $users, $course];
    }

    /**
     * Relit une thématique.
     *
     * @param \stdClass $theme
     * @return \stdClass
     */
    private function reload(\stdClass $theme): \stdClass {
        global $DB;
        return $DB->get_record('stage_theme', ['id' => $theme->id], '*', MUST_EXIST);
    }

    /**
     * États successifs d'une campagne : vœux ouverts, clos à la date fixée, puis appariée.
     */
    public function test_state(): void {
        $theme = (object) ['placementenabled' => 0, 'placementdeadline' => 0, 'placementmatchedtime' => 0];
        $this->assertSame(STAGE_PLACEMENT_OFF, stage_placement_state($theme));

        $theme->placementenabled = 1;
        $this->assertSame(STAGE_PLACEMENT_OPEN, stage_placement_state($theme), 'sans date de clôture');

        $theme->placementdeadline = 1000;
        $this->assertSame(STAGE_PLACEMENT_OPEN, stage_placement_state($theme, 999));
        $this->assertSame(STAGE_PLACEMENT_CLOSED, stage_placement_state($theme, 1000));

        $theme->placementmatchedtime = 1200;
        $this->assertSame(STAGE_PLACEMENT_MATCHED, stage_placement_state($theme, 999));
    }

    /**
     * Les vœux doivent être distincts, proposés pour la thématique et au nombre demandé ; ils se
     * remplacent, se retirent, et sont refusés une fois clos.
     */
    public function test_save_wishes(): void {
        global $DB;
        [, $theme, [$a, $b, $c], [$student]] = $this->fixture(1);
        $other = $this->getDataGenerator()->get_plugin_generator('mod_stage')->create_placement_offer(
            $this->getDataGenerator()->get_plugin_generator('mod_stage')->create_theme($theme->stageid)
        );

        $this->assertSame('placementwishesduplicate', stage_placement_save_wishes($theme, $student->id, [$a->id, $a->id]));
        $this->assertSame('placementwishesunknown', stage_placement_save_wishes($theme, $student->id, [$a->id, $other->id]));
        $this->assertSame('placementwishesincomplete', stage_placement_save_wishes($theme, $student->id, [$a->id]));
        $this->assertSame([], stage_placement_get_wishes($theme->id, $student->id));

        $this->assertNull(stage_placement_save_wishes($theme, $student->id, [$b->id, $a->id]));
        $this->assertSame([(int) $b->id, (int) $a->id], stage_placement_get_wishes($theme->id, $student->id));

        // Un nouvel enregistrement remplace le précédent.
        $this->assertNull(stage_placement_save_wishes($theme, $student->id, [$c->id, $b->id]));
        $this->assertSame([(int) $c->id, (int) $b->id], stage_placement_get_wishes($theme->id, $student->id));

        // Retrait.
        $this->assertNull(stage_placement_save_wishes($theme, $student->id, []));
        $this->assertSame(0, $DB->count_records('stage_placement_wish', ['themeid' => $theme->id]));

        // Clôture passée : plus aucun changement.
        $DB->set_field('stage_theme', 'placementdeadline', time() - 10, ['id' => $theme->id]);
        $this->assertSame('placementwishesclosed', stage_placement_save_wishes($theme, $student->id, [$a->id, $b->id]));
    }

    /**
     * Avec moins d'organismes que de vœux demandés, l'étudiant les classe tous.
     */
    public function test_required_wishes_capped_by_offers(): void {
        $theme = (object) ['placementchoices' => 5];
        $this->assertSame(3, stage_placement_required_wishes($theme, 3));
        $this->assertSame(5, stage_placement_required_wishes($theme, 8));
        $this->assertSame(0, stage_placement_required_wishes($theme, 0));
    }

    /**
     * Dans l'ordre de passage, chacun reçoit le mieux classé de ses vœux où il reste une place ;
     * les places ne sont jamais dépassées et un étudiant dont tous les vœux sont complets reste
     * sans affectation.
     */
    public function test_matching_follows_order_and_capacity(): void {
        global $DB;
        [, $theme, [$a, $b], [$s1, $s2, $s3]] = $this->fixture(3);
        foreach ([$s1, $s2, $s3] as $student) {
            $this->assertNull(stage_placement_save_wishes($theme, $student->id, [$a->id, $b->id]));
        }

        $result = stage_placement_run_matching($theme, [$s2->id, $s3->id, $s1->id]);

        $this->assertEquals([$s2->id => $a->id, $s3->id => $b->id], $result->assigned);
        $this->assertEquals([$s1->id], $result->unassigned);
        $this->assertEquals(1, stage_placement_get_assignment($theme->id, $s2->id)->wishrank);
        $this->assertEquals(2, stage_placement_get_assignment($theme->id, $s3->id)->wishrank);
        $this->assertFalse(stage_placement_get_assignment($theme->id, $s1->id));
        $this->assertSame(STAGE_PLACEMENT_MATCHED, stage_placement_state($this->reload($theme)));

        // Les vœux sont clos une fois l'appariement fait.
        $this->assertSame('placementwishesclosed', stage_placement_save_wishes($theme, $s1->id, [$b->id, $a->id]));
        $this->assertSame(2, $DB->count_records('stage_placement_assign', ['themeid' => $theme->id]));
    }

    /**
     * Rejouer l'appariement ne défait rien : seuls les étudiants sans affectation y passent, sur
     * les places restantes (tour complémentaire après ajout de places).
     */
    public function test_matching_again_is_a_complementary_round(): void {
        global $DB;
        [, $theme, [$a, $b], [$s1, $s2, $s3]] = $this->fixture(3);
        foreach ([$s1, $s2, $s3] as $student) {
            stage_placement_save_wishes($theme, $student->id, [$a->id, $b->id]);
        }
        stage_placement_run_matching($theme, [$s1->id, $s2->id, $s3->id]);
        $matchedtime = $this->reload($theme)->placementmatchedtime;

        $again = stage_placement_run_matching($this->reload($theme));
        $this->assertSame([], $again->assigned);
        $this->assertEquals([$s3->id], $again->unassigned);

        $DB->set_field('stage_placement_offer', 'places', 2, ['id' => $b->id]);
        $again = stage_placement_run_matching($this->reload($theme));
        $this->assertEquals([$s3->id => $b->id], $again->assigned);
        $this->assertEquals($a->id, stage_placement_get_assignment($theme->id, $s1->id)->offerid);
        $this->assertEquals($matchedtime, $this->reload($theme)->placementmatchedtime);
    }

    /**
     * Le tirage au sort ne dépasse jamais les places et n'affecte qu'à un vœu de l'étudiant ; un
     * étudiant désinscrit du cours n'est pas apparié.
     */
    public function test_random_matching_respects_wishes_and_enrolment(): void {
        global $DB;
        [, $theme, [$a, $b, $c], $students, $course] = $this->fixture(6);
        $wishes = [];
        foreach ($students as $index => $student) {
            $wishes[$student->id] = $index % 2 ? [(int) $a->id, (int) $c->id] : [(int) $b->id, (int) $c->id];
            stage_placement_save_wishes($theme, $student->id, $wishes[$student->id]);
        }
        $gone = array_pop($students);
        $enrol = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        $enrol->unenrol_user($instance, $gone->id);

        $result = stage_placement_run_matching($theme);

        $this->assertArrayNotHasKey($gone->id, $result->assigned);
        $this->assertNotContains($gone->id, $result->unassigned);
        $this->assertCount(5, $result->assigned + array_flip($result->unassigned));
        // Quatre places pour cinq étudiants : toutes sont pourvues.
        $this->assertCount(4, $result->assigned);
        $peroffer = array_count_values($result->assigned);
        foreach ([$a, $b, $c] as $offer) {
            $this->assertLessThanOrEqual($offer->places, $peroffer[$offer->id] ?? 0);
        }
        foreach ($result->assigned as $userid => $offerid) {
            $this->assertContains((int) $offerid, $wishes[$userid]);
        }
    }

    /**
     * La DEVE affecte, déplace et retire à la main, dans la limite des places.
     */
    public function test_manual_assignment(): void {
        [, $theme, [$a, $b], [$s1, $s2]] = $this->fixture(2);
        $deve = $this->getDataGenerator()->create_user();
        stage_placement_save_wishes($theme, $s1->id, [$b->id, $a->id]);

        $this->assertNull(stage_placement_set_assignment($theme, $s1->id, $a->id, $deve->id));
        $assignment = stage_placement_get_assignment($theme->id, $s1->id);
        $this->assertEquals(2, $assignment->wishrank, 'second vœu de l\'étudiant');
        $this->assertEquals($deve->id, $assignment->assignedby);

        // Organisme complet.
        $this->assertSame('placementofferfull', stage_placement_set_assignment($theme, $s2->id, $a->id, $deve->id));
        $this->assertNull(stage_placement_set_assignment($theme, $s2->id, $b->id, $deve->id));
        $this->assertEquals(0, stage_placement_get_assignment($theme->id, $s2->id)->wishrank, 'hors vœux');

        // Un étudiant affecté ne modifie plus ses vœux, et l'appariement le laisse en place.
        $this->assertSame('placementalreadyassigned', stage_placement_save_wishes($theme, $s1->id, [$a->id, $b->id]));
        $this->assertSame([], stage_placement_run_matching($theme)->assigned);

        // Retrait : la place est libérée.
        $this->assertNull(stage_placement_set_assignment($theme, $s1->id, 0, $deve->id));
        $this->assertFalse(stage_placement_get_assignment($theme->id, $s1->id));
        $this->assertNull(stage_placement_set_assignment($theme, $s2->id, $a->id, $deve->id));
    }

    /**
     * Un organisme où des étudiants sont affectés ne se supprime pas ; sinon ses vœux partent avec lui.
     */
    public function test_delete_offer(): void {
        global $DB;
        [, $theme, [$a, $b], [$s1]] = $this->fixture(1);
        stage_placement_save_wishes($theme, $s1->id, [$a->id, $b->id]);
        stage_placement_set_assignment($theme, $s1->id, $a->id, 2);

        $this->assertFalse(stage_placement_delete_offer($theme->id, $a->id));
        $this->assertTrue($DB->record_exists('stage_placement_offer', ['id' => $a->id]));

        $this->assertTrue(stage_placement_delete_offer($theme->id, $b->id));
        $this->assertFalse($DB->record_exists('stage_placement_offer', ['id' => $b->id]));
        $this->assertSame([(int) $a->id], stage_placement_get_wishes($theme->id, $s1->id));
    }

    /**
     * Règle d'une demande de convention sur une thématique à organismes proposés.
     */
    public function test_request_errors(): void {
        $theme = (object) ['id' => 5, 'placementenabled' => 1, 'placementofflist' => 0];
        $assignment = (object) ['themeid' => 5];
        $france = ['abroad' => 0, 'siret' => ''];

        // Thématique ordinaire : aucune contrainte.
        $this->assertSame([], stage_placement_request_errors((object) ['id' => 5, 'placementenabled' => 0], $france, null));
        // Demande déposée à partir de l'affectation : aucune contrainte, SIRET compris.
        $this->assertSame([], stage_placement_request_errors($theme, $france, $assignment));
        // L'affectation d'une autre thématique ne vaut pas pour celle-ci.
        $this->assertArrayHasKey('themeid', stage_placement_request_errors($theme, $france, (object) ['themeid' => 6]));
        // Hors liste non autorisé : refusé, sauf pour un stage déjà enregistré (par la DEVE).
        $this->assertArrayHasKey('themeid', stage_placement_request_errors($theme, ['abroad' => 0, 'siret' => self::SIRET], null));
        $this->assertArrayHasKey('siret', stage_placement_request_errors($theme, $france, null, true));
        $this->assertSame([], stage_placement_request_errors($theme, ['abroad' => 0, 'siret' => self::SIRET], null, true));

        // Hors liste autorisé : SIRET obligatoire en France, pas à l'étranger.
        $theme->placementofflist = 1;
        $this->assertArrayHasKey('siret', stage_placement_request_errors($theme, $france, null));
        $this->assertArrayHasKey('siret', stage_placement_request_errors($theme, ['abroad' => 0, 'siret' => ' '], null));
        $this->assertSame([], stage_placement_request_errors($theme, ['abroad' => 0, 'siret' => self::SIRET], null));
        $this->assertSame([], stage_placement_request_errors($theme, ['abroad' => 1, 'siret' => ''], null));
    }

    /**
     * Valeurs d'une demande complète, hors champs de l'organisme d'accueil et de la thématique.
     *
     * @param \stdClass $referent
     * @param \stdClass $template
     * @param \stdClass $theme
     * @return array
     */
    private function request(\stdClass $referent, \stdClass $template, \stdClass $theme): array {
        $date = function (int $days): array {
            $time = time() + $days * DAYSECS;
            return ['day' => (int) userdate($time, '%d'), 'month' => (int) userdate($time, '%m'),
                'year' => (int) userdate($time, '%Y')];
        };
        return [
            'id' => 0, 'themeid' => $theme->id, 'studyyear' => 3, 'structure' => 'Clinique du Parc', 'abroad' => 0,
            'country' => '', 'declaredduration' => 5, 'conventionlang' => 'fr', 'conventiontemplateid' => $template->id,
            'paperrequestedbystudent' => 0, 'referentteacherid' => $referent->id, 'yearsituation' => 'normal',
            'stagetype' => 'obligatoire', 'periodrepeats' => 1, 'perioddatestart' => [0 => $date(30)],
            'perioddateend' => [0 => $date(40)], 'studentbirthdate' => ['day' => 1, 'month' => 1, 'year' => 2000],
            'studentaddress' => '1 rue de la Gare', 'studentphone' => '0600000000', 'hostaddress' => '2 avenue des Champs',
            'hostrepresentative' => 'Dr Martin', 'hostrepresentativetitle' => 'Gérant', 'hostservice' => 'Rurale',
            'hostphone' => '0478000000', 'hostemail' => 'clinique@example.com', 'siret' => '', 'hostlocation' => '',
            'tutorname' => 'Dr Durand', 'tutorfunction' => 'Vétérinaire', 'tutorphone' => '0478000001',
            'tutoremail' => 'tuteur@example.com', 'nightpresence' => 0, 'sundaypresence' => 0, 'holidaypresence' => 0,
            'homebased' => 0, 'othermodality' => '', 'gratificationamount' => '0', 'hasleave' => 0, 'leavedays' => 0,
            'leavemodalities' => '',
        ];
    }

    /**
     * Le formulaire de l'étudiant applique la règle : demande refusée hors liste si la thématique
     * ne l'autorise pas, SIRET exigé en France si elle l'autorise, rien d'exigé à partir de
     * l'affectation de l'étudiant.
     */
    public function test_register_form_applies_placement_rule(): void {
        global $DB;
        [$stage, $theme, [$a], [$student]] = $this->fixture(1);
        $gen = $this->getDataGenerator();
        $stagegen = $gen->get_plugin_generator('mod_stage');
        $referent = $gen->create_user();
        $template = $stagegen->create_convention_template($stage, ['lang' => 'fr']);
        $this->setUser($student);
        $base = $this->request($referent, $template, $theme);

        $make = function (array $submitted, ?\stdClass $assignment = null) use ($stage, $student, $referent, $theme, $template) {
            student_register_form::mock_submit($submitted);
            return new student_register_form(null, [
                'themes' => [$theme->id => $this->reload($theme)],
                'templates' => [$template->id => $template],
                'referentteachers' => [$referent->id => $referent],
                'stageid' => $stage->id, 'userid' => $student->id, 'stage' => $stage, 'editing' => false,
                'excludeentryid' => 0, 'periods' => [], 'checklistsbytheme' => [], 'requestquestionsbytheme' => [],
                'assignment' => $assignment, 'existingthemeid' => 0,
            ]);
        };
        $error = function (\moodleform $form, string $field): ?string {
            $property = new \ReflectionProperty(\moodleform::class, '_form');
            $property->setAccessible(true);
            return $property->getValue($form)->getElementError($field);
        };

        // Hors liste non autorisé.
        $form = $make(['siret' => self::SIRET] + $base);
        $this->assertNull($form->get_data());
        $this->assertSame(get_string('placementrequired', 'mod_stage'), $error($form, 'themeid'));

        // À partir de l'affectation : accepté sans SIRET.
        stage_placement_set_assignment($theme, $student->id, $a->id, 2);
        $this->assertNotNull($make($base, stage_placement_get_assignment($theme->id, $student->id))->get_data());

        // Hors liste autorisé : SIRET obligatoire en France, facultatif à l'étranger.
        $DB->set_field('stage_theme', 'placementofflist', 1, ['id' => $theme->id]);
        $form = $make($base);
        $this->assertNull($form->get_data());
        $this->assertSame(get_string('placementsiretrequired', 'mod_stage'), $error($form, 'siret'));
        $this->assertNotNull($make(['siret' => self::SIRET] + $base)->get_data());
        $this->assertNotNull($make(['abroad' => 1, 'country' => 'Belgique'] + $base)->get_data());
    }

    /**
     * Les coordonnées de l'organisme attribué préremplissent la demande ; un pays en fait un stage
     * à l'étranger, et un champ vide n'est pas prérempli.
     */
    public function test_prefill(): void {
        [, $theme] = $this->fixture(0);
        $stagegen = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $offer = $stagegen->create_placement_offer($theme, [
            'name' => 'Clinique du Lac', 'address' => '3 quai du Lac, Annecy', 'siret' => '732 829 320 00074',
            'representative' => 'Dr Lac', 'tutoremail' => 'tuteur@example.com',
        ]);

        $data = stage_placement_prefill($offer);
        $this->assertSame('Clinique du Lac', $data['structure']);
        $this->assertSame('3 quai du Lac, Annecy', $data['hostaddress']);
        $this->assertSame(self::SIRET, $data['siret'], 'SIRET normalisé à l\'enregistrement');
        $this->assertSame('Dr Lac', $data['hostrepresentative']);
        $this->assertSame('tuteur@example.com', $data['tutoremail']);
        $this->assertSame(0, $data['abroad']);
        $this->assertArrayNotHasKey('hostphone', $data);

        $abroad = $stagegen->create_placement_offer($theme, ['name' => 'Clinique de Liège', 'country' => 'Belgique']);
        $data = stage_placement_prefill($abroad);
        $this->assertSame(1, $data['abroad']);
        $this->assertSame('Belgique', $data['country']);
    }

    /**
     * Une affectation sert une fois : elle redevient utilisable si le stage qui en découle est
     * annulé ou supprimé.
     */
    public function test_assignment_usable_until_entry_registered(): void {
        global $DB;
        [$stage, $theme, [$a], [$student]] = $this->fixture(1);
        $stagegen = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        stage_placement_set_assignment($theme, $student->id, $a->id, 2);
        $assignment = stage_placement_get_assignment($theme->id, $student->id);
        $this->assertTrue(stage_placement_assignment_usable($assignment));

        $entry = $stagegen->create_entry($stage, $student->id, $theme);
        stage_placement_link_entry($assignment->id, $entry->id);
        $this->assertFalse(stage_placement_assignment_usable_now($assignment->id));
        $this->assertEquals($assignment->id, stage_placement_get_entry_assignment($entry->id)->id);

        $DB->set_field('stage_entry', 'status', STAGE_STATUS_ANNULE, ['id' => $entry->id]);
        $this->assertTrue(stage_placement_assignment_usable_now($assignment->id));

        $DB->set_field('stage_entry', 'status', STAGE_STATUS_ENREGISTRE, ['id' => $entry->id]);
        stage_delete_entries([$entry->id]);
        $assignment = stage_placement_get_assignment($theme->id, $student->id);
        $this->assertNull($assignment->entryid);
        $this->assertTrue(stage_placement_assignment_usable($assignment));
    }

    /**
     * Reprise de stages précédents : un organisme par SIRET (ou par nom), avec les coordonnées du
     * stage le plus récent, sans les stages annulés ; un organisme déjà proposé n'est pas rajouté.
     */
    public function test_past_organisations(): void {
        global $DB;
        [$stage, $theme, , [$s1, $s2]] = $this->fixture(2);
        $stagegen = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $other = $stagegen->create_theme($stage, ['name' => 'Équine']);
        $old = $stagegen->create_entry($stage, $s1->id, $other, [
            'structure' => 'Clinique du Parc', 'datestart' => make_timestamp(2025, 3, 1), 'dateend' => make_timestamp(2025, 3, 10),
        ]);
        $recent = $stagegen->create_entry($stage, $s2->id, $other, [
            'structure' => 'clinique du PARC', 'datestart' => make_timestamp(2026, 3, 1), 'dateend' => make_timestamp(2026, 3, 10),
        ]);
        stage_save_convention_detail($old->id, (object) ['hostaddress' => 'Ancienne adresse', 'referentteacherid' => null]);
        stage_save_convention_detail($recent->id, (object) ['hostaddress' => 'Nouvelle adresse', 'tutorname' => 'Dr Neuf']);
        $stagegen->create_entry($stage, $s1->id, $theme, [
            'structure' => 'Ferme de Liège', 'abroad' => 1, 'country' => 'Belgique',
            'datestart' => make_timestamp(2026, 5, 1), 'dateend' => make_timestamp(2026, 5, 10),
        ]);
        $cancelled = $stagegen->create_entry($stage, $s2->id, $theme, [
            'structure' => 'Stage annulé', 'datestart' => make_timestamp(2026, 6, 1), 'dateend' => make_timestamp(2026, 6, 10),
        ]);
        $DB->set_field('stage_entry', 'status', STAGE_STATUS_ANNULE, ['id' => $cancelled->id]);

        $organisations = array_values(stage_placement_get_past_organisations($stage->id));
        $this->assertSame(['clinique du PARC', 'Ferme de Liège'], array_column($organisations, 'name'));
        $this->assertSame(2, $organisations[0]->count);
        $this->assertSame('Nouvelle adresse', $organisations[0]->address);
        $this->assertSame('Dr Neuf', $organisations[0]->tutorname);
        $this->assertSame('Belgique', $organisations[1]->country);

        // Filtre par thématique source.
        $this->assertCount(1, stage_placement_get_past_organisations($stage->id, $other->id));

        $before = count(stage_placement_get_offers($theme->id));
        $this->assertSame(2, stage_placement_add_offers($theme->id, $organisations));
        $this->assertSame(0, stage_placement_add_offers($theme->id, $organisations), 'déjà proposés');
        $this->assertCount($before + 2, stage_placement_get_offers($theme->id));
    }

    /**
     * Import Excel : colonnes reconnues par leur intitulé dans n'importe quel ordre, organisme
     * existant mis à jour sans effacer ce que le classeur ne renseigne pas, lignes en erreur signalées.
     */
    public function test_import(): void {
        global $DB;
        [, $theme, [$a]] = $this->fixture(0);
        $DB->set_field('stage_placement_offer', 'address', 'Adresse connue', ['id' => $a->id]);

        $records = placement_offer_importer::parse_rows([
            0 => ['', '', '', '', ''],
            1 => ['Nombre de places', 'Organisme', 'N° SIRET', 'Pays', 'Courriel du tuteur'],
            2 => ['3', 'clinique a', '', '', ''],
            3 => ['', 'Clinique du Lac', '732 829 320 00074', '', 'tuteur@example.com'],
            4 => ['deux', 'Clinique illisible', '', '', ''],
            5 => ['1', 'Clinique sans clé', '12345678901234', '', ''],
            6 => ['1', '', '', '', ''],
            7 => ['2', 'Clinique de Liège', '', 'Belgique', 'pas-une-adresse'],
        ]);
        $this->assertCount(6, $records);
        $this->assertSame(3, $records[0]->line);

        $results = placement_offer_importer::import($theme, $records);
        $this->assertSame(1, $results->added);
        $this->assertSame(1, $results->updated);
        $this->assertCount(4, $results->errors);

        $updated = $DB->get_record('stage_placement_offer', ['id' => $a->id]);
        $this->assertEquals(3, $updated->places);
        $this->assertSame('clinique a', $updated->name, 'les cellules renseignées remplacent les valeurs connues');
        $this->assertSame('Adresse connue', $updated->address);
        $added = $DB->get_record('stage_placement_offer', ['themeid' => $theme->id, 'siret' => self::SIRET]);
        $this->assertSame('Clinique du Lac', $added->name);
        $this->assertEquals(1, $added->places);

        // Réimport du même classeur : rien n'est ajouté une seconde fois.
        $results = placement_offer_importer::import($theme, $records);
        $this->assertSame(0, $results->added);
        $this->assertSame(2, $results->updated);
    }

    /**
     * Sans colonne du nom de l'organisme, le classeur est refusé ; les intitulés du modèle sont
     * reconnus en français comme en anglais.
     */
    public function test_import_headers(): void {
        $this->resetAfterTest();
        foreach (['fr', 'en'] as $lang) {
            $headers = array_values(placement_offer_importer::template_headers($lang));
            $records = placement_offer_importer::parse_rows([0 => $headers, 1 => array_keys($headers)]);
            $this->assertCount(count($headers) + 1, get_object_vars($records[0]), $lang);
        }

        $this->expectException(\moodle_exception::class);
        placement_offer_importer::parse_rows([0 => ['Adresse', 'Places'], 1 => ['1 rue', '2']]);
    }

    /**
     * La tâche planifiée apparie les thématiques dont les vœux sont clos, une seule fois, et
     * annonce le résultat à chaque étudiant qui avait des vœux.
     */
    public function test_task_matches_closed_themes_and_notifies(): void {
        global $DB;
        [, $theme, [$a, $b], [$s1, $s2, $s3]] = $this->fixture(3);
        stage_placement_save_wishes($theme, $s1->id, [$a->id, $b->id]);
        stage_placement_save_wishes($theme, $s2->id, [$a->id, $b->id]);
        stage_placement_save_wishes($theme, $s3->id, [$a->id, $b->id]);
        $task = new \mod_stage\task\run_placement_matching();

        // Vœux encore ouverts : rien ne se passe.
        $sink = $this->redirectEmails();
        ob_start();
        $task->execute();
        $this->assertSame(0, $DB->count_records('stage_placement_assign', ['themeid' => $theme->id]));

        $DB->set_field('stage_theme', 'placementdeadline', time() - 60, ['id' => $theme->id]);
        $task->execute();
        $this->assertSame(2, $DB->count_records('stage_placement_assign', ['themeid' => $theme->id]));
        $this->assertSame(3, $sink->count());
        $bodies = implode("\n", array_map(fn($message) => quoted_printable_decode($message->body), $sink->get_messages()));
        $this->assertStringContainsString('Clinique A', $bodies);
        $this->assertStringContainsString('Clinique B', $bodies);

        // Second passage : la thématique est déjà appariée.
        $sink->clear();
        $task->execute();
        ob_end_clean();
        $this->assertSame(0, $sink->count());
        $this->assertSame(2, $DB->count_records('stage_placement_assign', ['themeid' => $theme->id]));
    }

    /**
     * Réinitialisation du cours et suppression de la thématique : vœux et affectations partent,
     * les organismes restent tant que la thématique existe, et les vœux sont rouverts.
     */
    public function test_reset_and_delete(): void {
        global $DB;
        [$stage, $theme, [$a, $b], [$s1]] = $this->fixture(1);
        stage_placement_save_wishes($theme, $s1->id, [$a->id, $b->id]);
        stage_placement_run_matching($theme);

        stage_placement_reset_userdata($stage->id);
        $this->assertSame(0, $DB->count_records('stage_placement_wish', ['themeid' => $theme->id]));
        $this->assertSame(0, $DB->count_records('stage_placement_assign', ['themeid' => $theme->id]));
        $this->assertSame(3, $DB->count_records('stage_placement_offer', ['themeid' => $theme->id]));
        $this->assertSame(STAGE_PLACEMENT_OPEN, stage_placement_state($this->reload($theme)));

        stage_placement_delete_themes_data([$theme->id]);
        $this->assertSame(0, $DB->count_records('stage_placement_offer', ['themeid' => $theme->id]));
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
     * Un organisme est nettoyé avant enregistrement : espaces retirés, SIRET normalisé, valeurs
     * ramenées à la longueur de leur colonne, au moins une place, champs inconnus écartés.
     */
    public function test_clean_offer(): void {
        $this->resetAfterTest();
        $clean = stage_placement_clean_offer([
            'name' => '  Clinique du Lac  ',
            'siret' => '732.829.320-00074',
            'phone' => str_repeat('0', 45),
            'address' => str_repeat('é', 300),
            'places' => 0,
            'description' => "  Accueil en mars.\n",
            'themeid' => 99,
            'inconnu' => 'x',
        ]);
        $this->assertSame('Clinique du Lac', $clean->name);
        $this->assertSame(self::SIRET, $clean->siret);
        $this->assertSame(30, \core_text::strlen($clean->phone));
        $this->assertSame(255, \core_text::strlen($clean->address), 'tronqué en caractères, pas en octets');
        $this->assertSame(1, $clean->places);
        $this->assertSame('Accueil en mars.', $clean->description);
        $this->assertObjectNotHasProperty('themeid', $clean);
        $this->assertObjectNotHasProperty('inconnu', $clean);

        // Un champ absent reste absent : une mise à jour partielle n'efface rien.
        $partial = stage_placement_clean_offer(['places' => 4]);
        $this->assertSame(['places' => 4], get_object_vars($partial));
    }

    /**
     * Deux organismes se rapprochent par leur SIRET, à défaut par leur nom sans tenir compte de
     * la casse, des accents ni de la ponctuation.
     */
    public function test_offer_key(): void {
        $this->assertSame(stage_placement_offer_key('Clinique du Parc'), stage_placement_offer_key('  clinique  du PARC '));
        $this->assertSame(stage_placement_offer_key('Équine Sud-Est'), stage_placement_offer_key('equine sud est'));
        $this->assertNotSame(stage_placement_offer_key('Clinique 1'), stage_placement_offer_key('Clinique 2'));
        $this->assertSame(
            stage_placement_offer_key('Nom A', '732 829 320 00074'),
            stage_placement_offer_key('Nom B', self::SIRET)
        );
        $this->assertNotSame(stage_placement_offer_key('Nom A', self::SIRET), stage_placement_offer_key('Nom A'));
    }

    /**
     * Un organisme ne se modifie que dans sa thématique ; l'enregistrement met à jour sans
     * dupliquer.
     */
    public function test_save_offer_update(): void {
        global $DB;
        [$stage, $theme, [$a]] = $this->fixture(0);
        $other = $this->getDataGenerator()->get_plugin_generator('mod_stage')->create_theme($stage);

        $this->assertEquals($a->id, stage_placement_save_offer($theme->id, ['places' => 5, 'location' => 'Aveize'], $a->id));
        $updated = $DB->get_record('stage_placement_offer', ['id' => $a->id]);
        $this->assertEquals(5, $updated->places);
        $this->assertSame('Aveize', $updated->location);
        $this->assertSame('Clinique A', $updated->name);
        $this->assertSame(3, $DB->count_records('stage_placement_offer', ['themeid' => $theme->id]));

        $this->expectException(\dml_missing_record_exception::class);
        stage_placement_save_offer($other->id, ['places' => 9], $a->id);
    }

    /**
     * Places libres : jamais négatives, même si la DEVE a réduit les places d'un organisme déjà
     * pourvu, et les affectations à un organisme supprimé sont ignorées.
     */
    public function test_remaining_places(): void {
        $offers = [1 => (object) ['id' => 1, 'places' => 2], 2 => (object) ['id' => 2, 'places' => 1]];
        $assignments = [
            (object) ['offerid' => 1], (object) ['offerid' => 2], (object) ['offerid' => 2], (object) ['offerid' => 7],
        ];
        $this->assertSame([1 => 1, 2 => 0], stage_placement_remaining_places($offers, $assignments));
        $this->assertSame([1 => 2, 2 => 1], stage_placement_remaining_places($offers, []));
    }

    /**
     * L'appariement sans vœux, ou sans organisme, n'affecte personne mais clôt la campagne ; un
     * vœu dont l'organisme a été supprimé entre-temps est sauté.
     */
    public function test_matching_edge_cases(): void {
        global $DB;
        [, $theme, [$a, $b, $c], [$s1, $s2]] = $this->fixture(2);

        $result = stage_placement_run_matching($theme);
        $this->assertSame([], $result->assigned);
        $this->assertSame([], $result->unassigned);
        $this->assertSame(STAGE_PLACEMENT_MATCHED, stage_placement_state($this->reload($theme)));

        // Réouverture, vœux, puis suppression du premier vœu de s1 avant l'appariement.
        $DB->set_field('stage_theme', 'placementmatchedtime', 0, ['id' => $theme->id]);
        $theme = $this->reload($theme);
        stage_placement_save_wishes($theme, $s1->id, [$a->id, $b->id]);
        stage_placement_save_wishes($theme, $s2->id, [$a->id, $c->id]);
        $DB->delete_records('stage_placement_offer', ['id' => $a->id]);

        $result = stage_placement_run_matching($theme, [$s1->id, $s2->id]);
        $this->assertEquals([$s1->id => $b->id, $s2->id => $c->id], $result->assigned);
        $this->assertEquals(2, stage_placement_get_assignment($theme->id, $s1->id)->wishrank);
    }

    /**
     * Sur un grand nombre de tirages, chaque étudiant en concurrence pour une seule place l'obtient
     * à peu près aussi souvent que les autres : le tirage au sort n'avantage personne.
     */
    public function test_matching_draw_is_fair(): void {
        global $DB;
        [, $theme, [$a, $b], $students] = $this->fixture(4);
        foreach ($students as $student) {
            stage_placement_save_wishes($theme, $student->id, [$a->id, $b->id]);
        }
        $wins = array_fill_keys(array_column($students, 'id'), 0);
        $rounds = 400;
        for ($round = 0; $round < $rounds; $round++) {
            $DB->delete_records('stage_placement_assign', ['themeid' => $theme->id]);
            $result = stage_placement_run_matching($theme);
            $wins[array_search($a->id, $result->assigned)]++;
        }
        // Attendu : 100 par étudiant ; écart-type d'environ 8,7. La borne laisse plus de 5 écarts-types.
        foreach ($wins as $userid => $count) {
            $this->assertGreaterThan(50, $count, "étudiant $userid");
            $this->assertLessThan(150, $count, "étudiant $userid");
        }
        $this->assertSame($rounds, array_sum($wins));
    }

    /**
     * Promotion entière : 300 étudiants, 60 organismes, vœux concentrés sur les mêmes organismes.
     * Aucune place n'est dépassée, chacun reçoit un de ses vœux au bon rang, un étudiant sans
     * affectation n'avait que des vœux complets, et un premier vœu resté libre a toujours été servi.
     */
    public function test_matching_whole_cohort(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $stagegen = $gen->get_plugin_generator('mod_stage');
        $course = $gen->create_course();
        $stage = $gen->create_module('stage', ['course' => $course->id]);
        $theme = $stagegen->create_theme($stage, ['placementenabled' => 1, 'placementchoices' => 4]);
        $places = [];
        for ($i = 0; $i < 60; $i++) {
            $offer = $stagegen->create_placement_offer($theme, ['places' => 1 + $i % 5]);
            $places[(int) $offer->id] = (int) $offer->places;
        }
        $offerids = array_keys($places);
        mt_srand(2026);
        $wishes = [];
        $now = time();
        $rows = [];
        for ($i = 0; $i < 300; $i++) {
            $student = $gen->create_and_enrol($course, 'student');
            // Popularité inégale : le carré d'un tirage uniforme favorise les premiers organismes.
            $chosen = [];
            while (count($chosen) < 4) {
                $pick = $offerids[(int) floor((mt_rand() / mt_getrandmax()) ** 2 * 59.999)];
                $chosen[$pick] = $pick;
            }
            $wishes[$student->id] = array_values($chosen);
            foreach ($wishes[$student->id] as $rank => $offerid) {
                $rows[] = (object) ['themeid' => $theme->id, 'userid' => $student->id, 'offerid' => $offerid,
                    'wishrank' => $rank + 1, 'timecreated' => $now];
            }
        }
        $DB->insert_records('stage_placement_wish', $rows);

        $started = microtime(true);
        $result = stage_placement_run_matching($theme);
        $elapsed = microtime(true) - $started;

        $this->assertCount(300, $result->assigned + array_flip($result->unassigned));
        $used = array_count_values($result->assigned);
        foreach ($used as $offerid => $count) {
            $this->assertLessThanOrEqual($places[$offerid], $count, "organisme $offerid");
        }
        $assignments = stage_placement_get_assignments($theme->id);
        $this->assertCount(count($result->assigned), $assignments);
        foreach ($assignments as $userid => $assignment) {
            $rank = array_search((int) $assignment->offerid, $wishes[$userid], true);
            $this->assertNotFalse($rank, "étudiant $userid affecté hors de ses vœux");
            $this->assertEquals($rank + 1, $assignment->wishrank);
        }
        // Personne ne reste sans affectation alors qu'un de ses vœux a encore une place.
        foreach ($result->unassigned as $userid) {
            foreach ($wishes[$userid] as $offerid) {
                $this->assertSame($places[$offerid], $used[$offerid] ?? 0, "étudiant $userid, organisme $offerid");
            }
        }
        $this->assertNotEmpty($result->unassigned, 'les vœux concentrés laissent des étudiants sans affectation');
        $this->assertLessThan(10, $elapsed, 'appariement de la promotion en moins de dix secondes');
    }

    /**
     * La tâche planifiée ne retient que les thématiques activées, à date de clôture passée et pas
     * encore appariées.
     */
    public function test_themes_to_match(): void {
        [$stage, $due] = $this->fixture(0, ['placementdeadline' => 1000]);
        $stagegen = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $stagegen->create_theme($stage, ['name' => 'À venir', 'placementenabled' => 1, 'placementdeadline' => 3000]);
        $stagegen->create_theme($stage, ['name' => 'Sans date', 'placementenabled' => 1, 'placementdeadline' => 0]);
        $stagegen->create_theme($stage, ['name' => 'Désactivée', 'placementenabled' => 0, 'placementdeadline' => 1000]);
        $stagegen->create_theme($stage, [
            'name' => 'Déjà appariée', 'placementenabled' => 1, 'placementdeadline' => 1000, 'placementmatchedtime' => 1500,
        ]);

        $this->assertSame([], stage_placement_get_themes_to_match(999));
        $this->assertEquals([$due->id], array_keys(stage_placement_get_themes_to_match(2000)));
        $this->assertCount(2, stage_placement_get_themes_to_match(3000));
    }

    /**
     * Rouvrir les vœux après l'appariement : les affectations restent, les étudiants sans
     * affectation refont leurs vœux et passent seuls au tour suivant.
     */
    public function test_reopen_after_matching(): void {
        global $DB;
        [, $theme, [$a, $b, $c], [$s1, $s2]] = $this->fixture(2);
        stage_placement_save_wishes($theme, $s1->id, [$a->id, $b->id]);
        stage_placement_save_wishes($theme, $s2->id, [$a->id, $b->id]);
        $DB->set_field('stage_placement_offer', 'places', 0, ['id' => $b->id]);
        stage_placement_run_matching($theme, [$s1->id, $s2->id]);
        $this->assertFalse(stage_placement_get_assignment($theme->id, $s2->id));

        $DB->set_field('stage_theme', 'placementmatchedtime', 0, ['id' => $theme->id]);
        $theme = $this->reload($theme);
        $this->assertSame(STAGE_PLACEMENT_OPEN, stage_placement_state($theme));
        $this->assertSame('placementalreadyassigned', stage_placement_save_wishes($theme, $s1->id, [$c->id, $b->id]));
        $this->assertNull(stage_placement_save_wishes($theme, $s2->id, [$c->id, $a->id]));

        $result = stage_placement_run_matching($theme);
        $this->assertEquals([$s2->id => $c->id], $result->assigned);
        $this->assertEquals($a->id, stage_placement_get_assignment($theme->id, $s1->id)->offerid);
    }

    /**
     * L'export liste chaque étudiant avec ses vœux dans l'ordre, l'organisme attribué et le rang
     * du vœu satisfait.
     */
    public function test_export(): void {
        [, $theme, [$a, $b, $c], [$s1, $s2, $s3]] = $this->fixture(3);
        stage_placement_save_wishes($theme, $s1->id, [$b->id, $a->id]);
        stage_placement_save_wishes($theme, $s2->id, [$b->id, $c->id]);
        stage_placement_run_matching($theme, [$s1->id, $s2->id]);
        stage_placement_set_assignment($theme, $s3->id, $a->id, 2);

        $export = stage_placement_get_export($theme, [$s1->id => $s1, $s2->id => $s2, $s3->id => $s3]);
        $this->assertCount(7, $export['columns']);
        $this->assertSame(
            [$s1->lastname, $s1->firstname, $s1->email, 'Clinique B', 'Clinique A', 'Clinique B',
                get_string('placementwishrank', 'mod_stage', 1)],
            $export['rows'][0]
        );
        $this->assertSame(
            ['Clinique B', 'Clinique C', 'Clinique C', get_string('placementwishrank', 'mod_stage', 2)],
            array_slice($export['rows'][1], 3)
        );
        $this->assertSame(
            ['', '', 'Clinique A', get_string('placementoutsidewishes', 'mod_stage')],
            array_slice($export['rows'][2], 3)
        );
    }

    /**
     * L'encart de l'étudiant suit la campagne : vœux à classer, vœux enregistrés, attente, puis
     * affectation avec l'accès à la demande, et enfin demande déposée. Une thématique masquée ou
     * sans organismes proposés n'y figure pas.
     */
    public function test_student_section(): void {
        global $DB, $PAGE;
        [$stage, $theme, [$a, $b], [$s1, $s2]] = $this->fixture(2);
        $stagegen = $this->getDataGenerator()->get_plugin_generator('mod_stage');
        $stagegen->create_theme($stage, ['name' => 'Thématique ordinaire']);
        $stagegen->create_theme($stage, ['name' => 'Thématique masquée', 'placementenabled' => 1, 'visible' => 0]);
        $cm = get_coursemodule_from_instance('stage', $stage->id, 0, false, MUST_EXIST);
        $PAGE->set_url('/mod/stage/view.php', ['id' => $cm->id]);
        $this->setUser($s1);
        $render = fn($user) => stage_placement_render_student_section($stage, $cm, $user->id);

        $html = $render($s1);
        $this->assertStringContainsString('Clinique rurale', $html);
        $this->assertStringNotContainsString('ordinaire', $html);
        $this->assertStringNotContainsString('masquée', $html);
        $this->assertStringContainsString(get_string('placementmakewishes', 'mod_stage'), $html);
        $this->assertStringNotContainsString(get_string('placementofflistinfo', 'mod_stage'), $html);

        stage_placement_save_wishes($theme, $s1->id, [$a->id, $b->id]);
        $html = $render($s1);
        $this->assertStringContainsString(get_string('placementeditwishes', 'mod_stage'), $html);
        $this->assertStringContainsString(get_string('placementwishessaved', 'mod_stage', 2), $html);

        $DB->set_field('stage_theme', 'placementdeadline', time() - 5, ['id' => $theme->id]);
        $this->assertStringContainsString(get_string('placementclosedwaiting', 'mod_stage'), $render($s1));

        stage_placement_run_matching($this->reload($theme));
        $html = $render($s1);
        $this->assertStringContainsString('Clinique A', $html);
        $this->assertStringContainsString(get_string('placementcompleterequest', 'mod_stage'), $html);
        $this->assertStringContainsString('placement=' . $theme->id, $html);
        $this->assertStringContainsString(get_string('placementresultnone', 'mod_stage'), $render($s2));

        $DB->set_field('stage_theme', 'placementofflist', 1, ['id' => $theme->id]);
        $this->assertStringContainsString(get_string('placementresultnoneofflist', 'mod_stage'), $render($s2));

        $entry = $stagegen->create_entry($stage, $s1->id, $theme);
        stage_placement_link_entry(stage_placement_get_assignment($theme->id, $s1->id)->id, $entry->id);
        $html = $render($s1);
        $this->assertStringContainsString(get_string('placementrequestdone', 'mod_stage'), $html);
        $this->assertStringNotContainsString(get_string('placementcompleterequest', 'mod_stage'), $html);

        // Activité sans thématique à organismes proposés : pas d'encart.
        $DB->set_field('stage_theme', 'placementenabled', 0, ['stageid' => $stage->id]);
        $this->assertSame('', $render($s1));
    }

    /**
     * Le courriel de résultat nomme l'organisme attribué, mène à la page des organismes et dit, à
     * l'étudiant sans affectation, s'il peut déposer une demande hors liste.
     */
    public function test_notification_texts(): void {
        [$stage, $theme, [$a], [$s1, $s2]] = $this->fixture(2, ['placementofflist' => 1]);
        $cm = get_coursemodule_from_instance('stage', $stage->id, 0, false, MUST_EXIST);
        $sink = $this->redirectEmails();

        $sent = stage_placement_notify_results($stage, $cm, $theme, (object) [
            'assigned' => [$s1->id => $a->id], 'unassigned' => [$s2->id],
        ]);

        $this->assertSame(2, $sent);
        $bodies = [];
        foreach ($sink->get_messages() as $message) {
            $bodies[$message->to] = quoted_printable_decode($message->body);
        }
        $this->assertStringContainsString(get_string('placementresultassigned', 'mod_stage', 'Clinique A'), $bodies[$s1->email]);
        $this->assertStringContainsString('placement.php?id=' . $cm->id, $bodies[$s1->email]);
        $this->assertStringContainsString(
            get_string('placementresultnoneofflist', 'mod_stage'),
            preg_replace('/\s+/', ' ', $bodies[$s2->email])
        );
        $this->assertSame(0, stage_placement_notify_results($stage, $cm, $theme, (object) ['assigned' => [], 'unassigned' => []]));
    }

    /**
     * Le transfert d'un étudiant, comme la suppression de ses données, retire ses vœux et libère sa
     * place sans toucher aux autres étudiants ni aux autres activités.
     */
    public function test_delete_users_data(): void {
        global $DB;
        [$stage, $theme, [$a, $b], [$s1, $s2]] = $this->fixture(2);
        stage_placement_save_wishes($theme, $s1->id, [$a->id, $b->id]);
        stage_placement_save_wishes($theme, $s2->id, [$a->id, $b->id]);
        stage_placement_run_matching($theme, [$s1->id, $s2->id]);
        $gen = $this->getDataGenerator();
        $otherstage = $gen->create_module('stage', ['course' => $gen->create_course()->id]);
        $othertheme = $gen->get_plugin_generator('mod_stage')->create_theme($otherstage, ['placementenabled' => 1]);
        $otheroffer = $gen->get_plugin_generator('mod_stage')->create_placement_offer($othertheme);
        stage_placement_set_assignment($othertheme, $s1->id, $otheroffer->id, 2);

        stage_placement_delete_users_data($stage->id, [$s1->id]);

        $this->assertSame([], stage_placement_get_wishes($theme->id, $s1->id));
        $this->assertFalse(stage_placement_get_assignment($theme->id, $s1->id));
        $this->assertEquals($b->id, stage_placement_get_assignment($theme->id, $s2->id)->offerid);
        $this->assertEquals($otheroffer->id, stage_placement_get_assignment($othertheme->id, $s1->id)->offerid);
        $this->assertNull(stage_placement_set_assignment($theme, $s2->id, $a->id, 2), 'la place est libérée');
    }

    /**
     * Demande de convention d'un stage déjà enregistré (convention_request.php) : hors affectation,
     * l'étudiant doit indiquer le SIRET en France, pas à l'étranger ; la DEVE, pour qui la page ne
     * transmet pas la thématique, n'y est pas tenue.
     */
    public function test_request_form_applies_siret_rule(): void {
        [$stage, $theme, [$a], [$student]] = $this->fixture(1);
        $gen = $this->getDataGenerator();
        $stagegen = $gen->get_plugin_generator('mod_stage');
        $referent = $gen->create_user();
        $template = $stagegen->create_convention_template($stage, ['lang' => 'fr']);
        $entry = $stagegen->create_entry($stage, $student->id, $theme);
        $this->setUser($student);
        $base = ['entryid' => $entry->id] + $this->request($referent, $template, $theme);

        $make = function (array $submitted, array $custom) use ($referent, $theme, $template) {
            \mod_stage\form\convention_request_form::mock_submit($submitted);
            return new \mod_stage\form\convention_request_form(null, $custom + [
                'templates' => [$template->id => $template],
                'referentteachers' => [$referent->id => $referent],
                'periods' => [], 'themeid' => (int) $theme->id, 'checklistitems' => [], 'requestquestions' => [],
            ]);
        };
        $student = ['placementtheme' => $theme, 'assignment' => null, 'abroad' => false];

        $form = $make($base, $student);
        $this->assertNull($form->get_data());
        $this->assertSame(get_string('placementsiretrequired', 'mod_stage'), $this->field_error($form, 'siret'));
        $this->assertNotNull($make(['siret' => self::SIRET] + $base, $student)->get_data());
        $this->assertNotNull($make($base, ['abroad' => true] + $student)->get_data());
        $this->assertNotNull($make($base, ['assignment' => (object) ['themeid' => $theme->id]] + $student)->get_data());
        $this->assertNotNull($make($base, ['placementtheme' => null, 'assignment' => null, 'abroad' => false])->get_data());
    }

    /**
     * Formulaire d'un organisme : nom obligatoire, au moins une place, SIRET et courriel du tuteur
     * plausibles.
     */
    public function test_offer_form_validation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $base = ['id' => 1, 'themeid' => 1, 'offerid' => 0, 'name' => 'Clinique du Lac', 'places' => 2,
            'siret' => '732 829 320 00074', 'tutoremail' => 'tuteur@example.com'];
        $make = function (array $submitted) {
            \mod_stage\form\placement_offer_form::mock_submit($submitted);
            return new \mod_stage\form\placement_offer_form();
        };

        $data = $make($base)->get_data();
        $this->assertNotNull($data);
        $this->assertSame('Clinique du Lac', $data->name);

        foreach (['places' => 0, 'siret' => '12345678901234', 'tutoremail' => 'pas-une-adresse', 'name' => ''] as $field => $bad) {
            $form = $make([$field => $bad] + $base);
            $this->assertNull($form->get_data(), $field);
            $this->assertNotEmpty($this->field_error($form, $field), $field);
        }
    }

    /**
     * Fiche de la thématique : avec l'option activée, il faut au moins un vœu à classer ; la case
     * de réouverture n'est proposée qu'une fois l'appariement fait.
     */
    public function test_theme_form_placement_settings(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $make = function (array $submitted, bool $matched = false) {
            $custom = ['teachers' => [], 'checklistcount' => 0, 'evallists' => [], 'tutorenabled' => false,
                'filemanageroptions' => [], 'placementmatched' => $matched];
            \mod_stage\form\theme_edit_form::mock_submit($submitted + [
                'id' => 1, 'themeid' => 0, 'name' => 'Rurale', 'requiredduration' => 0, 'sortorder' => 0,
                'minstudyyear' => 0, 'maxstudyyear' => 0, 'reportmode' => STAGE_REPORT_NONE, 'objectivefiles' => 0,
            ]);
            return new \mod_stage\form\theme_edit_form(null, $custom);
        };
        $has = function (\moodleform $form, string $name): bool {
            $property = new \ReflectionProperty(\moodleform::class, '_form');
            $property->setAccessible(true);
            return $property->getValue($form)->elementExists($name);
        };

        $form = $make(['placementenabled' => 1, 'placementchoices' => 0]);
        $this->assertNull($form->get_data());
        $this->assertSame(get_string('placementchoicesinvalid', 'mod_stage'), $this->field_error($form, 'placementchoices'));
        $this->assertFalse($has($form, 'placementreopen'));

        $data = $make(['placementenabled' => 1, 'placementchoices' => 4, 'placementofflist' => 1], true)->get_data();
        $this->assertNotNull($data);
        $this->assertEquals(4, $data->placementchoices);
        $this->assertEquals(1, $data->placementofflist);
        $this->assertEquals(0, $data->placementdeadline, 'date facultative non activée');
        $this->assertTrue($has($make([], true), 'placementreopen'));

        // Option décochée : un nombre de vœux nul n'est pas une erreur.
        $this->assertNotNull($make(['placementenabled' => 0, 'placementchoices' => 0])->get_data());
    }

    /**
     * Données personnelles : les vœux et l'affectation d'un étudiant sont retrouvés et supprimés
     * avec lui ; le membre de la DEVE qui a décidé une affectation en est seulement dissocié.
     */
    public function test_privacy(): void {
        global $DB;
        [$stage, $theme, [$a, $b], [$s1, $s2]] = $this->fixture(2);
        $deve = $this->getDataGenerator()->create_user();
        $cm = get_coursemodule_from_instance('stage', $stage->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        stage_placement_save_wishes($theme, $s1->id, [$a->id, $b->id]);
        stage_placement_set_assignment($theme, $s2->id, $b->id, $deve->id);

        foreach ([$s1, $s2, $deve] as $user) {
            $this->assertEquals(
                [$context->id],
                \mod_stage\privacy\provider::get_contexts_for_userid($user->id)->get_contextids(),
                $user->id
            );
        }
        $userlist = new \core_privacy\local\request\userlist($context, 'mod_stage');
        \mod_stage\privacy\provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$s1->id, $s2->id, $deve->id], $userlist->get_userids());

        \mod_stage\privacy\provider::delete_data_for_user(
            new \core_privacy\local\request\approved_contextlist($deve, 'mod_stage', [$context->id])
        );
        $assignment = stage_placement_get_assignment($theme->id, $s2->id);
        $this->assertEquals($b->id, $assignment->offerid);
        $this->assertNull($assignment->assignedby);

        \mod_stage\privacy\provider::delete_data_for_user(
            new \core_privacy\local\request\approved_contextlist($s1, 'mod_stage', [$context->id])
        );
        $this->assertSame([], stage_placement_get_wishes($theme->id, $s1->id));
        $this->assertSame(1, $DB->count_records('stage_placement_assign', ['themeid' => $theme->id]));

        \mod_stage\privacy\provider::delete_data_for_user(
            new \core_privacy\local\request\approved_contextlist($s2, 'mod_stage', [$context->id])
        );
        $this->assertSame(0, $DB->count_records('stage_placement_assign', ['themeid' => $theme->id]));
        $this->assertSame(3, $DB->count_records('stage_placement_offer', ['themeid' => $theme->id]));
    }

    /**
     * L'import de thématiques depuis une autre activité reprend le réglage et les organismes, pas
     * la date de clôture ni les vœux.
     */
    public function test_theme_import_copies_offers(): void {
        global $DB;
        [$stage, $theme, [$a, $b], [$s1]] = $this->fixture(1, ['placementofflist' => 1]);
        stage_placement_save_wishes($theme, $s1->id, [$a->id, $b->id]);
        $target = $this->getDataGenerator()->create_module('stage', ['course' => $this->getDataGenerator()->create_course()->id]);

        stage_import_themes($stage->id, $target->id);

        $copy = $DB->get_record('stage_theme', ['stageid' => $target->id, 'name' => $theme->name], '*', MUST_EXIST);
        $this->assertEquals(1, $copy->placementenabled);
        $this->assertEquals(2, $copy->placementchoices);
        $this->assertEquals(1, $copy->placementofflist);
        $this->assertEquals(0, $copy->placementdeadline);
        $this->assertSame(
            ['Clinique A', 'Clinique B', 'Clinique C'],
            array_values(array_map(fn($offer) => $offer->name, stage_placement_get_offers($copy->id)))
        );
        $this->assertSame(0, $DB->count_records('stage_placement_wish', ['themeid' => $copy->id]));
    }
}
