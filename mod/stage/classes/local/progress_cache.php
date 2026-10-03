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

namespace mod_stage\local;

/**
 * Données de bilan préchargées pour toute une promotion, le temps d'un calcul groupé.
 *
 * Le bilan d'un étudiant (stage_get_student_progress(), stage_get_student_year_progress(),
 * stage_get_student_abroad_progress()) interroge la base pour chaque saisie, chaque année et
 * chaque thématique : appelé pour chacun des 180 étudiants d'une promotion, il en résultait plus
 * de 16 000 requêtes au tableau de pilotage. Ce cache charge en une dizaine de requêtes tout ce
 * dont ces fonctions ont besoin (activité, thématiques et leurs durées, durées par année, saisies
 * et types de stage des étudiants concernés), et ces fonctions le consultent tant qu'il couvre
 * l'activité et l'étudiant demandés. Sinon, elles interrogent la base comme avant.
 *
 * Il n'est actif que pendant un calcul en lecture seule (voir stage_get_pilotage_overview(), qui
 * l'arme puis le vide dans un bloc finally) : aucune écriture ne peut le rendre périmé.
 *
 * @package mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress_cache {
    /** @var int|null Activité couverte, null si le cache est vide. */
    private static $stageid = null;

    /** @var \stdClass|null Enregistrement de l'activité. */
    private static $stage = null;

    /** @var array Thématiques de l'activité (toutes), dans l'ordre de stage_get_themes(). */
    private static $themes = [];

    /** @var array themeid => [studyyear => durée requise]. */
    private static $durations = [];

    /** @var array studyyear => durée totale requise. */
    private static $yearrequirements = [];

    /** @var array userid => [entryid => saisie], dans l'ordre de stage_get_student_entries(). */
    private static $entries = [];

    /** @var array entryid => 'obligatoire'|'complementaire'. */
    private static $stagetypes = [];

    /**
     * Charge les données de bilan d'une activité pour les étudiants donnés.
     *
     * @param int $stageid
     * @param int[] $userids
     * @return void
     */
    public static function prime(int $stageid, array $userids): void {
        global $DB;

        self::clear();
        self::$stage = $DB->get_record('stage', ['id' => $stageid], '*', MUST_EXIST);
        self::$themes = $DB->get_records(
            'stage_theme',
            ['stageid' => $stageid],
            'minstudyyear ASC, maxstudyyear ASC, sortorder ASC, name ASC'
        );
        if (self::$themes) {
            [$insql, $inparams] = $DB->get_in_or_equal(array_keys(self::$themes));
            $records = $DB->get_recordset_select('stage_theme_duration', "themeid $insql", $inparams);
            foreach ($records as $record) {
                self::$durations[(int) $record->themeid][(int) $record->studyyear] = (int) $record->requiredduration;
            }
            $records->close();
        }
        foreach ($DB->get_records('stage_year_requirement', ['stageid' => $stageid]) as $record) {
            self::$yearrequirements[(int) $record->studyyear] = (int) $record->requiredduration;
        }

        $userids = array_values(array_unique(array_map('intval', $userids)));
        foreach ($userids as $userid) {
            self::$entries[$userid] = [];
        }
        // Par paquets, pour rester sous la limite de paramètres des bases de données.
        foreach (array_chunk($userids, 500) as $chunk) {
            [$insql, $inparams] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'u');
            $entries = $DB->get_records_select(
                'stage_entry',
                "stageid = :stageid AND userid $insql",
                ['stageid' => $stageid] + $inparams,
                'timecreated DESC, id DESC'
            );
            foreach ($entries as $entry) {
                self::$entries[(int) $entry->userid][$entry->id] = $entry;
                self::$stagetypes[(int) $entry->id] = 'obligatoire';
            }
        }
        foreach (array_chunk(array_keys(self::$stagetypes), 500) as $chunk) {
            [$insql, $inparams] = $DB->get_in_or_equal($chunk);
            $details = $DB->get_records_select('stage_convention_detail', "entryid $insql", $inparams, '', 'entryid, stagetype');
            foreach ($details as $detail) {
                self::$stagetypes[(int) $detail->entryid] = $detail->stagetype;
            }
        }
        self::$stageid = $stageid;
    }

    /**
     * Vide le cache : les fonctions de bilan interrogent de nouveau la base.
     *
     * @return void
     */
    public static function clear(): void {
        self::$stageid = null;
        self::$stage = null;
        self::$themes = [];
        self::$durations = [];
        self::$yearrequirements = [];
        self::$entries = [];
        self::$stagetypes = [];
    }

    /**
     * Indique si le cache couvre l'activité, et l'étudiant s'il est précisé.
     *
     * @param int $stageid
     * @param int|null $userid
     * @return bool
     */
    public static function covers($stageid, $userid = null): bool {
        if (self::$stageid === null || (int) $stageid !== self::$stageid) {
            return false;
        }
        return $userid === null || array_key_exists((int) $userid, self::$entries);
    }

    /**
     * Enregistrement de l'activité couverte.
     *
     * @return \stdClass
     */
    public static function stage(): \stdClass {
        return self::$stage;
    }

    /**
     * Thématiques de l'activité couverte, comme stage_get_themes().
     *
     * @param bool $onlyvisible
     * @return array
     */
    public static function themes(bool $onlyvisible = false): array {
        if (!$onlyvisible) {
            return self::$themes;
        }
        return array_filter(self::$themes, fn($theme) => (int) $theme->visible === 1);
    }

    /**
     * Indique si la thématique appartient à l'activité couverte.
     *
     * @param int $themeid
     * @return bool
     */
    public static function has_theme($themeid): bool {
        return self::$stageid !== null && isset(self::$themes[(int) $themeid]);
    }

    /**
     * Durée requise d'une thématique pour une année, selon les règles de
     * stage_get_theme_duration() : durée unique, sinon durée de l'année, sinon durée « toutes
     * années » (année 0), sinon 0.
     *
     * @param int $themeid
     * @param int $studyyear
     * @return int
     */
    public static function theme_duration($themeid, $studyyear): int {
        $theme = self::$themes[(int) $themeid];
        if (!empty($theme->requiredduration)) {
            return (int) $theme->requiredduration;
        }
        $durations = self::$durations[(int) $themeid] ?? [];
        if (array_key_exists((int) $studyyear, $durations)) {
            return $durations[(int) $studyyear];
        }
        if (!empty($studyyear) && array_key_exists(0, $durations)) {
            return $durations[0];
        }
        return 0;
    }

    /**
     * Durées totales requises par année de l'activité couverte.
     *
     * @return array studyyear => durée
     */
    public static function year_requirements(): array {
        return self::$yearrequirements;
    }

    /**
     * Saisies d'un étudiant couvert, comme stage_get_student_entries().
     *
     * @param int $userid
     * @return array
     */
    public static function entries($userid): array {
        return self::$entries[(int) $userid] ?? [];
    }

    /**
     * Types de stage des saisies, si toutes sont couvertes.
     *
     * @param int[] $entryids
     * @return array|null entryid => type, ou null si une saisie n'est pas couverte.
     */
    public static function stagetypes(array $entryids): ?array {
        if (self::$stageid === null) {
            return null;
        }
        $types = [];
        foreach ($entryids as $entryid) {
            if (!isset(self::$stagetypes[(int) $entryid])) {
                return null;
            }
            $types[$entryid] = self::$stagetypes[(int) $entryid];
        }
        return $types;
    }
}
