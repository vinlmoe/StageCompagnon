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
 * Export au format Excel (xlsx), pour la DEVE, en trois feuilles :
 *
 * - « Bilan de promotion » : une ligne par étudiant inscrit, avec la validation de l'année
 *   d'étude courante et des précédentes, les étudiants en défaut en tête (voir
 *   stage_get_promotion_report()). Les étudiants sans aucune saisie y figurent aussi, alors
 *   qu'ils étaient absents de l'export : ce sont précisément ceux à relancer en premier.
 * - « Stages » : une ligne par saisie, le détail complet pour retravailler les données. Toutes
 *   les informations de la saisie y figurent : caractéristiques du stage, convention (statut,
 *   étapes datées, et la totalité des informations saisies pour sa page 1), évaluations
 *   (étudiant, enseignant référent, maître de stage), rapport de stage déposé, validation DEVE et
 *   annulation.
 * - « Réponses aux questionnaires » : une ligne par réponse, seul moyen d'exporter les
 *   questionnaires définis par thématique, dont les réponses ne tiendraient pas en colonnes
 *   fixes (les questions diffèrent d'une thématique à l'autre).
 *
 * Le bilan destiné à être imprimé ou diffusé est produit en PDF par export_pdf.php.
 *
 * Le classeur est écrit en flux par OpenSpout, ligne après ligne, plutôt que construit en mémoire
 * par MoodleExcelWorkbook (PhpSpreadsheet) : celui-ci crée un objet et applique un style pour
 * chaque cellule, ce qui coûtait plus d'une minute et plus de 500 Mo pour quelques milliers de
 * stages, quand les requêtes en base ne prennent qu'une fraction de seconde. Les cellules sont
 * typées comme le faisait MoodleExcelWorksheet : nombres en cellules numériques, dates en cellules
 * date au format jj/mm/aaaa, textes en cellules texte (jamais en formules), protégés comme le fait
 * write_string() lorsque la version de Moodle le fait.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:viewall', $context);

// Une promotion entière (plusieurs milliers de saisies, leurs réponses et leurs évaluations) dépasse
// largement les limites d'une page ordinaire.
core_php_time_limit::raise();
raise_memory_limit(MEMORY_HUGE);

$filename = clean_filename(stage_plain_string($course->shortname) . '_' . stage_plain_string($stage->name) . '_stages.xlsx');

// Mêmes formats que MoodleExcelWorksheet : format « texte » pour les textes, général pour les
// nombres, jj/mm/aaaa pour les dates, en-têtes en gras. Le format texte est porté par la ligne
// plutôt que par chaque cellule : OpenSpout fusionne le style d'une cellule avec celui de sa
// ligne, à chaque cellule, ce qui triplait le temps d'écriture. Seuls les nombres et les dates,
// minoritaires, portent leur propre style.
$textstyle = (new \OpenSpout\Common\Entity\Style\Style())->setFormat('@');
$headerstyle = (new \OpenSpout\Common\Entity\Style\Style())->setFontBold()->setFormat('@');
$numberstyle = (new \OpenSpout\Common\Entity\Style\Style())->setFormat('General');
$datestyle = (new \OpenSpout\Common\Entity\Style\Style())->setFormat('dd/mm/yyyy');
$usertimezone = core_date::get_user_timezone_object();
// Protection des textes commençant par = + - ou @, appliquée par write_string() dans les versions
// de Moodle qui la connaissent : la restauration de l'export (global_export_importer) la retire.
$escapeformula = method_exists(\core\dataformat::class, 'escape_spreadsheet_formula');

$textcell = function ($value) use ($escapeformula) {
    $value = (string) $value;
    if ($escapeformula) {
        $value = (string) \core\dataformat::escape_spreadsheet_formula($value);
    }
    return new \OpenSpout\Common\Entity\Cell\StringCell($value, null);
};
$numbercell = function ($value) use ($numberstyle) {
    return new \OpenSpout\Common\Entity\Cell\NumericCell($value, $numberstyle);
};
// Date au format du tableur, vide si l'étape n'a pas eu lieu : une cellule vide se filtre et se
// trie, contrairement à un « - » ou à un horodatage à 0 affiché comme 01/01/1970. Comme
// write_date(), la date et l'heure sont celles du fuseau de l'utilisateur ; le format jj/mm/aaaa
// est celui que demandait l'ancien export, que MoodleExcelFormat ignorait (il n'accepte que
// quelques formats prédéfinis : les dates s'affichaient avec l'heure, au format m/d/yy h:mm).
$datecell = function ($timestamp) use ($textcell, $datestyle, $usertimezone) {
    if (empty($timestamp)) {
        return $textcell('');
    }
    $datetime = (new DateTimeImmutable('@' . (int) $timestamp))->setTimezone($usertimezone);
    return new \OpenSpout\Common\Entity\Cell\DateTimeCell($datetime, $datestyle);
};
// Cellule laissée vierge (ni valeur ni format), comme une cellule où l'ancien export n'écrivait
// rien : le format général l'emporte sur le format texte de la ligne, et OpenSpout n'écrit pas
// une cellule vide sans format particulier.
$blankcell = function () use ($numberstyle) {
    return new \OpenSpout\Common\Entity\Cell\EmptyCell(null, $numberstyle);
};
// Nom de feuille nettoyé comme le fait MoodleExcelWorksheet (caractères interdits, 31 caractères).
$sheetname = function ($name) {
    $name = trim(core_text::substr(strtr(trim($name, "'"), '[]*/\\?:', '       '), 0, 31), "'");
    return $name === '' ? 'Sheet' : $name;
};
$headerrow = function (array $headers) use ($textcell, $headerstyle) {
    return new \OpenSpout\Common\Entity\Row(array_map($textcell, $headers), $headerstyle);
};

\core\session\manager::write_close();
$writer = new \OpenSpout\Writer\XLSX\Writer();
if (method_exists($writer->getOptions(), 'setTempFolder')) {
    $writer->getOptions()->setTempFolder(make_request_directory());
}
$writer->openToBrowser($filename);

// ---------------------------------------------------------------------------------------------
// Feuille 1 : bilan de promotion.

$report = stage_get_promotion_report($stage, $context);

$writer->getCurrentSheet()->setName($sheetname(get_string('promotionreport', 'mod_stage')));

$headers = [get_string('student', 'mod_stage'), get_string('email')];
foreach ($report->years as $year) {
    $headers[] = stage_studyyear_label($year);
}
$headers = array_merge($headers, [
    get_string('promotionfailedyears', 'mod_stage'),
    get_string('totalretainedshort', 'mod_stage'),
    get_string('mandatorythemes', 'mod_stage'),
    get_string('summaryabroaddays', 'mod_stage'),
    get_string('pendingstages', 'mod_stage'),
    get_string('status', 'mod_stage'),
]);
$writer->addRow($headerrow($headers));

foreach ($report->rows as $reportrow) {
    $cells = [];
    $cells[] = $textcell(fullname($reportrow->user));
    $cells[] = $textcell((string) $reportrow->user->email);
    foreach ($report->years as $year) {
        // Une année sans objectif pour cet étudiant n'est ni validée ni en défaut : un tiret,
        // plutôt qu'un « non » qui se lirait comme un manquement.
        $cells[] = $textcell(!isset($reportrow->yearsdone[$year])
            ? '-'
            : ($reportrow->yearsdone[$year] ? get_string('yes') : get_string('no')));
    }
    $cells[] = $textcell(implode(', ', array_map(function ($year) {
        return stage_studyyear_label($year);
    }, $reportrow->failedyears)));
    $cells[] = $numbercell((int) $reportrow->progress->totalretained);
    $cells[] = $textcell($reportrow->mandatorytotal > 0
        ? "{$reportrow->mandatorydone} / {$reportrow->mandatorytotal}" : '-');
    // Une mobilité d'études validée par la DEVE remplace le décompte des jours à l'étranger.
    if ($reportrow->abroadprogress->studymobility !== null) {
        $abroadcell = get_string('studymobilitysummary', 'mod_stage', $reportrow->abroadprogress->studymobility->location);
    } else {
        $abroadcell = $reportrow->abroadprogress->required > 0
            ? "{$reportrow->abroadprogress->retained} / {$reportrow->abroadprogress->required}" : '-';
    }
    $cells[] = $textcell($abroadcell);
    $cells[] = $numbercell((int) $reportrow->pendingcount);
    $cells[] = $textcell($reportrow->uptodate
        ? get_string('promotionuptodate', 'mod_stage') : get_string('themetodo', 'mod_stage'));
    $writer->addRow(new \OpenSpout\Common\Entity\Row($cells, $textstyle));
}

// ---------------------------------------------------------------------------------------------
// Feuille 2 : détail des saisies.

$entries = $DB->get_records('stage_entry', ['stageid' => $stage->id], 'timecreated ASC');
$students = stage_get_entry_users($entries);
$themes = stage_get_themes($stage->id);
$stagetypes = stage_get_entry_stagetypes(array_keys($entries));
$entryids = array_keys($entries);

// Toutes les données rattachées aux saisies sont résolues en une requête chacune plutôt qu'une
// par saisie : sur une promotion entière (plusieurs centaines de saisies), une requête par saisie
// et par information suffisait à faire expirer l'export.
$conventiondetails = [];
$workdaycounts = [];
$reportfilenames = [];
$periodsbyentry = [];
if (!empty($entryids)) {
    // Sous-requête plutôt que liste IN des identifiants : une promotion entière dépasse la limite
    // de paramètres de MSSQL (2 100) ou de liste d'Oracle (1 000), voire de PostgreSQL (65 535).
    [$insql, $inparams] = stage_entry_ids_subquery($stage->id, 'export');

    $conventiondetails = $DB->get_records_select(
        'stage_convention_detail',
        "entryid $insql",
        $inparams,
        '',
        '*'
    );
    $conventiondetails = array_combine(array_map(function ($detail) {
        return $detail->entryid;
    }, $conventiondetails), $conventiondetails);

    $workdaycounts = $DB->get_records_sql_menu(
        "SELECT entryid, COUNT(1) FROM {stage_entry_workday} WHERE entryid $insql GROUP BY entryid",
        $inparams
    );

    // Les rapports de stage sont interrogés directement dans la table des fichiers : l'API
    // fichiers ne sait lire qu'une zone (donc une saisie) à la fois.
    $fileparams = $inparams + [
        'contextid' => $context->id, 'component' => 'mod_stage', 'filearea' => STAGE_REPORT_FILEAREA,
    ];
    $filerecords = $DB->get_records_sql(
        "SELECT id, itemid, filename
           FROM {files}
          WHERE contextid = :contextid AND component = :component AND filearea = :filearea
                AND filename <> '.' AND itemid $insql
       ORDER BY filename ASC",
        $fileparams
    );
    foreach ($filerecords as $filerecord) {
        $reportfilenames[$filerecord->itemid][] = $filerecord->filename;
    }

    $periodrecords = $DB->get_recordset_select('stage_entry_period', "entryid $insql", $inparams, 'entryid ASC, datestart ASC');
    foreach ($periodrecords as $period) {
        $periodsbyentry[(int) $period->entryid][] = $period;
    }
    $periodrecords->close();
}

// Les enseignants responsables sont attribués par thématique : une seule résolution par
// thématique, même si elle porte des dizaines de saisies.
$themeteachers = [];
foreach (stage_get_stage_theme_teachers($stage->id) as $exportedthemeid => $exportedteachers) {
    $themeteachers[$exportedthemeid] = implode(', ', array_map('fullname', $exportedteachers));
}

// L'évaluateur (stage_entry.teacherid) et les enseignants référents attribués à l'étudiant sont
// deux informations distinctes : la colonne unique de l'ancien export était intitulée
// « enseignants référents » mais contenait l'évaluateur. Les deux figurent désormais.
// Les autres intervenants (DEVE, convention, annulation) sont résolus dans le même lot.
$actorids = [];
foreach ($entries as $entry) {
    foreach (
        [$entry->teacherid, $entry->deveuserid, $entry->cancelledby, $entry->conventionrejectedby,
            $entry->conventionteachervalidatedby, $entry->conventionthemevalidatedby, $entry->conventioneditedby,
            $entry->conventionsignedby] as $actorid
    ) {
        if (!empty($actorid)) {
            $actorids[(int) $actorid] = (int) $actorid;
        }
    }
}
foreach ($conventiondetails as $detail) {
    if (!empty($detail->referentteacherid)) {
        $actorids[(int) $detail->referentteacherid] = (int) $detail->referentteacherid;
    }
}
$actors = $actorids ? $DB->get_records_list('user', 'id', $actorids, '', 'id, ' . implode(
    ', ',
    \core_user\fields::get_name_fields()
)) : [];
// Les référents sont attribués par étudiant : résolus pour toute la promotion en une requête.
$referentsbyuser = [];
foreach (stage_get_stage_student_teachers($stage->id) as $referentstudentid => $referents) {
    $referentsbyuser[$referentstudentid] = implode(', ', array_map('fullname', $referents));
}

// Nom d'un intervenant à partir de son id, vide s'il n'y en a pas (colonne laissée vide plutôt
// qu'un identifiant numérique, illisible dans un tableur).
$actorname = function ($userid) use ($actors) {
    return !empty($userid) && isset($actors[$userid]) ? fullname($actors[$userid]) : '';
};

$yesno = function ($value) {
    return !empty($value) ? get_string('yes') : get_string('no');
};

$writer->addNewSheetAndMakeItCurrent()->setName($sheetname(get_string('allstages', 'mod_stage')));

$headers = [
    // Identifiant de la saisie : sans lui, les réponses de la feuille 3 ne pourraient pas être
    // rattachées à leur stage (un étudiant peut avoir plusieurs stages sur la même thématique).
    get_string('exportentryid', 'mod_stage'),
    // Étudiant et thématique.
    get_string('student', 'mod_stage'),
    get_string('email'),
    get_string('studyyear', 'mod_stage'),
    get_string('conventionyearsituation', 'mod_stage'),
    get_string('theme', 'mod_stage'),
    get_string('mandatory', 'mod_stage'),
    get_string('themeteachers', 'mod_stage'),
    // Caractéristiques du stage.
    get_string('conventionstagetype', 'mod_stage'),
    get_string('structure', 'mod_stage'),
    get_string('abroad', 'mod_stage'),
    get_string('country', 'mod_stage'),
    get_string('datestart', 'mod_stage'),
    get_string('dateend', 'mod_stage'),
    get_string('periods', 'mod_stage'),
    get_string('workdayscount', 'mod_stage'),
    get_string('declaredduration', 'mod_stage'),
    get_string('retainedduration', 'mod_stage'),
    get_string('status', 'mod_stage'),
    // Convention : statut et étapes datées.
    get_string('conventionstatus', 'mod_stage'),
    get_string('conventiontemplatename', 'mod_stage'),
    get_string('conventionreferentteacher', 'mod_stage'),
    get_string('conventionrequesttime', 'mod_stage'),
    get_string('conventionteachervalidatedby', 'mod_stage'),
    get_string('conventionteachervalidatetime', 'mod_stage'),
    get_string('conventionthemevalidatedby', 'mod_stage'),
    get_string('conventionthemevalidatetime', 'mod_stage'),
    get_string('conventioneditedby', 'mod_stage'),
    get_string('conventionedittime', 'mod_stage'),
    get_string('conventionsignedby', 'mod_stage'),
    get_string('conventionsigntime', 'mod_stage'),
    get_string('conventionrejectedby', 'mod_stage'),
    get_string('conventionrejecttime', 'mod_stage'),
    get_string('conventionrejectcomment', 'mod_stage'),
    // Convention : informations saisies pour sa page 1.
    get_string('conventionbirthdate', 'mod_stage'),
    get_string('conventionstudentaddress', 'mod_stage'),
    get_string('conventionstudentphone', 'mod_stage'),
    get_string('conventionhostaddress', 'mod_stage'),
    get_string('conventionhostrepresentative', 'mod_stage'),
    get_string('conventionhostrepresentativetitle', 'mod_stage'),
    get_string('conventionhostservice', 'mod_stage'),
    get_string('conventionhostphone', 'mod_stage'),
    get_string('conventionhostemail', 'mod_stage'),
    get_string('conventionsiret', 'mod_stage'),
    get_string('conventionhostlocation', 'mod_stage'),
    get_string('conventiontutorname', 'mod_stage'),
    get_string('conventiontutorfunction', 'mod_stage'),
    get_string('conventiontutorphone', 'mod_stage'),
    get_string('conventiontutoremail', 'mod_stage'),
    get_string('conventionnightpresence', 'mod_stage'),
    get_string('conventionsundaypresence', 'mod_stage'),
    get_string('conventionholidaypresence', 'mod_stage'),
    get_string('conventionhomebased', 'mod_stage'),
    get_string('conventionothermodality', 'mod_stage'),
    get_string('conventiongratification', 'mod_stage'),
    get_string('conventionhasleave', 'mod_stage'),
    get_string('conventionleavedays', 'mod_stage'),
    get_string('conventionleavemodalities', 'mod_stage'),
    // Évaluations.
    get_string('studentselfeval', 'mod_stage'),
    get_string('referentteachers', 'mod_stage'),
    get_string('evaluatedby', 'mod_stage'),
    get_string('teachervalidationtime', 'mod_stage'),
    get_string('teachereval', 'mod_stage'),
    get_string('tutorevalheading', 'mod_stage'),
    get_string('tutorevaltime', 'mod_stage'),
    get_string('tutorevalbypassedcolumn', 'mod_stage'),
    get_string('exporttutorrequesttime', 'mod_stage'),
    get_string('exportconventionremindertime', 'mod_stage'),
    // Rapport de stage déposé.
    get_string('reportmode', 'mod_stage'),
    get_string('reportfilescount', 'mod_stage'),
    get_string('reportfiles', 'mod_stage'),
    // Validation DEVE, annulation et traçabilité.
    get_string('devevalidatedby', 'mod_stage'),
    get_string('devevalidationtime', 'mod_stage'),
    get_string('devecomment', 'mod_stage'),
    get_string('cancelledby', 'mod_stage'),
    get_string('canceltime', 'mod_stage'),
    get_string('cancelcomment', 'mod_stage'),
    get_string('timecreated', 'mod_stage'),
    get_string('lastmodified'),
];
$writer->addRow($headerrow($headers));

// Les plages sont écrites en JJ/MM/AAAA, quelle que soit la langue : la restauration de l'export
// (import_global.php) doit pouvoir les relire.
$exportdateformat = '%d/%m/%Y';
$stagetypeoptions = stage_convention_stagetype_options();
$yearsituationoptions = stage_convention_yearsituation_options();
$reportmodeoptions = stage_report_mode_options();
$conventiontemplates = stage_get_convention_templates($stage->id);

foreach ($entries as $entry) {
    $student = $students[$entry->userid] ?? null;
    $theme = $themes[$entry->themeid] ?? null;

    $periods = stage_get_or_seed_entry_periods($entry, $periodsbyentry[(int) $entry->id] ?? []);
    $periodlabels = array_map(function ($period) use ($exportdateformat) {
        return userdate($period->datestart, $exportdateformat) . ' - ' . userdate($period->dateend, $exportdateformat);
    }, $periods);
    $stagetype = $stagetypes[$entry->id] ?? 'obligatoire';

    $detail = $conventiondetails[$entry->id] ?? null;
    $reportfiles = $reportfilenames[$entry->id] ?? [];
    // Le gabarit est porté par la saisie elle-même : une saisie enregistrée par la DEVE peut en
    // avoir un sans que l'étudiant ait rempli le détail de sa convention.
    $template = !empty($entry->conventiontemplateid)
        ? ($conventiontemplates[$entry->conventiontemplateid] ?? null) : null;

    // Une valeur du détail de convention, vide tant que l'étudiant n'a pas rempli sa demande.
    $detailvalue = function ($field) use ($detail) {
        return $detail !== null ? (string) $detail->$field : '';
    };

    $cells = [];
    $cells[] = $numbercell((int) $entry->id);
    $cells[] = $textcell($student ? fullname($student) : '');
    $cells[] = $textcell($student ? $student->email : '');
    $cells[] = $textcell(stage_studyyear_label($entry->studyyear));
    $cells[] = $textcell($detail !== null
        ? ($yearsituationoptions[$detail->yearsituation] ?? $detail->yearsituation) : '');
    $cells[] = $textcell($theme ? stage_plain_string($theme->name) : '');
    $cells[] = $textcell($yesno($theme && $theme->mandatory));
    $cells[] = $textcell($theme ? ($themeteachers[$theme->id] ?? '') : '');

    $cells[] = $textcell($stagetypeoptions[$stagetype] ?? $stagetype);
    $cells[] = $textcell((string) $entry->structure);
    $cells[] = $textcell($yesno($entry->abroad));
    $cells[] = $textcell((string) $entry->country);
    $cells[] = $datecell($entry->datestart);
    $cells[] = $datecell($entry->dateend);

    // Les plages ne sont détaillées que quand il y en a plusieurs : sinon elles répètent
    // exactement les deux colonnes de dates qui précèdent.
    $cells[] = $textcell(count($periodlabels) > 1 ? implode(' ; ', $periodlabels) : '');

    $cells[] = $numbercell((int) ($workdaycounts[$entry->id] ?? 0));
    $cells[] = $numbercell((int) $entry->declaredduration);
    $cells[] = $numbercell((int) $entry->retainedduration);
    $cells[] = $textcell(stage_status_label($entry->status));

    $cells[] = $textcell(stage_convention_status_label($entry->conventionstatus));
    $cells[] = $textcell($template ? stage_plain_string($template->name) : '');
    $cells[] = $textcell($detail !== null ? $actorname($detail->referentteacherid) : '');
    $cells[] = $datecell($entry->conventionrequesttime);
    $cells[] = $textcell($actorname($entry->conventionteachervalidatedby));
    $cells[] = $datecell($entry->conventionteachervalidatetime);
    $cells[] = $textcell($actorname($entry->conventionthemevalidatedby));
    $cells[] = $datecell($entry->conventionthemevalidatetime);
    $cells[] = $textcell($actorname($entry->conventioneditedby));
    $cells[] = $datecell($entry->conventionedittime);
    $cells[] = $textcell($actorname($entry->conventionsignedby));
    $cells[] = $datecell($entry->conventionsigntime);
    $cells[] = $textcell($actorname($entry->conventionrejectedby));
    $cells[] = $datecell($entry->conventionrejecttime);
    $cells[] = $textcell((string) $entry->conventionrejectcomment);

    $cells[] = $datecell($detail !== null ? $detail->studentbirthdate : null);
    $cells[] = $textcell($detailvalue('studentaddress'));
    $cells[] = $textcell($detailvalue('studentphone'));
    $cells[] = $textcell($detailvalue('hostaddress'));
    $cells[] = $textcell($detailvalue('hostrepresentative'));
    $cells[] = $textcell($detailvalue('hostrepresentativetitle'));
    $cells[] = $textcell($detailvalue('hostservice'));
    $cells[] = $textcell($detailvalue('hostphone'));
    $cells[] = $textcell($detailvalue('hostemail'));
    $cells[] = $textcell($detailvalue('siret'));
    $cells[] = $textcell($detailvalue('hostlocation'));
    $cells[] = $textcell($detailvalue('tutorname'));
    $cells[] = $textcell($detailvalue('tutorfunction'));
    $cells[] = $textcell($detailvalue('tutorphone'));
    $cells[] = $textcell($detailvalue('tutoremail'));
    $cells[] = $textcell($detail !== null ? $yesno($detail->nightpresence) : '');
    $cells[] = $textcell($detail !== null ? $yesno($detail->sundaypresence) : '');
    $cells[] = $textcell($detail !== null ? $yesno($detail->holidaypresence) : '');
    $cells[] = $textcell($detail !== null ? $yesno($detail->homebased) : '');
    $cells[] = $textcell($detailvalue('othermodality'));
    $cells[] = $textcell($detailvalue('gratificationamount'));
    $cells[] = $textcell($detail !== null ? $yesno($detail->hasleave) : '');
    $cells[] = $textcell($detailvalue('leavedays'));
    $cells[] = $textcell($detailvalue('leavemodalities'));

    // Les évaluations sont exportées en texte : les réponses aux questionnaires définis par
    // thématique, elles, ne tiennent pas en colonnes fixes et font l'objet de la feuille 3. Un
    // texte brut (évaluation importée de StageVet, une information par ligne) garde ses retours à
    // la ligne ; seul un texte saisi dans l'éditeur est converti depuis le HTML.
    $selfeval = (string) $entry->studentselfeval;
    $cells[] = $textcell($selfeval !== strip_tags($selfeval) ? html_to_text($selfeval, 0, false) : $selfeval);
    $cells[] = $textcell($referentsbyuser[$entry->userid] ?? '');
    // L'évaluateur figure parmi les intervenants résolus plus haut (stage_entry.teacherid).
    $cells[] = $textcell($actorname($entry->teacherid));
    $cells[] = $datecell($entry->teachertime);
    $cells[] = $textcell((string) $entry->teachereval);
    $cells[] = $textcell((string) $entry->tutoreval);
    $cells[] = $datecell($entry->tutortime);
    $cells[] = $textcell($yesno($entry->tutorbypassed));
    // Messages automatiques déjà envoyés : relus par la restauration, pour ne pas les renvoyer.
    $cells[] = $datecell($entry->tutorrequesttime);
    $cells[] = $datecell($entry->conventionremindertime);

    $cells[] = $textcell($theme
        ? ($reportmodeoptions[stage_theme_report_mode($theme)] ?? '') : '');
    $cells[] = $numbercell(count($reportfiles));
    $cells[] = $textcell(implode(' ; ', $reportfiles));

    $cells[] = $textcell($actorname($entry->deveuserid));
    $cells[] = $datecell($entry->devetime);
    $cells[] = $textcell((string) $entry->devecomment);
    $cells[] = $textcell($actorname($entry->cancelledby));
    $cells[] = $datecell($entry->canceltime);
    $cells[] = $textcell((string) $entry->cancelcomment);
    $cells[] = $datecell($entry->timecreated);
    $cells[] = $datecell($entry->timemodified);

    $writer->addRow(new \OpenSpout\Common\Entity\Row($cells, $textstyle));
}

// ---------------------------------------------------------------------------------------------
// Feuille 3 : réponses aux questionnaires.

// Les questions étant définies par thématique, leurs réponses ne peuvent pas tenir en colonnes
// fixes de la feuille 2 : une ligne par réponse, avec de quoi la rattacher à sa saisie et la
// filtrer par thématique ou par type d'évaluation.
$writer->addNewSheetAndMakeItCurrent()->setName($sheetname(get_string('exportanswers', 'mod_stage')));

$headers = [
    get_string('exportentryid', 'mod_stage'),
    get_string('student', 'mod_stage'),
    get_string('theme', 'mod_stage'),
    get_string('datestart', 'mod_stage'),
    get_string('dateend', 'mod_stage'),
    get_string('evaltype', 'mod_stage'),
    get_string('questionlabel', 'mod_stage'),
    get_string('answer', 'mod_stage'),
    get_string('exportratingoutoffive', 'mod_stage'),
    get_string('exportnumericvalue', 'mod_stage'),
    get_string('qtype', 'mod_stage'),
];
$writer->addRow($headerrow($headers));

if (!empty($entryids)) {
    // Réponses aux questionnaires de l'activité, puis notes des évaluations textuelles (celles
    // importées de StageVet : une ligne « libellé : N/5 » par item, voir
    // stage_parse_evaluation_text()), pour que toutes les notes se filtrent et se comptent au
    // même endroit. La colonne de la note reste vide pour une réponse sans note ; celle de la
    // valeur numérique porte le nombre d'une question « nombre » et 1 ou 0 pour un oui/non (voir
    // stage_answer_export_values()). La réponse elle-même est écrite en clair : « Oui », date mise
    // en forme, cases cochées séparées par « ; ». Les stages se suivent par identifiant croissant.
    $qtypes = stage_question_types();

    // Notes des évaluations textuelles, par stage : peu nombreuses, calculées d'avance.
    $textlines = [];
    foreach ($entryids as $entryid) {
        $entry = $entries[$entryid] ?? null;
        if (!$entry) {
            continue;
        }
        foreach (['student' => (string) $entry->studentselfeval, 'tutor' => (string) $entry->tutoreval] as $evaltype => $text) {
            if (trim($text) === '' || $text !== strip_tags($text)) {
                continue;
            }
            $section = '';
            foreach (stage_parse_evaluation_text($text) as $block) {
                if ($block['type'] === 'heading') {
                    $section = $block['text'];
                } else if ($block['type'] === 'rating') {
                    $score = $block['score'];
                    $textlines[$entryid][] = [
                        'evaltype' => $evaltype,
                        'label' => ($section !== '' ? $section . ' - ' : '') . $block['label'],
                        'answer' => $score === null ? get_string('ratingnotprovided', 'mod_stage')
                            : format_float($score, floor($score) == $score ? 0 : 1, true, true) . '/5',
                        'score' => $score,
                        'number' => null,
                        'qtype' => $qtypes['rating'],
                    ];
                }
            }
        }
    }
    ksort($textlines);

    // Écrit les lignes d'un stage : ses réponses, puis les notes de ses évaluations textuelles. Ce
    // qui ne dépend que du stage (nom, thématique, dates) est calculé une fois par stage, et non à
    // chaque réponse : une promotion en compte des dizaines de milliers.
    $evaltypelabels = [];
    $writeentry = function (
        $entryid,
        array $answerlines
    ) use (
        &$textlines,
        &$evaltypelabels,
        $entries,
        $students,
        $themes,
        $writer,
        $textstyle,
        $textcell,
        $numbercell,
        $datecell,
        $blankcell,
    ) {
        $entrylines = array_merge($answerlines, $textlines[$entryid] ?? []);
        unset($textlines[$entryid]);
        $entry = $entries[$entryid];
        $student = $students[$entry->userid] ?? null;
        $theme = $themes[$entry->themeid] ?? null;
        $studentname = $student ? fullname($student) : '';
        $themename = $theme ? stage_plain_string($theme->name) : '';
        foreach ($entrylines as $line) {
            if (!isset($evaltypelabels[$line['evaltype']])) {
                $evaltypelabels[$line['evaltype']] = stage_evaltype_label($line['evaltype']);
            }
            $cells = [];
            $cells[] = $numbercell((int) $entry->id);
            $cells[] = $textcell($studentname);
            $cells[] = $textcell($themename);
            $cells[] = $datecell($entry->datestart);
            $cells[] = $datecell($entry->dateend);
            $cells[] = $textcell($evaltypelabels[$line['evaltype']]);
            $cells[] = $textcell($line['label']);
            $cells[] = $textcell($line['answer']);
            // Note et valeur numérique : cellule vierge en leur absence.
            $cells[] = $line['score'] !== null ? $numbercell($line['score']) : $blankcell();
            $cells[] = $line['number'] !== null ? $numbercell($line['number']) : $blankcell();
            $cells[] = $textcell($line['qtype']);
            $writer->addRow(new \OpenSpout\Common\Entity\Row($cells, $textstyle));
        }
    };

    // Les réponses sont lues en flux, stage après stage, plutôt que chargées d'un bloc : une
    // promotion en compte des dizaines de milliers.
    [$insql, $inparams] = stage_entry_ids_subquery($stage->id, 'export');
    $answerrecords = $DB->get_recordset_sql(
        "SELECT a.id, a.entryid, a.answertext, q.name AS questionname, q.evaltype, q.qtype, q.sortorder
           FROM {stage_answer} a
           JOIN {stage_question} q ON q.id = a.questionid
          WHERE a.entryid $insql
       ORDER BY a.entryid ASC, q.evaltype ASC, q.sortorder ASC",
        $inparams
    );
    $currentid = null;
    $currentlines = [];
    $questionlabels = [];
    foreach ($answerrecords as $answer) {
        $entryid = (int) $answer->entryid;
        if ($entryid !== $currentid) {
            if ($currentid !== null) {
                $writeentry($currentid, $currentlines);
            }
            // Stages sans réponse mais avec une évaluation textuelle notée, qui le précèdent.
            while ($textlines && array_key_first($textlines) < $entryid) {
                $writeentry(array_key_first($textlines), []);
            }
            $currentid = $entryid;
            $currentlines = [];
        }
        // Un intitulé de question revient à chaque réponse : mis en forme une seule fois.
        if (!isset($questionlabels[$answer->questionname])) {
            $questionlabels[$answer->questionname] = stage_plain_string($answer->questionname);
        }
        $values = stage_answer_export_values($answer, $answer->answertext);
        $currentlines[] = [
            'evaltype' => $answer->evaltype,
            'label' => $questionlabels[$answer->questionname],
            'answer' => $values['answer'],
            'score' => $values['score'],
            'number' => $values['number'],
            'qtype' => $qtypes[$answer->qtype] ?? $qtypes['text'],
        ];
    }
    $answerrecords->close();
    if ($currentid !== null) {
        $writeentry($currentid, $currentlines);
    }
    while ($textlines) {
        $writeentry(array_key_first($textlines), []);
    }
}

$writer->close();
exit;
