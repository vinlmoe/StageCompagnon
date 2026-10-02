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

use mod_stage\local\csv_importer;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/stage/locallib.php');

/**
 * Import StageVet des évaluations de l'étudiant et du maître de stage : lecture de l'export
 * actuel (60 colonnes, cellules multilignes), mise à jour d'un stage existant, demande
 * d'évaluation à l'enseignant référent, et affichage des notes sur 5 en étoiles.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_stage\local\csv_importer
 * @covers     ::stage_parse_evaluation_text
 * @covers     ::stage_render_evaluation_text
 * @covers     ::stage_notify_teachers_eval_request
 */
final class stagevet_evaluations_test extends \advanced_testcase {
    /** @var string[] En-têtes de l'export StageVet actuel, dans l'ordre. */
    const HEADERS = ['Étudiant', "Année d'étude", 'Organisme', 'Adresse organisme', 'N° convention',
        'Convention générée le', 'Date signature finale', 'Début stage', 'Fin stage', 'Dates brutes', 'Thème', 'Durée',
        'URL convention PDF', 'URL signature', 'Chemin PDF local', 'Contact école', 'Nom tuteur', 'Fonction tuteur',
        'Téléphone tuteur', 'Email tuteur', 'Organisme (convention)', 'Adresse organisme (convention)',
        'Représentant organisme', 'Qualité maître de stage', 'Téléphone organisme', 'Email organisme',
        'Nom maître de stage', 'Fonction maître de stage', 'Nom étudiant', 'Prénom étudiant',
        'Date de naissance étudiant', 'Année étudiant (convention)', 'Adresse étudiant', 'Téléphone étudiant',
        'Email étudiant', 'Année universitaire', 'Début (convention)', 'Fin (convention)', 'Durée (convention)',
        'Jours déclarés', 'Jours effectifs', 'Cohérence jours', 'Présence de nuit', 'Présence dimanche',
        'Présence jour férié', 'Présence à domicile', 'Repos hebdomadaire', 'Thème (convention)',
        'Statut gratification', 'Montant gratification', 'Cohérence gratification', 'Gratification',
        'Signature tuteur', 'Signature étudiant', 'Signature maître de stage', 'Signature école',
        'URL source des données', 'Texte brut de la convention', 'Évaluation par le maître de stage',
        'Évaluation par l’étudiant'];

    /** @var string Évaluation du maître de stage de l'exemple de la spécification. */
    const TUTOR_EVAL = "Savoir-être\nPonctualité : 4/5\nAvis global : 4/5\nCommentaire\n"
        . 'Bonne progression ; attitude "attentive".';

    /** @var string Évaluation de l'étudiant de l'exemple de la spécification. */
    const STUDENT_EVAL = "Accueil\nPrésentation de l’équipe : 0/5\nDisponibilité : Non renseigné\nAvis global : 3/5\n"
        . "Commentaire\nExemple fictif de commentaire.\nGestes techniques et actes pratiqués : Suture sur modèle.";

    /**
     * Produit un export au format StageVet : BOM, point-virgule, tous les champs entre guillemets,
     * guillemets doublés, fins de ligne CRLF et cellules multilignes.
     *
     * @param array $rows Lignes, chacune intitulé => valeur (les autres colonnes restent vides).
     * @param string[]|null $headers En-têtes à utiliser (par défaut, ceux de l'export actuel).
     * @return string
     */
    private function csv(array $rows, ?array $headers = null): string {
        $headers = $headers ?? self::HEADERS;
        $quote = fn($value) => '"' . str_replace('"', '""', (string) $value) . '"';
        $lines = [implode(';', array_map($quote, $headers))];
        foreach ($rows as $row) {
            $lines[] = implode(';', array_map(fn($header) => $quote($row[$header] ?? ''), $headers));
        }
        return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Activité, étudiant, enseignant référent attribué et thématique.
     *
     * @return array [stdClass $stage, \context_module $context, stdClass $student, stdClass $teacher]
     */
    private function fixture(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $stage = $gen->create_module('stage', ['course' => $course]);
        $context = \context_module::instance($stage->cmid);
        $student = $gen->create_user(['email' => 'camille@example.test', 'firstname' => 'Camille', 'lastname' => 'Exemple']);
        $teacher = $gen->create_user(['email' => 'prof@example.test']);
        $gen->enrol_user($student->id, $course->id, 'student');
        $gen->enrol_user($teacher->id, $course->id, 'teacher');
        $stagegen = $gen->get_plugin_generator('mod_stage');
        $stagegen->create_theme($stage, ['name' => 'Clinique']);
        $stagegen->assign_teacher($stage, $student->id, $teacher->id);
        return [$stage, $context, $student, $teacher];
    }

    /**
     * Ligne de base de l'exemple de la spécification, rattachée à la thématique de l'activité.
     *
     * @param array $overrides
     * @return array
     */
    private function row(array $overrides = []): array {
        return $overrides + [
            'Étudiant' => 'EXEMPLE Camille',
            "Année d'étude" => '3e',
            'Organisme' => 'Clinique "Exemple" ; site A',
            'N° convention' => 'DEMO-0001',
            'Date signature finale' => '20/01/2026',
            'Début stage' => '01/02/2026',
            'Fin stage' => '05/02/2026',
            'Thème' => 'Clinique',
            'Email étudiant' => 'camille@example.test',
            'Jours déclarés' => '5',
            'Jours effectifs' => '5',
            'Texte brut de la convention' => "Convention fictive.\nTexte sur deux lignes.",
        ];
    }

    /**
     * L'export actuel crée le stage avec ses deux évaluations et sollicite l'enseignant référent.
     */
    public function test_import_creates_entry_with_both_evaluations_and_requests_teacher(): void {
        global $DB;
        [$stage, $context, $student, $teacher] = $this->fixture();
        $sink = $this->redirectEmails();

        $csv = $this->csv([$this->row([
            'Évaluation par le maître de stage' => self::TUTOR_EVAL,
            'Évaluation par l’étudiant' => self::STUDENT_EVAL,
        ])]);
        $result = csv_importer::stagevet($stage, $context, $csv);

        $this->assertNull($result['error']);
        $this->assertSame(1, $result['results']->created);
        $this->assertSame(2, $result['results']->evaluations);
        $this->assertSame(1, $result['results']->notified);
        $this->assertEmpty($result['results']->errors);

        $entry = $DB->get_record('stage_entry', ['stageid' => $stage->id], '*', MUST_EXIST);
        $this->assertSame('Clinique "Exemple" ; site A', $entry->structure);
        $this->assertSame(self::STUDENT_EVAL, $entry->studentselfeval);
        $this->assertSame(self::TUTOR_EVAL, $entry->tutoreval);
        $this->assertNotEmpty($entry->tutortime);
        $this->assertEquals(STAGE_STATUS_EVAL_ETUDIANT, $entry->status);

        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertSame($teacher->email, $messages[0]->to);
        $this->assertStringContainsString('teacher.php', $messages[0]->body);
    }

    /**
     * Un stage déjà importé est mis à jour (dates de convention décalées comprises), une cellule
     * vide n'efface rien, et l'enseignant n'est sollicité qu'une fois.
     */
    public function test_import_updates_existing_entry_and_requests_teacher_once(): void {
        global $DB;
        [$stage, $context] = $this->fixture();
        $sink = $this->redirectEmails();

        // Premier import : pas encore d'évaluation.
        csv_importer::stagevet($stage, $context, $this->csv([$this->row()]));
        $this->assertEquals(1, $DB->count_records('stage_entry'));

        // Deuxième import : l'évaluation du maître de stage arrive, avec les dates de la convention.
        $second = csv_importer::stagevet($stage, $context, $this->csv([$this->row([
            'Début (convention)' => '02/02/2026',
            'Fin (convention)' => '06/02/2026',
            'Évaluation par le maître de stage' => self::TUTOR_EVAL,
        ])]));
        $this->assertSame(0, $second['results']->created);
        $this->assertSame(1, $second['results']->updated);
        $this->assertSame(0, $second['results']->notified);
        $entry = $DB->get_record('stage_entry', [], '*', MUST_EXIST);
        $this->assertSame(self::TUTOR_EVAL, $entry->tutoreval);
        $this->assertEquals(STAGE_STATUS_ENREGISTRE, $entry->status);

        // Troisième import : celle de l'étudiant complète le stage ; l'évaluation du maître de
        // stage, vide dans ce fichier, est conservée.
        $third = csv_importer::stagevet($stage, $context, $this->csv([$this->row([
            'Évaluation par l’étudiant' => self::STUDENT_EVAL,
        ])]));
        $this->assertSame(1, $third['results']->updated);
        $this->assertSame(1, $third['results']->notified);
        $entry = $DB->get_record('stage_entry', [], '*', MUST_EXIST);
        $this->assertSame(self::TUTOR_EVAL, $entry->tutoreval);
        $this->assertSame(self::STUDENT_EVAL, $entry->studentselfeval);
        $this->assertEquals(STAGE_STATUS_EVAL_ETUDIANT, $entry->status);

        // Même fichier une nouvelle fois : rien ne change, aucun nouveau courriel.
        $fourth = csv_importer::stagevet($stage, $context, $this->csv([$this->row([
            'Évaluation par l’étudiant' => self::STUDENT_EVAL,
            'Évaluation par le maître de stage' => self::TUTOR_EVAL,
        ])]));
        $this->assertSame(1, $fourth['results']->unchanged);
        $this->assertSame(0, $fourth['results']->notified);
        $this->assertEquals(1, $DB->count_records('stage_entry'));
        $this->assertCount(1, $sink->get_messages());
    }

    /**
     * Une évaluation saisie dans l'activité pour un stage non importé n'est jamais remplacée.
     */
    public function test_import_keeps_evaluation_entered_in_activity(): void {
        global $DB;
        [$stage, $context, $student] = $this->fixture();
        $theme = $DB->get_record('stage_theme', ['stageid' => $stage->id], '*', MUST_EXIST);
        $entry = $this->getDataGenerator()->get_plugin_generator('mod_stage')->create_entry($stage, $student->id, $theme, [
            'datestart' => strtotime('2026-02-01'),
            'dateend' => strtotime('2026-02-05'),
        ]);
        $DB->set_field('stage_entry', 'studentselfeval', '<p>Mon auto-évaluation</p>', ['id' => $entry->id]);

        $result = csv_importer::stagevet($stage, $context, $this->csv([$this->row([
            'Évaluation par l’étudiant' => self::STUDENT_EVAL,
        ])]));
        $this->assertSame(0, $result['results']->created);
        $this->assertCount(1, $result['results']->errors);
        $this->assertEquals('<p>Mon auto-évaluation</p>', $DB->get_field('stage_entry', 'studentselfeval', ['id' => $entry->id]));
    }

    /**
     * En-têtes retouchés (sans accents, apostrophe droite, majuscules) et ancien export sans les
     * colonnes d'évaluation sont acceptés ; un étudiant sans référent est signalé.
     */
    public function test_import_tolerates_header_variants_and_reports_missing_referent(): void {
        global $DB;
        [$stage, $context, $student] = $this->fixture();
        stage_set_student_teachers($stage->id, $student->id, []);
        $sink = $this->redirectEmails();

        $headers = ['Email étudiant', 'Thème', 'Début stage', 'Fin stage', 'EVALUATION MAITRE DE STAGE',
            "evaluation par l'etudiant"];
        $result = csv_importer::stagevet($stage, $context, $this->csv([[
            'Email étudiant' => 'camille@example.test',
            'Thème' => 'Clinique',
            'Début stage' => '01/02/2026',
            'Fin stage' => '05/02/2026',
            'EVALUATION MAITRE DE STAGE' => "Avis global : 5/5\r\n",
            "evaluation par l'etudiant" => 'Avis global : 4 / 5',
        ]], $headers));
        $this->assertSame(1, $result['results']->created);
        $this->assertSame(0, $result['results']->notified);
        $this->assertSame([$student->id => fullname($student)], $result['results']->noreferent);
        $this->assertCount(0, $sink->get_messages());
        $entry = $DB->get_record('stage_entry', [], '*', MUST_EXIST);
        $this->assertSame('Avis global : 5/5', $entry->tutoreval);

        // Ancien export à 58 colonnes : l'import fonctionne et ne touche pas aux évaluations.
        $old = csv_importer::stagevet($stage, $context, $this->csv([$this->row()], array_slice(self::HEADERS, 0, 58)));
        $this->assertNull($old['error']);
        $this->assertSame(1, $old['results']->unchanged);
        $this->assertSame('Avis global : 5/5', $DB->get_field('stage_entry', 'tutoreval', ['id' => $entry->id]));
    }

    /**
     * Rubriques, notes (zéro et absence distincts), avis global et commentaire sont reconnus.
     */
    public function test_parse_evaluation_text(): void {
        $blocks = stage_parse_evaluation_text(self::STUDENT_EVAL);
        $this->assertSame(['type' => 'heading', 'text' => 'Accueil'], $blocks[0]);
        $this->assertSame('rating', $blocks[1]['type']);
        $this->assertSame(0.0, $blocks[1]['score']);
        $this->assertSame('Disponibilité', $blocks[2]['label']);
        $this->assertNull($blocks[2]['score']);
        $this->assertTrue($blocks[3]['overall']);
        $this->assertSame(3.0, $blocks[3]['score']);
        $this->assertSame('comment', $blocks[4]['type']);
        $this->assertStringContainsString('Exemple fictif de commentaire.', $blocks[4]['text']);

        // Un item absent des exports précédents (« Tenue ») est reconnu comme les autres : les
        // items ne sont pas une liste fixe, chaque ligne « libellé : N/5 » est une note.
        $newitem = stage_parse_evaluation_text("Savoir-être\nPonctualité : 4/5\nTenue : 3/5");
        $this->assertSame('Tenue', $newitem[2]['label']);
        $this->assertSame(3.0, $newitem[2]['score']);

        $tolerant = stage_parse_evaluation_text("Note : 4,5 / 5\nAutre :3/5");
        $this->assertSame(4.5, $tolerant[0]['score']);
        $this->assertSame(3.0, $tolerant[1]['score']);
    }

    /**
     * Les notes sont rendues en étoiles, le texte est échappé, et un texte sans note ou en HTML
     * est affiché comme auparavant.
     */
    public function test_render_evaluation_text(): void {
        $this->resetAfterTest();
        $html = stage_render_evaluation_text(self::TUTOR_EVAL . "\n<script>alert(1)</script>");
        $this->assertStringContainsString('stage-evaluation-table', $html);
        $this->assertSame(8, substr_count($html, 'stage-star-full'));
        $this->assertSame(2, substr_count($html, 'stage-star-empty'));
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&quot;attentive&quot;', $html);

        $missing = stage_render_evaluation_text("Disponibilité : Non renseigné\nAccueil : 0/5");
        $this->assertStringContainsString(get_string('ratingnotprovided', 'mod_stage'), $missing);
        $this->assertSame(5, substr_count($missing, 'stage-star-empty'));

        $plain = stage_render_evaluation_text('<p>Très bon stage</p>', FORMAT_HTML);
        $this->assertStringNotContainsString('stage-evaluation-table', $plain);
        $this->assertStringContainsString('Très bon stage', $plain);
    }
}
