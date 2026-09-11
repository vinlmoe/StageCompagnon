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
 * Étape de restauration de la structure d'une instance de mod_stage.
 *
 * @package   mod_stage
 * @category  backup
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restaure l'arbre décrit par backup_stage_activity_structure_step.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_stage_activity_structure_step extends restore_activity_structure_step {
    /**
     * Déclare les chemins à restaurer.
     *
     * @return array
     */
    protected function define_structure() {

        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('stage', '/activity/stage');
        $paths[] = new restore_path_element('stage_theme', '/activity/stage/themes/theme');
        $paths[] = new restore_path_element(
            'stage_theme_teacher',
            '/activity/stage/themes/theme/themeteachers/themeteacher'
        );
        $paths[] = new restore_path_element(
            'stage_theme_duration',
            '/activity/stage/themes/theme/themedurations/themeduration'
        );
        $paths[] = new restore_path_element(
            'stage_year_requirement',
            '/activity/stage/yearrequirements/yearrequirement'
        );
        $paths[] = new restore_path_element(
            'stage_convention_template',
            '/activity/stage/conventiontemplates/conventiontemplate'
        );
        $paths[] = new restore_path_element('stage_question', '/activity/stage/questions/question');
        $paths[] = new restore_path_element(
            'stage_question_theme',
            '/activity/stage/questions/question/questionthemes/questiontheme'
        );
        $paths[] = new restore_path_element(
            'stage_email_template',
            '/activity/stage/emailtemplates/emailtemplate'
        );

        if ($userinfo) {
            $paths[] = new restore_path_element(
                'stage_entry_teacher',
                '/activity/stage/entryteachers/entryteacher'
            );
            $paths[] = new restore_path_element('stage_entry', '/activity/stage/entries/entry');
            $paths[] = new restore_path_element(
                'stage_entry_period',
                '/activity/stage/entries/entry/periods/period'
            );
            $paths[] = new restore_path_element(
                'stage_entry_workday',
                '/activity/stage/entries/entry/workdays/workday'
            );
            $paths[] = new restore_path_element(
                'stage_convention_detail',
                '/activity/stage/entries/entry/conventiondetails/conventiondetail'
            );
            $paths[] = new restore_path_element(
                'stage_answer',
                '/activity/stage/entries/entry/answers/answer'
            );
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restaure l'instance elle-même.
     *
     * @param array $data
     */
    protected function process_stage($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();

        $newitemid = $DB->insert_record('stage', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restaure une thématique.
     *
     * @param array $data
     */
    protected function process_stage_theme($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->stageid = $this->get_new_parentid('stage');

        $newitemid = $DB->insert_record('stage_theme', $data);
        $this->set_mapping('stage_theme', $oldid, $newitemid);
    }

    /**
     * Restaure un enseignant responsable de thématique. La ligne est écartée si le compte n'est
     * pas présent dans la sauvegarde (restauration sans les utilisateurs).
     *
     * @param array $data
     */
    protected function process_stage_theme_teacher($data) {
        global $DB;

        $data = (object) $data;
        unset($data->id);

        $teacherid = $this->get_mappingid('user', $data->teacherid);
        if (!$teacherid) {
            return;
        }

        $data->themeid = $this->get_new_parentid('stage_theme');
        $data->teacherid = $teacherid;

        // L'index (themeid, teacherid) est unique : deux comptes distincts de la sauvegarde
        // peuvent se retrouver fusionnés sur un même compte du site cible.
        if ($DB->record_exists('stage_theme_teacher', ['themeid' => $data->themeid, 'teacherid' => $teacherid])) {
            return;
        }

        $DB->insert_record('stage_theme_teacher', $data);
    }

    /**
     * Restaure une durée requise par année d'étude pour une thématique.
     *
     * @param array $data
     */
    protected function process_stage_theme_duration($data) {
        global $DB;

        $data = (object) $data;
        unset($data->id);
        $data->themeid = $this->get_new_parentid('stage_theme');

        $DB->insert_record('stage_theme_duration', $data);
    }

    /**
     * Restaure une durée totale requise pour une année d'étude.
     *
     * @param array $data
     */
    protected function process_stage_year_requirement($data) {
        global $DB;

        $data = (object) $data;
        unset($data->id);
        $data->stageid = $this->get_new_parentid('stage');

        $DB->insert_record('stage_year_requirement', $data);
    }

    /**
     * Restaure un gabarit de convention (les fichiers PDF suivent, par la correspondance).
     *
     * @param array $data
     */
    protected function process_stage_convention_template($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->stageid = $this->get_new_parentid('stage');

        $newitemid = $DB->insert_record('stage_convention_template', $data);
        $this->set_mapping('stage_convention_template', $oldid, $newitemid, true);
    }

    /**
     * Restaure une question d'évaluation.
     *
     * @param array $data
     */
    protected function process_stage_question($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->stageid = $this->get_new_parentid('stage');
        $data->themeid = $this->get_mappingid('stage_theme', $data->themeid) ?: 0;

        $newitemid = $DB->insert_record('stage_question', $data);
        $this->set_mapping('stage_question', $oldid, $newitemid);
    }

    /**
     * Restaure le rattachement d'une question à une thématique supplémentaire.
     *
     * @param array $data
     */
    protected function process_stage_question_theme($data) {
        global $DB;

        $data = (object) $data;
        unset($data->id);

        $themeid = $this->get_mappingid('stage_theme', $data->themeid);
        if (!$themeid) {
            return;
        }

        $data->questionid = $this->get_new_parentid('stage_question');
        $data->themeid = $themeid;

        $DB->insert_record('stage_question_theme', $data);
    }

    /**
     * Restaure la personnalisation d'un courriel.
     *
     * @param array $data
     */
    protected function process_stage_email_template($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->stageid = $this->get_new_parentid('stage');

        $newitemid = $DB->insert_record('stage_email_template', $data);
        $this->set_mapping('stage_email_template', $oldid, $newitemid);
    }

    /**
     * Restaure l'attribution d'un enseignant référent à un étudiant.
     *
     * @param array $data
     */
    protected function process_stage_entry_teacher($data) {
        global $DB;

        $data = (object) $data;
        unset($data->id);

        $studentid = $this->get_mappingid('user', $data->studentid);
        $teacherid = $this->get_mappingid('user', $data->teacherid);
        if (!$studentid || !$teacherid) {
            return;
        }

        $data->stageid = $this->get_new_parentid('stage');
        $data->studentid = $studentid;
        $data->teacherid = $teacherid;

        if ($DB->record_exists('stage_entry_teacher', (array) $data)) {
            return;
        }

        $DB->insert_record('stage_entry_teacher', $data);
    }

    /**
     * Restaure un stage.
     *
     * Les dates de stage ne sont pas décalées par apply_date_offset() : elles constatent une
     * période réellement effectuée, et non une échéance du cours à replacer dans le calendrier.
     *
     * @param array $data
     * @return int|void SKIP_ALL_CHILDREN si la saisie est écartée.
     */
    protected function process_stage_entry($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $userid = $this->get_mappingid('user', $data->userid);
        if (!$userid) {
            // Sans étudiant, la saisie n'a plus de sens : elle est écartée, et avec elle ses
            // plages, jours, détail de convention et réponses, qui seraient sinon rattachés à la
            // saisie précédente (get_new_parentid() ne retient que la dernière correspondance).
            return self::SKIP_ALL_CHILDREN;
        }

        $data->stageid = $this->get_new_parentid('stage');
        $data->userid = $userid;
        $data->themeid = $this->get_mappingid('stage_theme', $data->themeid) ?: 0;
        $data->conventiontemplateid = empty($data->conventiontemplateid) ? null
            : ($this->get_mappingid('stage_convention_template', $data->conventiontemplateid) ?: null);

        foreach (
            ['teacherid', 'deveuserid', 'conventionrejectedby', 'conventionteachervalidatedby',
                'conventioneditedby', 'conventionsignedby', 'cancelledby'] as $field
        ) {
            $data->$field = empty($data->$field) ? null : ($this->get_mappingid('user', $data->$field) ?: null);
        }

        $newitemid = $DB->insert_record('stage_entry', $data);
        $this->set_mapping('stage_entry', $oldid, $newitemid, true);
    }

    /**
     * Restaure une plage de dates d'une saisie.
     *
     * @param array $data
     */
    protected function process_stage_entry_period($data) {
        global $DB;

        $data = (object) $data;
        unset($data->id);
        $data->entryid = $this->get_new_parentid('stage_entry');

        $DB->insert_record('stage_entry_period', $data);
    }

    /**
     * Restaure un jour de stage effectif.
     *
     * @param array $data
     */
    protected function process_stage_entry_workday($data) {
        global $DB;

        $data = (object) $data;
        unset($data->id);
        $data->entryid = $this->get_new_parentid('stage_entry');

        $DB->insert_record('stage_entry_workday', $data);
    }

    /**
     * Restaure le complément de convention d'une saisie.
     *
     * @param array $data
     */
    protected function process_stage_convention_detail($data) {
        global $DB;

        $data = (object) $data;
        unset($data->id);
        $data->entryid = $this->get_new_parentid('stage_entry');
        $data->referentteacherid = empty($data->referentteacherid) ? null
            : ($this->get_mappingid('user', $data->referentteacherid) ?: null);

        $DB->insert_record('stage_convention_detail', $data);
    }

    /**
     * Restaure une réponse à une question d'évaluation.
     *
     * @param array $data
     */
    protected function process_stage_answer($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $questionid = $this->get_mappingid('stage_question', $data->questionid);
        if (!$questionid) {
            return;
        }

        $data->entryid = $this->get_new_parentid('stage_entry');
        $data->questionid = $questionid;

        $newitemid = $DB->insert_record('stage_answer', $data);
        $this->set_mapping('stage_answer', $oldid, $newitemid);
    }

    /**
     * Rattache les fichiers une fois toutes les correspondances établies.
     */
    protected function after_execute() {
        // Zones sans itemid, rattachées au contexte du module.
        $this->add_related_files('mod_stage', 'intro', null);
        $this->add_related_files('mod_stage', 'conventionlogoleft', null);
        $this->add_related_files('mod_stage', 'conventionlogoright', null);

        // Gabarits de convention : un PDF par gabarit.
        $this->add_related_files('mod_stage', 'conventiontemplate', 'stage_convention_template');

        if ($this->get_setting_value('userinfo')) {
            $this->add_related_files('mod_stage', 'signedconvention', 'stage_entry');
            $this->add_related_files('mod_stage', STAGE_REPORT_FILEAREA, 'stage_entry');
        }
    }
}
