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
 * Prévisualisation d'un import en plusieurs étapes (import historique), conservée d'une requête
 * à l'autre dans un fichier temporaire plutôt que dans la session.
 *
 * Un classeur de plusieurs milliers de lignes, avec ses doublons probables, alourdissait la
 * session de l'utilisateur, relue et réécrite à chaque page qu'il ouvre, au point de dépasser la
 * taille maximale d'un élément quand les sessions sont stockées dans memcached ou redis. Le
 * fichier, propre à l'utilisateur et à l'activité, est placé dans le répertoire temporaire de
 * Moodle, partagé entre les serveurs d'une grappe et purgé par la tâche de nettoyage ; une
 * prévisualisation de plus d'un jour est de toute façon considérée comme expirée.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_preview {
    /** @var int Durée de validité d'une prévisualisation, en secondes. */
    const LIFETIME = DAYSECS;

    /**
     * Lit la prévisualisation de l'utilisateur courant pour une activité.
     *
     * @param string $kind Type d'import (« historical »).
     * @param int $stageid
     * @return array|null Null si elle n'existe pas, a expiré ou est illisible.
     */
    public static function get(string $kind, int $stageid): ?array {
        $path = self::path($kind, $stageid);
        if (!is_readable($path)) {
            return null;
        }
        if (filemtime($path) < time() - self::LIFETIME) {
            @unlink($path);
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    /**
     * Enregistre (ou remplace) la prévisualisation de l'utilisateur courant pour une activité.
     *
     * @param string $kind
     * @param int $stageid
     * @param array $data Valeurs scalaires et tableaux uniquement (encodées en JSON).
     * @return void
     */
    public static function set(string $kind, int $stageid, array $data): void {
        $path = self::path($kind, $stageid);
        // Écriture dans un fichier voisin puis renommage : une lecture concurrente ne voit jamais
        // un fichier à moitié écrit.
        $tmp = $path . '.' . random_string(8);
        file_put_contents($tmp, json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
        rename($tmp, $path);
    }

    /**
     * Supprime la prévisualisation de l'utilisateur courant pour une activité.
     *
     * @param string $kind
     * @param int $stageid
     * @return void
     */
    public static function delete(string $kind, int $stageid): void {
        $path = self::path($kind, $stageid);
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    /**
     * Chemin du fichier de prévisualisation de l'utilisateur courant.
     *
     * @param string $kind
     * @param int $stageid
     * @return string
     */
    private static function path(string $kind, int $stageid): string {
        global $USER;

        $dir = make_temp_directory('mod_stage/importpreview');
        return $dir . '/' . clean_param($kind, PARAM_ALPHA) . '-' . (int) $USER->id . '-' . $stageid . '.json';
    }
}
