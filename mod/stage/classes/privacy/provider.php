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
 * Privacy provider for mod_stage.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_stage\privacy;

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

/**
 * Fournisseur de données personnelles de mod_stage.
 *
 * Un utilisateur apparaît ici sous deux rôles bien distincts, qui n'appellent pas le même
 * traitement à la suppression :
 *
 * - **l'étudiant**, propriétaire de la saisie (stage_entry.userid) : toutes ses données de stage
 *   lui sont rattachées, et la suppression les efface intégralement (stages, auto-évaluation,
 *   évaluations enseignant et maître de stage, détail de convention, périodes, jours ouvrés,
 *   réponses aux questionnaires, convention signée et rapport de stage) ;
 * - **le personnel** (enseignant référent, responsable de thématique, DEVE), simplement *cité*
 *   dans la saisie d'un étudiant : sa suppression ne doit pas emporter le stage de l'étudiant,
 *   qui ne lui appartient pas. Ses références sont donc dissociées et les textes dont il est
 *   l'auteur effacés, la saisie de l'étudiant restant intacte.
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    /**
     * Décrit les données personnelles stockées par le plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('stage_entry', [
            'userid' => 'privacy:metadata:stage_entry:userid',
            'themeid' => 'privacy:metadata:stage_entry:themeid',
            'studyyear' => 'privacy:metadata:stage_entry:studyyear',
            'structure' => 'privacy:metadata:stage_entry:structure',
            'country' => 'privacy:metadata:stage_entry:country',
            'datestart' => 'privacy:metadata:stage_entry:datestart',
            'dateend' => 'privacy:metadata:stage_entry:dateend',
            'status' => 'privacy:metadata:stage_entry:status',
            'studentselfeval' => 'privacy:metadata:stage_entry:studentselfeval',
            'teacherid' => 'privacy:metadata:stage_entry:teacherid',
            'teachereval' => 'privacy:metadata:stage_entry:teachereval',
            'tutoreval' => 'privacy:metadata:stage_entry:tutoreval',
            'deveuserid' => 'privacy:metadata:stage_entry:deveuserid',
            'devecomment' => 'privacy:metadata:stage_entry:devecomment',
            'conventionstatus' => 'privacy:metadata:stage_entry:conventionstatus',
            'cancelcomment' => 'privacy:metadata:stage_entry:cancelcomment',
            'timecreated' => 'privacy:metadata:stage_entry:timecreated',
        ], 'privacy:metadata:stage_entry');

        $collection->add_database_table('stage_convention_detail', [
            'studentbirthdate' => 'privacy:metadata:stage_convention_detail:studentbirthdate',
            'studentaddress' => 'privacy:metadata:stage_convention_detail:studentaddress',
            'studentphone' => 'privacy:metadata:stage_convention_detail:studentphone',
            'referentteacherid' => 'privacy:metadata:stage_convention_detail:referentteacherid',
            'tutorname' => 'privacy:metadata:stage_convention_detail:tutorname',
            'tutorfunction' => 'privacy:metadata:stage_convention_detail:tutorfunction',
            'tutorphone' => 'privacy:metadata:stage_convention_detail:tutorphone',
            'tutoremail' => 'privacy:metadata:stage_convention_detail:tutoremail',
            'gratificationamount' => 'privacy:metadata:stage_convention_detail:gratificationamount',
        ], 'privacy:metadata:stage_convention_detail');

        $collection->add_database_table('stage_entry_period', [
            'datestart' => 'privacy:metadata:stage_entry_period:datestart',
            'dateend' => 'privacy:metadata:stage_entry_period:dateend',
        ], 'privacy:metadata:stage_entry_period');

        $collection->add_database_table('stage_entry_workday', [
            'workdate' => 'privacy:metadata:stage_entry_workday:workdate',
        ], 'privacy:metadata:stage_entry_workday');

        $collection->add_database_table('stage_answer', [
            'questionid' => 'privacy:metadata:stage_answer:questionid',
            'answertext' => 'privacy:metadata:stage_answer:answertext',
        ], 'privacy:metadata:stage_answer');

        $collection->add_database_table('stage_entry_teacher', [
            'studentid' => 'privacy:metadata:stage_entry_teacher:studentid',
            'teacherid' => 'privacy:metadata:stage_entry_teacher:teacherid',
        ], 'privacy:metadata:stage_entry_teacher');

        $collection->add_database_table('stage_theme_teacher', [
            'themeid' => 'privacy:metadata:stage_theme_teacher:themeid',
            'teacherid' => 'privacy:metadata:stage_theme_teacher:teacherid',
        ], 'privacy:metadata:stage_theme_teacher');

        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:core_files');

        return $collection;
    }

    /**
     * Contextes dans lesquels l'utilisateur donné a des données.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $params = [
            'modname' => 'stage',
            'contextlevel' => CONTEXT_MODULE,
            'studentid' => $userid,
            'teacherid' => $userid,
            'deveuserid' => $userid,
            'validatedby' => $userid,
            'editedby' => $userid,
            'signedby' => $userid,
            'rejectedby' => $userid,
            'cancelledby' => $userid,
        ];

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {stage} s ON s.id = cm.instance
                  JOIN {stage_entry} e ON e.stageid = s.id
                 WHERE e.userid = :studentid
                    OR e.teacherid = :teacherid
                    OR e.deveuserid = :deveuserid
                    OR e.conventionteachervalidatedby = :validatedby
                    OR e.conventioneditedby = :editedby
                    OR e.conventionsignedby = :signedby
                    OR e.conventionrejectedby = :rejectedby
                    OR e.cancelledby = :cancelledby";
        $contextlist->add_from_sql($sql, $params);

        // Attributions de référent et responsabilités de thématique : elles existent même sans
        // aucune saisie de stage, et doivent donc être cherchées séparément.
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {stage} s ON s.id = cm.instance
                  JOIN {stage_entry_teacher} et ON et.stageid = s.id
                 WHERE et.studentid = :studentid OR et.teacherid = :teacherid";
        $contextlist->add_from_sql($sql, [
            'modname' => 'stage',
            'contextlevel' => CONTEXT_MODULE,
            'studentid' => $userid,
            'teacherid' => $userid,
        ]);

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {stage} s ON s.id = cm.instance
                  JOIN {stage_theme} t ON t.stageid = s.id
                  JOIN {stage_theme_teacher} tt ON tt.themeid = t.id
                 WHERE tt.teacherid = :teacherid";
        $contextlist->add_from_sql($sql, [
            'modname' => 'stage',
            'contextlevel' => CONTEXT_MODULE,
            'teacherid' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Utilisateurs ayant des données dans le contexte donné.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }

        $params = ['instanceid' => $context->instanceid, 'modname' => 'stage'];

        foreach (['userid', 'teacherid', 'deveuserid', 'conventionteachervalidatedby', 'conventioneditedby',
                'conventionsignedby', 'conventionrejectedby', 'cancelledby'] as $field) {
            $userlist->add_from_sql($field, "SELECT e.$field
                                               FROM {course_modules} cm
                                               JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                                               JOIN {stage} s ON s.id = cm.instance
                                               JOIN {stage_entry} e ON e.stageid = s.id
                                              WHERE cm.id = :instanceid", $params);
        }

        foreach (['studentid', 'teacherid'] as $field) {
            $userlist->add_from_sql($field, "SELECT et.$field
                                               FROM {course_modules} cm
                                               JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                                               JOIN {stage} s ON s.id = cm.instance
                                               JOIN {stage_entry_teacher} et ON et.stageid = s.id
                                              WHERE cm.id = :instanceid", $params);
        }

        $userlist->add_from_sql('teacherid', "SELECT tt.teacherid
                                                FROM {course_modules} cm
                                                JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                                                JOIN {stage} s ON s.id = cm.instance
                                                JOIN {stage_theme} t ON t.stageid = s.id
                                                JOIN {stage_theme_teacher} tt ON tt.themeid = t.id
                                               WHERE cm.id = :instanceid", $params);
    }

    /**
     * Exporte les données de l'utilisateur pour les contextes approuvés.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('stage', $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }

            $entries = $DB->get_records('stage_entry', ['stageid' => $cm->instance, 'userid' => $user->id],
                'datestart ASC');
            foreach ($entries as $entry) {
                $subcontext = [get_string('mystages', 'mod_stage'), (string) $entry->id];

                $data = (object) [
                    'studyyear' => $entry->studyyear,
                    'structure' => $entry->structure,
                    'country' => $entry->country,
                    'datestart' => $entry->datestart ? transform::datetime($entry->datestart) : null,
                    'dateend' => $entry->dateend ? transform::datetime($entry->dateend) : null,
                    'declaredduration' => $entry->declaredduration,
                    'retainedduration' => $entry->retainedduration,
                    'status' => stage_status_label($entry->status),
                    'studentselfeval' => $entry->studentselfeval,
                    'teachereval' => $entry->teachereval,
                    'tutoreval' => $entry->tutoreval,
                    'devecomment' => $entry->devecomment,
                    'conventionstatus' => stage_convention_status_label($entry->conventionstatus),
                    'cancelcomment' => $entry->cancelcomment,
                    'timecreated' => transform::datetime($entry->timecreated),
                ];
                writer::with_context($context)->export_data($subcontext, $data);

                $detail = $DB->get_record('stage_convention_detail', ['entryid' => $entry->id]);
                if ($detail) {
                    unset($detail->id, $detail->entryid);
                    writer::with_context($context)->export_data(
                        array_merge($subcontext, [get_string('conventiondetails', 'mod_stage')]), $detail);
                }

                $periods = $DB->get_records('stage_entry_period', ['entryid' => $entry->id], 'datestart ASC');
                if ($periods) {
                    $rows = array_map(function($period) {
                        return (object) [
                            'datestart' => transform::datetime($period->datestart),
                            'dateend' => transform::datetime($period->dateend),
                        ];
                    }, array_values($periods));
                    writer::with_context($context)->export_data(
                        array_merge($subcontext, [get_string('periods', 'mod_stage')]), (object) ['periods' => $rows]);
                }

                $answers = $DB->get_records('stage_answer', ['entryid' => $entry->id]);
                if ($answers) {
                    $rows = [];
                    foreach ($answers as $answer) {
                        $question = $DB->get_record('stage_question', ['id' => $answer->questionid]);
                        $rows[] = (object) [
                            'question' => $question ? $question->name : '',
                            'answer' => $answer->answertext,
                        ];
                    }
                    writer::with_context($context)->export_data(
                        array_merge($subcontext, [get_string('answers', 'mod_stage')]), (object) ['answers' => $rows]);
                }

                foreach (['signedconvention', STAGE_REPORT_FILEAREA] as $filearea) {
                    writer::with_context($context)->export_area_files($subcontext, 'mod_stage', $filearea, $entry->id);
                }
            }

            // Stages d'autres étudiants dans lesquels l'utilisateur intervient comme personnel :
            // les données exportées sont les siennes (ce qu'il a rédigé, ce qu'il a validé), pas
            // le dossier de l'étudiant, qui relève de l'export de ce dernier.
            self::export_staff_data($context, $cm, $user->id);
        }
    }

    /**
     * Exporte, pour un membre du personnel, sa propre intervention dans les stages d'autrui :
     * l'appréciation qu'il a rédigée et les étapes du circuit qu'il a franchies.
     *
     * @param context_module $context
     * @param stdClass $cm
     * @param int $userid
     * @return void
     */
    protected static function export_staff_data(context_module $context, $cm, int $userid) {
        global $DB;

        $sql = "SELECT *
                  FROM {stage_entry}
                 WHERE stageid = :stageid
                   AND userid <> :self
                   AND (teacherid = :teacherid
                        OR deveuserid = :deveuserid
                        OR conventionteachervalidatedby = :validatedby
                        OR conventioneditedby = :editedby
                        OR conventionsignedby = :signedby
                        OR conventionrejectedby = :rejectedby
                        OR cancelledby = :cancelledby)
              ORDER BY datestart ASC";
        $entries = $DB->get_records_sql($sql, [
            'stageid' => $cm->instance,
            'self' => $userid,
            'teacherid' => $userid,
            'deveuserid' => $userid,
            'validatedby' => $userid,
            'editedby' => $userid,
            'signedby' => $userid,
            'rejectedby' => $userid,
            'cancelledby' => $userid,
        ]);

        foreach ($entries as $entry) {
            // L'étudiant a pu être supprimé entre-temps : get_user() renvoie alors false, que
            // fullname() n'accepte pas.
            $student = \core_user::get_user($entry->userid);
            $data = (object) [
                'student' => $student ? fullname($student) : '',
                'datestart' => $entry->datestart ? transform::datetime($entry->datestart) : null,
                'dateend' => $entry->dateend ? transform::datetime($entry->dateend) : null,
                'issupervisingteacher' => transform::yesno((int) $entry->teacherid === $userid),
                'teachereval' => ((int) $entry->teacherid === $userid) ? $entry->teachereval : null,
                'devecomment' => ((int) $entry->deveuserid === $userid) ? $entry->devecomment : null,
                'conventionrejectcomment' => ((int) $entry->conventionrejectedby === $userid)
                    ? $entry->conventionrejectcomment : null,
                'cancelcomment' => ((int) $entry->cancelledby === $userid) ? $entry->cancelcomment : null,
            ];
            writer::with_context($context)->export_data(
                [get_string('supervisedstages', 'mod_stage'), (string) $entry->id], $data);
        }
    }

    /**
     * Supprime les données de tous les utilisateurs du contexte donné.
     *
     * @param context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('stage', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        stage_delete_entries($DB->get_fieldset_select('stage_entry', 'id', 'stageid = ?', [$cm->instance]), $context);
        $DB->delete_records('stage_entry_teacher', ['stageid' => $cm->instance]);

        $themeids = $DB->get_fieldset_select('stage_theme', 'id', 'stageid = ?', [$cm->instance]);
        if ($themeids) {
            [$insql, $inparams] = $DB->get_in_or_equal($themeids);
            $DB->delete_records_select('stage_theme_teacher', "themeid $insql", $inparams);
        }
    }

    /**
     * Supprime les données d'un utilisateur dans les contextes approuvés.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_module) {
                self::delete_data_for_users_in_context($context, [$userid]);
            }
        }
    }

    /**
     * Supprime les données des utilisateurs listés dans un contexte.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if ($context instanceof context_module) {
            self::delete_data_for_users_in_context($context, $userlist->get_userids());
        }
    }

    /**
     * Traitement commun aux deux suppressions ciblées.
     *
     * Les utilisateurs sont traités selon le rôle sous lequel ils apparaissent, et un même
     * utilisateur peut relever des deux : ses propres stages sont supprimés en tant qu'étudiant,
     * et il est par ailleurs dissocié des stages d'autrui où il n'est que cité.
     *
     * @param context_module $context
     * @param int[] $userids
     * @return void
     */
    protected static function delete_data_for_users_in_context(context_module $context, array $userids) {
        global $DB;

        $userids = array_values(array_filter(array_unique(array_map('intval', $userids))));
        if (empty($userids)) {
            return;
        }
        $cm = get_coursemodule_from_id('stage', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        // Un paramètre nommé ne peut pas apparaître deux fois dans une même requête : la clause
        // sur stage_entry_teacher, qui teste la même liste sur deux colonnes, a besoin d'un second
        // jeu de paramètres portant un autre préfixe.
        [$usersql2, $userparams2] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u2');
        $params = array_merge(['stageid' => $cm->instance], $userparams);

        // 1. En tant qu'étudiant : suppression intégrale des stages et de tout ce qui en dépend
        // (évaluations, détail de convention, périodes, jours ouvrés, réponses, fichiers).
        $entryids = $DB->get_fieldset_select('stage_entry', 'id',
            "stageid = :stageid AND userid $usersql", $params);
        stage_delete_entries($entryids, $context);

        // Attributions de référent, que l'utilisateur y figure comme étudiant ou comme référent.
        $DB->delete_records_select('stage_entry_teacher',
            "stageid = :stageid AND (studentid $usersql OR teacherid $usersql2)",
            array_merge(['stageid' => $cm->instance], $userparams, $userparams2));

        // 2. En tant que personnel cité dans le stage d'un autre étudiant : le stage appartient à
        // cet étudiant et doit survivre, seules les références à l'utilisateur supprimé sautent.
        // Les colonnes concernées sont toutes nullable, la dissociation est donc propre.
        $staffcolumns = [
            'teacherid' => ['teachereval', 'teachertime'],
            'deveuserid' => ['devecomment', 'devetime'],
            'conventionteachervalidatedby' => ['conventionteachervalidatetime'],
            'conventioneditedby' => ['conventionedittime'],
            'conventionsignedby' => ['conventionsigntime'],
            'conventionrejectedby' => ['conventionrejectcomment', 'conventionrejecttime'],
            'cancelledby' => ['cancelcomment', 'canceltime'],
        ];
        foreach ($staffcolumns as $column => $authored) {
            $affected = $DB->get_fieldset_select('stage_entry', 'id',
                "stageid = :stageid AND $column $usersql", $params);
            foreach ($affected as $entryid) {
                $update = (object) ['id' => $entryid, $column => null, 'timemodified' => time()];
                // Les textes rédigés par cette personne partent avec la référence : les conserver
                // sous une référence vide reviendrait à garder son appréciation nominative.
                foreach ($authored as $field) {
                    $update->$field = null;
                }
                $DB->update_record('stage_entry', $update);
            }
        }

        // Référent nommé dans le détail de convention : même raisonnement.
        $sql = "UPDATE {stage_convention_detail}
                   SET referentteacherid = NULL
                 WHERE referentteacherid $usersql
                   AND entryid IN (SELECT id FROM {stage_entry} WHERE stageid = :stageid)";
        $DB->execute($sql, $params);

        // Responsabilité de thématique : un simple rattachement, supprimé.
        $themeids = $DB->get_fieldset_select('stage_theme', 'id', 'stageid = ?', [$cm->instance]);
        if ($themeids) {
            [$themesql, $themeparams] = $DB->get_in_or_equal($themeids, SQL_PARAMS_NAMED, 'th');
            $DB->delete_records_select('stage_theme_teacher',
                "themeid $themesql AND teacherid $usersql", array_merge($themeparams, $userparams));
        }
    }
}
