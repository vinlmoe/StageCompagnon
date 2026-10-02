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

namespace mod_stage\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

/**
 * Formulaire unique de paramétrage des conventions (DEVE) : paramètres généraux, informations de
 * l'établissement et logos, enregistrés en une fois (voir convention_templates.php). Les gabarits
 * sont listés sur la même page mais s'éditent un par un (convention_template.php).
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class conventions_admin_form extends \moodleform {
    /**
     * Options du gestionnaire de fichiers d'un logo (un seul PNG).
     *
     * @return array
     */
    public static function logo_file_options(): array {
        return ['subdirs' => 0, 'maxfiles' => 1, 'maxbytes' => 2 * 1024 * 1024, 'accepted_types' => ['.png']];
    }

    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Paramètres généraux.
        $mform->addElement('header', 'settingshdr', get_string('generalsettings', 'mod_stage'));
        $mform->setExpanded('settingshdr', true);
        $mform->addElement(
            'advcheckbox',
            'conventionrequireteachervalidation',
            get_string('conventionrequireteachervalidation', 'mod_stage')
        );
        $mform->addHelpButton('conventionrequireteachervalidation', 'conventionrequireteachervalidation', 'mod_stage');

        // Établissement d'enseignement.
        $mform->addElement('header', 'establishmenthdr', get_string('conventionestablishment', 'mod_stage'));
        $mform->setExpanded('establishmenthdr', true);
        $mform->addElement('static', 'establishmentintro', '', get_string('conventionestablishment_help', 'mod_stage'));
        foreach (
            ['name', 'address', 'representative', 'representativetitle', 'phone', 'email', 'signatory'] as $field
        ) {
            $mform->addElement(
                'text',
                'establishment' . $field,
                get_string('conventionestablishment' . $field, 'mod_stage'),
                ['size' => '64']
            );
            $mform->setType('establishment' . $field, PARAM_TEXT);
        }
        $mform->addHelpButton('establishmentsignatory', 'conventionestablishmentsignatory', 'mod_stage');

        // Logos.
        $mform->addElement('header', 'logoshdr', get_string('conventionlogos', 'mod_stage'));
        $mform->setExpanded('logoshdr', true);
        $mform->addElement('static', 'logosintro', '', get_string('conventionlogos_help', 'mod_stage'));
        foreach (['logoleft', 'logoright'] as $logo) {
            $mform->addElement(
                'filemanager',
                $logo,
                get_string('convention' . $logo, 'mod_stage'),
                null,
                self::logo_file_options()
            );
        }

        $this->add_action_buttons(false, get_string('savechanges'));
    }
}
