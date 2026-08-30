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
 * English strings for mod_stage.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Internship management';
$string['modulename'] = 'Internship management';
$string['modulenameplural'] = 'Internship managements';
$string['modulename_help'] = 'This module allows students to declare their internships by theme, self-assess them, '
    . 'referent teachers to evaluate them, and the academic office to manage mandatory themes and give the final '
    . 'validation of internships, either in bulk or one by one.';
$string['pluginadministration'] = 'Internship management administration';
$string['stagename'] = 'Activity name';

// Capabilities.
$string['stage:addinstance'] = 'Add a new internship management activity';
$string['stage:view'] = 'View the internship management activity';
$string['stage:submit'] = 'Submit own internships';
$string['stage:evaluateteacher'] = 'Evaluate internships as a referent teacher';
$string['stage:registerstages'] = 'Register student internships';
$string['stage:managethemes'] = 'Manage internship themes';
$string['stage:validatedeve'] = 'Give final validation of internships (academic office)';
$string['stage:viewall'] = 'View all internships of all students';
$string['stage:manageteachers'] = 'Assign referent teachers';

// Navigation / actions.
$string['managethemes'] = 'Manage themes';
$string['administration'] = 'Administration';
$string['importfromcourse'] = 'Import from another course';
$string['importfromcourse_help'] = 'Copy the themes, agreement templates, logos and/or establishment information from '
    . "another instance of the activity (usually in another course) into this one, so you don't have to redo "
    . "everything for each new course. Only instances where you yourself can manage themes are offered as a "
    . 'source. Imported items are added to what is already here (themes and templates are not merged with '
    . 'existing ones; logos and establishment information already set here are replaced).';
$string['importsource'] = 'Source instance';
$string['importthemes'] = 'Themes';
$string['importtemplates'] = 'Agreement templates';
$string['importlogos'] = 'Logos';
$string['importestablishment'] = 'Educational establishment information';
$string['importnothingselected'] = 'Select at least one item to import.';
$string['noimportsources'] = 'No other instance of the activity where you can manage themes was found.';
$string['importdone'] = 'Import complete: {$a->themes} theme(s), {$a->templates} agreement template(s), '
    . '{$a->logos} logo(s), establishment {$a->establishmenttext}.';
$string['importdoneestablishmentyes'] = 'imported';
$string['importdoneestablishmentno'] = 'not imported';
$string['manageteachers'] = 'Assign referent teachers';
$string['devevalidation'] = 'Academic office validation';
$string['teachervalidation'] = 'Teacher validation';
$string['mystages'] = 'My internships';
$string['registerstages'] = 'Register internships';
$string['importcsv'] = 'Import a CSV file';
$string['exportexcel'] = 'Export to Excel';
$string['allstages'] = 'Internships';
$string['promotionreport'] = 'Cohort report';
$string['promotionreportpdf'] = 'Cohort report (PDF)';
$string['promotiongeneratedon'] = 'Generated on {$a}';
$string['promotionsummary'] = '{$a->total} student(s): {$a->failed} failing an elapsed year, {$a->uptodate} up to date.';
$string['promotionfailedheading'] = 'Students not completing an elapsed year';
$string['promotionfailedheading_help'] = 'Ordered from furthest behind to least: first by number of years not completed, then by how old the backlog is.';
$string['promotionuptodateheading'] = 'Students up to date';
$string['promotionnofailed'] = 'None: the whole cohort completes the elapsed years.';
$string['promotionnouptodate'] = 'No student completes all elapsed years yet.';
$string['promotionyeardone'] = 'OK';
$string['promotionyearfailed'] = 'NO';
$string['promotionuptodate'] = 'Up to date';
$string['promotionfailedyears'] = 'Years not completed';
$string['promotioncoldays'] = 'Days retained';
$string['promotioncolthemes'] = 'Themes';
$string['promotionlegend'] = 'OK: year completed. NO: year not completed. -: no objective defined for that student that year. Only the activity current study year and the previous ones are taken into account.';
$string['import'] = 'Import';
$string['importcsv_help'] = 'Import a CSV file (saved from Excel via "Save As > CSV"), with the following columns, '
    . 'separated by semicolons or commas, with an optional header row: '
    . '<code>email;theme;structure;datestart;dateend;duration</code>. The <em>email</em> field must match a student '
    . 'enrolled in the course, <em>theme</em> the exact name of an existing theme, the dates in YYYY-MM-DD format '
    . '(optional), and <em>duration</em> the declared duration in days.';
$string['importresult'] = '{$a} internship(s) successfully imported.';
$string['importerrorupload'] = 'The file could not be uploaded. Check its size and try again.';
$string['importerrorunknownemail'] = 'Line {$a->line}: no enrolled student with email "{$a->email}".';
$string['importerrorunknowntheme'] = 'Line {$a->line}: theme "{$a->theme}" not found.';
$string['importerrorduplicate'] = 'Line {$a->line}: "{$a->email}" already has an internship on theme "{$a->theme}" '
    . 'with these same dates, line skipped.';
$string['importstagevetcsv'] = 'Import a StageVet export (CSV)';
$string['importstagevetcsv_help'] = 'Import the CSV file exported directly from StageVet (StageVet\'s export '
    . 'menu, unmodified). Columns are recognised by their header ("Nom étudiant", "Prénom étudiant", "Thème", '
    . '"Début (convention)"/"Fin (convention)", host organisation and tutor details, terms, gratuity...), in the '
    . 'order StageVet usually provides them. The student is matched by email if the "Email étudiant" column is '
    . 'filled in, otherwise by first/last name (accent- and case-insensitive) among students enrolled on the '
    . 'course. The internship study year is read from "Année étudiant (convention)", falling back to "Année '
    . 'd\'étude". The export "tuteur" is matched to the Moodle referent teacher, while the "maître de stage" is '
    . 'stored as the host organisation supervisor. The theme name must exactly match a theme already created in '
    . 'this activity: since StageVet does '
    . 'not use the same names by default, first create themes with the same names used in StageVet (e.g. '
    . '"THEME LIBRE / A2, A3, A4, A5"). Each imported internship is registered with agreement status "Signed '
    . '(StageVet)" (already signed outside this plugin): the agreement details available in the export are still '
    . 'saved for reference, without triggering any PDF generation. The start and end dates from the export form the '
    . "imported internship's only date period: a line without usable dates is reported and skipped.";
$string['importstagevetnoheader'] = 'The file does not seem to have a recognisable header row. Check that it is '
    . 'an unmodified StageVet export.';
$string['importstageveterrornotheme'] = 'Line {$a}: no theme provided.';
$string['importstageveterrordates'] = 'Line {$a->line} ({$a->student}): start and end dates missing or inconsistent. These dates form the internship\'s only period: the line is skipped.';
$string['importstagevetunknownstudentsreport'] = '{$a} student(s) not found among those enrolled on the course';
$string['importstagevetunknownthemesreport'] = '{$a} theme(s) not found';
$string['importstagevetreportline'] = '{$a->value} (line(s) {$a->lines})';
$string['importteacherscsv'] = 'Import a CSV file';
$string['importteacherscsv_help'] = 'Import a CSV file (saved from Excel via "Save As > CSV"), with the following '
    . 'columns, separated by semicolons or commas, with an optional header row: '
    . '<code>studentemail;teacher1email;teacher2email</code>. The <em>studentemail</em> field must match a student '
    . 'enrolled on the course, <em>teacher1email</em> a potential referent teacher enrolled on the course; '
    . '<em>teacher2email</em> is optional (second referent). Each line replaces the student\'s existing assignment.';
$string['importteachersresult'] = '{$a} student(s) updated successfully.';
$string['importerrorunknownteacher'] = 'Line {$a->line}: no potential referent teacher with email "{$a->email}".';
$string['errorduplicateentry'] = 'This student already has an internship registered on this theme with these same dates.';
$string['registerstage'] = 'Register an internship';
$string['editstage'] = 'Edit an internship';
$string['registerstageandconvention'] = 'Request an agreement (outside StageVet)';
$string['registerstageandconvention_help'] = 'For an internship not handled by StageVet, register it yourself and '
    . 'request its agreement in one go. The DEVE will then process your agreement request (editing then '
    . 'signing); self-assessment will only be possible once the agreement is signed.';
$string['stageandconventionregistered'] = 'The internship has been registered and the agreement request sent to the DEVE.';
$string['bulkregisterstages'] = 'Register internships in bulk';
$string['bulkregisterselected'] = 'Register for checked students';
$string['bulkregistersignvethelp'] = 'Internships registered in bulk are considered already signed on StageVet: '
    . 'their agreement status automatically switches to "Signed (StageVet)", without going through this '
    . "plugin's agreement management workflow (no template or PDF to generate or upload). The student's "
    . 'self-assessment is immediately open.';
$string['selectstudents'] = 'Select the students concerned';
$string['selfeval'] = 'Self-assess my internship';
$string['registeredbydeve'] = 'Internships and agreements with French veterinary practices are generated by '
    . 'StageVet. To request an agreement for another type of internship, submit the request below. Once the '
    . 'internship is over, complete your self-assessment and request the teacher\'s evaluation. Agreement '
    . 'requests must be made at least 2 weeks before the internship, or 4 weeks for an internship abroad.';
$string['allmystages'] = 'All my internships';
$string['mandatorythemes'] = 'Mandatory themes';
$string['actions'] = 'Actions';

// Fields.
$string['theme'] = 'Theme';
$string['structure'] = 'Host structure';
$string['datestart'] = 'Start date';
$string['dateend'] = 'End date';
$string['declaredduration'] = 'Declared duration (days)';
$string['retainedduration'] = 'Retained duration (days)';
$string['requiredduration'] = 'Required duration (days)';
$string['requiredduration_help'] = 'Total duration required to validate this theme, regardless of study year (0 = not used). Alternative to setting a duration per year (the "Durations per year" page): use only one of the two methods, not both. For a theme bound to a range of years (e.g. A2 to A4), this duration is checked at the last year of the range, against the theme\'s cumulative entries.';
$string['studyyear'] = 'Study year';
$string['studyyear_unspecified'] = 'Unspecified (all years)';
$string['studyyear_n'] = 'Year {$a}';
$string['minstudyyear'] = 'Minimum study year';
$string['maxstudyyear'] = 'Maximum study year';
$string['studyyearrange_error'] = 'The minimum year must be less than or equal to the maximum year.';
$string['currentstudyyear'] = 'Students\' current study year';
$string['currentstudyyear_help'] = 'Study year (N) of the students enrolled in this course. Used as the reference for the internships they can declare in a convention: year N (normal), N-1 (debt/resit) or N+1 (anticipation). Leave on "Unspecified" to not restrict the choice.';
$string['abroad'] = 'Internship abroad';
$string['requiredabroaddays'] = 'Required international mobility days';
$string['requiredabroaddays_help'] = 'Total number of days abroad each student must accumulate across all their internships (0 = no requirement). Only internships marked "Internship abroad" and mandatory internships (excluding complementary ones) count towards this total.';
$string['abroadtotal'] = 'International mobility';
$string['abroadbeforeyear'] = 'Study year before which mobility is required';
$string['abroaddaysrequired'] = 'Days abroad required';
$string['abroaddaysretained'] = 'Days abroad retained';
$string['themeabroaddays_help'] = 'Number of days abroad required for this theme (0 = no requirement), accumulated across all internships done on this theme (both mandatory AND complementary, unlike the required duration above which excludes complementary internships). For a theme bound to a range of years, checked at its last year like the required duration.';
$string['abroadrule'] = 'International mobility rule (shown to students)';
$string['abroadrule_help'] = 'Free text describing the international mobility conditions for this theme (e.g. eligible countries, minimum continuous duration, partner organisations...). Shown to students when registering an internship on this theme and in their summary.';
$string['themeabroadsaved'] = 'The international mobility settings have been saved.';
$string['country'] = 'Country';
$string['workdays'] = 'Actual internship days';
$string['workdays_help'] = 'Among this internship\'s date ranges, tick the days actually worked. Reminder: regulations require at least one rest day per week.';
$string['restdayrule'] = 'Reminder: at least one rest day is required each week.';
$string['restdaywarning'] = 'Warning: at least one selected week has no rest day.';
$string['periods'] = 'Internship periods';
$string['stagesummary'] = 'The internship';
$string['conventionfollowup'] = 'Agreement follow-up';
$string['stagestoevaluate'] = 'Internships to evaluate';
$string['adminsectionrequirements'] = 'What students have to do';
$string['adminsectionconventions'] = 'Internship agreements';
$string['adminsectionnotifications'] = 'Notifications';
$string['notifications_desc'] = 'Enable evaluation by the workplace tutor and customize the text of the '
    . 'emails sent by the activity.';
$string['adminsectionteachers'] = 'Student supervision';
$string['adminsectionsetup'] = 'Setting up the activity';
$string['adminsectionpage'] = 'Page';
$string['adminsectionpurpose'] = 'What it is for';
$string['managethemes_desc'] = 'The internship themes offered to students: their name, whether they are mandatory, the study years concerned and the duration required for each.';
$string['manageyearrequirements_desc'] = 'The total internship duration required for each study year, across all themes, as well as the international mobility requirement.';
$string['conventiontemplates_desc'] = 'The PDF templates offered to students when they request an agreement, the logos and the establishment information shown on the first page.';
$string['manageteachers_desc'] = 'Assigning referent teachers to students: each student needs one before they can request their agreement.';
$string['importfromcourse_desc'] = 'Bring themes, templates, logos and establishment information over from another instance of the activity, so you do not have to re-enter everything for each new course.';
$string['transferstudent'] = 'Transfer a student';
$string['transferstudent_desc'] = 'Move a student and all their internships to another instance of the activity (repeating a year, changing cohort, changing course), so that their record follows them instead of staying in the course they are leaving.';
$string['transferstudent_help'] = 'Moves a student and all their internships to another instance of the activity, usually in another course. Internships are moved, not copied: they disappear from this course. A summary of what will be transferred is shown before anything is changed.';
$string['transfertarget'] = 'Destination activity';
$string['transfertarget_help'] = 'Only instances of the activity where you can register internships yourself are offered. The student must already be enrolled on the corresponding course.';
$string['transfersource'] = 'Source activity';
$string['transferpreview'] = 'Prepare the transfer';
$string['transfersummary'] = 'Transfer to perform';
$string['transferentries'] = 'Internships that will be transferred';
$string['transferentrycount'] = 'Number of internships';
$string['transferconfirm'] = 'Confirm the transfer';
$string['transferirreversible'] = 'The transfer cannot be undone: to bring the student back to this course, you will have to transfer them back from the destination activity.';
$string['transferdone'] = '{$a->count} internship(s) of {$a->student} transferred to "{$a->target}".';
$string['transfernotargets'] = 'No other instance of the activity where you can register internships was found.';
$string['transfernoentries'] = 'This student has no internship in this activity: there is nothing to transfer.';
$string['transfernotenrolled'] = 'The student is not enrolled on course "{$a}". Enrol them first: without an enrolment, their internships would not appear on any dashboard in the destination.';
$string['transferunmatchedthemes'] = 'These themes do not exist in the destination activity: {$a}. Create them there first (with exactly the same name), or use "Import from another course": without them, the internships concerned would lose their theme and distort the student record.';
$string['transferunmatchedtemplates'] = 'These agreement templates do not exist in the destination activity: {$a}. The internships concerned will be transferred without a template: their already signed agreement remains available, but regenerating its PDF will require choosing one again.';
$string['transferdroppedanswers'] = '{$a} evaluation answer(s) will be deleted: the matching questions do not exist in the destination themes.';
$string['transferreferentteachers'] = 'The referent teacher assignment ({$a}) is not transferred: it belongs to the course. Remember to assign a referent teacher to the student in the destination course.';
$string['rejectstageheading'] = 'Reject the entry';
$string['rejectstageheading_help'] = 'Sends the entry back to the student for correction. The reason below is passed on to them: it is required.';
$string['dates'] = 'Dates';
$string['periodstart'] = 'Start';
$string['periodend'] = 'End';
$string['addperiod'] = 'Add a period';
$string['removeperiod'] = 'Remove';
$string['periods_help'] = 'An internship can have several non-contiguous date ranges (e.g. two separate stays). The student will pick their actual working days among these ranges during their self-evaluation.';
$string['periodsrequired'] = 'Enter at least one date period: the internship dates are derived from them.';
$string['periodendbeforestart'] = 'The end date of a period cannot come before its start date.';
$string['periodsoverlap'] = 'Two date periods overlap ({$a->first} and {$a->second}). The same days would be counted twice: adjust them so they do not overlap.';
$string['conventionsignaturedate'] = 'Date: ............................';
$string['noperiodsdefined'] = 'No date range has been defined for this internship yet.';
$string['workdayssaved'] = 'The actual internship days have been saved.';
$string['totalrequiredduration'] = 'Total required duration (days)';
$string['managethemedurations'] = 'Durations per year';
$string['durationperyear'] = 'Per year (see Durations per year)';
$string['durationflatignored'] = 'A single duration of {$a} day(s) is set on this theme (see its edit form): it is used instead of the per-year durations below, which are ignored.';
$string['themedurationssaved'] = 'Durations saved.';
$string['manageyearrequirements'] = 'Total required durations per year';
$string['yearrequirementssaved'] = 'Total durations saved.';
$string['yearrequirements_help'] = 'Total mandatory internship duration required for each study year, across all themes. Complementary internships do not count towards this total.';
$string['yeartotals'] = 'Study year summary';
$string['validatedyears'] = 'Validated: {$a}';
$string['status'] = 'Status';
$string['mandatory'] = 'Mandatory';
$string['sortorder'] = 'Order';
$string['studentselfeval'] = 'Student self-assessment';
$string['teachereval'] = 'Teacher evaluation';
$string['devecomment'] = 'Academic office comment';
$string['student'] = 'Student';
$string['referentteachers'] = 'Referent teachers';
$string['currentreferentteachers'] = 'Current referent teacher(s)';
$string['noreferentteacher'] = 'None';
$string['availableteachers'] = 'Available teachers';
$string['selectedteachers'] = 'Selected referent teachers';
$string['addselected'] = 'Add';
$string['removeselected'] = 'Remove';

// Statuses.
$string['status_enregistre'] = 'Registered';
$string['status_evaletudiant'] = 'Self-assessed by student';
$string['status_evalenseignant'] = 'Evaluated by teacher';
$string['status_validedeve'] = 'Validated';
$string['status_nonvalide'] = 'Not validated';
$string['status_annule'] = 'Cancelled';
$string['themedone'] = 'Completed';
$string['themetodo'] = 'To complete';

// Actions / buttons.
$string['addtheme'] = 'Add a theme';
$string['evalquestions'] = 'Evaluation questions';
$string['addquestion'] = 'Add a question';
$string['evaltype'] = 'Applies to form';
$string['evaltype_student'] = 'Student self-assessment';
$string['evaltype_teacher'] = 'Teacher evaluation';
$string['qtype'] = 'Question type';
$string['qtype_choice'] = 'Multiple choice';
$string['qtype_text'] = 'Free comment';
$string['questionlabel'] = 'Question label';
$string['choiceoptions'] = 'Choice options (one per line)';
$string['choiceoptionsrequired'] = 'Please enter at least one option, one per line.';
$string['questionrequired'] = 'Answer required';
$string['questionsaved'] = 'Question saved.';
$string['questiondeleted'] = 'Question removed from this theme.';
$string['questionattached'] = 'Question linked to this theme.';
$string['noanswer'] = 'Not answered';
$string['noquestionsyet'] = 'No question defined for this form: a generic free comment will be used.';
$string['confirmdeletequestion'] = 'Remove this question from this theme? If it is not used by any other theme, it will be deleted along with its answers.';
$string['assignedthemes'] = 'Themes';
$string['assignedthemes_help'] = 'Select one or more themes: the same question (label, options) will be used for each of them, so you do not have to recreate it.';
$string['themesrequired'] = 'Please select at least one theme.';
$string['reusequestion'] = 'Attach';
$string['selectexistingquestion'] = 'Reuse an existing question...';
$string['savebulkchanges'] = 'Save changes';
$string['toggle'] = 'Toggle mandatory';
$string['evaluate'] = 'Evaluate';
$string['validate'] = 'Validate';
$string['selectall'] = 'Select all';
$string['bulkvalidateselected'] = 'Validate selection';
$string['pilotage'] = 'Pilotage dashboard';
$string['viewdetails'] = 'View details';
$string['pendingstages'] = 'Pending stages';
$string['totalretainedshort'] = 'Total retained duration';
$string['searchstudent'] = 'Search a student...';
$string['allthemes'] = 'All themes';
$string['allstatuses'] = 'All steps';
$string['resetfilters'] = 'Reset';
$string['registeredon'] = 'Registered on';
$string['markinvalid'] = 'Mark as not validated';
$string['rejectcomment'] = 'Reason for non-validation';
$string['entrynoteditable'] = 'This entry has already been evaluated and can no longer be edited. '
    . 'Only the DEVE can reset it to allow a new submission.';
$string['resetentry'] = 'Reset (allow a new submission)';
$string['entryreset'] = 'The entry has been reset: a new self-assessment is now possible.';
$string['confirmresetentry'] = 'Reset this entry? The student and the referent teacher will be able to edit it again.';
$string['cancelentry'] = 'Cancel this internship';
$string['confirmcancelentry'] = 'Cancel this internship? The entry will be kept as-is, but its status will '
    . 'permanently change to "Cancelled". Please give a reason below.';
$string['cancelcomment'] = 'Reason for cancellation';
$string['cancelledby'] = 'Cancelled by';
$string['canceltime'] = 'Cancellation date';
$string['stagecancelled'] = 'The internship has been cancelled.';
$string['evaluatedby'] = 'Evaluated by';
$string['onlyunassigned'] = 'Students without a referent only';
$string['selfevalnotifsubject'] = 'Internship self-assessment ready for review - {$a}';
$string['selfevalnotifbody'] = '{$a->student} has just self-assessed their internship "{$a->stage}". '
    . 'You can view and evaluate this entry here: {$a->url}';
$string['generateconvention'] = 'Generate the convention';
$string['includesignatureblock'] = 'Add a signature block (student, workplace supervisor, host '
    . 'organisation representative, referent teacher, establishment) at the bottom of the first '
    . 'page, for a printed convention to be signed by hand.';
$string['conventionsignatures'] = 'Signatures';
$string['conventionsignaturestudent'] = 'The student';
$string['conventionsignaturetutor'] = 'The workplace supervisor';
$string['conventionsignaturehostrepresentative'] = 'The host organisation representative';
$string['conventionsignaturereferentteacher'] = 'The referent teacher';
$string['conventionsignatureestablishment'] = 'The educational establishment';
$string['conventionsignaturename'] = 'Name: {$a}';
$string['conventionsignaturedelegation'] = 'By delegation of the head of establishment';
$string['conventionsignaturedelegationname'] = 'By delegation of the head of establishment: {$a}';
$string['conventionestablishmentsignatory'] = 'Person with signing delegation';
$string['conventionestablishmentsignatory_help'] = 'Name of the person holding a signing delegation from the '
    . 'head of establishment (in place of a signature by the head of establishment themselves). Pre-filled in '
    . 'the signature block of the printed convention, when that option is ticked when generating the convention.';
$string['conventiontitle'] = 'Internship agreement';
$string['conventionestablishment'] = 'Educational establishment';
$string['conventionestablishmentname'] = 'Name';
$string['conventionestablishmentaddress'] = 'Address';
$string['conventionestablishmentrepresentative'] = 'Represented by';
$string['conventionestablishmentrepresentativetitle'] = "Representative's title";
$string['conventionestablishmentphone'] = 'Phone';
$string['conventionestablishmentemail'] = 'Email';
$string['conventionestablishment_help'] = 'This information is displayed on page 1 of every agreement for this '
    . 'internship. Leave a field empty if not applicable; the name defaults to "VetAgro Sup" until set here.';
$string['conventionestablishmentsaved'] = 'The establishment information has been saved.';
$string['conventionhoststructure'] = 'Host organisation';
$string['conventionhoststructurename'] = 'Organisation';
$string['conventionstudent'] = 'The intern';
$string['conventionthemeduration'] = 'Theme and duration';
$string['conventionsupervision'] = 'Supervision';
$string['conventiontutor'] = 'Host organisation supervisor';
$string['conventiontemplatemissing'] = 'The PDF file of the selected agreement template is missing. '
    . 'Upload it again from the agreement templates page before generating this agreement.';
$string['conventionfpdimissing'] = 'The FPDI library required to generate conventions '
    . '(mod/stage/thirdparty/vendor) could not be found on this site. Contact an administrator.';

// Conventions: request, templates, logos, DEVE workflow.
$string['conventions'] = 'Internship agreements';
$string['requestconvention'] = 'Request the agreement';
$string['requestconvention_help'] = 'Choose the agreement template matching your internship. '
    . 'The DEVE will then process your request (edited, then signed); self-assessment will only '
    . 'be possible once the agreement has been signed.';
$string['conventionalreadyrequested'] = 'The agreement for this internship has already been requested.';
$string['conventionrequested'] = 'The agreement request has been sent to the DEVE.';
$string['conventionnotemplatechosen'] = 'No agreement template has been chosen for this internship.';
$string['conventionnotsignedyet'] = 'The internship agreement must be signed by the DEVE before you can '
    . 'self-assess. Check your agreement status on your dashboard.';
$string['conventionstatus'] = 'Agreement status';
$string['conventionstatus_none'] = 'Not requested';
$string['conventionstatus_requested'] = 'Requested';
$string['conventionstatus_edited'] = 'Edited';
$string['conventionstatus_signed'] = 'Signed';
$string['conventionstatus_signvet'] = 'Signed (StageVet)';
$string['conventionstatus_rejected'] = 'Rejected';
$string['conventionmarksigned'] = 'Mark as signed';
$string['conventionmarkedsigned'] = 'The agreement has been marked as signed: the student and the referent '
    . 'teacher can now proceed with the evaluations. If a signed PDF was provided, it can be downloaded by the '
    . 'student from their dashboard.';
$string['conventionsignedfile'] = 'Signed agreement (PDF)';
$string['conventionsignedfile_help'] = 'Optional: upload the PDF of the actually signed agreement (a scan of the '
    . 'paper document). If provided, the student will be able to download it from their dashboard. Either way, '
    . 'submitting this form moves the internship on to the evaluations.';
$string['conventionsignedfilemissing'] = 'The PDF of the signed agreement was not found.';
$string['downloadsignedconvention'] = 'Download the signed agreement';
$string['noconventionrequests'] = 'No agreement requests yet.';
$string['conventionreview'] = 'Generate the agreement';
$string['conventionreviewfor'] = 'Agreement request from {$a}: check and complete the information below if '
    . 'needed before validating, or reject the request with a comment so the student can correct it.';
$string['conventionteachervalidatefor'] = 'Agreement request from {$a}: check and correct the information below '
    . 'if needed, then validate to send it to the DEVE, or reject it with a comment so the student can correct it.';
$string['conventionnotrequested'] = 'This agreement is not (or no longer) awaiting review.';
$string['validateconvention'] = 'Validate';
$string['rejectconvention'] = 'Reject';
$string['conventionrejectcomment'] = 'Comment (sent to the student if rejected)';
$string['conventionrejected'] = 'The agreement request has been rejected. The student has been notified by email.';
$string['conventionrejectedwithcomment'] = 'Rejected: {$a}';
$string['conventionrejectedby'] = 'Rejected by';
$string['conventionvalidatedby'] = 'Validated by the referent teacher';
$string['conventioneditedby'] = 'Edited by';
$string['conventionsignedby'] = 'Signed by';
$string['conventionrejectedexplain'] = 'Your agreement request was rejected by the DEVE, for the following '
    . 'reason: "{$a}". Please correct your request below and submit it again.';
$string['conventionrejectednotifsubject'] = 'Internship agreement rejected: {$a}';
$string['conventionrejectednotifbody'] = "Your agreement request for the internship \"{\$a->stage}\" was rejected "
    . "by the DEVE, for the following reason:\n\n{\$a->comment}\n\n"
    . "Please correct your request and submit it again:\n{\$a->url}";
$string['noreferentteacheryet'] = 'No referent teacher has been assigned to you yet for this internship. '
    . 'Please contact the DEVE.';
$string['conventionvalidatedpdferror'] = 'The request was validated, but PDF generation failed: {$a} You will be '
    . 'able to re-download the agreement from this list once the issue is resolved.';
$string['conventionstatus_teacherpending'] = 'Awaiting referent teacher';
$string['conventionstatus_exempt'] = 'No convention';
$string['exemptfromconvention'] = 'Exempt from convention';
$string['exemptfromconvention_help'] = 'If checked, this internship does not require any convention: its convention status is set directly to "No convention" and the student\'s self-evaluation is immediately opened, without waiting for a request or a signature.';
$string['conventionrequireteachervalidation'] = 'Require referent teacher validation before sending to the DEVE';
$string['conventionrequireteachervalidation_help'] = 'If enabled, an agreement request submitted by a student '
    . 'must first be validated by one of their referent teachers before it appears in the list of requests to '
    . 'process for the DEVE. The teacher can also reject the request with a comment, sent back to the student '
    . 'for correction, exactly like a rejection by the DEVE.';
$string['conventionsettingssaved'] = 'The general agreement settings have been saved.';
$string['conventionteachervalidation'] = 'Agreement requests to validate';
$string['conventionteachervalidate'] = 'Validate the request';
$string['conventionteachervalidated'] = 'The agreement request has been validated and sent to the DEVE.';
$string['conventionteacherpendingnotifsubject'] = 'Internship agreement to validate: {$a}';
$string['conventionteacherpendingnotifbody'] = 'A student you supervise has submitted an agreement request for '
    . "the internship \"{\$a->stage}\", awaiting your validation before it is sent to the DEVE:\n{\$a->url}";
$string['noconventionteachervalidations'] = 'No agreement requests awaiting your validation.';
$string['generalsettings'] = 'General settings';
$string['conventiontemplates'] = 'Agreement templates';
$string['addconventiontemplate'] = 'Add a template';
$string['conventionrequestdate'] = 'Request date';
$string['conventiontemplatename'] = 'Template name';
$string['conventiontemplatefile'] = 'PDF file (articles, pages 2 to 4)';
$string['conventiontemplatefilerequired'] = 'Please select a PDF file.';
$string['conventiontemplatesaved'] = 'The template has been saved.';
$string['conventiontemplatedeleted'] = 'The template has been deleted.';
$string['conventiontemplateinuse'] = 'Cannot delete: this template is used by at least one agreement request.';
$string['confirmdeleteconventiontemplate'] = 'Delete this agreement template?';
$string['noconventiontemplatesyet'] = 'No agreement template has been created by the DEVE yet.';
$string['conventionlogos'] = 'Agreement logos';
$string['conventionlogos_help'] = 'These two logos (PNG) are displayed at the top of page 1 of every '
    . 'agreement for this internship activity: one on the left, one on the right.';
$string['conventionlogoleft'] = 'Top-left logo';
$string['conventionlogoright'] = 'Top-right logo';
$string['conventionlogossaved'] = 'The logos have been saved.';
$string['conventionlang'] = 'Agreement language';
$string['conventionlang_fr'] = 'French (standard)';
$string['conventionlang_en'] = 'English';
$string['conventiontemplatelangmismatch'] = 'The selected template does not match the chosen language.';

// Convention: additional information requested from the student.
$string['conventionyearsituation'] = 'Situation';
$string['conventionyearsituation_normal'] = 'Normal year';
$string['conventionyearsituation_redoublant'] = 'Repeating year';
$string['conventionyearsituation_detteue'] = 'Outstanding credit (dette d\'UE)';
$string['conventionstagetype'] = 'Internship type';
$string['conventionstagetype_obligatoire'] = 'Mandatory internship';
$string['conventionstagetype_complementaire'] = 'Additional internship (EP)';
$string['conventionreferentteacher'] = 'Referent teacher';
$string['conventionreferentteacherstatus'] = 'Status';
$string['conventionreferentteacherstatusvalue'] = 'Teacher';
$string['conventionreferentteacheremail'] = 'Email';
$string['conventionbirthdate'] = 'Date of birth';
$string['conventionstudentaddress'] = 'Address';
$string['conventionstudentphone'] = 'Phone';
$string['conventionhostaddress'] = 'Host organisation address';
$string['conventionhostrepresentative'] = 'Represented by';
$string['conventionhostrepresentativetitle'] = "Representative's title";
$string['conventionhostservice'] = 'Department where the internship will take place';
$string['conventionhostphone'] = 'Host organisation phone';
$string['conventionhostemail'] = 'Host organisation email';
$string['conventionhostlocation'] = 'Internship location (if different from the host organisation address)';
$string['conventionhostlocation_help'] = 'Fill this in only if the internship takes place at an address different from the host organisation\'s.';
$string['conventiontutorname'] = 'Name of the host organisation supervisor';
$string['conventiontutorfunction'] = 'Role';
$string['conventiontutorphone'] = 'Phone';
$string['conventiontutoremail'] = 'Email';
$string['conventionmodalities'] = 'Specific internship arrangements (art. 3.2)';
$string['conventionnightpresence'] = 'Night presence';
$string['conventionsundaypresence'] = 'Sunday presence';
$string['conventionholidaypresence'] = 'Public holiday presence';
$string['conventionhomebased'] = 'Home-based internship';
$string['conventionothermodality'] = 'Other specific arrangement';
$string['conventiongratification'] = 'Monthly allowance amount (in euros)';
$string['conventionleave'] = 'Leave and absence authorisations (art. 10.1)';
$string['conventionhasleave'] = 'This internship includes leave or absence authorisations';
$string['conventionleavedays'] = 'Number of leave days';
$string['conventionleavemodalities'] = 'Leave and absence authorisation arrangements';

// Messages.
$string['stagesaved'] = 'Internship saved.';
$string['themesaved'] = 'Theme saved.';
$string['themedeleted'] = 'Theme deleted.';
$string['themevisibilitytoggled'] = "The theme's activation has been updated.";
$string['themevisible_help'] = 'Click Yes/No in the "Visible" column to activate or deactivate a theme for this '
    . 'course. A deactivated theme is no longer offered when registering an internship (by the DEVE or the '
    . 'student), but stays listed here and on internships already registered on it.';
$string['themeinuse'] = 'Cannot delete: internships use this theme.';
$string['bulkthemessaved'] = 'Themes updated.';
$string['teachersassigned'] = 'Referent teachers updated.';
$string['evalsaved'] = 'Evaluation saved.';
$string['bulkvalidated'] = '{$a} internship(s) validated.';
$string['bulkregistered'] = '{$a} internship(s) registered.';
$string['bulkduplicatesskipped'] = 'Already registered on this theme with these same dates, skipped: {$a}';
$string['nothemesyet'] = 'No theme has been created yet.';
$string['nomandatorythemes'] = 'No mandatory theme for now.';
$string['nostages'] = 'No internship declared.';
$string['nostudents'] = 'No student enrolled in this course.';
$string['noteachers'] = 'No potential referent teacher enrolled in this course.';
$string['noassignedstudents'] = 'No student assigned to you yet.';
$string['nopendingstages'] = 'No internship pending validation.';
$string['confirmdeletetheme'] = 'Delete this theme?';
$string['totalretained'] = 'Total retained duration: {$a} days';
$string['totalcomplementary'] = 'Including complementary internships (EP): {$a} days (not counted)';
$string['complementarystages'] = 'Complementary internships (EP)';
$string['summary'] = 'Summary';
$string['summaryitem'] = 'Indicator';
$string['summaryvalue'] = 'Value';
$string['summarytotaldays'] = 'Total duration retained';
$string['summaryyearsdone'] = 'Years completed';
$string['summarythemesdone'] = 'Mandatory themes completed';
$string['summaryabroaddays'] = 'International mobility';
$string['summarycomplementarydays'] = 'Including complementary internships (EP, not counted)';
$string['progressofdays'] = '{$a->retained} / {$a->required} days';
$string['retaineddaysonly'] = '{$a} days';
$string['remainingduration'] = 'Remaining (days)';
$string['objective'] = 'Objective';
$string['yeartotalobjective'] = 'Total duration for the year';
$string['completebyyear'] = 'To be completed by';
$string['numstages'] = '{$a} internship(s) declared';

// Headings.
$string['evaluatestage'] = 'Evaluate the internship of {$a}';
$string['validatestage'] = 'Validate the internship of {$a}';

// Workplace tutor evaluation and email customization.
$string['tutorevaluationenabled'] = 'Enable evaluation by the workplace tutor';
$string['tutorevaluationenabled_help'] = "If enabled, the workplace tutor (the student's supervisor in the "
    . "host organisation, who has no Moodle account) receives an email with a unique token link letting them "
    . "answer the evaluation questionnaire defined for the internship's theme, as soon as the student "
    . "self-assesses. Their answer is then displayed to the referent teacher and to the administration "
    . "office when they carry out their own evaluation.";
$string['emailkeyselfeval'] = 'Self-assessment notification (to the referent teacher)';
$string['emailkeyteacherpending'] = 'Agreement pending validation notification (to the referent teacher)';
$string['emailkeystudentrejected'] = 'Agreement rejected notification (to the student)';
$string['emailkeytutorrequest'] = 'Invitation to evaluate the internship (to the workplace tutor)';
$string['tutorevalnotifsubject'] = 'Evaluation of {$a}\'s internship';
$string['tutorevalnotifbody'] = 'You are currently supervising {$a->student} during their internship '
    . '"{$a->stage}". Please evaluate this internship by following this link, which does not require an '
    . "account:\n{\$a->url}";
$string['evaltype_tutor'] = 'Workplace tutor';
$string['tutorevalheading'] = 'Workplace tutor evaluation';
$string['notutoreval'] = 'The workplace tutor has not yet answered their evaluation questionnaire.';
$string['tutorevalpagetitle'] = 'Internship evaluation';
$string['tutorevalinvalidtoken'] = 'This evaluation link is no longer valid.';
$string['tutorevalalreadysubmitted'] = 'Your evaluation has been recorded, thank you.';
$string['tutorevalsubmit'] = 'Submit my evaluation';
$string['tutorevalintro'] = 'You are supervising {$a->student} during their internship "{$a->stage}". '
    . 'Please answer the evaluation questionnaire below.';
$string['notifications'] = 'Notifications and emails';
$string['notificationssettings'] = 'Customize the emails sent';
$string['notificationssettings_help'] = "For each email sent by the activity, you can replace the subject "
    . "and body with custom text. Leave both fields empty to use the default text. Custom text is not a "
    . "language string: use the {{variable}} syntax (double curly braces) to insert the available variables, "
    . "listed under each email.";
$string['notificationssaved'] = 'The custom emails have been saved.';
$string['emailsubject'] = 'Subject';
$string['emailbody'] = 'Message body';
$string['emailavailablevars'] = 'Available variables: {$a}';
$string['emailresettodefault'] = 'Leave both fields empty to use the default text.';
