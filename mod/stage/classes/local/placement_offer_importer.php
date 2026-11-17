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

namespace mod_stage\local;

/**
 * Import Excel des organismes proposés d'une thématique (voir placementlib.php).
 *
 * Le classeur est lu sur sa première feuille, la première ligne non vide portant les intitulés.
 * Les colonnes sont reconnues par leur intitulé, en français ou en anglais, sans tenir compte des
 * accents ni de la casse ; leur ordre est libre et seule la colonne du nom de l'organisme est
 * obligatoire. Une colonne absente ne modifie rien ; le nombre de places vaut 1 par défaut.
 *
 * Un organisme déjà proposé pour la thématique (même SIRET, ou à défaut même nom si l'un des deux
 * n'a pas de SIRET) est mis à jour
 * par les cellules renseignées de sa ligne, les autres sont ajoutés : le classeur peut être
 * réimporté après correction. Chaque ligne est traitée indépendamment : une ligne en erreur est
 * signalée et n'empêche pas l'import des autres.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class placement_offer_importer {
    /** @var array Intitulés reconnus pour chaque colonne, en plus de ceux du modèle, déjà normalisés. */
    const ALIASES = [
        'name' => [
            'organisme', 'organisme d accueil', 'structure', 'structure d accueil', 'nom', 'nom de l organisme',
            'etablissement', 'clinique', 'organisation', 'host organisation', 'host structure', 'name',
        ],
        'places' => ['places', 'nombre de places', 'nb places', 'capacite', 'effectif', 'capacity', 'seats'],
        'description' => ['precisions', 'description', 'commentaire', 'remarques', 'details', 'comment', 'notes'],
        'address' => ['adresse', 'adresse de l organisme', 'address', 'host organisation address'],
        'representative' => ['representant', 'represente par', 'representative', 'represented by'],
        'representativetitle' => [
            'qualite du representant', 'qualite', 'qualite du representant de la representante',
            'representative s title', 'representative title',
        ],
        'service' => ['service', 'department'],
        'phone' => ['telephone', 'tel', 'telephone de l organisme', 'phone', 'host organisation phone'],
        'email' => [
            'courriel', 'email', 'e mail', 'mail', 'courriel de l organisme', 'adresse mail', 'host organisation email',
        ],
        'siret' => ['siret', 'numero siret', 'n siret', 'numero siret de l organisme d accueil', 'siret number'],
        'location' => ['lieu du stage', 'lieu', 'ville', 'commune', 'internship location', 'location', 'city'],
        'country' => ['pays', 'country'],
        'tutorname' => [
            'tuteur', 'tutrice', 'nom du tuteur', 'maitre de stage', 'tuteur de stage', 'supervisor', 'tutor',
            'supervisor name',
        ],
        'tutorfunction' => ['fonction du tuteur', 'fonction', 'supervisor s role', 'supervisor role', 'role'],
        'tutorphone' => ['telephone du tuteur', 'tel tuteur', 'supervisor s phone', 'supervisor phone'],
        'tutoremail' => [
            'courriel du tuteur', 'email du tuteur', 'mail du tuteur', 'supervisor s email', 'supervisor email',
        ],
        'datestart' => ['debut', 'date de debut', 'debut d accueil', 'start', 'start date', 'from'],
        'dateend' => ['fin', 'date de fin', 'fin d accueil', 'end', 'end date', 'to'],
    ];

    /** @var string[] Colonnes de numéros de téléphone : un tableur retire le 0 initial d'un numéro saisi comme un nombre. */
    const PHONE_FIELDS = ['phone', 'tutorphone'];

    /**
     * Intitulés des colonnes du modèle téléchargeable, dans l'ordre du classeur.
     *
     * @param string|null $lang Langue des intitulés (langue courante par défaut).
     * @return string[] Champ de l'organisme => intitulé.
     */
    public static function template_headers(?string $lang = null): array {
        $headers = [];
        foreach (array_keys(self::ALIASES) as $field) {
            $headers[$field] = get_string_manager()->get_string('placementcol' . $field, 'mod_stage', null, $lang);
        }
        return $headers;
    }

    /**
     * Lit la première feuille du classeur.
     *
     * @param string $filepath Classeur XLSX.
     * @return array Lignes : stdClass{line, et les champs présents dans le classeur}.
     * @throws \moodle_exception Si le fichier n'est pas un classeur ou si la colonne du nom manque.
     */
    public static function read(string $filepath): array {
        $sheets = historical_importer::read_sheets($filepath);
        $rows = reset($sheets) ?: [];
        return self::parse_rows($rows);
    }

    /**
     * Interprète les lignes d'une feuille, la première ligne non vide portant les intitulés.
     *
     * @param array $rows Lignes de la feuille, indexées par numéro de ligne puis de colonne.
     * @return array Voir read().
     * @throws \moodle_exception Si la colonne du nom de l'organisme manque.
     */
    public static function parse_rows(array $rows): array {
        ksort($rows);
        $columns = null;
        $records = [];
        $line = 0;
        foreach ($rows as $row) {
            $line++;
            $cells = array_map(fn($value) => trim((string) $value), $row);
            if (implode('', $cells) === '') {
                continue;
            }
            if ($columns === null) {
                $columns = self::columns($cells);
                if (!isset($columns['name'])) {
                    throw new \moodle_exception('placementimportnoname', 'mod_stage');
                }
                continue;
            }
            $record = (object) ['line' => $line];
            foreach ($columns as $field => $col) {
                $record->$field = self::normalize_cell($field, $cells[$col] ?? '');
            }
            $records[] = $record;
        }
        return $records;
    }

    /**
     * Remet une cellule dans la forme attendue, quelle que soit la façon dont le tableur l'a
     * enregistrée. Excel et LibreOffice enregistrent un nombre saisi dans une cellule « standard »
     * comme un nombre : « 2 » devient « 2.0 », un SIRET « 73282932000074 » peut devenir
     * « 7.3282932000074E+13 », un téléphone « 0478000000 » perd son 0 initial, une date devient un
     * numéro de série (jours depuis le 30 décembre 1899).
     *
     * @param string $field Champ de l'organisme.
     * @param string $value Cellule, telle que lue dans le classeur.
     * @return string|int Valeur normalisée ; un horodatage pour une date reconnue.
     */
    public static function normalize_cell(string $field, string $value) {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        // Nombre entier écrit comme un décimal ou en notation scientifique (14 chiffres tiennent
        // sans perte dans un flottant).
        $integer = $value;
        if (preg_match('/^\d+[.,]0+$/', $value)) {
            $integer = preg_replace('/[.,]0+$/', '', $value);
        } else if (preg_match('/^\d+(?:[.,]\d+)?[eE]\+?\d+$/', $value)) {
            $integer = sprintf('%.0f', (float) str_replace(',', '.', $value));
        }
        if ($field === 'places' || $field === 'siret') {
            return $integer;
        }
        if (in_array($field, self::PHONE_FIELDS, true) && preg_match('/^[1-9]\d{8}$/', $integer)) {
            // Numéro français de dix chiffres dont le tableur a retiré le 0 initial.
            return '0' . $integer;
        }
        if (in_array($field, self::PHONE_FIELDS, true)) {
            return $integer;
        }
        if ($field === 'datestart' || $field === 'dateend') {
            return self::parse_date($value);
        }
        return $value;
    }

    /**
     * Date d'une cellule : numéro de série d'un tableur, jj/mm/aaaa ou aaaa-mm-jj.
     *
     * @param string $value
     * @return int|string Horodatage à minuit (heure de l'utilisateur), ou la valeur telle quelle si
     *         elle n'est pas une date : la ligne sera alors refusée (voir record_error()).
     */
    public static function parse_date(string $value) {
        $value = trim($value);
        if (preg_match('/^\d{4,5}(?:[.,]\d+)?$/', $value)) {
            $days = (int) floor((float) str_replace(',', '.', $value));
            $date = (new \DateTimeImmutable('1899-12-30', new \DateTimeZone('UTC')))->modify("+{$days} days");
            return make_timestamp((int) $date->format('Y'), (int) $date->format('n'), (int) $date->format('j'));
        }
        if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{2}|\d{4})$#', $value, $m)) {
            [$day, $month, $year] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            $year += $year < 100 ? 2000 : 0;
        } else if (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})#', $value, $m)) {
            [$year, $month, $day] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return $value;
        }
        return checkdate($month, $day, $year) ? make_timestamp($year, $month, $day) : $value;
    }

    /**
     * Enregistre les organismes lus par read().
     *
     * @param \stdClass $theme Thématique à organismes proposés.
     * @param array $records Lignes de read().
     * @return \stdClass {added, updated, errors}
     */
    public static function import(\stdClass $theme, array $records): \stdClass {
        global $DB;

        $results = (object) ['added' => 0, 'updated' => 0, 'errors' => []];
        // Sous le verrou de l'activité : deux imports simultanés ajouteraient deux fois les mêmes
        // organismes, chacun ne voyant pas ceux de l'autre.
        $lock = stage_get_activity_lock($theme->stageid);
        try {
            $transaction = $DB->start_delegated_transaction();
            $known = stage_placement_index_offers(stage_placement_get_offers($theme->id));
            foreach ($records as $record) {
                $error = self::record_error($record);
                if ($error !== null) {
                    $results->errors[] = $error;
                    continue;
                }
                $data = clone $record;
                unset($data->line);
                // Une cellule vide ne remplace pas une valeur déjà enregistrée.
                foreach (get_object_vars($data) as $field => $value) {
                    if ($value === '') {
                        unset($data->$field);
                    }
                }
                // Même SIRET, ou à défaut même nom : deux organismes de même nom qui ont chacun leur
                // SIRET restent distincts (voir stage_placement_find_offer()).
                $match = stage_placement_find_offer($known, $record->name, $record->siret ?? '');
                $offerid = $match ? (int) $match->id : 0;
                // Moins de places que d'étudiants déjà affectés : l'organisme serait en dépassement.
                if ($offerid && isset($data->places)) {
                    $assigned = $DB->count_records('stage_placement_assign', ['offerid' => $offerid]);
                    if ((int) $data->places < $assigned) {
                        $results->errors[] = get_string('placementimportplacesbelow', 'mod_stage', (object) [
                            'line' => $record->line, 'name' => $record->name, 'value' => $assigned,
                        ]);
                        continue;
                    }
                }
                $savedid = stage_placement_save_offer($theme->id, $data, $offerid);
                if (!$match) {
                    // Une ligne suivante du même classeur doit retrouver l'organisme qui vient d'être créé.
                    stage_placement_index_add($known, (object) [
                        'id' => $savedid, 'name' => $record->name, 'siret' => $record->siret ?? '',
                    ]);
                } else if (stage_normalize_siret($match->siret ?? '') === '' && ($record->siret ?? '') !== '') {
                    // L'organisme rapproché par son nom reçoit le SIRET de la ligne : il est désormais
                    // retrouvé par ce SIRET.
                    $match->siret = $record->siret;
                    stage_placement_index_add($known, $match);
                }
                if ($offerid) {
                    $results->updated++;
                } else {
                    $results->added++;
                }
            }
            $transaction->allow_commit();
        } finally {
            $lock->release();
        }
        return $results;
    }

    /**
     * Raison pour laquelle une ligne ne peut pas être importée.
     *
     * @param \stdClass $record Ligne de read().
     * @return string|null Message, ou null si la ligne est importable.
     */
    private static function record_error(\stdClass $record): ?string {
        $a = (object) ['line' => $record->line, 'name' => $record->name ?? '', 'value' => ''];
        if (trim((string) ($record->name ?? '')) === '') {
            return get_string('placementimportnonameline', 'mod_stage', $a);
        }
        $places = (string) ($record->places ?? '');
        if ($places !== '' && (!ctype_digit($places) || (int) $places < 1)) {
            $a->value = $places;
            return get_string('placementimportbadplaces', 'mod_stage', $a);
        }
        $siret = stage_normalize_siret($record->siret ?? '');
        if ($siret !== '' && !stage_siret_valid($siret)) {
            $a->value = $record->siret;
            return get_string('placementimportbadsiret', 'mod_stage', $a);
        }
        foreach (['datestart', 'dateend'] as $field) {
            if (isset($record->$field) && $record->$field !== '' && !is_int($record->$field)) {
                $a->value = $record->$field;
                return get_string('placementimportbaddate', 'mod_stage', $a);
            }
        }
        $start = $record->datestart ?? '';
        $end = $record->dateend ?? '';
        if (is_int($start) !== is_int($end) || (is_int($start) && $end < $start)) {
            return get_string('placementimportbadperiod', 'mod_stage', $a);
        }
        $tutoremail = (string) ($record->tutoremail ?? '');
        if ($tutoremail !== '' && !validate_email($tutoremail)) {
            $a->value = $tutoremail;
            return get_string('placementimportbademail', 'mod_stage', $a);
        }
        return null;
    }

    /**
     * Repère les colonnes utiles d'après la ligne d'intitulés : ceux du modèle (dans la langue
     * courante, en français et en anglais) et les variantes usuelles.
     *
     * @param array $headers Intitulés, indexés par numéro de colonne.
     * @return array Champ de l'organisme => numéro de colonne. La première colonne reconnue l'emporte.
     */
    private static function columns(array $headers): array {
        $names = self::ALIASES;
        foreach ([null, 'fr', 'en'] as $lang) {
            foreach (self::template_headers($lang) as $field => $label) {
                $names[$field][] = stage_normalize_name($label);
            }
        }
        $columns = [];
        foreach ($headers as $col => $header) {
            $normalized = stage_normalize_name($header);
            foreach ($names as $field => $fieldnames) {
                if (!isset($columns[$field]) && in_array($normalized, $fieldnames, true)) {
                    $columns[$field] = $col;
                    break;
                }
            }
        }
        return $columns;
    }
}
