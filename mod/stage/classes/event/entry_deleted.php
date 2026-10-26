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

namespace mod_stage\event;

/**
 * Stage supprimé définitivement par la DEVE, lors d'une suppression en lot (delete_entries.php).
 *
 * Déclenché avant la suppression, pendant que la saisie existe encore : « other » porte ses
 * statuts, sa thématique et sa durée retenue, seule trace du stage qui reste ensuite.
 *
 * @package mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entry_deleted extends entry_event_base {
    /** @var string Lettre CRUD de l'événement. */
    const CRUD = 'd';

    /** @var int Niveau éducatif de l'événement. */
    const EDULEVEL = self::LEVEL_OTHER;

    /**
     * Description pour les journaux.
     *
     * @return string
     */
    public function get_description() {
        return $this->describe('supprimé');
    }

    /**
     * La saisie n'existe plus : la liste des stages de l'activité remplace sa page de détail.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/stage/register.php', ['id' => $this->contextinstanceid]);
    }
}
