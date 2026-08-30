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
 * Upgrade steps for mod_stage.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Executes the upgrade steps for mod_stage.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_stage_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026082303) {
        $table = new xmldb_table('stage_question');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('stageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $table->add_field('themeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $table->add_field('evaltype', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null);
        $table->add_field('qtype', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null);
        $table->add_field('name', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null);
        $table->add_field('options', XMLDB_TYPE_TEXT, null, null, null, null);
        $table->add_field('required', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('stageid', XMLDB_KEY_FOREIGN, ['stageid'], 'stage', ['id']);
        $table->add_key('themeid', XMLDB_KEY_FOREIGN, ['themeid'], 'stage_theme', ['id']);
        $table->add_index('themeid-evaltype', XMLDB_INDEX_NOTUNIQUE, ['themeid', 'evaltype']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('stage_answer');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('entryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $table->add_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $table->add_field('answertext', XMLDB_TYPE_TEXT, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('entryid', XMLDB_KEY_FOREIGN, ['entryid'], 'stage_entry', ['id']);
        $table->add_key('questionid', XMLDB_KEY_FOREIGN, ['questionid'], 'stage_question', ['id']);
        $table->add_index('entryid-questionid', XMLDB_INDEX_UNIQUE, ['entryid', 'questionid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026082303, 'stage');
    }

    if ($oldversion < 2026082311) {
        $table = new xmldb_table('stage_question_theme');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $table->add_field('themeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('questionid', XMLDB_KEY_FOREIGN, ['questionid'], 'stage_question', ['id']);
        $table->add_key('themeid', XMLDB_KEY_FOREIGN, ['themeid'], 'stage_theme', ['id']);
        $table->add_index('questionid-themeid', XMLDB_INDEX_UNIQUE, ['questionid', 'themeid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Fait migrer l'affectation thématique existante (une seule) de chaque question vers la
        // table d'association, qui permet d'en réutiliser une pour plusieurs thématiques.
        $existing = $DB->get_records('stage_question', null, '', 'id, themeid, timecreated');
        foreach ($existing as $question) {
            if (!$DB->record_exists('stage_question_theme',
                    ['questionid' => $question->id, 'themeid' => $question->themeid])) {
                $DB->insert_record('stage_question_theme', (object) [
                    'questionid' => $question->id,
                    'themeid' => $question->themeid,
                    'timecreated' => $question->timecreated,
                ]);
            }
        }

        upgrade_mod_savepoint(true, 2026082311, 'stage');
    }

    if ($oldversion < 2026082401) {
        $table = new xmldb_table('stage_theme');
        $field = new xmldb_field('studyyear', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'requiredduration');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026082401, 'stage');
    }

    if ($oldversion < 2026082406) {
        $table = new xmldb_table('stage_convention_template');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('stageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('stageid', XMLDB_KEY_FOREIGN, ['stageid'], 'stage', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $entrytable = new xmldb_table('stage_entry');

        $field = new xmldb_field('conventiontemplateid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'devetime');
        if (!$dbman->field_exists($entrytable, $field)) {
            $dbman->add_field($entrytable, $field);
        }
        $field = new xmldb_field('conventionstatus', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0',
            'conventiontemplateid');
        if (!$dbman->field_exists($entrytable, $field)) {
            $dbman->add_field($entrytable, $field);
        }
        $field = new xmldb_field('conventionrequesttime', XMLDB_TYPE_INTEGER, '10', null, null, null, null,
            'conventionstatus');
        if (!$dbman->field_exists($entrytable, $field)) {
            $dbman->add_field($entrytable, $field);
        }
        $field = new xmldb_field('conventioneditedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null,
            'conventionrequesttime');
        if (!$dbman->field_exists($entrytable, $field)) {
            $dbman->add_field($entrytable, $field);
        }
        $field = new xmldb_field('conventionedittime', XMLDB_TYPE_INTEGER, '10', null, null, null, null,
            'conventioneditedby');
        if (!$dbman->field_exists($entrytable, $field)) {
            $dbman->add_field($entrytable, $field);
        }
        $field = new xmldb_field('conventionsignedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null,
            'conventionedittime');
        if (!$dbman->field_exists($entrytable, $field)) {
            $dbman->add_field($entrytable, $field);
        }
        $field = new xmldb_field('conventionsigntime', XMLDB_TYPE_INTEGER, '10', null, null, null, null,
            'conventionsignedby');
        if (!$dbman->field_exists($entrytable, $field)) {
            $dbman->add_field($entrytable, $field);
        }

        $key = new xmldb_key('conventiontemplateid', XMLDB_KEY_FOREIGN, ['conventiontemplateid'],
            'stage_convention_template', ['id']);
        $dbman->add_key($entrytable, $key);

        upgrade_mod_savepoint(true, 2026082406, 'stage');
    }

    if ($oldversion < 2026082407) {
        $table = new xmldb_table('stage_convention_template');
        $field = new xmldb_field('lang', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'fr', 'name');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026082407, 'stage');
    }

    if ($oldversion < 2026082408) {
        $table = new xmldb_table('stage_convention_detail');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('entryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $table->add_field('yearsituation', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'normal');
        $table->add_field('stagetype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'obligatoire');
        $table->add_field('studentbirthdate', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('studentaddress', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('studentphone', XMLDB_TYPE_CHAR, '30', null, null, null, null);
        $table->add_field('hostaddress', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('hostrepresentative', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('hostrepresentativetitle', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('hostservice', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('hostphone', XMLDB_TYPE_CHAR, '30', null, null, null, null);
        $table->add_field('hostemail', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('hostlocation', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('tutorname', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('tutorfunction', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('tutorphone', XMLDB_TYPE_CHAR, '30', null, null, null, null);
        $table->add_field('tutoremail', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('nightpresence', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sundaypresence', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('holidaypresence', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('homebased', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('othermodality', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('hasleave', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('leavedays', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('leavemodalities', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('gratificationamount', XMLDB_TYPE_CHAR, '20', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('entryid', XMLDB_KEY_FOREIGN_UNIQUE, ['entryid'], 'stage_entry', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026082408, 'stage');
    }

    if ($oldversion < 2026082409) {
        $entrytable = new xmldb_table('stage_entry');

        $field = new xmldb_field('cancelledby', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'conventionsigntime');
        if (!$dbman->field_exists($entrytable, $field)) {
            $dbman->add_field($entrytable, $field);
        }
        $field = new xmldb_field('canceltime', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'cancelledby');
        if (!$dbman->field_exists($entrytable, $field)) {
            $dbman->add_field($entrytable, $field);
        }
        $field = new xmldb_field('cancelcomment', XMLDB_TYPE_TEXT, null, null, null, null, null, 'canceltime');
        if (!$dbman->field_exists($entrytable, $field)) {
            $dbman->add_field($entrytable, $field);
        }
        $field = new xmldb_field('conventionrejectedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null,
            'conventionstatus');
        if (!$dbman->field_exists($entrytable, $field)) {
            $dbman->add_field($entrytable, $field);
        }
        $field = new xmldb_field('conventionrejecttime', XMLDB_TYPE_INTEGER, '10', null, null, null, null,
            'conventionrejectedby');
        if (!$dbman->field_exists($entrytable, $field)) {
            $dbman->add_field($entrytable, $field);
        }
        $field = new xmldb_field('conventionrejectcomment', XMLDB_TYPE_TEXT, null, null, null, null, null,
            'conventionrejecttime');
        if (!$dbman->field_exists($entrytable, $field)) {
            $dbman->add_field($entrytable, $field);
        }

        $key = new xmldb_key('cancelledby', XMLDB_KEY_FOREIGN, ['cancelledby'], 'user', ['id']);
        $dbman->add_key($entrytable, $key);
        $key = new xmldb_key('conventionrejectedby', XMLDB_KEY_FOREIGN, ['conventionrejectedby'], 'user', ['id']);
        $dbman->add_key($entrytable, $key);

        $detailtable = new xmldb_table('stage_convention_detail');
        $field = new xmldb_field('referentteacherid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'entryid');
        if (!$dbman->field_exists($detailtable, $field)) {
            $dbman->add_field($detailtable, $field);
        }
        $key = new xmldb_key('referentteacherid', XMLDB_KEY_FOREIGN, ['referentteacherid'], 'user', ['id']);
        $dbman->add_key($detailtable, $key);

        upgrade_mod_savepoint(true, 2026082409, 'stage');
    }

    if ($oldversion < 2026082411) {
        $stagetable = new xmldb_table('stage');

        $field = new xmldb_field('establishmentname', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'introformat');
        if (!$dbman->field_exists($stagetable, $field)) {
            $dbman->add_field($stagetable, $field);
        }
        $field = new xmldb_field('establishmentaddress', XMLDB_TYPE_CHAR, '255', null, null, null, null,
            'establishmentname');
        if (!$dbman->field_exists($stagetable, $field)) {
            $dbman->add_field($stagetable, $field);
        }
        $field = new xmldb_field('establishmentrepresentative', XMLDB_TYPE_CHAR, '255', null, null, null, null,
            'establishmentaddress');
        if (!$dbman->field_exists($stagetable, $field)) {
            $dbman->add_field($stagetable, $field);
        }
        $field = new xmldb_field('establishmentrepresentativetitle', XMLDB_TYPE_CHAR, '255', null, null, null, null,
            'establishmentrepresentative');
        if (!$dbman->field_exists($stagetable, $field)) {
            $dbman->add_field($stagetable, $field);
        }
        $field = new xmldb_field('establishmentphone', XMLDB_TYPE_CHAR, '64', null, null, null, null,
            'establishmentrepresentativetitle');
        if (!$dbman->field_exists($stagetable, $field)) {
            $dbman->add_field($stagetable, $field);
        }
        $field = new xmldb_field('establishmentemail', XMLDB_TYPE_CHAR, '255', null, null, null, null,
            'establishmentphone');
        if (!$dbman->field_exists($stagetable, $field)) {
            $dbman->add_field($stagetable, $field);
        }

        upgrade_mod_savepoint(true, 2026082411, 'stage');
    }

    if ($oldversion < 2026082412) {
        $stagetable = new xmldb_table('stage');
        $field = new xmldb_field('conventionrequireteachervalidation', XMLDB_TYPE_INTEGER, '1', null,
            XMLDB_NOTNULL, null, '0', 'establishmentemail');
        if (!$dbman->field_exists($stagetable, $field)) {
            $dbman->add_field($stagetable, $field);
        }

        $entrytable = new xmldb_table('stage_entry');
        $field = new xmldb_field('conventionteachervalidatedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null,
            'conventionrequesttime');
        if (!$dbman->field_exists($entrytable, $field)) {
            $dbman->add_field($entrytable, $field);
        }
        $field = new xmldb_field('conventionteachervalidatetime', XMLDB_TYPE_INTEGER, '10', null, null, null, null,
            'conventionteachervalidatedby');
        if (!$dbman->field_exists($entrytable, $field)) {
            $dbman->add_field($entrytable, $field);
        }
        $key = new xmldb_key('conventionteachervalidatedby', XMLDB_KEY_FOREIGN, ['conventionteachervalidatedby'],
            'user', ['id']);
        $dbman->add_key($entrytable, $key);

        upgrade_mod_savepoint(true, 2026082412, 'stage');
    }

    if ($oldversion < 2026082415) {
        $table = new xmldb_table('stage_theme');

        $minfield = new xmldb_field('minstudyyear', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0',
            'requiredduration');
        if (!$dbman->field_exists($table, $minfield)) {
            $dbman->add_field($table, $minfield);
        }
        $maxfield = new xmldb_field('maxstudyyear', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0',
            'minstudyyear');
        if (!$dbman->field_exists($table, $maxfield)) {
            $dbman->add_field($table, $maxfield);
        }

        // Fait migrer l'ancienne année d'étude unique vers la nouvelle plage min/max.
        $oldfield = new xmldb_field('studyyear');
        if ($dbman->field_exists($table, $oldfield)) {
            $DB->execute('UPDATE {stage_theme} SET minstudyyear = studyyear, maxstudyyear = studyyear');
            $dbman->drop_field($table, $oldfield);
        }

        upgrade_mod_savepoint(true, 2026082415, 'stage');
    }

    if ($oldversion < 2026082416) {
        // Année d'étude courante des étudiants (référence N pour les conventions en N-1/N+1).
        $stagetable = new xmldb_table('stage');
        $field = new xmldb_field('currentstudyyear', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0',
            'conventionrequireteachervalidation');
        if (!$dbman->field_exists($stagetable, $field)) {
            $dbman->add_field($stagetable, $field);
        }

        // Année d'étude à laquelle chaque saisie de stage est rattachée.
        $entrytable = new xmldb_table('stage_entry');
        $field = new xmldb_field('studyyear', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'themeid');
        if (!$dbman->field_exists($entrytable, $field)) {
            $dbman->add_field($entrytable, $field);
        }

        // Durée obligatoire requise par thématique, déclinée par année d'étude (remplace
        // stage_theme.requiredduration, qui ne permettait qu'une seule valeur par thématique).
        $durationtable = new xmldb_table('stage_theme_duration');
        $durationtable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $durationtable->add_field('themeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $durationtable->add_field('studyyear', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $durationtable->add_field('requiredduration', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $durationtable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $durationtable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $durationtable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $durationtable->add_key('themeid', XMLDB_KEY_FOREIGN, ['themeid'], 'stage_theme', ['id']);
        $durationtable->add_index('themeid-studyyear', XMLDB_INDEX_UNIQUE, ['themeid', 'studyyear']);
        if (!$dbman->table_exists($durationtable)) {
            $dbman->create_table($durationtable);
        }

        // Durée totale obligatoire requise par année d'étude, toutes thématiques confondues.
        $yeartable = new xmldb_table('stage_year_requirement');
        $yeartable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $yeartable->add_field('stageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $yeartable->add_field('studyyear', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $yeartable->add_field('requiredduration', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $yeartable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $yeartable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $yeartable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $yeartable->add_key('stageid', XMLDB_KEY_FOREIGN, ['stageid'], 'stage', ['id']);
        $yeartable->add_index('stageid-studyyear', XMLDB_INDEX_UNIQUE, ['stageid', 'studyyear']);
        if (!$dbman->table_exists($yeartable)) {
            $dbman->create_table($yeartable);
        }

        // Migration des données existantes : l'ancienne durée requise unique de chaque thématique
        // devient sa durée requise pour chacune des années de sa plage [minstudyyear, maxstudyyear]
        // (ou pour l'année 0 si la thématique ne précise pas d'année). Les saisies existantes
        // héritent de l'année minimale de leur thématique, faute de mieux.
        $oldfield = new xmldb_field('requiredduration');
        if ($dbman->field_exists(new xmldb_table('stage_theme'), $oldfield)) {
            $themes = $DB->get_records('stage_theme', null, '', 'id, requiredduration, minstudyyear, maxstudyyear');
            foreach ($themes as $theme) {
                $minyear = (int) $theme->minstudyyear;
                $maxyear = (int) $theme->maxstudyyear;
                if (empty($minyear) && empty($maxyear)) {
                    $years = [0];
                } else {
                    $minyear = $minyear ?: $maxyear;
                    $maxyear = $maxyear ?: $minyear;
                    $years = range(min($minyear, $maxyear), max($minyear, $maxyear));
                }
                foreach ($years as $year) {
                    if (!$DB->record_exists('stage_theme_duration', ['themeid' => $theme->id, 'studyyear' => $year])) {
                        $DB->insert_record('stage_theme_duration', (object) [
                            'themeid' => $theme->id,
                            'studyyear' => $year,
                            'requiredduration' => $theme->requiredduration,
                            'timecreated' => time(),
                            'timemodified' => time(),
                        ]);
                    }
                }
            }

            $DB->execute("UPDATE {stage_entry} e
                             SET studyyear = COALESCE((SELECT t.minstudyyear FROM {stage_theme} t
                                                         WHERE t.id = e.themeid), 0)");

            $dbman->drop_field(new xmldb_table('stage_theme'), $oldfield);
        }

        upgrade_mod_savepoint(true, 2026082416, 'stage');
    }

    if ($oldversion < 2026082417) {
        // Obligation de mobilité internationale : nombre de jours de stage à l'étranger requis.
        $stagetable = new xmldb_table('stage');
        $field = new xmldb_field('requiredabroaddays', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0',
            'currentstudyyear');
        if (!$dbman->field_exists($stagetable, $field)) {
            $dbman->add_field($stagetable, $field);
        }

        // Indique si une saisie de stage a été effectuée à l'étranger.
        $entrytable = new xmldb_table('stage_entry');
        $field = new xmldb_field('abroad', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'structure');
        if (!$dbman->field_exists($entrytable, $field)) {
            $dbman->add_field($entrytable, $field);
        }

        upgrade_mod_savepoint(true, 2026082417, 'stage');
    }

    if ($oldversion < 2026082418) {
        // Réintroduit une durée requise unique pour une thématique, en alternative à une durée
        // par année (stage_theme_duration) : l'un ou l'autre, pas les deux.
        $table = new xmldb_table('stage_theme');
        $field = new xmldb_field('requiredduration', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0',
            'mandatory');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026082418, 'stage');
    }

    if ($oldversion < 2026082419) {
        // Obligation de mobilité internationale par thématique (jours à l'étranger requis, tous
        // stages confondus) et règle associée affichée à l'étudiant.
        $themetable = new xmldb_table('stage_theme');
        $field = new xmldb_field('requiredabroaddays', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0',
            'maxstudyyear');
        if (!$dbman->field_exists($themetable, $field)) {
            $dbman->add_field($themetable, $field);
        }
        $field = new xmldb_field('abroadrule', XMLDB_TYPE_TEXT, null, null, null, null, null, 'requiredabroaddays');
        if (!$dbman->field_exists($themetable, $field)) {
            $dbman->add_field($themetable, $field);
        }

        // Pays du stage, renseigné quand la saisie est marquée "à l'étranger".
        $entrytable = new xmldb_table('stage_entry');
        $field = new xmldb_field('country', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'abroad');
        if (!$dbman->field_exists($entrytable, $field)) {
            $dbman->add_field($entrytable, $field);
        }

        // Plages de dates multiples pour une saisie de stage.
        $periodtable = new xmldb_table('stage_entry_period');
        $periodtable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $periodtable->add_field('entryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $periodtable->add_field('datestart', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $periodtable->add_field('dateend', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $periodtable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $periodtable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $periodtable->add_key('entryid', XMLDB_KEY_FOREIGN, ['entryid'], 'stage_entry', ['id']);
        if (!$dbman->table_exists($periodtable)) {
            $dbman->create_table($periodtable);
        }

        // Jours de stage effectifs sélectionnés par l'étudiant parmi ces plages.
        $workdaytable = new xmldb_table('stage_entry_workday');
        $workdaytable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $workdaytable->add_field('entryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $workdaytable->add_field('workdate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $workdaytable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $workdaytable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $workdaytable->add_key('entryid', XMLDB_KEY_FOREIGN, ['entryid'], 'stage_entry', ['id']);
        $workdaytable->add_index('entryid-workdate', XMLDB_INDEX_UNIQUE, ['entryid', 'workdate']);
        if (!$dbman->table_exists($workdaytable)) {
            $dbman->create_table($workdaytable);
        }

        upgrade_mod_savepoint(true, 2026082419, 'stage');
    }

    if ($oldversion < 2026082420) {
        // L'obligation de mobilité internationale n'est pas liée à une thématique : ses
        // paramètres, gérés depuis la page "Durées de stage par année" (year_requirements.php),
        // migrent de stage_theme vers stage, avec une année limite et une consigne en plus.
        $stagetable = new xmldb_table('stage');
        $field = new xmldb_field('abroadbeforeyear', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0',
            'requiredabroaddays');
        if (!$dbman->field_exists($stagetable, $field)) {
            $dbman->add_field($stagetable, $field);
        }
        $field = new xmldb_field('abroadrule', XMLDB_TYPE_TEXT, null, null, null, null, null, 'abroadbeforeyear');
        if (!$dbman->field_exists($stagetable, $field)) {
            $dbman->add_field($stagetable, $field);
        }

        $themetable = new xmldb_table('stage_theme');
        $field = new xmldb_field('requiredabroaddays');
        if ($dbman->field_exists($themetable, $field)) {
            $dbman->drop_field($themetable, $field);
        }
        $field = new xmldb_field('abroadrule');
        if ($dbman->field_exists($themetable, $field)) {
            $dbman->drop_field($themetable, $field);
        }

        upgrade_mod_savepoint(true, 2026082420, 'stage');
    }

    if ($oldversion < 2026082421) {
        // Nom de la personne ayant délégation de signature du chef d'établissement, affiché dans
        // le cadre de signatures de la convention imprimée (voir generate_page1()).
        $stagetable = new xmldb_table('stage');
        $field = new xmldb_field('establishmentsignatory', XMLDB_TYPE_CHAR, '255', null, null, null, null,
            'establishmentemail');
        if (!$dbman->field_exists($stagetable, $field)) {
            $dbman->add_field($stagetable, $field);
        }

        upgrade_mod_savepoint(true, 2026082421, 'stage');
    }

    if ($oldversion < 2026082422) {
        // Les plages de dates sont devenues la seule saisie possible des dates d'un stage, et
        // toute saisie créée depuis reçoit la sienne (voir stage_register_entry()). Les saisies
        // antérieures n'en ont pas : leurs dates étaient jusque-là reconstituées à la volée
        // (stage_get_or_seed_entry_periods()), ce qui suffisait à les afficher mais laissait la
        // base incohérente selon l'ancienneté de la saisie. On matérialise la plage manquante.
        $sql = "SELECT e.id, e.datestart, e.dateend
                  FROM {stage_entry} e
             LEFT JOIN {stage_entry_period} p ON p.entryid = e.id
                 WHERE p.id IS NULL AND e.datestart > 0 AND e.dateend > 0";
        $records = [];
        foreach ($DB->get_recordset_sql($sql) as $entry) {
            $records[] = (object) [
                'entryid' => $entry->id,
                'datestart' => $entry->datestart,
                'dateend' => $entry->dateend,
                'timecreated' => time(),
            ];
        }
        if ($records) {
            $DB->insert_records('stage_entry_period', $records);
        }

        upgrade_mod_savepoint(true, 2026082422, 'stage');
    }

    if ($oldversion < 2026082423) {
        // Évaluation du maître de stage (questionnaire par thématique, comme pour l'étudiant et
        // l'enseignant référent, accessible sans compte Moodle via un lien à jeton) et
        // personnalisation des e-mails envoyés par l'activité.
        $stagetable = new xmldb_table('stage');
        $field = new xmldb_field('tutorevaluationenabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0',
            'conventionrequireteachervalidation');
        if (!$dbman->field_exists($stagetable, $field)) {
            $dbman->add_field($stagetable, $field);
        }

        $entrytable = new xmldb_table('stage_entry');
        $field = new xmldb_field('tutortoken', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'teachertime');
        if (!$dbman->field_exists($entrytable, $field)) {
            $dbman->add_field($entrytable, $field);
        }
        $field = new xmldb_field('tutoreval', XMLDB_TYPE_TEXT, null, null, null, null, null, 'tutortoken');
        if (!$dbman->field_exists($entrytable, $field)) {
            $dbman->add_field($entrytable, $field);
        }
        $field = new xmldb_field('tutortime', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'tutoreval');
        if (!$dbman->field_exists($entrytable, $field)) {
            $dbman->add_field($entrytable, $field);
        }
        $index = new xmldb_index('tutortoken', XMLDB_INDEX_UNIQUE, ['tutortoken']);
        if (!$dbman->index_exists($entrytable, $index)) {
            $dbman->add_index($entrytable, $index);
        }

        $emailtable = new xmldb_table('stage_email_template');
        if (!$dbman->table_exists($emailtable)) {
            $emailtable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $emailtable->add_field('stageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
            $emailtable->add_field('emailkey', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null);
            $emailtable->add_field('subject', XMLDB_TYPE_TEXT, null, null, null, null);
            $emailtable->add_field('body', XMLDB_TYPE_TEXT, null, null, null, null);
            $emailtable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
            $emailtable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $emailtable->add_key('stageid', XMLDB_KEY_FOREIGN, ['stageid'], 'stage', ['id']);
            $emailtable->add_index('stageid-emailkey', XMLDB_INDEX_UNIQUE, ['stageid', 'emailkey']);
            $dbman->create_table($emailtable);
        }

        upgrade_mod_savepoint(true, 2026082423, 'stage');
    }

    return true;
}
