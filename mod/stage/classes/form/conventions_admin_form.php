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
 * Formulaire unique de paramétrage des conventions (DEVE) : paramètres généraux, gabarits
 * (modifiables en ligne, avec ajout d'un nouveau gabarit), informations de l'établissement et
 * logos, tout enregistré en une fois (voir convention_templates.php).
 *
 * Données attendues dans customdata :
 * - templates : id => gabarit (stage_convention_template) ;
 * - inuse : id => nombre de demandes de convention utilisant le gabarit (non supprimable).
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class conventions_admin_form extends \moodleform {
    /**
     * Options du gestionnaire de fichiers d'un gabarit (un seul PDF).
     *
     * @return array
     */
    public static function template_file_options(): array {
        global $CFG;
        return ['subdirs' => 0, 'maxfiles' => 1, 'maxbytes' => $CFG->maxbytes, 'accepted_types' => ['.pdf']];
    }

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
        $templates = $this->_customdata['templates'] ?? [];
        $inuse = $this->_customdata['inuse'] ?? [];

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

        // Gabarits existants, un bloc chacun, puis un bloc pour en ajouter un.
        $mform->addElement('header', 'templateshdr', get_string('conventiontemplates', 'mod_stage'));
        $mform->setExpanded('templateshdr', true);
        if (empty($templates)) {
            $mform->addElement('static', 'notemplates', '', get_string('noconventiontemplatesyet', 'mod_stage'));
        }
        foreach ($templates as $template) {
            $tid = (int) $template->id;
            $mform->addElement('static', 'templatesep_' . $tid, '', \html_writer::tag('hr', ''));
            $mform->addElement(
                'text',
                'templatename_' . $tid,
                get_string('conventiontemplatename', 'mod_stage'),
                ['size' => '64']
            );
            $mform->setType('templatename_' . $tid, PARAM_TEXT);
            $mform->addElement(
                'select',
                'templatelang_' . $tid,
                get_string('conventionlang', 'mod_stage'),
                stage_convention_lang_options()
            );
            $mform->addElement(
                'filemanager',
                'templatefile_' . $tid,
                get_string('conventiontemplatefile', 'mod_stage'),
                null,
                self::template_file_options()
            );
            if (!empty($inuse[$tid])) {
                $mform->addElement(
                    'static',
                    'templateinuse_' . $tid,
                    '',
                    \html_writer::span(get_string('conventiontemplateusedby', 'mod_stage', $inuse[$tid]), 'text-muted')
                );
            } else {
                $mform->addElement(
                    'advcheckbox',
                    'templatedelete_' . $tid,
                    get_string('deleteconventiontemplaterow', 'mod_stage')
                );
            }
        }

        $mform->addElement(
            'static',
            'newtemplatesep',
            '',
            \html_writer::tag('strong', get_string('addconventiontemplate', 'mod_stage'))
        );
        $mform->addElement('text', 'newtemplatename', get_string('conventiontemplatename', 'mod_stage'), ['size' => '64']);
        $mform->setType('newtemplatename', PARAM_TEXT);
        $mform->addElement('select', 'newtemplatelang', get_string('conventionlang', 'mod_stage'), stage_convention_lang_options());
        $mform->setDefault('newtemplatelang', 'fr');
        $mform->addElement(
            'filemanager',
            'newtemplatefile',
            get_string('conventiontemplatefile', 'mod_stage'),
            null,
            self::template_file_options()
        );
        $mform->addElement('static', 'newtemplatehint', '', get_string('newconventiontemplate_hint', 'mod_stage'));

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

    /**
     * Server-side validation : un gabarit garde un nom ; un nouveau gabarit n'est créé
     * que si son nom et son PDF sont fournis tous les deux (l'un sans l'autre est une erreur).
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        foreach ($this->_customdata['templates'] ?? [] as $template) {
            $tid = (int) $template->id;
            if (!empty($data['templatedelete_' . $tid])) {
                continue;
            }
            if (trim((string) ($data['templatename_' . $tid] ?? '')) === '') {
                $errors['templatename_' . $tid] = get_string('required');
            }
        }

        $newname = trim((string) ($data['newtemplatename'] ?? ''));
        $newfile = self::draft_has_file($data['newtemplatefile'] ?? 0);
        if ($newname !== '' && !$newfile) {
            $errors['newtemplatefile'] = get_string('conventiontemplatefilerequired', 'mod_stage');
        } else if ($newname === '' && $newfile) {
            $errors['newtemplatename'] = get_string('required');
        }

        return $errors;
    }

    /**
     * Indique si une zone de brouillon contient au moins un fichier. La vérification est faite
     * côté serveur car une règle "required" côté client n'est pas fiable sur un filemanager.
     *
     * @param int $draftitemid
     * @return bool
     */
    public static function draft_has_file($draftitemid): bool {
        global $USER;

        if (empty($draftitemid)) {
            return false;
        }
        $usercontext = \context_user::instance($USER->id);
        foreach (get_file_storage()->get_area_files($usercontext->id, 'user', 'draft', (int) $draftitemid) as $file) {
            if (!$file->is_directory()) {
                return true;
            }
        }
        return false;
    }
}
