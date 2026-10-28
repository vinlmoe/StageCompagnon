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

namespace mod_stage\fixtures;

use mod_stage\local\convention_detail;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Formulaire minimal portant deux champs de convention et les règles de longueur.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class convention_detail_test_form extends \moodleform {
    /**
     * Définition du formulaire.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('text', 'gratificationamount', 'Gratification');
        $mform->setType('gratificationamount', PARAM_TEXT);
        $mform->addElement('text', 'hostphone', 'Téléphone');
        $mform->setType('hostphone', PARAM_TEXT);
        convention_detail::add_maxlength_rules($mform);
    }
}
