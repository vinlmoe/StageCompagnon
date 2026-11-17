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
    ];

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
                $record->$field = $cells[$col] ?? '';
            }
            $records[] = $record;
        }
        return $records;
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
