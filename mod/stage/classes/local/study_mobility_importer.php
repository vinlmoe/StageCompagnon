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
 * Import Excel des mobilités d'études validées par la DEVE (voir stage_set_student_study_mobility()).
 *
 * Le classeur est lu sur sa première feuille, colonnes reconnues par leur intitulé (en français ou
 * en anglais, sans tenir compte des accents ni de la casse) :
 * - « Email » (obligatoire) : adresse de l'étudiant, qui doit être inscrit au cours ;
 * - « Lieu » (obligatoire pour valider) : lieu de la mobilité d'études ;
 * - « Mobilité d'études » (facultative) : « oui » valide la mobilité, « non » retire la validation.
 *   Sans cette colonne, toute ligne portant un lieu valide la mobilité.
 * Une ligne sans lieu ni valeur dans « Mobilité d'études » est ignorée : le modèle téléchargeable
 * listant tous les étudiants, ceux que la DEVE n'a pas complétés ne sont pas des erreurs.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class study_mobility_importer {
    /** @var array Intitulés reconnus pour chaque colonne, déjà normalisés. */
    const HEADERS = [
        'email' => [
            'email', 'e mail', 'courriel', 'adresse mail', 'adresse email', 'adresse de courriel', 'email address',
            'studentemail',
        ],
        'location' => [
            'lieu', 'location', 'lieu de la mobilite', 'lieu de la mobilite d etudes', 'study mobility location',
        ],
        'validated' => ['mobilite d etudes', 'mobilite', 'validee', 'valide', 'study mobility', 'validated'],
    ];

    /** @var string[] Valeurs de la colonne « Mobilité d'études » qui valident, normalisées. */
    const YES = ['1', 'oui', 'o', 'x', 'v', 'valide', 'validee', 'yes', 'y', 'true'];

    /** @var string[] Valeurs de la colonne « Mobilité d'études » qui retirent la validation. */
    const NO = ['0', 'non', 'n', 'no', 'false'];

    /**
     * Lit la première feuille du classeur.
     *
     * @param string $filepath Classeur XLSX.
     * @return array Lignes : stdClass{line, email, location, validated}, dans l'ordre du classeur.
     * @throws \moodle_exception Si le fichier n'est pas un classeur ou si la colonne Email manque.
     */
    public static function read(string $filepath): array {
        $sheets = historical_importer::read_sheets($filepath);
        $rows = reset($sheets) ?: [];
        return self::parse_rows($rows);
    }

    /**
     * Interprète les lignes d'une feuille, la première ligne non vide portant les intitulés.
     *
     * @param array $rows Lignes de la feuille, indexées par numéro de ligne (à partir de 0) puis de colonne.
     * @return array Voir read().
     * @throws \moodle_exception Si la colonne Email manque.
     */
    public static function parse_rows(array $rows): array {
        ksort($rows);
        $columns = null;
        $records = [];
        foreach ($rows as $index => $row) {
            $cells = array_map(fn($value) => trim((string) $value), $row);
            if (implode('', $cells) === '') {
                continue;
            }
            if ($columns === null) {
                $columns = self::columns($cells);
                if (!isset($columns['email'])) {
                    throw new \moodle_exception('studymobilityimportnoemail', 'mod_stage');
                }
                continue;
            }
            $validated = isset($columns['validated']) ? ($cells[$columns['validated']] ?? '') : null;
            $records[] = (object) [
                'line' => $index + 1,
                'email' => \core_text::strtolower($cells[$columns['email']] ?? ''),
                'location' => isset($columns['location']) ? ($cells[$columns['location']] ?? '') : '',
                'validated' => $validated,
            ];
        }
        return $records;
    }

    /**
     * Enregistre les mobilités d'études lues par read().
     *
     * Chaque ligne est traitée indépendamment : une ligne en erreur (étudiant inconnu, lieu
     * manquant, valeur illisible) est signalée et n'empêche pas l'import des autres.
     *
     * @param \stdClass $stage Activité.
     * @param \context $context Contexte du module.
     * @param array $records Lignes de read().
     * @param int $byuserid Membre de la DEVE à l'origine de l'import.
     * @return \stdClass {validated, removed, skipped, errors}
     */
    public static function import(\stdClass $stage, \context $context, array $records, int $byuserid): \stdClass {
        $studentsbyemail = [];
        foreach (stage_get_enrolled_students($context) as $student) {
            $studentsbyemail[\core_text::strtolower(trim($student->email))][] = $student;
        }

        global $DB;

        $results = (object) ['validated' => 0, 'removed' => 0, 'skipped' => 0, 'errors' => []];
        // Sous le verrou de l'activité et en une transaction : deux imports simultanés (ou un import
        // et la validation unitaire d'une mobilité) lisaient tous deux l'absence de mobilité d'un
        // étudiant puis l'inséraient, ce que l'index unique refusait au milieu du fichier, en
        // laissant appliquées les lignes déjà traitées.
        $lock = stage_get_activity_lock($stage->id);
        try {
            $transaction = $DB->start_delegated_transaction();
            self::import_locked($stage->id, $studentsbyemail, $records, $byuserid, $results);
            $transaction->allow_commit();
        } finally {
            $lock->release();
        }
        return $results;
    }

    /**
     * Applique les lignes de l'import, sous le verrou et dans la transaction ouverts par import().
     *
     * @param int $stageid Activité.
     * @param array $studentsbyemail Courriel normalisé => étudiants inscrits.
     * @param array $records Lignes de read().
     * @param int $byuserid Membre de la DEVE à l'origine de l'import.
     * @param \stdClass $results Compte rendu, complété en place.
     * @return void
     */
    private static function import_locked(
        int $stageid,
        array $studentsbyemail,
        array $records,
        int $byuserid,
        \stdClass $results
    ): void {
        foreach ($records as $record) {
            if (trim($record->location) === '' && trim((string) $record->validated) === '') {
                $results->skipped++;
                continue;
            }
            $a = (object) ['line' => $record->line, 'email' => $record->email, 'value' => $record->validated];
            $matches = $studentsbyemail[$record->email] ?? [];
            if ($record->email === '' || count($matches) !== 1) {
                $results->errors[] = get_string(
                    count($matches) > 1 ? 'studymobilityimportduplicate' : 'studymobilityimportunknown',
                    'mod_stage',
                    $a
                );
                continue;
            }
            $student = reset($matches);

            $validate = true;
            if ($record->validated !== null && $record->validated !== '') {
                $value = global_export_importer::normalize($record->validated);
                if (in_array($value, self::NO, true)) {
                    $validate = false;
                } else if (!in_array($value, self::YES, true)) {
                    $results->errors[] = get_string('studymobilityimportbadvalue', 'mod_stage', $a);
                    continue;
                }
            }
            if ($validate && trim($record->location) === '') {
                $results->errors[] = get_string('studymobilityimportnolocation', 'mod_stage', $a);
                continue;
            }

            stage_set_student_study_mobility($stageid, $student->id, $validate, $record->location, $byuserid);
            if ($validate) {
                $results->validated++;
            } else {
                $results->removed++;
            }
        }
    }

    /**
     * Repère les colonnes utiles d'après la ligne d'intitulés.
     *
     * @param array $headers Intitulés, indexés par numéro de colonne.
     * @return array Champ (email, location, validated) => numéro de colonne. La première colonne
     *               reconnue l'emporte.
     */
    private static function columns(array $headers): array {
        $columns = [];
        foreach ($headers as $col => $header) {
            $normalized = global_export_importer::normalize($header);
            foreach (self::HEADERS as $field => $names) {
                if (!isset($columns[$field]) && in_array($normalized, $names, true)) {
                    $columns[$field] = $col;
                }
            }
        }
        return $columns;
    }
}
