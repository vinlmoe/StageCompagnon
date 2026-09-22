<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_stage\local;

use core_text;
use csv_import_reader;
use DateTime;

/**
 * Traitement des imports CSV indépendant des formulaires d'envoi de fichier.
 *
 * @package mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csv_importer {
    /**
     * Importe le CSV après contrôle des droits et du fichier par la page appelante.
     *
     * @param \stdClass $stage Activité cible.
     * @param \context $context Contexte de l'activité cible.
     * @param string $content Contenu UTF-8 du CSV.
     * @return array Résultats par ligne et erreur de lecture éventuelle.
     */
    public static function entries(\stdClass $stage, \context $context, string $content): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/csvlib.class.php');
        require_once($CFG->dirroot . '/mod/stage/locallib.php');
        $themes = stage_get_themes($stage->id, true);
        $students = stage_get_enrolled_students($context);
        $studentsbyemail = [];
        foreach ($students as $student) {
            $studentsbyemail[core_text::strtolower($student->email)] = $student;
        }
        $themesbyname = [];
        foreach ($themes as $theme) {
            $themesbyname[core_text::strtolower(trim($theme->name))] = $theme;
        }

        $results = null;
        $uploaderror = null;

        // Excel francophone exporte en points-virgules ; on accepte aussi la virgule.
        $delimiter = (strpos($content, ';') !== false) ? 'semicolon' : 'comma';

        $cir = new csv_import_reader(csv_import_reader::get_new_iid('stage'), 'stage');

        if ($cir->load_csv_content($content, 'UTF-8', $delimiter) === false) {
            $uploaderror = $cir->get_error();
            $cir->cleanup(true);
        } else {
            $results = (object) ['created' => 0, 'errors' => []];
            $records = [];
            $cir->init();
            // La première ligne est consommée comme en-tête par load_csv_content().
            $linenum = 1;

            // Doublons détectés contre les stages déjà enregistrés, et entre les lignes
            // du fichier lui-même (un même étudiant répété deux fois sur la même thématique).
            $existingpairs = stage_get_existing_theme_pairs($stage->id);

            while ($row = $cir->next()) {
                $linenum++;
                // Colonnes attendues : email, theme, structure, datestart, dateend, duration.
                $email = isset($row[0]) ? trim($row[0]) : '';
                $themename = isset($row[1]) ? trim($row[1]) : '';
                $structure = isset($row[2]) ? trim($row[2]) : '';
                $datestartraw = isset($row[3]) ? trim($row[3]) : '';
                $dateendraw = isset($row[4]) ? trim($row[4]) : '';
                $duration = isset($row[5]) ? (int) trim($row[5]) : 0;

                // Ignore les lignes vides et une éventuelle seconde ligne d'en-tête.
                if ($email === '' || $themename === '' || core_text::strtolower($email) === 'email') {
                    continue;
                }

                $student = $studentsbyemail[core_text::strtolower($email)] ?? null;
                if (!$student) {
                    $results->errors[] = get_string('importerrorunknownemail', 'mod_stage', (object) [
                        'line' => $linenum, 'email' => $email,
                    ]);
                    continue;
                }

                $theme = $themesbyname[core_text::strtolower($themename)] ?? null;
                if (!$theme) {
                    $results->errors[] = get_string('importerrorunknowntheme', 'mod_stage', (object) [
                        'line' => $linenum, 'theme' => $themename,
                    ]);
                    continue;
                }

                $start = $datestartraw ? strtotime($datestartraw) : false;
                $end = $dateendraw ? strtotime($dateendraw) : false;

                $pairkey = stage_duplicate_key($student->id, $theme->id, $start ?: null, $end ?: null);
                if (isset($existingpairs[$pairkey])) {
                    $results->errors[] = get_string('importerrorduplicate', 'mod_stage', (object) [
                        'line' => $linenum, 'email' => $email, 'theme' => $themename,
                    ]);
                    continue;
                }
                $existingpairs[$pairkey] = true;

                $records[] = (object) [
                    'stageid' => $stage->id,
                    'userid' => $student->id,
                    'themeid' => $theme->id,
                    'structure' => $structure,
                    'datestart' => $start ?: null,
                    'dateend' => $end ?: null,
                    'declaredduration' => $duration,
                    'retainedduration' => 0,
                    'status' => STAGE_STATUS_ENREGISTRE,
                    'timecreated' => time(),
                    'timemodified' => time(),
                ];
            }
            $cir->cleanup(true);

            // Insertion groupée : un import de plusieurs centaines de lignes ne doit pas
            // déclencher autant de requêtes individuelles.
            if ($records) {
                $DB->insert_records('stage_entry', $records);
                $results->created = count($records);
            }
        }

        return ['results' => $results, 'error' => $uploaderror];
    }

    /**
     * Importe le CSV après contrôle des droits et du fichier par la page appelante.
     *
     * @param \stdClass $stage Activité cible.
     * @param \context $context Contexte de l'activité cible.
     * @param string $content Contenu UTF-8 du CSV.
     * @return array Résultats par ligne et erreur de lecture éventuelle.
     */
    public static function teachers(\stdClass $stage, \context $context, string $content): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/csvlib.class.php');
        require_once($CFG->dirroot . '/mod/stage/locallib.php');
        $students = stage_get_enrolled_students($context);
        $studentsbyemail = [];
        foreach ($students as $student) {
            $studentsbyemail[core_text::strtolower($student->email)] = $student;
        }
        $teachers = stage_get_potential_teachers($context);
        $teachersbyemail = [];
        foreach ($teachers as $teacher) {
            $teachersbyemail[core_text::strtolower($teacher->email)] = $teacher;
        }

        $results = null;
        $uploaderror = null;

        // Excel francophone exporte en points-virgules ; on accepte aussi la virgule.
        $delimiter = (strpos($content, ';') !== false) ? 'semicolon' : 'comma';

        $cir = new csv_import_reader(csv_import_reader::get_new_iid('stageteachers'), 'stageteachers');

        if ($cir->load_csv_content($content, 'UTF-8', $delimiter) === false) {
            $uploaderror = $cir->get_error();
            $cir->cleanup(true);
        } else {
            $results = (object) ['assigned' => 0, 'errors' => []];
            $cir->init();
            $linenum = 1;

            while ($row = $cir->next()) {
                $linenum++;
                $studentemail = isset($row[0]) ? trim($row[0]) : '';
                $teacher1email = isset($row[1]) ? trim($row[1]) : '';
                $teacher2email = isset($row[2]) ? trim($row[2]) : '';

                // Ignore les lignes vides et une éventuelle seconde ligne d'en-tête.
                if ($studentemail === '' || core_text::strtolower($studentemail) === 'studentemail') {
                    continue;
                }

                $student = $studentsbyemail[core_text::strtolower($studentemail)] ?? null;
                if (!$student) {
                    $results->errors[] = get_string('importerrorunknownemail', 'mod_stage', (object) [
                        'line' => $linenum, 'email' => $studentemail,
                    ]);
                    continue;
                }

                $teacherids = [];
                foreach ([$teacher1email, $teacher2email] as $teacheremail) {
                    if ($teacheremail === '') {
                        continue;
                    }
                    $teacher = $teachersbyemail[core_text::strtolower($teacheremail)] ?? null;
                    if (!$teacher) {
                        $results->errors[] = get_string('importerrorunknownteacher', 'mod_stage', (object) [
                            'line' => $linenum, 'email' => $teacheremail,
                        ]);
                        continue;
                    }
                    $teacherids[] = $teacher->id;
                }

                stage_set_student_teachers($stage->id, $student->id, $teacherids);
                $results->assigned++;
            }
            $cir->cleanup(true);
        }

        return ['results' => $results, 'error' => $uploaderror];
    }

    /**
     * Importe le CSV après contrôle des droits et du fichier par la page appelante.
     *
     * Les lignes dont l'étudiant n'a pas pu être rapproché d'un inscrit au cours sont remontées
     * dans $results->unknownstudents, groupées par libellé : aucune ligne n'est écartée en
     * silence. La page appelante propose alors à la DEVE de désigner elle-même l'étudiant
     * inscrit correspondant à chaque libellé, puis rappelle cette méthode avec le même contenu
     * et la table de correspondance obtenue.
     *
     * @param \stdClass $stage Activité cible.
     * @param \context $context Contexte de l'activité cible.
     * @param string $content Contenu UTF-8 du CSV.
     * @param array $studentresolutions Libellé d'étudiant non rapproché => identifiant de
     *        l'étudiant inscrit désigné par la DEVE. Dès que cette table n'est pas vide, seules
     *        les lignes qu'elle rattache sont importées : les autres l'ont déjà été à la
     *        première passe et ne seraient plus vues que comme des doublons.
     * @return array Résultats par ligne et erreur de lecture éventuelle.
     */
    public static function stagevet(
        \stdClass $stage,
        \context $context,
        string $content,
        array $studentresolutions = []
    ): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/csvlib.class.php');
        require_once($CFG->dirroot . '/mod/stage/locallib.php');
        // En-têtes StageVet reconnus (une ligne d'en-tête est obligatoire) => clé interne utilisée
        // ci-dessous. Les colonnes absentes du fichier sont simplement ignorées (valeur vide).
        $columnmap = [
            'étudiant' => 'fullname',
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
        // La colonne « Étudiant » de StageVet donne le nom dans l'ordre « Nom Prénom », alors que
        // les colonnes de convention le donnent en deux champs séparés. Les deux ordres sont donc
        // indexés, et seul un rapprochement sans ambiguïté est retenu (un libellé qui désignerait
        // deux inscrits différents selon l'ordre de lecture est laissé à l'arbitrage de la DEVE).
        $studentsbyreversedname = [];
        foreach ($students as $student) {
            $studentsbyemail[core_text::strtolower($student->email)] = $student;
            $studentsbyname[stage_normalize_name($student->firstname . ' ' . $student->lastname)] = $student;
            $studentsbyreversedname[stage_normalize_name($student->lastname . ' ' . $student->firstname)] = $student;
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

        $results = null;
        $uploaderror = null;

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

                // Lit une colonne de la ligne courante par clé interne (ou l'une de ses
                // variantes, la première non vide étant retenue), '' si absente/vide.
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
                // Seconde passe : la DEVE a rattaché des libellés à des inscrits. Les lignes que
                // le fichier suffit à rapprocher ont déjà été importées à la première passe.
                $resolvedonly = $studentresolutions !== [];
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
                    $fullname = $getcol($row, 'fullname');
                    $themename = $getcol($row, 'theme', 'themealt');

                    // Une ligne entièrement vide (dernier saut de ligne du fichier) n'est pas une
                    // donnée manquante. Toute autre ligne doit en revanche aboutir à un étudiant
                    // ou être signalée : une ligne écartée en silence ferait annoncer un import
                    // réussi alors que des stages n'ont pas été créés.
                    if (trim(implode('', array_map('strval', $row))) === '') {
                        continue;
                    }

                    $student = null;
                    if ($email !== '') {
                        $student = $studentsbyemail[core_text::strtolower($email)] ?? null;
                    }
                    if (!$student && ($firstname !== '' || $lastname !== '')) {
                        $student = $studentsbyname[stage_normalize_name($firstname . ' ' . $lastname)] ?? null;
                    }
                    if (!$student && $fullname !== '') {
                        // Repli sur la colonne « Étudiant » du tableau de bord StageVet, toujours
                        // renseignée : les colonnes « Nom étudiant », « Prénom étudiant » et
                        // « Email étudiant » proviennent de la convention PDF et sont vides tant
                        // que celle-ci n'a pas été analysée par StageVetManager.
                        $namekey = stage_normalize_name($fullname);
                        $direct = $studentsbyname[$namekey] ?? null;
                        $reversed = $studentsbyreversedname[$namekey] ?? null;
                        if ($direct && $reversed && $direct->id !== $reversed->id) {
                            $direct = $reversed = null;
                        }
                        $student = $direct ?: $reversed;
                    }

                    $studentlabel = trim($firstname . ' ' . $lastname);
                    if ($studentlabel === '') {
                        $studentlabel = $fullname !== '' ? $fullname : $email;
                    }
                    if ($studentlabel === '') {
                        $studentlabel = get_string('importstagevetunnamedstudent', 'mod_stage', $linenum);
                    }

                    // Étudiant désigné par la DEVE pour ce libellé. La correspondance est relue
                    // dans la liste des inscrits : un identifiant forgé dans le formulaire ne peut
                    // pas rattacher un stage à quelqu'un qui n'est pas inscrit au cours.
                    $resolved = false;
                    if (!$student && isset($studentresolutions[$studentlabel])) {
                        $student = $students[(int) $studentresolutions[$studentlabel]] ?? null;
                        $resolved = $student !== null;
                    }

                    if (!$student) {
                        $results->unknownstudents[$studentlabel][] = $linenum;
                        continue;
                    }

                    if ($resolvedonly && !$resolved) {
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
                    $start = self::parse_date($getcol($row, 'datestartalt', 'datestart'));
                    $end = self::parse_date($getcol($row, 'dateendalt', 'dateend'));
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
                        $duration = self::parse_duration($getcol($row, 'durationtext'));
                    }

                    // L'année propre à l'étudiant dans la convention décrit l'année à laquelle
                    // ce stage doit être rattaché. L'année d'étude générale de l'export sert de
                    // repli pour les anciens exports qui ne fournissent pas la première colonne.
                    $studyyear = self::parse_studyyear($getcol($row, 'studentyear', 'studyyear'));

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
                        'studentbirthdate' => self::parse_date($getcol($row, 'studentbirthdate')),
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

        return ['results' => $results, 'error' => $uploaderror];
    }
    /**
     * Convertit une date StageVet (JJ/MM/AAAA) en timestamp, ou null si vide/invalide.
     *
     * @param string $raw
     * @return int|null
     */
    public static function parse_date($raw) {
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
    public static function parse_duration($raw) {
        return preg_match('/(\d+)/', $raw, $matches) ? (int) $matches[1] : 0;
    }

    /**
     * Extrait l'année d'étude d'une valeur StageVet ("2", "2ème année", etc.).
     *
     * @param string $raw
     * @return int 0 si la valeur est vide ou ne contient pas une année valide
     */
    public static function parse_studyyear($raw) {
        if (!preg_match('/\d+/', trim($raw), $matches)) {
            return 0;
        }

        $year = (int) $matches[0];
        return $year >= 1 && $year <= 6 ? $year : 0;
    }
}
