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
 * English strings for mod_stagesynthesis.
 *
 * @package   mod_stagesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Internship tracking (teacher synthesis)';
$string['modulename'] = 'Internship tracking';
$string['modulenameplural'] = 'Internship trackings';
$string['modulename_help'] = 'Groups, for each supervising teacher, the internships of every student assigned to them '
    . 'across the linked "Internship management" activities, even when those students come from different '
    . 'courses/cohorts. Which activities feed into this view is chosen in this activity\'s administration page; a '
    . 'cohort that is no longer tracked can be removed here without touching its original activity. Permissions '
    . '(who supervises whom) remain managed in each "Internship management" activity: this activity only shows a '
    . 'combined view of them, it grants no additional right.';
$string['modulename_link'] = 'mod/stagesynthesis/view';
$string['pluginadministration'] = 'Internship tracking administration';

$string['stagesynthesis:addinstance'] = 'Add a new internship tracking activity';
$string['stagesynthesis:view'] = 'View the internship tracking activity';
$string['stagesynthesis:managelinks'] = 'Choose the linked "Internship management" activities';

$string['stagesynthesisname'] = 'Activity name';
$string['linksnotice'] = 'Choose which "Internship management" activities feed into this view after saving, from the '
    . 'activity page ("Manage links" link). Because linking designates courses and cohorts outside this one, that '
    . 'choice is reserved to whoever administers the activity: a non-editing teacher only sees the synthesis of '
    . 'their own students.';

$string['managelinks'] = 'Manage links';
$string['managelinks_help'] = 'Tick the "Internship management" activities whose students should appear here for '
    . 'their supervising teacher. Untick an activity (e.g. a cohort that has left) to remove it from this view '
    . 'without deleting it or changing its data.';
$string['linkedcount'] = '{$a} linked "Internship management" activity/activities.';
$string['linkssaved'] = 'Linked activities updated.';
$string['linked'] = 'Linked';
$string['noactivities'] = 'No "Internship management" activity exists yet on this site.';
$string['hiddencourse'] = 'hidden course';
$string['hiddenactivity'] = 'hidden activity';

$string['nostudents'] = 'No student is currently assigned to you as supervising teacher on the linked activities.';
$string['student'] = 'Student';

$string['privacy:metadata'] = 'The Internship tracking plugin stores no personal data of its own: it only displays '
    . 'data already held in the linked "Internship management" activities.';
