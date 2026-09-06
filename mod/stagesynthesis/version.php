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
 * Version details for mod_stagesynthesis.
 *
 * @package   mod_stagesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'mod_stagesynthesis';
$plugin->version   = 2026090402;
$plugin->requires  = 2022041900; // Moodle 4.0+.
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '0.1.5';
$plugin->dependencies = [
    // Version corrigeant la perte de returnurl dans convention_teacher_validate.php, dont la
    // cohérence des allers-retours vers cette synthèse dépend directement.
    'mod_stage' => 2026090300,
];
