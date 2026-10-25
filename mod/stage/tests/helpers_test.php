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
 * @covers     ::stage_teacher_assignment_options
 * @covers     ::stage_optional_status_param
 * @covers     ::stage_sort_pilotage_rows
 * @covers     ::stage_render_pilotage_status
 * @covers     ::stage_pilotage_columns
 * @covers     ::stage_render_pilotage_table
 * @covers     ::stage_render_pilotage_search_form
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
     * Régression : les chiffres étaient supprimés, si bien que « Clinique 1 » et « Clinique 2 »
     * (thématiques, questions) étaient confondues au transfert et à l'import.
     */
    public function test_normalize_name_keeps_digits(): void {
        $this->assertNotSame(stage_normalize_name('Clinique 1'), stage_normalize_name('Clinique 2'));
        $this->assertNotSame(stage_normalize_name('Clinique 1'), stage_normalize_name('Clinique'));
        $this->assertSame('stage 1ere annee', stage_normalize_name('Stage 1ère  ANNÉE'));
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

    /**
     * Les enseignants déjà attribués sont rechargés dans la liste sélectionnée même lorsque
     * Moodle fournit leur identifiant sous forme de chaîne et l'affectation sous forme d'entier.
     */
    public function test_teacher_assignment_options_restore_existing_selection(): void {
        $teachers = [
            (object) [
                'id' => '12', 'firstname' => 'Alice', 'lastname' => 'Martin',
                'firstnamephonetic' => '', 'lastnamephonetic' => '', 'middlename' => '', 'alternatename' => '',
            ],
            (object) [
                'id' => '34', 'firstname' => 'Bruno', 'lastname' => 'Durand',
                'firstnamephonetic' => '', 'lastnamephonetic' => '', 'middlename' => '', 'alternatename' => '',
            ],
        ];

        [$available, $selected] = stage_teacher_assignment_options($teachers, [12]);

        $this->assertStringContainsString('Bruno', $available);
        $this->assertStringNotContainsString('Alice', $available);
        $this->assertStringContainsString('Alice', $selected);
        $this->assertStringContainsString('selected="selected"', $selected);
        $this->assertStringNotContainsString('Bruno', $selected);
    }

    /**
     * Valeurs reçues par le filtre de statut et valeur retenue.
     *
     * @return array[]
     */
    public static function status_param_provider(): array {
        return [
            'vide : tous les statuts' => ['', ''],
            'statut 0 conservé, non confondu avec « tous »' => ['0', '0'],
            'statut positif' => ['3', '3'],
            'statut négatif' => ['-2', '-2'],
            'zéros non significatifs normalisés' => ['007', '7'],
            'lettres rejetées' => ['abc', ''],
            'injection rejetée' => ['1;drop', ''],
        ];
    }

    /**
     * Le filtre de statut distingue « tous les statuts » (chaîne vide) du statut 0, accepte les
     * codes négatifs et ignore ce qui n'est pas un code.
     *
     * @dataProvider status_param_provider
     * @param string $given
     * @param string $expected
     */
    public function test_optional_status_param(string $given, string $expected): void {
        $_GET['status'] = $given;
        try {
            $this->assertSame($expected, stage_optional_status_param('status'));
        } finally {
            unset($_GET['status']);
        }
    }

    /**
     * Sans paramètre, tous les statuts.
     */
    public function test_optional_status_param_absent_means_all(): void {
        unset($_GET['status']);
        $this->assertSame('', stage_optional_status_param('status'));
    }

    /**
     * Ligne de pilotage minimale, comme stage_get_pilotage_overview() en produit.
     *
     * @param string $firstname
     * @param string $lastname
     * @param int $done Thématiques obligatoires validées.
     * @param int $total Thématiques obligatoires.
     * @param int $pending Saisies en attente.
     * @param int $retained Jours retenus.
     * @param array $yearprogress
     * @return \stdClass
     */
    private function pilotage_row($firstname, $lastname, $done, $total, $pending, $retained, array $yearprogress = []) {
        static $id = 100;
        return (object) [
            'user' => (object) [
                'id' => ++$id, 'firstname' => $firstname, 'lastname' => $lastname,
                'firstnamephonetic' => '', 'lastnamephonetic' => '', 'middlename' => '', 'alternatename' => '',
            ],
            'coursename' => 'Promotion ' . $lastname,
            'currentstudyyear' => 2,
            'cmid' => 7,
            'mandatorydone' => $done,
            'mandatorytotal' => $total,
            'pendingcount' => $pending,
            'progress' => (object) ['totalretained' => $retained],
            'complete' => $total > 0 && $done >= $total,
            'yearprogress' => $yearprogress,
        ];
    }

    /**
     * Le tri et la recherche du pilotage : clé inconnue ramenée à l'étudiant, sens décroissant,
     * recherche insensible à la casse, clé de tri supplémentaire de la synthèse.
     */
    public function test_sort_pilotage_rows_filters_and_sorts(): void {
        $rows = [
            $this->pilotage_row('Zoé', 'Martin', 2, 4, 1, 30),
            $this->pilotage_row('Anne', 'Durand', 4, 4, 0, 12),
            $this->pilotage_row('Luc', 'Bernard', 0, 4, 3, 50),
        ];
        $names = function (array $rows) {
            return array_values(array_map(function ($row) {
                return $row->user->firstname;
            }, $rows));
        };

        [$sorted, $key] = stage_sort_pilotage_rows($rows, '', 'student', 'ASC');
        $this->assertSame('student', $key);
        $this->assertSame(['Anne', 'Luc', 'Zoé'], $names($sorted));

        [$sorted, $key] = stage_sort_pilotage_rows($rows, '', 'pending', 'DESC');
        $this->assertSame('pending', $key);
        $this->assertSame(['Luc', 'Zoé', 'Anne'], $names($sorted));

        [$sorted, $key] = stage_sort_pilotage_rows($rows, '', 'progress', 'ASC');
        $this->assertSame(['Luc', 'Zoé', 'Anne'], $names($sorted));

        [$sorted, $key] = stage_sort_pilotage_rows($rows, '', 'retained', 'ASC');
        $this->assertSame(['Anne', 'Zoé', 'Luc'], $names($sorted));

        [$sorted, $key] = stage_sort_pilotage_rows($rows, '', 'inconnu', 'ASC');
        $this->assertSame('student', $key);
        $this->assertSame(['Anne', 'Luc', 'Zoé'], $names($sorted));

        [$sorted] = stage_sort_pilotage_rows($rows, 'MART', 'student', 'ASC');
        $this->assertSame(['Zoé'], $names($sorted));

        [$sorted, $key] = stage_sort_pilotage_rows($rows, '', 'course', 'DESC', [
            'course' => function ($row) {
                return $row->coursename;
            },
        ]);
        $this->assertSame('course', $key);
        $this->assertSame(['Zoé', 'Anne', 'Luc'], $names($sorted));
    }

    /**
     * Le statut global : validé, ou à compléter avec les années déjà validées quand il y en a.
     */
    public function test_render_pilotage_status_lists_validated_years(): void {
        $done = stage_render_pilotage_status($this->pilotage_row('A', 'B', 4, 4, 0, 0));
        $this->assertStringContainsString('badge-success', $done);

        $partial = stage_render_pilotage_status($this->pilotage_row('A', 'B', 2, 4, 0, 0, [
            (object) ['studyyear' => 1, 'done' => true],
            (object) ['studyyear' => 2, 'done' => false],
        ]));
        $this->assertStringContainsString('badge-warning', $partial);
        $this->assertStringContainsString(stage_studyyear_label(1), $partial);
        $this->assertStringNotContainsString(stage_studyyear_label(2), $partial);

        $none = stage_render_pilotage_status($this->pilotage_row('A', 'B', 0, 4, 0, 0, [
            (object) ['studyyear' => 1, 'done' => false],
        ]));
        $this->assertStringContainsString('badge-warning', $none);
        $this->assertStringNotContainsString('text-muted small', $none);
    }

    /**
     * Le tableau suit l'ordre des colonnes fournies, la synthèse pouvant intercaler les siennes
     * parmi les colonnes communes.
     */
    public function test_render_pilotage_table_follows_column_order(): void {
        $rows = [$this->pilotage_row('Zoé', 'Martin', 2, 4, 1, 30)];
        $listurl = new \moodle_url('/mod/stage/dashboard.php', ['id' => 7]);
        $common = stage_pilotage_columns($listurl, 'student', 'ASC', function ($row) {
            return new \moodle_url('/mod/stage/dashboard.php', ['id' => 7, 'studentid' => $row->user->id]);
        });
        $this->assertSame(['student', 'progress', 'pending', 'retained', 'status', 'actions'], array_keys($common));

        $columns = [
            'course' => [
                'head' => get_string('course'),
                'cell' => function ($row) {
                    return $row->coursename;
                },
            ],
            'student' => $common['student'],
        ] + $common;
        $this->assertSame(['course', 'student', 'progress', 'pending', 'retained', 'status', 'actions'], array_keys($columns));

        $html = stage_render_pilotage_table($columns, $rows, 0, $listurl);
        $this->assertStringContainsString('Promotion Martin', $html);
        $this->assertStringContainsString('Zoé Martin', $html);
        $this->assertStringContainsString('2 / 4', $html);
        $this->assertStringContainsString('badge-info', $html);
        $this->assertStringContainsString('studentid=' . $rows[0]->user->id, $html);
        $this->assertLessThan(strpos($html, 'Zoé Martin'), strpos($html, 'Promotion Martin'));

        $form = stage_render_pilotage_search_form($listurl, 7, 'O\'Neil');
        $this->assertStringContainsString('value="O&#039;Neil"', $form);
        $this->assertStringNotContainsString('&amp;#039;', $form);
    }
}
