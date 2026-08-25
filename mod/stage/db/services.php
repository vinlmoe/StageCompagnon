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
 * Web service declarations for mod_stage.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_stage_register_entries' => [
        'classname'   => 'mod_stage\external\register_entries',
        'methodname'  => 'execute',
        'description' => "Enregistre un ou plusieurs stages pour des étudiants (réservé à la DEVE). "
            . "Permet l'import depuis un système externe (ex. tableur Excel converti côté client).",
        'type'        => 'write',
        'capabilities' => 'mod/stage:registerstages',
        'ajax'        => true,
    ],
    'mod_stage_get_my_stages' => [
        'classname'   => 'mod_stage\external\get_my_stages',
        'methodname'  => 'execute',
        'description' => "Renvoie les stages de l'utilisateur courant pour une activité donnée.",
        'type'        => 'read',
        'capabilities' => 'mod/stage:submit',
        'ajax'        => true,
    ],
];

$services = [
    'Gestion des stages (mod_stage)' => [
        'functions' => [
            'mod_stage_register_entries',
            'mod_stage_get_my_stages',
        ],
        'restrictedusers' => 0,
        'enabled' => 0,
        'shortname' => 'mod_stage',
    ],
];
