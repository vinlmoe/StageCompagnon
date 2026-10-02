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

        $cir = new csv_import_reader(csv_import_reader::get_new_iid('stage'), 'stage');

        if ($cir->load_csv_content($content, self::detect_encoding($content), self::detect_delimiter($content)) === false) {
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
                // Colonnes attendues : email, theme, structure, datestart, dateend, duration, et
                // studyyear (facultative).
                $email = isset($row[0]) ? trim($row[0]) : '';
                $themename = isset($row[1]) ? trim($row[1]) : '';
                $structure = isset($row[2]) ? trim($row[2]) : '';
                $datestartraw = isset($row[3]) ? trim($row[3]) : '';
                $dateendraw = isset($row[4]) ? trim($row[4]) : '';
                $duration = isset($row[5]) ? (int) trim($row[5]) : 0;
                $studyyear = isset($row[6]) ? self::parse_studyyear((string) $row[6]) : 0;

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

                // Les dates sont lues au format AAAA-MM-JJ ou JJ/MM/AAAA (celui qu'Excel
                // francophone réécrit à l'enregistrement), jamais à l'américaine : une date
                // illisible ou une plage inversée est signalée au lieu d'être vidée ou permutée.
                $start = self::parse_date($datestartraw);
                $end = self::parse_date($dateendraw);
                $baddate = null;
                if ($datestartraw !== '' && !$start) {
                    $baddate = $datestartraw;
                } else if ($dateendraw !== '' && !$end) {
                    $baddate = $dateendraw;
                }
                if ($baddate !== null) {
                    $results->errors[] = get_string('importerrordate', 'mod_stage', (object) [
                        'line' => $linenum, 'value' => $baddate,
                    ]);
                    continue;
                }
                if ($start && $end && $end < $start) {
                    $results->errors[] = get_string('importerrordaterange', 'mod_stage', $linenum);
                    continue;
                }

                $pairkey = stage_duplicate_key($student->id, $theme->id, $start ?: null, $end ?: null);
                if (isset($existingpairs[$pairkey])) {
                    $results->errors[] = get_string('importerrorduplicate', 'mod_stage', (object) [
                        'line' => $linenum, 'email' => $email, 'theme' => $themename,
                    ]);
                    continue;
                }
                $existingpairs[$pairkey] = true;

                $records[] = (object) [
                    'userid' => $student->id,
                    'themeid' => $theme->id,
                    'structure' => $structure,
                    'datestart' => $start ?: null,
                    'dateend' => $end ?: null,
                    'declaredduration' => $duration,
                    'studyyear' => $studyyear,
                ];
            }
            $cir->cleanup(true);

            // Création par stage_register_entry(), comme toute saisie : chaque stage daté reçoit
            // sa plage. Une transaction évite un import à moitié fait en cas d'erreur.
            if ($records) {
                $transaction = $DB->start_delegated_transaction();
                foreach ($records as $record) {
                    stage_register_entry(
                        $stage->id,
                        $record->userid,
                        $record->themeid,
                        $record->structure,
                        $record->datestart,
                        $record->dateend,
                        $record->declaredduration,
                        $record->studyyear
                    );
                }
                $transaction->allow_commit();
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

        $cir = new csv_import_reader(csv_import_reader::get_new_iid('stageteachers'), 'stageteachers');

        if ($cir->load_csv_content($content, self::detect_encoding($content), self::detect_delimiter($content)) === false) {
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
                $invalid = false;
                foreach ([$teacher1email, $teacher2email] as $teacheremail) {
                    if ($teacheremail === '') {
                        continue;
                    }
                    $teacher = $teachersbyemail[core_text::strtolower($teacheremail)] ?? null;
                    if (!$teacher) {
                        $results->errors[] = get_string('importerrorunknownteacher', 'mod_stage', (object) [
                            'line' => $linenum, 'email' => $teacheremail,
                        ]);
                        $invalid = true;
                        continue;
                    }
                    $teacherids[] = $teacher->id;
                }
                // Une ligne dont un référent n'est pas reconnu (faute de frappe, enseignant non
                // inscrit) n'est pas appliquée : l'appliquer à moitié retirerait à l'étudiant le
                // référent qu'il a déjà, alors que le fichier voulait en désigner un.
                if ($invalid) {
                    continue;
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
     * Les lignes dont l'étudiant n'a pas pu être rapproché d'un inscrit au cours (ou l'a été de
     * plusieurs, en cas d'homonymes) sont remontées dans $results->unknownstudents, groupées par
     * libellé : aucune ligne n'est écartée en silence. La page appelante propose alors à la DEVE
     * de désigner elle-même l'étudiant inscrit correspondant à chaque libellé, puis rappelle
     * cette méthode avec le même contenu, la table de correspondance obtenue et la liste des
     * lignes encore en attente (voir pending_lines()).
     *
     * @param \stdClass $stage Activité cible.
     * @param \context $context Contexte de l'activité cible.
     * @param string $content Contenu du CSV (UTF-8 ou Windows-1252).
     * @param array $studentresolutions Libellé d'étudiant non rapproché => identifiant de
     *        l'étudiant inscrit désigné par la DEVE.
     * @param array $duplicateresolutions Numéro de ligne => décision de la DEVE pour une ligne qui
     *        ressemblait à un stage déjà enregistré (voir $results->probableduplicates) : 'new'
     *        pour créer le stage malgré tout, 'skip' pour ne pas l'importer, ou l'identifiant du
     *        stage existant auquel rattacher la ligne.
     * @param array|null $onlylines Numéros des seules lignes à traiter (celles restées en attente
     *        d'un arbitrage aux passes précédentes), ou null pour tout le fichier. Les deux
     *        arbitrages sont repris ensemble : trancher l'un ne fait jamais perdre l'autre.
     * @return array Résultats par ligne et erreur de lecture éventuelle.
     */
    public static function stagevet(
        \stdClass $stage,
        \context $context,
        string $content,
        array $studentresolutions = [],
        array $duplicateresolutions = [],
        ?array $onlylines = null
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
            'durée' => 'durationlabel',
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
            // Évaluations récupérées par StageVet Manager (colonnes facultatives : un export
            // antérieur qui ne les contient pas s'importe comme avant).
            'évaluation par le maître de stage' => 'tutorevaluation',
            'évaluation par l’étudiant' => 'studentevaluation',
        ];
        // Les en-têtes sont comparés sous une forme normalisée (casse, accents, apostrophe droite
        // ou typographique, espaces, BOM) : un fichier retouché dans un tableur reste reconnu.
        $normalizedmap = [];
        foreach ($columnmap as $header => $key) {
            $normalizedmap[self::normalize_header($header)] = $key;
        }

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
        // indexés, et seul un rapprochement sans ambiguïté est retenu : un libellé qui désigne
        // plusieurs inscrits (homonymes, ou ordre de lecture ambigu) est laissé à l'arbitrage de
        // la DEVE plutôt que rattaché au dernier inscrit lu.
        $studentsbyreversedname = [];
        foreach ($students as $student) {
            $studentsbyemail[core_text::strtolower($student->email)][$student->id] = $student;
            $studentsbyname[stage_normalize_name($student->firstname . ' ' . $student->lastname)][$student->id] = $student;
            $studentsbyreversedname[stage_normalize_name($student->lastname . ' ' . $student->firstname)][$student->id] =
                $student;
        }

        // Dans l'export StageVet, le « tuteur » est l'enseignant référent de l'école. Il est distinct du
        // « maître de stage », qui encadre l'étudiant dans la structure d'accueil et est enregistré dans
        // les champs tutor* de la convention.
        $teachersbyemail = [];
        $teachersbyname = [];
        foreach (stage_get_potential_teachers($context) as $teacher) {
            $teachersbyemail[core_text::strtolower($teacher->email)][$teacher->id] = $teacher;
            $teachersbyname[stage_normalize_name($teacher->firstname . ' ' . $teacher->lastname)][$teacher->id] = $teacher;
        }

        $results = null;
        $uploaderror = null;

        $cir = new csv_import_reader(csv_import_reader::get_new_iid('stagevet'), 'stagevet');

        if ($cir->load_csv_content($content, self::detect_encoding($content), self::detect_delimiter($content)) === false) {
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
                    $key = $normalizedmap[self::normalize_header($header)] ?? self::guess_evaluation_column($header);
                    if ($key && !isset($colindex[$key])) {
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
                $results = (object) [
                    'created' => 0,
                    'updated' => 0,
                    'unchanged' => 0,
                    'evaluations' => 0,
                    'notified' => 0,
                    'notifyskipped' => 0,
                    'noreferent' => [],
                    'probableduplicates' => [],
                    'unknownstudents' => [],
                    'unknownthemes' => [],
                    'errors' => [],
                ];
                $onlylines = $onlylines === null ? null : array_flip(array_map('intval', $onlylines));
                $allthemes = stage_get_themes($stage->id);
                $entryrecords = [];
                $detailbyrowkey = [];
                $studentbyrowkey = [];
                $cir->init();
                $linenum = 1;
                // Stages déjà enregistrés, par étudiant et thématique : une ligne qui correspond à
                // l'un d'eux le met à jour (évaluations) au lieu d'en créer un second.
                $existingbykey = [];
                $existingfields = 'id, userid, themeid, datestart, dateend, status, conventionstatus, '
                    . 'studentselfeval, tutoreval, tutortime';
                foreach ($DB->get_records('stage_entry', ['stageid' => $stage->id], 'id ASC', $existingfields) as $existing) {
                    $existingbykey[$existing->userid . '-' . $existing->themeid][$existing->id] = $existing;
                }
                // Doublons à l'intérieur du fichier lui-même : même stage répété à l'identique, ou
                // plages qui se recoupent pour le même étudiant et la même thématique (dates de la
                // convention d'un côté, du tableau de bord de l'autre).
                $filepairs = [];
                $pendingranges = [];
                // Stages dont les deux évaluations viennent d'être complétées : l'enseignant
                // référent est sollicité une fois l'import terminé.
                $torequest = [];

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
                    if ($onlylines !== null && !isset($onlylines[$linenum])) {
                        continue;
                    }

                    $student = null;
                    if ($email !== '') {
                        $student = self::unique_match($studentsbyemail[core_text::strtolower($email)] ?? []);
                    }
                    if (!$student && ($firstname !== '' || $lastname !== '')) {
                        $student = self::unique_match(
                            $studentsbyname[stage_normalize_name($firstname . ' ' . $lastname)] ?? []
                        );
                    }
                    if (!$student && $fullname !== '') {
                        // Repli sur la colonne « Étudiant » du tableau de bord StageVet, toujours
                        // renseignée : les colonnes « Nom étudiant », « Prénom étudiant » et
                        // « Email étudiant » proviennent de la convention PDF et sont vides tant
                        // que celle-ci n'a pas été analysée par StageVetManager.
                        $namekey = stage_normalize_name($fullname);
                        $student = self::unique_match(
                            ($studentsbyname[$namekey] ?? []) + ($studentsbyreversedname[$namekey] ?? [])
                        );
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
                    if (!$student && isset($studentresolutions[$studentlabel])) {
                        $student = $students[(int) $studentresolutions[$studentlabel]] ?? null;
                    }

                    if (!$student) {
                        $results->unknownstudents[$studentlabel][] = $linenum;
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
                    if (isset($filepairs[$pairkey])) {
                        $results->errors[] = get_string('importerrorduplicate', 'mod_stage', (object) [
                            'line' => $linenum, 'email' => fullname($student), 'theme' => $themename,
                        ]);
                        continue;
                    }
                    $filepairs[$pairkey] = true;
                    $rangekey = $student->id . '-' . $theme->id;

                    $studenteval = self::clean_evaluation($getcol($row, 'studentevaluation'));
                    $tutoreval = self::clean_evaluation($getcol($row, 'tutorevaluation'));

                    // Stage déjà présent : mêmes étudiant et thématique, et plage qui recoupe celle
                    // du fichier (les dates de la convention peuvent différer de quelques jours
                    // de celles du tableau de bord utilisées lors d'un import précédent).
                    $matches = self::find_existing_entries(
                        $existingbykey[$student->id . '-' . $theme->id] ?? [],
                        $start,
                        $end
                    );
                    if (count($matches) > 1) {
                        $results->errors[] = get_string('importstageveterrorambiguous', 'mod_stage', (object) [
                            'line' => $linenum, 'student' => fullname($student), 'theme' => $themename,
                        ]);
                        continue;
                    }
                    // Aucun stage de cette thématique ne correspond, mais un stage du même étudiant
                    // (autre thématique aux dates qui se recoupent, ou stage sans dates, comme
                    // ceux d'un ancien suivi) pourrait être le même : la DEVE décide, plutôt
                    // qu'un second exemplaire soit créé en silence.
                    if (!$matches) {
                        // Une ligne précédente du fichier crée déjà ce stage (plage qui recoupe) :
                        // il ne doit pas être créé une seconde fois.
                        foreach ($pendingranges[$rangekey] ?? [] as [$pendingstart, $pendingend]) {
                            if ($pendingstart <= $end && $pendingend >= $start) {
                                $results->errors[] = get_string('importerrorduplicate', 'mod_stage', (object) [
                                    'line' => $linenum, 'email' => fullname($student), 'theme' => $themename,
                                ]);
                                continue 2;
                            }
                        }
                        $probables = stage_find_probable_duplicate_entries(
                            $stage->id,
                            $student->id,
                            $theme->id,
                            $start,
                            $end,
                            self::parse_studyyear($getcol($row, 'studentyear', 'studyyear'))
                        );
                        $decision = $duplicateresolutions[$linenum] ?? null;
                        if ($probables && $decision === null) {
                            $results->probableduplicates[$linenum] = (object) [
                                'line' => $linenum,
                                'student' => fullname($student),
                                'theme' => $themename,
                                'start' => $start,
                                'end' => $end,
                                'candidates' => array_map(
                                    fn($entry) => stage_entry_short_description($entry, $allthemes),
                                    $probables
                                ),
                            ];
                            continue;
                        }
                        if ($probables && $decision === 'skip') {
                            continue;
                        }
                        if ($probables && isset($probables[(int) $decision])) {
                            $matches = [$probables[(int) $decision]];
                        }
                    }
                    if ($matches) {
                        $existing = reset($matches);
                        $outcome = self::apply_evaluations($existing, $studenteval, $tutoreval);
                        foreach ($outcome['kept'] as $kind) {
                            $results->errors[] = get_string('importstagevetevalkept', 'mod_stage', (object) [
                                'line' => $linenum,
                                'student' => fullname($student),
                                'evaluation' => get_string(
                                    $kind === 'student' ? 'studentselfeval' : 'tutorevalheading',
                                    'mod_stage'
                                ),
                            ]);
                        }
                        if ($outcome['changed']) {
                            $results->updated++;
                            $results->evaluations += $outcome['changed'];
                        } else {
                            $results->unchanged++;
                        }
                        if ($outcome['completed']) {
                            $torequest[$existing->id] = $student;
                        }
                        continue;
                    }

                    // Les compteurs en jours viennent tous de la convention PDF. Sans elle, la
                    // seule durée de l'export est le libellé du tableau de bord, exprimé en
                    // semaines : la plage de dates du stage prend alors le relais. La durée ainsi
                    // obtenue reste indicative, la DEVE fixant la durée retenue à la validation.
                    $duration = (int) $getcol($row, 'durationeffective');
                    if (!$duration) {
                        $duration = (int) $getcol($row, 'durationdeclared');
                    }
                    if (!$duration) {
                        $duration = self::parse_duration($getcol($row, 'durationtext'));
                    }
                    if (!$duration) {
                        $duration = self::parse_duration($getcol($row, 'durationlabel'));
                    }
                    if (!$duration) {
                        $duration = self::count_period_days($start, $end);
                    }

                    // L'année propre à l'étudiant dans la convention décrit l'année à laquelle
                    // ce stage doit être rattaché. L'année d'étude générale de l'export sert de
                    // repli pour les anciens exports qui ne fournissent pas la première colonne.
                    $studyyear = self::parse_studyyear($getcol($row, 'studentyear', 'studyyear'));

                    $pendingranges[$rangekey][] = [$start, $end];
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
                        // L'évaluation de l'étudiant, si l'export la fournit, tient lieu de son
                        // auto-évaluation ; celle du maître de stage remplace l'invitation que
                        // l'activité lui aurait sinon envoyée.
                        'status' => $studenteval !== '' ? STAGE_STATUS_EVAL_ETUDIANT : STAGE_STATUS_ENREGISTRE,
                        'studentselfeval' => $studenteval !== '' ? $studenteval : null,
                        'tutoreval' => $tutoreval !== '' ? $tutoreval : null,
                        'tutortime' => $tutoreval !== '' ? time() : null,
                        'conventionstatus' => STAGE_CONVENTION_SIGNVET,
                        'timecreated' => time(),
                        'timemodified' => time(),
                    ];
                    $results->evaluations += ($studenteval !== '' ? 1 : 0) + ($tutoreval !== '' ? 1 : 0);
                    $studentbyrowkey[$rowkey] = $student;

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
                        $referentteacher = self::unique_match($teachersbyemail[core_text::strtolower($referentemail)] ?? []);
                    }
                    $referentname = $getcol($row, 'referentteachername');
                    if (!$referentteacher && $referentname !== '') {
                        $referentteacher = self::unique_match($teachersbyname[stage_normalize_name($referentname)] ?? []);
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
                    if (!empty($record->studentselfeval) && !empty($record->tutoreval)) {
                        $torequest[$entryid] = $studentbyrowkey[$rowkey];
                    }
                }
                $results->created = count($entryrecords);

                // Les deux évaluations amont sont disponibles : l'enseignant référent est invité
                // à évaluer à son tour. Un étudiant sans référent est signalé à la DEVE, qui
                // devra en désigner un pour que l'évaluation puisse avoir lieu.
                if ($torequest) {
                    $cm = get_coursemodule_from_id('stage', $context->instanceid, 0, false, MUST_EXIST);
                    foreach ($torequest as $entryid => $student) {
                        $entry = $DB->get_record('stage_entry', ['id' => $entryid], '*', MUST_EXIST);
                        // Le même stage est déjà validé sous une autre forme (suivi historique,
                        // autre thématique) : rien à demander à l'enseignant.
                        if (stage_has_validated_probable_duplicate($entry)) {
                            $results->notifyskipped++;
                            continue;
                        }
                        if (stage_notify_teachers_eval_request($stage, $cm, $entry, $student)) {
                            $results->notified++;
                        } else {
                            $results->noreferent[$student->id] = fullname($student);
                        }
                    }
                }
            }
        }

        return ['results' => $results, 'error' => $uploaderror];
    }

    /**
     * Lignes d'un import StageVet qui attendent encore un arbitrage de la DEVE : étudiant non
     * rapproché ou doublon probable. Ce sont les seules à reprendre à la passe suivante.
     *
     * @param \stdClass|null $results Résultats renvoyés par stagevet().
     * @return int[] Numéros de ligne, triés.
     */
    public static function pending_lines(?\stdClass $results): array {
        if (!$results) {
            return [];
        }
        $lines = array_keys($results->probableduplicates ?? []);
        foreach ($results->unknownstudents ?? [] as $linenums) {
            $lines = array_merge($lines, $linenums);
        }
        $lines = array_values(array_unique(array_map('intval', $lines)));
        sort($lines);
        return $lines;
    }

    /**
     * Seul élément d'un ensemble de correspondances, ou null s'il n'y en a aucun ou plusieurs.
     *
     * @param array $matches id => enregistrement
     * @return \stdClass|null
     */
    private static function unique_match(array $matches): ?\stdClass {
        return count($matches) === 1 ? reset($matches) : null;
    }

    /**
     * Séparateur du CSV, déterminé sur la seule ligne d'en-tête : un « ; » ou une « , » présents
     * dans un texte libre (évaluation, adresse) ne doivent pas changer la lecture du fichier.
     *
     * @param string $content
     * @return string Nom de séparateur attendu par csv_import_reader ('semicolon', 'comma', 'tab').
     */
    public static function detect_delimiter(string $content): string {
        $content = str_replace("\xEF\xBB\xBF", '', $content);
        $header = '';
        foreach (preg_split('/\R/', $content) as $line) {
            if (trim($line) !== '') {
                $header = $line;
                break;
            }
        }
        $counts = [
            'semicolon' => substr_count($header, ';'),
            'comma' => substr_count($header, ','),
            'tab' => substr_count($header, "\t"),
        ];
        arsort($counts);
        $best = array_key_first($counts);
        return $counts[$best] > 0 ? $best : 'comma';
    }

    /**
     * Encodage du CSV : UTF-8 s'il est valide, sinon Windows-1252, celui des CSV enregistrés par
     * Excel francophone (sans quoi les accents des en-têtes et des thématiques seraient perdus).
     *
     * @param string $content
     * @return string
     */
    public static function detect_encoding(string $content): string {
        return mb_check_encoding($content, 'UTF-8') ? 'UTF-8' : 'WINDOWS-1252';
    }

    /**
     * Forme normalisée d'un en-tête de colonne, pour une comparaison tolérante : sans BOM, sans
     * accents, apostrophes droite et typographique confondues, casse et espaces ignorés.
     *
     * @param string $header
     * @return string
     */
    public static function normalize_header($header) {
        $header = str_replace("\xEF\xBB\xBF", '', (string) $header);
        return stage_normalize_name($header);
    }

    /**
     * Reconnaît une colonne d'évaluation dont l'intitulé s'écarte de celui de l'export actuel
     * (« Evaluation maitre de stage », « Évaluation étudiant », etc.).
     *
     * @param string $header
     * @return string|null 'tutorevaluation', 'studentevaluation' ou null
     */
    public static function guess_evaluation_column($header) {
        $normalized = ' ' . self::normalize_header($header) . ' ';
        if (strpos($normalized, ' evaluation') === false) {
            return null;
        }
        if (preg_match('/ (maitre|maitres|tuteur entreprise|encadrant|tutor) /', $normalized)) {
            return 'tutorevaluation';
        }
        if (preg_match('/ (etudiant|etudiante|stagiaire|student) /', $normalized)) {
            return 'studentevaluation';
        }
        return null;
    }

    /**
     * Met en forme une évaluation lue dans l'export : retours à la ligne unifiés, espaces de fin
     * de ligne et lignes vides en excès retirés. Une cellule vide, ou qui ne fait qu'annoncer
     * l'absence d'évaluation, donne une chaîne vide : elle n'efface jamais une évaluation déjà
     * enregistrée (une extraction incomplète ne doit rien faire perdre).
     *
     * @param string $raw
     * @return string
     */
    public static function clean_evaluation($raw) {
        $text = preg_replace('/\R/u', "\n", (string) $raw);
        $text = preg_replace('/[ \t]+$/mu', '', $text);
        $text = trim(preg_replace("/\n{3,}/", "\n\n", $text));
        $placeholder = '/^(aucune?\s+(évaluation|evaluation)(\s+(disponible|fournie|récupérée|recuperee))?|non\s+disponible|'
            . 'non\s+renseign\S*|n\/?a|-+)\.?$/iu';
        return preg_match($placeholder, $text) ? '' : $text;
    }

    /**
     * Stages déjà enregistrés auxquels une ligne de l'export correspond : la même plage d'abord,
     * puis, à défaut, toute plage qui la recoupe.
     *
     * @param array $candidates Stages du même étudiant sur la même thématique.
     * @param int $start
     * @param int $end
     * @return array Stages correspondants (vide : la ligne crée un stage).
     */
    public static function find_existing_entries(array $candidates, $start, $end) {
        $exact = array_filter($candidates, fn($entry) => (int) $entry->datestart === (int) $start
            && (int) $entry->dateend === (int) $end);
        if ($exact) {
            return $exact;
        }
        return array_filter($candidates, fn($entry) => !empty($entry->datestart) && !empty($entry->dateend)
            && $entry->datestart <= $end && $entry->dateend >= $start);
    }

    /**
     * Reporte sur un stage existant les évaluations de l'export. Une évaluation vide dans le
     * fichier ne change rien. Un stage importé de StageVet reçoit la version la plus récente de
     * l'export ; pour un stage créé autrement, une évaluation déjà saisie dans l'activité n'est
     * jamais remplacée (elle est signalée comme conservée).
     *
     * @param \stdClass $entry Stage existant (champs lus par stagevet()), mis à jour en place.
     * @param string $studenteval
     * @param string $tutoreval
     * @return array ['changed' => nombre d'évaluations écrites, 'kept' => types conservés,
     *               'completed' => les deux évaluations viennent d'être réunies]
     */
    public static function apply_evaluations(\stdClass $entry, $studenteval, $tutoreval) {
        global $DB;

        $hadboth = trim((string) $entry->studentselfeval) !== '' && trim((string) $entry->tutoreval) !== '';
        $fromstagevet = (int) $entry->conventionstatus === STAGE_CONVENTION_SIGNVET;
        $update = (object) ['id' => $entry->id];
        $changed = 0;
        $kept = [];

        $incoming = ['student' => ['studentselfeval', $studenteval], 'tutor' => ['tutoreval', $tutoreval]];
        foreach ($incoming as $kind => [$field, $value]) {
            $current = trim((string) $entry->$field);
            if ($value === '' || $current === $value) {
                continue;
            }
            if ($current !== '' && !$fromstagevet) {
                $kept[] = $kind;
                continue;
            }
            $update->$field = $entry->$field = $value;
            $changed++;
        }
        if (isset($update->tutoreval)) {
            $update->tutortime = $entry->tutortime = time();
        }
        // L'évaluation de l'étudiant fait avancer un stage simplement enregistré, jamais un stage
        // annulé, non validé ou déjà évalué par l'enseignant.
        if (isset($update->studentselfeval) && (int) $entry->status === STAGE_STATUS_ENREGISTRE) {
            $update->status = $entry->status = STAGE_STATUS_EVAL_ETUDIANT;
        }
        if ($changed) {
            $update->timemodified = time();
            $DB->update_record('stage_entry', $update);
        }

        $hasboth = trim((string) $entry->studentselfeval) !== '' && trim((string) $entry->tutoreval) !== '';
        $awaitingteacher = in_array((int) $entry->status, [STAGE_STATUS_ENREGISTRE, STAGE_STATUS_EVAL_ETUDIANT], true);
        return ['changed' => $changed, 'kept' => $kept, 'completed' => !$hadboth && $hasboth && $awaitingteacher];
    }

    /**
     * Convertit une date JJ/MM/AAAA (StageVet, Excel francophone) ou AAAA-MM-JJ en timestamp
     * (minuit), ou null si elle est vide ou invalide. L'année doit avoir quatre chiffres et la
     * date exister : « 15/03/26 » ou « 31/02/2026 » sont refusées plutôt que lues comme l'an 26
     * ou le 3 mars. Une heure éventuelle, ajoutée par un tableur, est ignorée.
     *
     * @param string|null $raw
     * @return int|null
     */
    public static function parse_date($raw) {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        $time = '(?:[ T]\d{1,2}:\d{2}(?::\d{2})?)?';
        if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})' . $time . '$#', $raw, $matches)) {
            [, $day, $month, $year] = $matches;
        } else if (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})' . $time . '$#', $raw, $matches)) {
            [, $year, $month, $day] = $matches;
        } else {
            return null;
        }
        if ((int) $year < 1900 || !checkdate((int) $month, (int) $day, (int) $year)) {
            return null;
        }
        return make_timestamp((int) $year, (int) $month, (int) $day);
    }

    /**
     * Extrait le nombre de jours d'un texte de durée StageVet ("7  jours effectifs" -> 7).
     *
     * Un libellé exprimé dans une autre unité ("4 semaines", côté tableau de bord) est refusé
     * plutôt que converti : passer des semaines aux jours suppose de trancher entre jours
     * calendaires et jours ouvrés, ce qui relève de la scolarité et non de l'import. La plage de
     * dates du stage, exacte et présente dans le fichier, sert de repli (count_period_days()).
     *
     * @param string $raw
     * @return int 0 si aucun nombre, ou si l'unité n'est pas le jour
     */
    public static function parse_duration($raw) {
        $raw = trim($raw);
        if (!preg_match('/(\d+)/', $raw, $matches)) {
            return 0;
        }
        if (preg_match('/semaine|mois|ann[ée]e/iu', $raw)) {
            return 0;
        }
        return (int) $matches[1];
    }

    /**
     * Nombre de jours calendaires couverts par la plage du stage, bornes comprises.
     *
     * Les deux bornes sont des minuits : l'arrondi absorbe l'heure gagnée ou perdue lorsque la
     * plage traverse un changement d'heure.
     *
     * @param int|null $start
     * @param int|null $end
     * @return int 0 si la plage est absente ou incohérente
     */
    public static function count_period_days($start, $end) {
        if (empty($start) || empty($end) || $end < $start) {
            return 0;
        }
        return (int) round(($end - $start) / DAYSECS) + 1;
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
