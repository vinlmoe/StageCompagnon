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

namespace mod_stage\event;

/**
 * Vœux d'une thématique à organismes proposés rouverts après l'appariement. « other » porte la
 * nouvelle date de clôture (deadline, 0 : sans date).
 *
 * @package mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class placement_wishes_reopened extends placement_event_base {
    /**
     * Description pour les journaux.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' has reopened the wishes of the theme with id '{$this->other['themeid']}'.";
    }
}
