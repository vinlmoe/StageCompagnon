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
 * Longueurs maximales des champs texte de la table stage_convention_detail.
 *
 * Plusieurs de ces champs sont courts (gratification : 20 caractères, téléphones : 30) : une
 * valeur plus longue faisait échouer l'écriture en base, et la demande de convention restait à
 * moitié enregistrée. Les longueurs sont lues dans la structure de la table elle-même, pour ne
 * pas diverger de db/install.xml. Les formulaires les signalent à la saisie (add_maxlength_rules()),
 * et les imports, qui ne passent pas par un formulaire, tronquent en dernier recours (fit()).
 *
 * @package mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class convention_detail {
    /** @var string Table des informations complémentaires de convention. */
    const TABLE = 'stage_convention_detail';

    /**
     * Longueur maximale de chaque champ texte court de la table.
     *
     * @return int[] Nom du champ => nombre maximal de caractères.
     */
    public static function max_lengths(): array {
        global $DB;

        $lengths = [];
        foreach ($DB->get_columns(self::TABLE) as $name => $column) {
            if ($column->meta_type === 'C' && $column->max_length > 0) {
                $lengths[$name] = (int) $column->max_length;
            }
        }
        return $lengths;
    }

    /**
     * Ajoute à un formulaire une règle de longueur maximale sur chacun des champs de convention
     * qu'il contient, avec le message standard de Moodle.
     *
     * @param \MoodleQuickForm $mform
     * @return void
     */
    public static function add_maxlength_rules(\MoodleQuickForm $mform): void {
        foreach (self::max_lengths() as $name => $length) {
            if ($mform->elementExists($name)) {
                $mform->addRule($name, get_string('maximumchars', '', $length), 'maxlength', $length, 'client');
            }
        }
    }

    /**
     * Tronque les valeurs trop longues, en caractères et non en octets.
     *
     * @param \stdClass $data Informations de convention à enregistrer, modifiées sur place.
     * @return \stdClass
     */
    public static function fit(\stdClass $data): \stdClass {
        foreach (self::max_lengths() as $name => $length) {
            if (isset($data->$name) && is_string($data->$name) && \core_text::strlen($data->$name) > $length) {
                $data->$name = \core_text::substr($data->$name, 0, $length);
            }
        }
        return $data;
    }
}
