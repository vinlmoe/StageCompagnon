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
 * Tests de petites fonctions utilitaires pures, sans dépendance à la base de données : libellés
 * d'années d'étude, année limite de validation d'une thématique, normalisation de nom pour le
 * rapprochement tolérant (import StageVet, transfert d'étudiant), et rendu des actions/badges
 * communs aux vues.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::stage_normalize_name
 * @covers     ::stage_theme_final_year
 * @covers     ::stage_studyyear_range_label
 * @covers     ::stage_render_actions
 * @covers     ::stage_render_status_badge
 */
final class helpers_test extends \advanced_testcase {

    /**
     * La normalisation ignore accents, casse et espaces multiples, pour rapprocher des noms
     * saisis dans des systèmes différents (StageVet, une autre instance de l'activité).
     */
    public function test_normalize_name_is_accent_and_case_insensitive(): void {
        $this->assertSame(
            stage_normalize_name('Animaux de compagnie'),
            stage_normalize_name('  ANIMAUX   de   Compagnie ')
        );
        $this->assertSame(
            stage_normalize_name('Élevage'),
            stage_normalize_name('elevage')
        );
        $this->assertNotSame(
            stage_normalize_name('Ruminants'),
            stage_normalize_name('Ruminant')
        );
    }

    /**
     * L'année limite de validation d'une thématique est le maximum de sa plage ; une thématique
     * sans plage définie (les deux bornes vides) n'a pas d'année limite.
     */
    public function test_theme_final_year(): void {
        $ranged = (object) ['minstudyyear' => 2, 'maxstudyyear' => 4];
        $this->assertSame(4, stage_theme_final_year($ranged));

        $singleyear = (object) ['minstudyyear' => 3, 'maxstudyyear' => 3];
        $this->assertSame(3, stage_theme_final_year($singleyear));

        // Une seule borne renseignée : l'autre vaut alors la même année.
        $onlymin = (object) ['minstudyyear' => 5, 'maxstudyyear' => 0];
        $this->assertSame(5, stage_theme_final_year($onlymin));

        $undefined = (object) ['minstudyyear' => 0, 'maxstudyyear' => 0];
        $this->assertNull(stage_theme_final_year($undefined));
    }

    /**
     * Le libellé de plage d'années se réduit à une seule année quand les deux bornes sont
     * identiques ou que l'une d'elles n'est pas spécifiée.
     */
    public function test_studyyear_range_label_collapses_when_bounds_equal_or_unset(): void {
        $range = stage_studyyear_range_label(2, 4);
        $this->assertStringContainsString(' - ', $range);

        $samebound = stage_studyyear_range_label(3, 3);
        $this->assertStringNotContainsString(' - ', $samebound);

        $onlymax = stage_studyyear_range_label(0, 5);
        $this->assertStringNotContainsString(' - ', $onlymax);
    }

    /**
     * Une entrée dont l'URL est nulle est simplement omise du rendu, plutôt que de produire un
     * lien cassé ; un ensemble entièrement vide retombe sur la valeur de repli fournie.
     */
    public function test_render_actions_skips_null_urls(): void {
        $url = new \moodle_url('/mod/stage/register.php');
        $html = stage_render_actions(['Modifier' => $url, 'Absent' => null]);

        $this->assertStringContainsString('Modifier', $html);
        $this->assertStringNotContainsString('Absent', $html);

        $this->assertSame('-', stage_render_actions(['Rien' => null]));
        $this->assertSame('', stage_render_actions(['Rien' => null], 'btn', ''));
    }

    /**
     * Le badge de statut distingue au moins visuellement (classe CSS) l'état complété de l'état à
     * compléter : deux appels avec des booléens opposés ne doivent pas produire le même HTML.
     */
    public function test_status_badge_differs_by_state(): void {
        $done = stage_render_status_badge(true);
        $todo = stage_render_status_badge(false);

        $this->assertNotSame($done, $todo);
    }
}
