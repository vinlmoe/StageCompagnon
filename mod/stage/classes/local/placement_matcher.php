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

namespace mod_stage\local;

/**
 * Règles d'appariement des thématiques à organismes proposés, sans accès à la base : elles reçoivent
 * l'ordre de passage, les vœux et les places, et rendent les affectations. Le même calcul sert à
 * l'appariement et à sa vérification après coup (voir stage_placement_replay_round()).
 *
 * Deux règles :
 * - **tirage au sort** (STAGE_PLACEMENT_MODE_DRAW) : dans l'ordre tiré, chacun reçoit le mieux classé
 *   de ses vœux où il reste une place. Personne n'a intérêt à classer ses vœux autrement que selon
 *   ses préférences, mais un étudiant peut rester sans affectation alors qu'un autre ordre l'aurait
 *   placé ;
 * - **le plus d'étudiants placés** (STAGE_PLACEMENT_MODE_MAXPLACED) : parmi toutes les affectations
 *   possibles, celles qui placent le plus d'étudiants ; parmi elles, celle dont la somme des rangs
 *   de vœux est la plus petite ; à égalité, les premiers de l'ordre tiré passent devant. C'est un
 *   flot de coût minimal. Un étudiant peut y avoir intérêt à classer stratégiquement ses vœux.
 *
 * Dans les deux cas, une affectation porte toujours sur un vœu de l'étudiant et ne dépasse jamais
 * les places d'un organisme.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class placement_matcher {
    /**
     * Ordre de passage tiré au sort à partir d'une graine : les mêmes étudiants et la même graine
     * donnent toujours le même ordre, quelle que soit la version de PHP. Le générateur (xorshift
     * sur 32 bits) est propre à la classe : le tirage ne dépend ni ne modifie l'état de mt_rand().
     *
     * @param int[] $userids Étudiants à ordonner, dans n'importe quel ordre.
     * @param int $seed Graine du tirage (entier positif).
     * @return int[] Les mêmes identifiants, dans l'ordre de passage.
     */
    public static function order(array $userids, int $seed): array {
        $order = array_values(array_unique(array_map('intval', $userids)));
        sort($order);
        $state = ($seed & 0xffffffff) ?: 0x9e3779b9;
        for ($i = count($order) - 1; $i > 0; $i--) {
            $state ^= ($state << 13) & 0xffffffff;
            $state ^= $state >> 17;
            $state ^= ($state << 5) & 0xffffffff;
            $j = $state % ($i + 1);
            [$order[$i], $order[$j]] = [$order[$j], $order[$i]];
        }
        return $order;
    }

    /**
     * Applique une règle d'appariement.
     *
     * @param string $mode STAGE_PLACEMENT_MODE_DRAW ou STAGE_PLACEMENT_MODE_MAXPLACED.
     * @param int[] $order Ordre de passage (identifiants d'étudiants).
     * @param array $wishes userid => identifiants d'organismes, du premier au dernier vœu.
     * @param int[] $places offerid => places libres.
     * @return array userid => [offerid, rang du vœu (1 = premier)], pour les étudiants placés.
     */
    public static function match(string $mode, array $order, array $wishes, array $places): array {
        return $mode === STAGE_PLACEMENT_MODE_MAXPLACED
            ? self::max_placed($order, $wishes, $places)
            : self::draw($order, $wishes, $places);
    }

    /**
     * Tirage au sort : dans l'ordre de passage, le mieux classé des vœux où il reste une place.
     *
     * @param int[] $order
     * @param array $wishes
     * @param int[] $places
     * @return array Voir match().
     */
    public static function draw(array $order, array $wishes, array $places): array {
        $assigned = [];
        foreach ($order as $userid) {
            foreach (array_values($wishes[$userid] ?? []) as $index => $offerid) {
                if (($places[$offerid] ?? 0) > 0) {
                    $places[$offerid]--;
                    $assigned[$userid] = [(int) $offerid, $index + 1];
                    break;
                }
            }
        }
        return $assigned;
    }

    /**
     * Le plus d'étudiants placés, puis la plus petite somme des rangs, puis l'ordre de passage : flot
     * de coût minimal (plus courts chemins successifs, algorithme de Dijkstra sur les coûts réduits
     * par des potentiels).
     *
     * Le coût d'un arc étudiant → organisme vaut rang × M + position de l'étudiant dans l'ordre, M
     * dépassant la somme de toutes les positions : la somme des rangs l'emporte toujours, la position
     * ne départage que des solutions de même somme. Les plus courts chemins successifs donnent un flot
     * maximal de coût minimal : le nombre d'étudiants placés est d'abord le plus grand possible.
     *
     * @param int[] $order
     * @param array $wishes
     * @param int[] $places
     * @return array Voir match().
     */
    public static function max_placed(array $order, array $wishes, array $places): array {
        $students = array_values(array_filter($order, fn($userid) => !empty($wishes[$userid])));
        $offerids = [];
        foreach ($students as $userid) {
            foreach ($wishes[$userid] as $offerid) {
                if (($places[$offerid] ?? 0) > 0) {
                    $offerids[(int) $offerid] = true;
                }
            }
        }
        $offerids = array_keys($offerids);
        $n = count($students);
        $m = count($offerids);
        if (!$n || !$m) {
            return [];
        }
        $source = 0;
        $sink = $n + $m + 1;
        $offernode = [];
        foreach ($offerids as $k => $offerid) {
            $offernode[$offerid] = $n + 1 + $k;
        }
        $big = $n * $n + 1;

        // Graphe en tableaux : arc e de $from vers $to[$e], arc retour e ^ 1.
        $to = $cap = $cost = $next = [];
        $head = array_fill(0, $sink + 1, -1);
        $add = function (int $u, int $v, int $capacity, int $c) use (&$to, &$cap, &$cost, &$next, &$head) {
            foreach ([[$u, $v, $capacity, $c], [$v, $u, 0, -$c]] as [$a, $b, $k, $w]) {
                $e = count($to);
                $to[$e] = $b;
                $cap[$e] = $k;
                $cost[$e] = $w;
                $next[$e] = $head[$a];
                $head[$a] = $e;
            }
        };
        $rankof = [];
        foreach ($students as $i => $userid) {
            $node = $i + 1;
            $add($source, $node, 1, 0);
            foreach (array_values($wishes[$userid]) as $index => $offerid) {
                if (isset($offernode[$offerid]) && !isset($rankof[$node][$offerid])) {
                    $rankof[$node][$offerid] = $index + 1;
                    $add($node, $offernode[$offerid], 1, ($index + 1) * $big + $i);
                }
            }
        }
        foreach ($offerids as $offerid) {
            $add($offernode[$offerid], $sink, (int) $places[$offerid], 0);
        }

        // Plus courts chemins successifs. Les coûts initiaux sont positifs : potentiels nuls.
        $potential = array_fill(0, $sink + 1, 0);
        $infinity = PHP_INT_MAX;
        while (true) {
            $dist = array_fill(0, $sink + 1, $infinity);
            $via = array_fill(0, $sink + 1, -1);
            $dist[$source] = 0;
            $queue = new \SplPriorityQueue();
            $queue->setExtractFlags(\SplPriorityQueue::EXTR_BOTH);
            $queue->insert($source, 0);
            while (!$queue->isEmpty()) {
                $top = $queue->extract();
                $u = $top['data'];
                if (-$top['priority'] > $dist[$u]) {
                    continue;
                }
                for ($e = $head[$u]; $e !== -1; $e = $next[$e]) {
                    if ($cap[$e] <= 0) {
                        continue;
                    }
                    $v = $to[$e];
                    $candidate = $dist[$u] + $cost[$e] + $potential[$u] - $potential[$v];
                    if ($candidate < $dist[$v]) {
                        $dist[$v] = $candidate;
                        $via[$v] = $e;
                        $queue->insert($v, -$candidate);
                    }
                }
            }
            if ($dist[$sink] === $infinity) {
                break;
            }
            foreach ($dist as $v => $d) {
                if ($d !== $infinity) {
                    $potential[$v] += $d;
                }
            }
            // Chaque chemin passe par un arc source → étudiant de capacité 1 : il porte une unité.
            for ($v = $sink; $v !== $source; $v = $to[$via[$v] ^ 1]) {
                $cap[$via[$v]]--;
                $cap[$via[$v] ^ 1]++;
            }
        }

        $assigned = [];
        foreach ($students as $i => $userid) {
            $node = $i + 1;
            for ($e = $head[$node]; $e !== -1; $e = $next[$e]) {
                // Arc aller vers un organisme, saturé : l'étudiant y est placé.
                if ($e % 2 === 0 && $to[$e] !== $source && $cap[$e] === 0) {
                    $offerid = $offerids[$to[$e] - $n - 1];
                    $assigned[$userid] = [(int) $offerid, $rankof[$node][$offerid]];
                }
            }
        }
        return $assigned;
    }
}
