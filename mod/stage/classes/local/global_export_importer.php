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

/**
 * Transforme la feuille « Stages » de l'export global en données restaurables.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class global_export_importer {
    /**
     * Restaure les saisies préparées par la prévisualisation, sous le verrou d'écriture de
     * l'activité.
     *
     * Sous le verrou, les doublons sont relus : une seconde restauration du même export (confirmée
     * par un collègue), ou un import, a pu enregistrer ces stages depuis la prévisualisation. Une
     * saisie que la prévisualisation avait trouvée sans doublon (clé probableduplicates présente et
     * vide) et qui en a un désormais n'est pas restaurée : la DEVE n'a pas pu l'arbitrer. Une saisie
     * signalée comme doublon probable et cochée par la DEVE est restaurée telle quelle.
     *
     * @param int $stageid Activité cible contrôlée par l'appelant.
     * @param array $records Saisies préparées par la prévisualisation.
     * @param int|null $skipped Nombre de saisies écartées, devenues des doublons depuis la
     *        prévisualisation.
     * @return int Nombre de saisies restaurées.
     */
    public static function restore(int $stageid, array $records, ?int &$skipped = null): int {
        global $DB;

        $skipped = 0;
        $restored = 0;
        $createdids = [];
        $lock = \stage_get_activity_lock($stageid);
        try {
            $transaction = $DB->start_delegated_transaction();
            foreach ($records as $saved) {
                $saved = (object) $saved;
                $entry = clone (object) $saved->entry;
                if (isset($saved->probableduplicates) && empty($saved->probableduplicates)) {
                    $probables = array_diff_key(\stage_find_probable_duplicate_entries(
                        $stageid,
                        $entry->userid,
                        $entry->themeid,
                        $entry->datestart ?: null,
                        $entry->dateend ?: null,
                        $entry->studyyear ?? 0
                    ), $createdids);
                    if ($probables) {
                        $skipped++;
                        continue;
                    }
                }
                $entry->stageid = $stageid;
                $entry->timecreated = $entry->timecreated ?: time();
                $entry->timemodified = $entry->timemodified ?: $entry->timecreated;
                $entryid = $DB->insert_record('stage_entry', $entry);
                $createdids[$entryid] = true;
                if (!empty($saved->detail)) {
                    $detail = clone (object) $saved->detail;
                    $detail->entryid = $entryid;
                    $detail->timecreated = $entry->timecreated;
                    $detail->timemodified = $entry->timemodified;
                    $DB->insert_record('stage_convention_detail', convention_detail::fit($detail));
                }
                // Les plages exportées sont restaurées telles quelles : un stage en plusieurs plages
                // ne doit pas devenir une plage continue, qui compterait les jours entre deux.
                $periods = !empty($saved->periods) ? array_values((array) $saved->periods) : [];
                if (!$periods && $entry->datestart && $entry->dateend) {
                    $periods = [['datestart' => $entry->datestart, 'dateend' => $entry->dateend]];
                }
                if ($periods) {
                    \stage_save_entry_periods($entryid, array_map(fn($period) => (array) $period, $periods));
                    // L'enregistrement des plages date la saisie d'aujourd'hui : sa date d'origine est
                    // rétablie.
                    $DB->set_field('stage_entry', 'timemodified', $entry->timemodified, ['id' => $entryid]);
                }
                $restored++;
            }
            $transaction->allow_commit();
        } finally {
            $lock->release();
        }
        return $restored;
    }

    /**
     * Lit la feuille « Stages » du classeur et en tire une ligne restaurable par stage.
     *
     * @param string $filepath Chemin du classeur exporté.
     * @return array{records: array, warnings: array}
     */
    public static function read(string $filepath): array {
        $sheets = historical_importer::read_sheets($filepath);
        foreach ($sheets as $rows) {
            $headers = $rows[0] ?? [];
            $columns = self::columns($headers);
            if (!isset($columns['entryid'], $columns['email'], $columns['theme'])) {
                continue;
            }
            $records = [];
            foreach ($rows as $index => $row) {
                if ($index === 0 || trim(self::value($row, $columns, 'email')) === '') {
                    continue;
                }
                $record = new \stdClass();
                foreach ($columns as $field => $column) {
                    // L'export protège les textes commençant par = + - ou @ d'une apostrophe, pour
                    // qu'Excel ne les prenne pas pour des formules (voir escape_spreadsheet_formula()).
                    $record->$field = preg_replace("/^'(?=[=+\\-@])/", '', trim((string) ($row[$column] ?? '')));
                }
                $record->line = $index + 1;
                foreach (
                    ['datestart', 'dateend', 'studentbirthdate', 'teachertime', 'tutortime', 'tutorrequesttime',
                        'conventionremindertime', 'devetime',
                        'canceltime', 'timecreated', 'timemodified', 'conventionrequesttime',
                        'conventionteachervalidatetime', 'conventionedittime', 'conventionsigntime',
                        'conventionrejecttime'] as $field
                ) {
                    $record->$field = self::date($record->$field ?? '');
                }
                // Dates des messages automatiques : absentes des exports antérieurs à ces colonnes,
                // ce que la restauration doit pouvoir distinguer d'un message jamais envoyé.
                foreach (['tutorrequesttime', 'conventionremindertime'] as $field) {
                    if (!isset($columns[$field])) {
                        unset($record->$field);
                    }
                }
                $record->periods = self::periods($record->periods ?? '');
                $records[] = $record;
            }
            return ['records' => $records, 'warnings' => []];
        }
        throw new \moodle_exception('globalimportinvalid', 'mod_stage');
    }

    /**
     * Colonnes reconnues dans un export français ou anglais.
     *
     * @param array $headers Ligne d'intitulés du classeur.
     * @return array Nom de champ => index de colonne.
     */
    private static function columns(array $headers): array {
        $languagekeys = [
            'entryid' => 'exportentryid', 'email' => 'email', 'studyyear' => 'studyyear', 'theme' => 'theme',
            'stagetype' => 'conventionstagetype', 'structure' => 'structure', 'abroad' => 'abroad',
            'country' => 'country', 'datestart' => 'datestart', 'dateend' => 'dateend', 'periods' => 'periods',
            'declaredduration' => 'declaredduration', 'retainedduration' => 'retainedduration', 'status' => 'status',
            'conventionstatus' => 'conventionstatus', 'conventiontemplatename' => 'conventiontemplatename',
            'yearsituation' => 'conventionyearsituation', 'studentselfeval' => 'studentselfeval',
            'evaluatedby' => 'evaluatedby', 'teachertime' => 'teachervalidationtime', 'teachereval' => 'teachereval',
            'tutoreval' => 'tutorevalheading', 'tutortime' => 'tutorevaltime',
            'tutorbypassed' => 'tutorevalbypassedcolumn', 'tutorrequesttime' => 'exporttutorrequesttime',
            'conventionremindertime' => 'exportconventionremindertime', 'devevalidatedby' => 'devevalidatedby',
            'devetime' => 'devevalidationtime', 'devecomment' => 'devecomment', 'cancelledby' => 'cancelledby',
            'canceltime' => 'canceltime', 'cancelcomment' => 'cancelcomment', 'timecreated' => 'timecreated',
            'timemodified' => 'lastmodified', 'referentteacher' => 'conventionreferentteacher',
            'conventionrequesttime' => 'conventionrequesttime',
            'conventionteachervalidatedby' => 'conventionteachervalidatedby',
            'conventionteachervalidatetime' => 'conventionteachervalidatetime',
            'conventioneditedby' => 'conventioneditedby', 'conventionedittime' => 'conventionedittime',
            'conventionsignedby' => 'conventionsignedby', 'conventionsigntime' => 'conventionsigntime',
            'conventionrejectedby' => 'conventionrejectedby', 'conventionrejecttime' => 'conventionrejecttime',
            'conventionrejectcomment' => 'conventionrejectcomment', 'studentbirthdate' => 'conventionbirthdate',
            'studentaddress' => 'conventionstudentaddress', 'studentphone' => 'conventionstudentphone',
            'hostaddress' => 'conventionhostaddress', 'hostrepresentative' => 'conventionhostrepresentative',
            'hostrepresentativetitle' => 'conventionhostrepresentativetitle', 'hostservice' => 'conventionhostservice',
            'hostphone' => 'conventionhostphone', 'hostemail' => 'conventionhostemail',
            'hostlocation' => 'conventionhostlocation', 'tutorname' => 'conventiontutorname',
            'tutorfunction' => 'conventiontutorfunction', 'tutorphone' => 'conventiontutorphone',
            'tutoremail' => 'conventiontutoremail', 'nightpresence' => 'conventionnightpresence',
            'sundaypresence' => 'conventionsundaypresence', 'holidaypresence' => 'conventionholidaypresence',
            'homebased' => 'conventionhomebased', 'othermodality' => 'conventionothermodality',
            'gratificationamount' => 'conventiongratification', 'hasleave' => 'conventionhasleave',
            'leavedays' => 'conventionleavedays', 'leavemodalities' => 'conventionleavemodalities',
        ];
        $aliases = [
            'entryid' => ['n de stage', 'internship id'], 'email' => ['adresse de courriel', 'email'],
            'studyyear' => ['annee d etude', 'study year'], 'theme' => ['thematique', 'theme'],
            'stagetype' => ['type de stage', 'internship type'], 'structure' => ['structure d accueil', 'host organisation'],
            'abroad' => ['a l etranger', 'abroad'], 'country' => ['pays', 'country'],
            'datestart' => ['date de debut', 'start date'], 'dateend' => ['date de fin', 'end date'],
            'periods' => ['plages de dates', 'date ranges'],
            'declaredduration' => ['duree declaree jours', 'declared duration days'],
            'retainedduration' => ['duree retenue jours', 'retained duration days'],
            'status' => ['statut', 'status'], 'conventionstatus' => ['statut de la convention', 'agreement status'],
            'conventiontemplatename' => ['gabarit de convention', 'agreement template'],
            'yearsituation' => ['situation dans l annee', 'year situation'],
            'studentselfeval' => ['auto evaluation etudiant', 'student self evaluation'],
            'evaluatedby' => ['evalue par', 'evaluated by'],
            'teachertime' => ['date de validation enseignant', 'teacher validation date'],
            'teachereval' => ['evaluation enseignant', 'teacher evaluation'],
            'tutoreval' => ['evaluation du maitre de stage', 'workplace tutor evaluation'],
            'tutortime' => ['date de l evaluation du maitre de stage', 'workplace tutor evaluation date'],
            'tutorbypassed' => ['evaluation du maitre de stage ignoree', 'workplace tutor evaluation skipped'],
            'tutorrequesttime' => ['date de l invitation du maitre de stage', 'workplace tutor invitation date'],
            'conventionremindertime' => ['date de la relance de convention', 'agreement reminder date'],
            'devevalidatedby' => ['valide par la deve', 'validated by the administration office'],
            'devetime' => ['date de validation deve', 'administration office validation date'],
            'devecomment' => ['commentaire deve', 'administration office comment'],
            'cancelledby' => ['annule par', 'cancelled by'], 'canceltime' => ['date d annulation', 'cancellation date'],
            'cancelcomment' => ['motif d annulation', 'cancellation comment'],
            'timecreated' => ['date de creation', 'time created'], 'timemodified' => ['derniere modification', 'last modified'],
            'referentteacher' => ['enseignant referent de la convention', 'agreement referring teacher'],
            'conventionrequesttime' => ['date de demande de convention', 'agreement request date'],
            'conventionteachervalidatedby' => ['demande de convention validee par', 'agreement request validated by'],
            'conventionteachervalidatetime' => ['date de validation de la demande', 'agreement request validation date'],
            'conventioneditedby' => ['convention editee par', 'agreement edited by'],
            'conventionedittime' => ['date d edition de la convention', 'agreement editing date'],
            'conventionsignedby' => ['convention signee par', 'agreement signed by'],
            'conventionsigntime' => ['date de signature de la convention', 'agreement signing date'],
            'conventionrejectedby' => ['convention refusee par', 'agreement rejected by'],
            'conventionrejecttime' => ['date de refus de la convention', 'agreement rejection date'],
            'conventionrejectcomment' => ['motif du refus de la convention', 'agreement rejection comment'],
            'studentbirthdate' => ['date de naissance', 'birth date'],
            'studentaddress' => ['adresse de l etudiant', 'student address'],
            'studentphone' => ['telephone de l etudiant', 'student phone'],
            'hostaddress' => ['adresse de l organisme', 'host address'],
            'hostrepresentative' => ['representant de l organisme', 'host representative'],
            'hostrepresentativetitle' => ['qualite du representant', 'host representative title'],
            'hostservice' => ['service d accueil', 'host service'], 'hostphone' => ['telephone de l organisme', 'host phone'],
            'hostemail' => ['courriel de l organisme', 'host email'], 'hostlocation' => ['lieu du stage', 'internship location'],
            'tutorname' => ['maitre de stage', 'workplace tutor'],
            'tutorfunction' => ['fonction du maitre de stage', 'workplace tutor function'],
            'tutorphone' => ['telephone du maitre de stage', 'workplace tutor phone'],
            'tutoremail' => ['courriel du maitre de stage', 'workplace tutor email'],
            'nightpresence' => ['presence de nuit', 'night presence'],
            'sundaypresence' => ['presence le dimanche', 'sunday presence'],
            'holidaypresence' => ['presence un jour ferie', 'public holiday presence'],
            'homebased' => ['stage au domicile', 'home based'],
            'othermodality' => ['autre modalite', 'other arrangement'], 'gratificationamount' => ['gratification', 'allowance'],
            'hasleave' => ['conges prevus', 'leave provided'], 'leavedays' => ['nombre de jours de conge', 'leave days'],
            'leavemodalities' => ['modalites des conges', 'leave arrangements'],
        ];
        // Intitulés de l'export dans toutes les langues installées : un export fait en anglais se
        // restaure depuis une session en français, et inversement.
        $labels = self::option_labels(function () use ($languagekeys) {
            $labels = [];
            foreach ($languagekeys as $field => $key) {
                $component = $key === 'email' || $key === 'lastmodified' ? 'moodle' : 'mod_stage';
                $labels[$field] = get_string($key, $component);
            }
            return $labels;
        });
        foreach ($labels as $field => $fieldlabels) {
            foreach ($fieldlabels as $label) {
                $aliases[$field][] = self::normalize($label);
            }
        }
        $result = [];
        foreach ($headers as $column => $header) {
            $normalized = self::normalize((string) $header);
            foreach ($aliases as $field => $names) {
                // La première colonne reconnue l'emporte : un intitulé générique plus loin dans la
                // feuille (le « Courriel » du maître de stage, par exemple) ne doit pas remplacer
                // la colonne principale du même nom. Une colonne ne renseigne qu'un champ : deux
                // colonnes de même intitulé (« Téléphone » de l'étudiant puis du maître de stage)
                // vont, dans l'ordre de la feuille, aux champs dans l'ordre où ils sont déclarés.
                if (!isset($result[$field]) && in_array($normalized, $names, true)) {
                    $result[$field] = $column;
                    break;
                }
            }
        }
        return $result;
    }

    /**
     * Valeur d'un champ dans une ligne, chaîne vide si la colonne est absente.
     *
     * @param array $row
     * @param array $columns Voir columns().
     * @param string $field
     * @return string
     */
    private static function value(array $row, array $columns, string $field): string {
        return isset($columns[$field]) ? (string) ($row[$columns[$field]] ?? '') : '';
    }

    /**
     * Convertit une date Excel ou une date textuelle issue du même export.
     *
     * L'export écrit les dates dans le fuseau de l'utilisateur (voir write_date()) : le numéro de
     * série Excel décrit donc une heure locale, et non une heure UTC. Il est reconverti dans ce
     * même fuseau, faute de quoi chaque date restaurée serait décalée de l'écart à UTC et ne
     * correspondrait plus au stage d'origine.
     *
     * @param mixed $value
     * @return int|null Horodatage, ou null si la valeur n'est pas une date.
     */
    private static function date($value): ?int {
        if ($value === '' || $value === null) {
            return null;
        }
        if (is_numeric($value)) {
            $wallclock = (int) round(((float) $value - 25569) * 86400);
            [$year, $month, $day, $hour, $minute, $second] = array_map('intval', explode('-', gmdate('Y-n-j-G-i-s', $wallclock)));
            return make_timestamp($year, $month, $day, $hour, $minute, $second);
        }
        $value = trim((string) $value);
        $timestamp = csv_importer::parse_date($value) ?? self::text_date($value);
        if ($timestamp === null) {
            $timestamp = strtotime($value);
        }
        return $timestamp === false ? null : $timestamp;
    }

    /**
     * Date écrite en toutes lettres par l'export (« 15 mars 2026 », « 15 March 2026 »), telle
     * qu'elle figure dans la colonne des plages de dates.
     *
     * @param string $value
     * @return int|null
     */
    private static function text_date(string $value): ?int {
        $months = [
            'janvier' => 1, 'fevrier' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6, 'juillet' => 7,
            'aout' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'decembre' => 12,
            'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4, 'may' => 5, 'june' => 6, 'july' => 7,
            'august' => 8, 'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
        ];
        if (!preg_match('/^(\d{1,2}) ([a-z]+) (\d{4})$/', self::normalize($value), $matches)) {
            return null;
        }
        $month = $months[$matches[2]] ?? 0;
        if (!$month || !checkdate($month, (int) $matches[1], (int) $matches[3])) {
            return null;
        }
        return make_timestamp((int) $matches[3], $month, (int) $matches[1]);
    }

    /**
     * Plages de dates d'un stage, lues dans la colonne « Plages de dates » de l'export
     * (« début - fin ; début - fin », renseignée seulement quand il y en a plusieurs).
     *
     * @param string $value
     * @return array Liste de ['datestart' => int, 'dateend' => int] ; vide si absente ou illisible.
     */
    public static function periods(string $value): array {
        $periods = [];
        foreach (preg_split('/\s*;\s*/', trim($value), -1, PREG_SPLIT_NO_EMPTY) as $label) {
            $bounds = preg_split('/\s+-\s+/', $label);
            if (count($bounds) !== 2) {
                return [];
            }
            $start = self::date($bounds[0]);
            $end = self::date($bounds[1]);
            if (!$start || !$end || $end < $start) {
                return [];
            }
            $periods[] = ['datestart' => $start, 'dateend' => $end];
        }
        return $periods;
    }

    /**
     * Forme comparable d'un libellé : minuscules, sans accent ni ponctuation.
     *
     * @param string $value
     * @return string
     */
    public static function normalize(string $value): string {
        // Les noms de thématique et de gabarit sont écrits par format_string() : « & » y devient
        // « &amp; ». Les entités sont décodées pour retrouver le nom enregistré.
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = \core_text::strtolower(trim($value));
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = $ascii === false ? $value : $ascii;
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', $value)));
    }

    /**
     * Libellés de chaque valeur d'une liste, dans toutes les langues installées : un export fait
     * en anglais doit se restaurer depuis une session en français, et inversement.
     *
     * @param callable $build Fonction sans argument qui rend valeur => libellé dans la langue
     *        courante (celle-ci est changée le temps de chaque appel).
     * @return array Valeur => libellés.
     */
    public static function option_labels(callable $build): array {
        $languages = array_unique(array_merge(['en'], array_keys(get_string_manager()->get_list_of_translations(true))));
        $labels = [];
        $previous = force_current_language('en');
        try {
            foreach ($languages as $language) {
                force_current_language($language);
                foreach ($build() as $value => $label) {
                    $labels[$value][] = (string) $label;
                }
            }
        } finally {
            force_current_language($previous);
        }
        return $labels;
    }

    /**
     * Valeur dont l'un des libellés (ou la valeur elle-même) correspond au texte de la cellule.
     *
     * @param string $text Texte de la cellule.
     * @param array $options Valeur => libellés (voir option_labels()).
     * @return int|string|null La valeur, ou null si le texte n'en désigne aucune.
     */
    public static function match_option(string $text, array $options) {
        $needle = self::normalize($text);
        if ($needle === '') {
            return null;
        }
        foreach ($options as $value => $labels) {
            foreach (array_merge([(string) $value], (array) $labels) as $label) {
                if ($needle === self::normalize($label)) {
                    return $value;
                }
            }
        }
        return null;
    }

    /**
     * Compte désigné par son nom complet dans l'export (validateur DEVE, évaluateur, signataire...).
     *
     * Les inscrits au cours sont cherchés d'abord ; à défaut, tous les comptes du site, la DEVE
     * n'étant souvent pas inscrite au cours (rôle attribué sur la catégorie). Seule une
     * correspondance unique est retenue : des homonymes ne désignent personne.
     *
     * @param string $name Nom complet tel qu'écrit dans l'export.
     * @param array $enrolled Nom normalisé => comptes inscrits portant ce nom.
     * @return int|null Identifiant du compte, ou null si aucun ou plusieurs comptes correspondent.
     */
    public static function find_user(string $name, array $enrolled): ?int {
        global $DB;

        $key = self::normalize($name);
        if ($key === '') {
            return null;
        }
        if (isset($enrolled[$key])) {
            return count($enrolled[$key]) === 1 ? (int) reset($enrolled[$key])->id : null;
        }
        // Le nom est exporté dans l'ordre d'affichage du site (prénom nom, ou nom prénom) : les
        // deux ordres sont essayés, sans tenir compte de la casse. Les accents comptent (PostgreSQL
        // ne sait pas les ignorer ici) : l'export reprend les noms tels qu'ils sont enregistrés.
        $words = preg_split('/\s+/', trim($name));
        $candidates = [];
        for ($split = 1; $split < count($words); $split++) {
            $first = implode(' ', array_slice($words, 0, $split));
            $last = implode(' ', array_slice($words, $split));
            foreach ([[$first, $last], [$last, $first]] as [$firstname, $lastname]) {
                $select = 'deleted = 0 AND ' . $DB->sql_like('firstname', ':firstname', false)
                    . ' AND ' . $DB->sql_like('lastname', ':lastname', false);
                $params = ['firstname' => $DB->sql_like_escape($firstname), 'lastname' => $DB->sql_like_escape($lastname)];
                foreach ($DB->get_fieldset_select('user', 'id', $select, $params) as $id) {
                    $candidates[(int) $id] = true;
                }
            }
        }
        return count($candidates) === 1 ? (int) array_key_first($candidates) : null;
    }
}
