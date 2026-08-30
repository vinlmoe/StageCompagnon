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
 * Import en masse des stages depuis un export CSV de StageVet (conventions déjà générées et
 * signées par StageVet, hors du circuit de gestion de convention de ce plugin), par la DEVE.
 *
 * Colonnes lues par nom d'en-tête (voir $columnmap ci-dessous), dans l'ordre où StageVet les
 * exporte habituellement : "Nom étudiant", "Prénom étudiant", "Email étudiant" (souvent vide côté
 * StageVet : l'étudiant est alors identifié par nom/prénom), "Thème", "Début (convention)",
 * "Fin (convention)", "Durée (convention)", "Organisme (convention)" et l'ensemble des
 * coordonnées de convention (organisme, tuteur, maître de stage, modalités, gratification,
 * congés). Le nom exact d'une thématique déjà créée dans l'activité est requis pour chaque ligne
 * (comme pour import.php), StageVet n'utilisant pas les mêmes intitulés par défaut.
 *
 * Chaque stage importé est enregistré au statut "Enregistré" avec le statut de convention
 * "Signée (SignVet)" (voir STAGE_CONVENTION_SIGNVET) : déjà signé hors de ce plugin, l'
 * auto-évaluation de l'étudiant est immédiatement ouverte. Les informations de convention
 * disponibles dans l'export sont malgré tout enregistrées (stage_convention_detail), à titre de
 * référence consultable, mais ne déclenchent aucune génération de PDF ni gestion de convention
 * dans ce plugin.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:registerstages', $context);

$baseurl = new moodle_url('/mod/stage/import_stagevet.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('importstagevetcsv', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// En-têtes StageVet reconnus (une ligne d'en-tête est obligatoire) => clé interne utilisée
// ci-dessous. Les colonnes absentes du fichier sont simplement ignorées (valeur vide).
$columnmap = [
    'nom étudiant' => 'lastname',
    'prénom étudiant' => 'firstname',
    'email étudiant' => 'email',
    'année étudiant (convention)' => 'studentyear',
    'année d\'étude' => 'studyyear',
    'thème' => 'theme',
    'thème (convention)' => 'themealt',
    'organisme' => 'structure',
    'organisme (convention)' => 'structurealt',
    'début stage' => 'datestart',
    'début (convention)' => 'datestartalt',
    'fin stage' => 'dateend',
    'fin (convention)' => 'dateendalt',
    'jours effectifs' => 'durationeffective',
    'jours déclarés' => 'durationdeclared',
    'durée (convention)' => 'durationtext',
    'date de naissance étudiant' => 'studentbirthdate',
    'adresse étudiant' => 'studentaddress',
    'téléphone étudiant' => 'studentphone',
    'adresse organisme' => 'hostaddress',
    'adresse organisme (convention)' => 'hostaddressalt',
    'représentant organisme' => 'hostrepresentative',
    'téléphone organisme' => 'hostphone',
    'email organisme' => 'hostemail',
    'nom tuteur' => 'referentteachername',
    'fonction tuteur' => 'referentteacherfunction',
    'téléphone tuteur' => 'referentteacherphone',
    'email tuteur' => 'referentteacheremail',
    'nom maître de stage' => 'tutorname',
    'fonction maître de stage' => 'tutorfunction',
    'présence de nuit' => 'nightpresence',
    'présence dimanche' => 'sundaypresence',
    'présence jour férié' => 'holidaypresence',
    'présence à domicile' => 'homebased',
    'montant gratification' => 'gratificationamount',
];

$themes = stage_get_themes($stage->id, true);
$themesbyname = [];
foreach ($themes as $theme) {
    $themesbyname[core_text::strtolower(trim($theme->name))] = $theme;
}

$students = stage_get_enrolled_students($context);
$studentsbyemail = [];
$studentsbyname = [];
foreach ($students as $student) {
    $studentsbyemail[core_text::strtolower($student->email)] = $student;
    $studentsbyname[stage_normalize_name($student->firstname . ' ' . $student->lastname)] = $student;
}

// Dans l'export StageVet, le « tuteur » est l'enseignant référent de l'école. Il est distinct du
// « maître de stage », qui encadre l'étudiant dans la structure d'accueil et est enregistré dans
// les champs tutor* de la convention.
$teachersbyemail = [];
$teachersbyname = [];
foreach (stage_get_potential_teachers($context) as $teacher) {
    $teachersbyemail[core_text::strtolower($teacher->email)] = $teacher;
    $teachersbyname[stage_normalize_name($teacher->firstname . ' ' . $teacher->lastname)] = $teacher;
}

/**
 * Convertit une date StageVet (JJ/MM/AAAA) en timestamp, ou null si vide/invalide.
 *
 * @param string $raw
 * @return int|null
 */
function stagevet_parse_date($raw) {
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    $date = DateTime::createFromFormat('d/m/Y', $raw);
    return $date ? $date->setTime(0, 0)->getTimestamp() : null;
}

/**
 * Extrait le nombre de jours d'un texte de durée StageVet ("7  jours effectifs" -> 7).
 *
 * @param string $raw
 * @return int
 */
function stagevet_parse_duration($raw) {
    return preg_match('/(\d+)/', $raw, $matches) ? (int) $matches[1] : 0;
}

/**
 * Extrait l'année d'étude d'une valeur StageVet ("2", "2ème année", etc.).
 *
 * @param string $raw
 * @return int 0 si la valeur est vide ou ne contient pas une année valide
 */
function stagevet_parse_studyyear($raw) {
    if (!preg_match('/\d+/', trim($raw), $matches)) {
        return 0;
    }

    $year = (int) $matches[0];
    return $year >= 1 && $year <= 6 ? $year : 0;
}

$results = null;
$uploaderror = null;

if (data_submitted() && confirm_sesskey()) {
    $upload = $_FILES['csvfile'] ?? null;

    if (empty($upload) || $upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
        $uploaderror = get_string('importerrorupload', 'mod_stage');
    } else {
        $content = file_get_contents($upload['tmp_name']);
        $delimiter = (strpos($content, ';') !== false) ? 'semicolon' : 'comma';

        $cir = new csv_import_reader(csv_import_reader::get_new_iid('stagevet'), 'stagevet');

        if ($cir->load_csv_content($content, 'UTF-8', $delimiter) === false) {
            $uploaderror = $cir->get_error();
            $cir->cleanup(true);
        } else {
            $columns = $cir->get_columns();
            if (!$columns) {
                $uploaderror = get_string('importstagevetnoheader', 'mod_stage');
            } else {
                // Associe chaque colonne du fichier (par en-tête, normalisé) à sa clé interne.
                $colindex = [];
                foreach ($columns as $index => $header) {
                    $key = $columnmap[core_text::strtolower(trim($header))] ?? null;
                    if ($key) {
                        $colindex[$key] = $index;
                    }
                }

                /**
                 * Lit une colonne de la ligne courante par clé interne (ou l'une de ses variantes,
                 * la première non vide étant retenue), '' si absente/vide.
                 */
                $getcol = function (array $row, ...$keys) use ($colindex) {
                    foreach ($keys as $key) {
                        if (isset($colindex[$key]) && isset($row[$colindex[$key]])) {
                            $value = trim($row[$colindex[$key]]);
                            if ($value !== '') {
                                return $value;
                            }
                        }
                    }
                    return '';
                };

                // Les étudiants et thématiques introuvables sont regroupés par valeur (plutôt
                // qu'une ligne d'erreur par occurrence) pour produire un rapport lisible même sur
                // un fichier de plusieurs centaines de lignes concernant le même étudiant ou la
                // même thématique manquante (ex. un étudiant non encore inscrit au cours).
                $results = (object) ['created' => 0, 'unknownstudents' => [], 'unknownthemes' => [], 'errors' => []];
                $entryrecords = [];
                $detailbyrowkey = [];
                $cir->init();
                $linenum = 1;
                $existingpairs = stage_get_existing_theme_pairs($stage->id);

                while ($row = $cir->next()) {
                    $linenum++;

                    $lastname = $getcol($row, 'lastname');
                    $firstname = $getcol($row, 'firstname');
                    $email = $getcol($row, 'email');
                    $themename = $getcol($row, 'theme', 'themealt');
                    if ($lastname === '' && $firstname === '' && $email === '') {
                        continue;
                    }

                    $student = null;
                    if ($email !== '') {
                        $student = $studentsbyemail[core_text::strtolower($email)] ?? null;
                    }
                    if (!$student && ($firstname !== '' || $lastname !== '')) {
                        $student = $studentsbyname[stage_normalize_name($firstname . ' ' . $lastname)] ?? null;
                    }
                    if (!$student) {
                        $studentname = trim($firstname . ' ' . $lastname) ?: $email;
                        $results->unknownstudents[$studentname][] = $linenum;
                        continue;
                    }

                    if ($themename === '') {
                        $results->errors[] = get_string('importstageveterrornotheme', 'mod_stage', $linenum);
                        continue;
                    }
                    $theme = $themesbyname[core_text::strtolower($themename)] ?? null;
                    if (!$theme) {
                        $results->unknownthemes[$themename][] = $linenum;
                        continue;
                    }

                    // Les dates de début et de fin de l'export constituent l'unique plage du
                    // stage importé, et donc ses dates. Sans elles, ou si la fin précède le début,
                    // la ligne est refusée : un stage sans plage n'aurait ni dates affichables ni
                    // convention exploitable, et ne serait plus réenregistrable sans que la DEVE
                    // invente des dates.
                    $start = stagevet_parse_date($getcol($row, 'datestartalt', 'datestart'));
                    $end = stagevet_parse_date($getcol($row, 'dateendalt', 'dateend'));
                    if (empty($start) || empty($end) || $end < $start) {
                        $results->errors[] = get_string('importstageveterrordates', 'mod_stage', (object) [
                            'line' => $linenum, 'student' => fullname($student),
                        ]);
                        continue;
                    }

                    $pairkey = stage_duplicate_key($student->id, $theme->id, $start, $end);
                    if (isset($existingpairs[$pairkey])) {
                        $results->errors[] = get_string('importerrorduplicate', 'mod_stage', (object) [
                            'line' => $linenum, 'email' => fullname($student), 'theme' => $themename,
                        ]);
                        continue;
                    }
                    $existingpairs[$pairkey] = true;

                    $duration = (int) $getcol($row, 'durationeffective');
                    if (!$duration) {
                        $duration = (int) $getcol($row, 'durationdeclared');
                    }
                    if (!$duration) {
                        $duration = stagevet_parse_duration($getcol($row, 'durationtext'));
                    }

                    // L'année propre à l'étudiant dans la convention décrit l'année à laquelle
                    // ce stage doit être rattaché. L'année d'étude générale de l'export sert de
                    // repli pour les anciens exports qui ne fournissent pas la première colonne.
                    $studyyear = stagevet_parse_studyyear($getcol($row, 'studentyear', 'studyyear'));

                    $rowkey = count($entryrecords);
                    $entryrecords[$rowkey] = (object) [
                        'stageid' => $stage->id,
                        'userid' => $student->id,
                        'themeid' => $theme->id,
                        'studyyear' => $studyyear,
                        'structure' => $getcol($row, 'structurealt', 'structure'),
                        'datestart' => $start,
                        'dateend' => $end,
                        'declaredduration' => $duration,
                        'retainedduration' => 0,
                        'status' => STAGE_STATUS_ENREGISTRE,
                        'conventionstatus' => STAGE_CONVENTION_SIGNVET,
                        'timecreated' => time(),
                        'timemodified' => time(),
                    ];

                    $detailbyrowkey[$rowkey] = (object) [
                        'referentteacherid' => null,
                        'yearsituation' => 'normal',
                        'stagetype' => 'obligatoire',
                        'studentbirthdate' => stagevet_parse_date($getcol($row, 'studentbirthdate')),
                        'studentaddress' => $getcol($row, 'studentaddress'),
                        'studentphone' => $getcol($row, 'studentphone'),
                        'hostaddress' => $getcol($row, 'hostaddressalt', 'hostaddress'),
                        'hostrepresentative' => $getcol($row, 'hostrepresentative'),
                        'hostrepresentativetitle' => '',
                        'hostservice' => '',
                        'hostphone' => $getcol($row, 'hostphone'),
                        'hostemail' => $getcol($row, 'hostemail'),
                        'hostlocation' => '',
                        'tutorname' => $getcol($row, 'tutorname'),
                        'tutorfunction' => $getcol($row, 'tutorfunction'),
                        'tutorphone' => '',
                        'tutoremail' => '',
                        'nightpresence' => core_text::strtolower($getcol($row, 'nightpresence')) === 'oui' ? 1 : 0,
                        'sundaypresence' => core_text::strtolower($getcol($row, 'sundaypresence')) === 'oui' ? 1 : 0,
                        'holidaypresence' => core_text::strtolower($getcol($row, 'holidaypresence')) === 'oui' ? 1 : 0,
                        'homebased' => core_text::strtolower($getcol($row, 'homebased')) === 'oui' ? 1 : 0,
                        'othermodality' => '',
                        'hasleave' => 0,
                        'leavedays' => null,
                        'leavemodalities' => '',
                        'gratificationamount' => $getcol($row, 'gratificationamount'),
                    ];

                    $referentteacher = null;
                    $referentemail = $getcol($row, 'referentteacheremail');
                    if ($referentemail !== '') {
                        $referentteacher = $teachersbyemail[core_text::strtolower($referentemail)] ?? null;
                    }
                    $referentname = $getcol($row, 'referentteachername');
                    if (!$referentteacher && $referentname !== '') {
                        $referentteacher = $teachersbyname[stage_normalize_name($referentname)] ?? null;
                    }
                    if ($referentteacher) {
                        $detailbyrowkey[$rowkey]->referentteacherid = $referentteacher->id;
                    }
                }
                $cir->cleanup(true);

                foreach ($entryrecords as $rowkey => $record) {
                    $entryid = $DB->insert_record('stage_entry', $record);
                    // Comme toute saisie créée par stage_register_entry(), celle-ci reçoit sa
                    // plage : les dates de l'export en sont l'unique plage. Les lignes sans dates
                    // exploitables ont été écartées plus haut, elle est donc toujours créée.
                    $DB->insert_record('stage_entry_period', (object) [
                        'entryid' => $entryid,
                        'datestart' => $record->datestart,
                        'dateend' => $record->dateend,
                        'timecreated' => time(),
                    ]);
                    $detailbyrowkey[$rowkey]->entryid = $entryid;
                    $detailbyrowkey[$rowkey]->timecreated = time();
                    $detailbyrowkey[$rowkey]->timemodified = time();
                    $DB->insert_record('stage_convention_detail', $detailbyrowkey[$rowkey]);
                }
                $results->created = count($entryrecords);
            }
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importstagevetcsv', 'mod_stage'));
echo html_writer::link(new moodle_url('/mod/stage/register.php', ['id' => $cm->id]), get_string('back'));

if ($uploaderror !== null) {
    echo $OUTPUT->notification($uploaderror, \core\output\notification::NOTIFY_ERROR);
}

if ($results) {
    echo $OUTPUT->notification(get_string('importresult', 'mod_stage', $results->created),
        \core\output\notification::NOTIFY_SUCCESS);

    // Rapport groupé : un étudiant ou une thématique manquant sur cent lignes ne doit apparaître
    // qu'une fois, avec la liste des lignes concernées, plutôt que cent messages identiques.
    if (!empty($results->unknownstudents)) {
        echo $OUTPUT->heading(get_string('importstagevetunknownstudentsreport', 'mod_stage',
            count($results->unknownstudents)), 4);
        $lines = [];
        foreach ($results->unknownstudents as $name => $linenums) {
            $lines[] = get_string('importstagevetreportline', 'mod_stage', (object) [
                'value' => $name, 'lines' => implode(', ', $linenums),
            ]);
        }
        echo $OUTPUT->notification(implode(html_writer::empty_tag('br'), $lines),
            \core\output\notification::NOTIFY_WARNING);
    }

    if (!empty($results->unknownthemes)) {
        echo $OUTPUT->heading(get_string('importstagevetunknownthemesreport', 'mod_stage',
            count($results->unknownthemes)), 4);
        $lines = [];
        foreach ($results->unknownthemes as $name => $linenums) {
            $lines[] = get_string('importstagevetreportline', 'mod_stage', (object) [
                'value' => $name, 'lines' => implode(', ', $linenums),
            ]);
        }
        echo $OUTPUT->notification(implode(html_writer::empty_tag('br'), $lines),
            \core\output\notification::NOTIFY_WARNING);
    }

    if (!empty($results->errors)) {
        echo $OUTPUT->notification(implode(html_writer::empty_tag('br'), $results->errors),
            \core\output\notification::NOTIFY_WARNING);
    }
}

echo $OUTPUT->box(get_string('importstagevetcsv_help', 'mod_stage'), 'generalbox mb-3');

echo html_writer::start_tag('form', [
    'method' => 'post', 'action' => $baseurl, 'enctype' => 'multipart/form-data',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'file', 'name' => 'csvfile', 'accept' => '.csv', 'required' => 'required']);
echo html_writer::tag('button', get_string('import', 'mod_stage'),
    ['type' => 'submit', 'class' => 'btn btn-primary ml-2']);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
