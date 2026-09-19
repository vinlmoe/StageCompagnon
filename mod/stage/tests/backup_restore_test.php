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

namespace mod_stage;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Tests de la sauvegarde et de la restauration de cours (backup/moodle2/) pour mod_stage.
 *
 * L'activité mêle du paramétrage (thématiques, questions, gabarits de convention) et des données
 * d'étudiants reliées entre elles par identifiants (réponses vers questions, saisies vers
 * thématiques et gabarits) : ces tests vérifient que la copie restaurée pointe bien vers ses
 * propres lignes et non vers celles de l'activité d'origine.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_stage_activity_structure_step
 * @covers     \restore_stage_activity_structure_step
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * Sauvegarde un cours puis le restaure dans un nouveau cours, données utilisateur comprises.
     *
     * @param \stdClass $course
     * @return \stdClass Le cours restauré.
     */
    protected function backup_and_restore(\stdClass $course): \stdClass {
        global $CFG, $USER;

        // En mode général, le plan compresse la sauvegarde en .mbz puis efface son dossier de
        // travail, alors que la restauration lit ce dossier. Le conserver évite d'avoir à
        // réextraire l'archive sous le même identifiant.
        $CFG->keeptempdirectoriesonbackup = true;
        $CFG->backup_file_logger_level = \backup::LOG_NONE;

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        try {
            $bc->execute_plan();
        } catch (\Throwable $e) {
            $this->drop_leftover_temp_tables();
            throw $e;
        } finally {
            $bc->destroy();
        }

        $newcourseid = \restore_dbops::create_new_course(
            $course->fullname,
            $course->shortname . '_copie',
            $course->category
        );
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        try {
            $rc->execute_precheck();
            $rc->execute_plan();
        } catch (\Throwable $e) {
            $this->drop_leftover_temp_tables();
            throw $e;
        } finally {
            $rc->destroy();
        }

        return get_course($newcourseid);
    }

    /**
     * Supprime les tables temporaires qu'un plan de sauvegarde ou de restauration interrompu
     * aurait laissées derrière lui.
     *
     * Un plan mené à son terme s'en charge lui-même — d'où le nettoyage sur le seul chemin
     * d'échec, et l'absence de bruit si les tables ont déjà disparu. Sans cela, le test suivant
     * du fichier tombe sur une erreur DDL sans rapport, qui masque la vraie cause.
     */
    protected function drop_leftover_temp_tables(): void {
        global $DB;

        $dbman = $DB->get_manager();
        foreach (['backup_ids_temp', 'backup_files_temp'] as $name) {
            try {
                $dbman->drop_table(new \xmldb_table($name));
            } catch (\moodle_exception $e) {
                continue;
            }
        }
    }

    /**
     * Renvoie l'unique instance d'un module dans un cours.
     *
     * @param int $courseid
     * @param string $modname
     * @return \cm_info
     */
    protected function single_instance(int $courseid, string $modname): \cm_info {
        $instances = get_fast_modinfo($courseid)->get_instances_of($modname);
        $this->assertCount(1, $instances);
        return reset($instances);
    }

    /**
     * Une activité complète (paramétrage, saisies, fichiers) survit à l'aller-retour, et la copie
     * est refermée sur elle-même : ses réponses désignent ses propres questions, ses saisies ses
     * propres thématiques et gabarits.
     */
    public function test_course_backup_restore_keeps_configuration_and_entries(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');

        $stage = $generator->create_module('stage', [
            'course' => $course->id,
            'name' => 'Stages A3',
            'establishmentname' => 'VetAgro Sup',
            'currentstudyyear' => 3,
        ]);
        $cm = get_coursemodule_from_instance('stage', $stage->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        /** @var \mod_stage_generator $stagegen */
        $stagegen = $generator->get_plugin_generator('mod_stage');
        $theme = $stagegen->create_theme($stage, ['name' => 'Animaux de compagnie']);
        $stagegen->assign_teacher($stage, $student->id, $teacher->id);

        $now = time();
        $DB->insert_record('stage_theme_teacher', (object) [
            'themeid' => $theme->id,
            'teacherid' => $teacher->id,
            'timecreated' => $now,
        ]);
        $DB->insert_record('stage_year_requirement', (object) [
            'stageid' => $stage->id,
            'studyyear' => 3,
            'requiredduration' => 20,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $templateid = $DB->insert_record('stage_convention_template', (object) [
            'stageid' => $stage->id,
            'name' => 'Convention type',
            'lang' => 'fr',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $questionid = $DB->insert_record('stage_question', (object) [
            'stageid' => $stage->id,
            'themeid' => $theme->id,
            'evaltype' => 'student',
            'qtype' => 'text',
            'name' => 'Qu\'avez-vous appris ?',
            'required' => 1,
            'sortorder' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $checklistitem = $stagegen->create_checklist_item($theme, ['name' => 'Consultation en autonomie']);

        $entry = $stagegen->create_entry($stage, $student->id, $theme);
        $DB->set_field('stage_entry', 'conventiontemplateid', $templateid, ['id' => $entry->id]);
        $DB->set_field('stage_entry', 'tutortoken', bin2hex(random_bytes(32)), ['id' => $entry->id]);
        $DB->insert_record('stage_answer', (object) [
            'entryid' => $entry->id,
            'questionid' => $questionid,
            'answertext' => 'Beaucoup de choses.',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $DB->insert_record('stage_entry_checklist', (object) [
            'entryid' => $entry->id,
            'itemid' => $checklistitem->id,
            'checked' => 0,
            'explanation' => 'Structure trop petite.',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $context->id, 'component' => 'mod_stage',
            'filearea' => 'conventiontemplate', 'itemid' => $templateid,
            'filepath' => '/', 'filename' => 'modele.pdf',
        ], 'gabarit');
        $fs->create_file_from_string([
            'contextid' => $context->id, 'component' => 'mod_stage',
            'filearea' => STAGE_REPORT_FILEAREA, 'itemid' => $entry->id,
            'filepath' => '/', 'filename' => 'rapport.pdf',
        ], 'rapport');

        $fs->create_file_from_string([
            'contextid' => $context->id, 'component' => 'mod_stage',
            'filearea' => STAGE_THEME_OBJECTIVE_FILEAREA, 'itemid' => $theme->id,
            'filepath' => '/', 'filename' => 'objectifs.pdf',
        ], 'objectifs');

        $newcourse = $this->backup_and_restore($course);
        $newcm = $this->single_instance($newcourse->id, 'stage');
        $newstage = $DB->get_record('stage', ['id' => $newcm->instance], '*', MUST_EXIST);
        $this->assertNotEquals($stage->id, $newstage->id);

        // Paramétrage de l'instance.
        $this->assertSame('Stages A3', $newstage->name);
        $this->assertSame('VetAgro Sup', $newstage->establishmentname);
        $this->assertEquals(3, $newstage->currentstudyyear);
        $this->assertEquals(20, $DB->get_field(
            'stage_year_requirement',
            'requiredduration',
            ['stageid' => $newstage->id, 'studyyear' => 3]
        ));

        // Thématique et son enseignant responsable.
        $newthemes = $DB->get_records('stage_theme', ['stageid' => $newstage->id]);
        $this->assertCount(1, $newthemes);
        $newtheme = reset($newthemes);
        $this->assertNotEquals($theme->id, $newtheme->id);
        $this->assertSame('Animaux de compagnie', $newtheme->name);
        $this->assertTrue($DB->record_exists(
            'stage_theme_teacher',
            ['themeid' => $newtheme->id, 'teacherid' => $teacher->id]
        ));

        // Attribution de l'enseignant référent.
        $this->assertTrue($DB->record_exists(
            'stage_entry_teacher',
            ['stageid' => $newstage->id, 'studentid' => $student->id, 'teacherid' => $teacher->id]
        ));

        // Saisie : rattachée aux thématique et gabarit de la copie, pas à ceux de l'original.
        $newentries = $DB->get_records('stage_entry', ['stageid' => $newstage->id]);
        $this->assertCount(1, $newentries);
        $newentry = reset($newentries);
        $this->assertEquals($student->id, $newentry->userid);
        $this->assertEquals($newtheme->id, $newentry->themeid);
        $this->assertSame($entry->structure, $newentry->structure);

        $newtemplates = $DB->get_records('stage_convention_template', ['stageid' => $newstage->id]);
        $this->assertCount(1, $newtemplates);
        $newtemplate = reset($newtemplates);
        $this->assertEquals($newtemplate->id, $newentry->conventiontemplateid);

        // Le jeton d'accès du maître de stage n'est pas recopié : il reste propre à l'original.
        $this->assertNull($newentry->tutortoken);

        // Plage de dates créée avec la saisie.
        $this->assertEquals(1, $DB->count_records('stage_entry_period', ['entryid' => $newentry->id]));

        // Réponse : rattachée à la question de la copie.
        $newquestions = $DB->get_records('stage_question', ['stageid' => $newstage->id]);
        $this->assertCount(1, $newquestions);
        $newquestion = reset($newquestions);
        $this->assertEquals($newtheme->id, $newquestion->themeid);
        $newanswers = $DB->get_records('stage_answer', ['entryid' => $newentry->id]);
        $this->assertCount(1, $newanswers);
        $newanswer = reset($newanswers);
        $this->assertEquals($newquestion->id, $newanswer->questionid);
        $this->assertSame('Beaucoup de choses.', $newanswer->answertext);

        // Fichiers : gabarit de convention et rapport déposé, dans le contexte de la copie.
        $newcontext = \context_module::instance($newcm->id);
        $this->assertCount(1, $fs->get_area_files(
            $newcontext->id,
            'mod_stage',
            'conventiontemplate',
            $newtemplate->id,
            'itemid',
            false
        ));
        $this->assertCount(1, $fs->get_area_files(
            $newcontext->id,
            'mod_stage',
            STAGE_REPORT_FILEAREA,
            $newentry->id,
            'itemid',
            false
        ));

        // Objectifs de la thématique : check-list, réponse de l'étudiant et document déposé, tous
        // rattachés à la thématique ou à la saisie de la copie.
        $newitems = $DB->get_records('stage_theme_checklist', ['themeid' => $newtheme->id]);
        $this->assertCount(1, $newitems);
        $newitem = reset($newitems);
        $this->assertSame('Consultation en autonomie', $newitem->name);

        $newchecklist = $DB->get_records('stage_entry_checklist', ['entryid' => $newentry->id]);
        $this->assertCount(1, $newchecklist);
        $newanswerrow = reset($newchecklist);
        $this->assertEquals($newitem->id, $newanswerrow->itemid);
        $this->assertEquals(0, $newanswerrow->checked);
        $this->assertSame('Structure trop petite.', $newanswerrow->explanation);

        $this->assertCount(1, $fs->get_area_files(
            $newcontext->id,
            'mod_stage',
            STAGE_THEME_OBJECTIVE_FILEAREA,
            $newtheme->id,
            'itemid',
            false
        ));
    }
}
