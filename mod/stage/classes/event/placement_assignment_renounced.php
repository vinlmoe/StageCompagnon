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
 * Un étudiant renonce à l'organisme qui lui a été attribué : la place est libérée et ses vœux
 * retirés. « other » porte l'organisme (offerid).
 *
 * @package mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class placement_assignment_renounced extends placement_event_base {
    /** @var int Niveau éducatif de l'événement. */
    const EDULEVEL = self::LEVEL_PARTICIPATING;

    /**
     * Description pour les journaux.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' has renounced the organisation with id '{$this->other['offerid']}' "
            . "assigned for the theme with id '{$this->other['themeid']}'.";
    }
}
