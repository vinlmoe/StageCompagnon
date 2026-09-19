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
 * Étape de sauvegarde de la structure d'une instance de mod_stage.
 *
 * @package   mod_stage
 * @category  backup
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Décrit l'arbre XML (stage.xml) d'une instance de mod_stage, ses annotations d'identifiants et
 * ses zones de fichiers.
 *
 * Le paramétrage de l'activité (thématiques et leurs durées, leurs objectifs — documents et
 * check-list —, exigences annuelles, gabarits de convention, questions d'évaluation, modèles de
 * courriels) est toujours sauvegardé ; les stages eux-mêmes et tout ce qui s'y rattache ne le sont
 * que si les données utilisateur sont demandées.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_stage_activity_structure_step extends backup_activity_structure_step {
    /**
     * Construit la structure sauvegardée.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {

        $userinfo = $this->get_setting_value('userinfo');

        // Instance : « course » et « id » ne sont pas sauvegardés, la restauration les recalcule.
        $stage = new backup_nested_element('stage', ['id'], [
            'name', 'intro', 'introformat',
            'establishmentname', 'establishmentaddress', 'establishmentrepresentative',
            'establishmentrepresentativetitle', 'establishmentphone', 'establishmentemail',
            'establishmentsignatory', 'conventionrequireteachervalidation', 'tutorevaluationenabled',
            'currentstudyyear', 'requiredabroaddays', 'abroadbeforeyear', 'abroadrule',
            'timecreated', 'timemodified',
        ]);

        $themes = new backup_nested_element('themes');
        $theme = new backup_nested_element('theme', ['id'], [
            'name', 'description', 'mandatory', 'requiredduration', 'minstudyyear', 'maxstudyyear',
            'sortorder', 'visible', 'tutorevaluationenabled', 'reportmode',
            'timecreated', 'timemodified',
        ]);

        $themeteachers = new backup_nested_element('themeteachers');
        $themeteacher = new backup_nested_element('themeteacher', ['id'], ['teacherid', 'timecreated']);

        $themechecklists = new backup_nested_element('themechecklists');
        $themechecklist = new backup_nested_element('themechecklist', ['id'], [
            'name', 'description', 'sortorder', 'timecreated', 'timemodified',
        ]);

        $themedurations = new backup_nested_element('themedurations');
        $themeduration = new backup_nested_element('themeduration', ['id'], [
            'studyyear', 'requiredduration', 'timecreated', 'timemodified',
        ]);

        $yearrequirements = new backup_nested_element('yearrequirements');
        $yearrequirement = new backup_nested_element('yearrequirement', ['id'], [
            'studyyear', 'requiredduration', 'timecreated', 'timemodified',
        ]);

        $conventiontemplates = new backup_nested_element('conventiontemplates');
        $conventiontemplate = new backup_nested_element('conventiontemplate', ['id'], [
            'name', 'lang', 'timecreated', 'timemodified',
        ]);

        $questions = new backup_nested_element('questions');
        $question = new backup_nested_element('question', ['id'], [
            'themeid', 'evaltype', 'qtype', 'name', 'nameen', 'options', 'optionsen',
            'required', 'sortorder', 'timecreated', 'timemodified',
        ]);

        $questionthemes = new backup_nested_element('questionthemes');
        $questiontheme = new backup_nested_element('questiontheme', ['id'], ['themeid', 'timecreated']);

        $emailtemplates = new backup_nested_element('emailtemplates');
        $emailtemplate = new backup_nested_element('emailtemplate', ['id'], [
            'emailkey', 'subject', 'body', 'timemodified',
        ]);

        $entryteachers = new backup_nested_element('entryteachers');
        $entryteacher = new backup_nested_element('entryteacher', ['id'], ['studentid', 'teacherid']);

        $entries = new backup_nested_element('entries');
        // « tutortoken » n'est volontairement pas sauvegardé : c'est un secret d'accès sans compte
        // (tutor_eval.php) soumis à un index unique, qu'une restauration sur le même site
        // dupliquerait. La copie restaurée en régénère un à la demande (stage_get_tutor_eval_url()),
        // les réponses déjà données (tutoreval, tutortime) étant conservées telles quelles.
        $entry = new backup_nested_element('entry', ['id'], [
            'themeid', 'studyyear', 'userid', 'structure', 'abroad', 'country',
            'datestart', 'dateend', 'declaredduration', 'retainedduration', 'status',
            'studentselfeval', 'teacherid', 'teachereval', 'teachertime',
            'tutoreval', 'tutortime', 'tutorbypassed',
            'deveuserid', 'devecomment', 'devetime',
            'conventiontemplateid', 'conventionstatus',
            'conventionrejectedby', 'conventionrejecttime', 'conventionrejectcomment',
            'conventionrequesttime', 'conventionremindertime',
            'conventionteachervalidatedby', 'conventionteachervalidatetime',
            'conventioneditedby', 'conventionedittime',
            'conventionsignedby', 'conventionsigntime',
            'cancelledby', 'canceltime', 'cancelcomment',
            'timecreated', 'timemodified',
        ]);

        $periods = new backup_nested_element('periods');
        $period = new backup_nested_element('period', ['id'], ['datestart', 'dateend', 'timecreated']);

        $workdays = new backup_nested_element('workdays');
        $workday = new backup_nested_element('workday', ['id'], ['workdate', 'timecreated']);

        $conventiondetails = new backup_nested_element('conventiondetails');
        $conventiondetail = new backup_nested_element('conventiondetail', ['id'], [
            'referentteacherid', 'yearsituation', 'stagetype', 'studentbirthdate',
            'studentaddress', 'studentphone', 'hostaddress', 'hostrepresentative',
            'hostrepresentativetitle', 'hostservice', 'hostphone', 'hostemail', 'hostlocation',
            'tutorname', 'tutorfunction', 'tutorphone', 'tutoremail',
            'nightpresence', 'sundaypresence', 'holidaypresence', 'homebased', 'othermodality',
            'hasleave', 'leavedays', 'leavemodalities', 'gratificationamount',
            'paperrequestedbystudent', 'paperrequestedbyteacher', 'timecreated', 'timemodified',
        ]);

        $answers = new backup_nested_element('answers');
        $answer = new backup_nested_element('answer', ['id'], [
            'questionid', 'answertext', 'timecreated', 'timemodified',
        ]);

        $entrychecklists = new backup_nested_element('entrychecklists');
        $entrychecklist = new backup_nested_element('entrychecklist', ['id'], [
            'itemid', 'checked', 'explanation', 'timecreated', 'timemodified',
        ]);

        // Arbre : les thématiques précèdent les questions puis les stages, de sorte que la
        // restauration dispose déjà des correspondances d'identifiants dont ils dépendent.
        $stage->add_child($themes);
        $themes->add_child($theme);
        $theme->add_child($themeteachers);
        $themeteachers->add_child($themeteacher);
        $theme->add_child($themechecklists);
        $themechecklists->add_child($themechecklist);
        $theme->add_child($themedurations);
        $themedurations->add_child($themeduration);

        $stage->add_child($yearrequirements);
        $yearrequirements->add_child($yearrequirement);

        $stage->add_child($conventiontemplates);
        $conventiontemplates->add_child($conventiontemplate);

        $stage->add_child($questions);
        $questions->add_child($question);
        $question->add_child($questionthemes);
        $questionthemes->add_child($questiontheme);

        $stage->add_child($emailtemplates);
        $emailtemplates->add_child($emailtemplate);

        $stage->add_child($entryteachers);
        $entryteachers->add_child($entryteacher);

        $stage->add_child($entries);
        $entries->add_child($entry);
        $entry->add_child($periods);
        $periods->add_child($period);
        $entry->add_child($workdays);
        $workdays->add_child($workday);
        $entry->add_child($conventiondetails);
        $conventiondetails->add_child($conventiondetail);
        $entry->add_child($answers);
        $answers->add_child($answer);
        $entry->add_child($entrychecklists);
        $entrychecklists->add_child($entrychecklist);

        // Sources.
        $stage->set_source_table('stage', ['id' => backup::VAR_ACTIVITYID]);

        $theme->set_source_table('stage_theme', ['stageid' => backup::VAR_PARENTID], 'sortorder, id');
        $themechecklist->set_source_table(
            'stage_theme_checklist',
            ['themeid' => backup::VAR_PARENTID],
            'sortorder, id'
        );
        $themeduration->set_source_table('stage_theme_duration', ['themeid' => backup::VAR_PARENTID], 'studyyear');
        $yearrequirement->set_source_table('stage_year_requirement', ['stageid' => backup::VAR_PARENTID], 'studyyear');
        $conventiontemplate->set_source_table('stage_convention_template', ['stageid' => backup::VAR_PARENTID], 'id');
        $question->set_source_table('stage_question', ['stageid' => backup::VAR_PARENTID], 'sortorder, id');
        $questiontheme->set_source_table('stage_question_theme', ['questionid' => backup::VAR_PARENTID], 'id');
        $emailtemplate->set_source_table('stage_email_template', ['stageid' => backup::VAR_PARENTID], 'emailkey');

        // Les enseignants responsables d'une thématique relèvent du paramétrage de l'activité et
        // non des données d'un étudiant : ils sont sauvegardés dans tous les cas, et la
        // restauration ignore ceux dont le compte n'est pas présent dans l'archive.
        $themeteacher->set_source_table('stage_theme_teacher', ['themeid' => backup::VAR_PARENTID], 'id');

        if ($userinfo) {
            $entryteacher->set_source_table('stage_entry_teacher', ['stageid' => backup::VAR_PARENTID], 'id');
            $entry->set_source_table('stage_entry', ['stageid' => backup::VAR_PARENTID], 'id');
            $period->set_source_table('stage_entry_period', ['entryid' => backup::VAR_PARENTID], 'datestart, id');
            $workday->set_source_table('stage_entry_workday', ['entryid' => backup::VAR_PARENTID], 'workdate');
            $conventiondetail->set_source_table('stage_convention_detail', ['entryid' => backup::VAR_PARENTID], 'id');
            $answer->set_source_table('stage_answer', ['entryid' => backup::VAR_PARENTID], 'id');
            $entrychecklist->set_source_table('stage_entry_checklist', ['entryid' => backup::VAR_PARENTID], 'id');
        }

        // Annotations d'identifiants.
        $themeteacher->annotate_ids('user', 'teacherid');
        $entryteacher->annotate_ids('user', 'studentid');
        $entryteacher->annotate_ids('user', 'teacherid');
        $entry->annotate_ids('user', 'userid');
        $entry->annotate_ids('user', 'teacherid');
        $entry->annotate_ids('user', 'deveuserid');
        $entry->annotate_ids('user', 'conventionrejectedby');
        $entry->annotate_ids('user', 'conventionteachervalidatedby');
        $entry->annotate_ids('user', 'conventioneditedby');
        $entry->annotate_ids('user', 'conventionsignedby');
        $entry->annotate_ids('user', 'cancelledby');
        $conventiondetail->annotate_ids('user', 'referentteacherid');

        // Zones de fichiers. Les logos de convention n'ont pas d'itemid (toujours 0).
        $stage->annotate_files('mod_stage', 'intro', null);
        $stage->annotate_files('mod_stage', 'conventionlogoleft', null);
        $stage->annotate_files('mod_stage', 'conventionlogoright', null);
        $conventiontemplate->annotate_files('mod_stage', 'conventiontemplate', 'id');
        // Documents d'objectifs : un ou plusieurs fichiers par thématique, itemid = id de la
        // thématique. Ils relèvent du paramétrage de l'activité, comme les gabarits : sauvegardés
        // même sans les données utilisateur.
        $theme->annotate_files('mod_stage', STAGE_THEME_OBJECTIVE_FILEAREA, 'id');
        if ($userinfo) {
            $entry->annotate_files('mod_stage', 'signedconvention', 'id');
            $entry->annotate_files('mod_stage', STAGE_REPORT_FILEAREA, 'id');
        }

        return $this->prepare_activity_structure($stage);
    }
}
