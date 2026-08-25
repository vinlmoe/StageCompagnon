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

    return true;
}
