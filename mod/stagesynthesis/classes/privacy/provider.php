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

/**
 * Privacy provider for mod_stagesynthesis.
 *
 * @package   mod_stagesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_stagesynthesis\privacy;

/**
 * The stagesynthesis_link table only stores which mod_stage activities are configured to feed
 * into a synthesis view (a course-module id) : no personal data of its own. Everything displayed
 * (students, evaluations...) is read live from mod_stage, which is responsible for its own
 * privacy provider.
 */
class provider implements \core_privacy\local\metadata\null_provider {

    /**
     * Explains why this plugin has no personal data of its own.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
