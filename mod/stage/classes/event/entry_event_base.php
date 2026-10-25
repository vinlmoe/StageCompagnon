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

namespace mod_stage\event;

/**
 * Socle des événements du circuit d'un stage, tous rattachés à une saisie (table stage_entry).
 *
 * Chaque étape du circuit (enregistrement, évaluations, validation, annulation, convention)
 * déclenche un événement de cette famille, depuis stage_trigger_entry_event() dans locallib.php,
 * une fois la base mise à jour. Les journaux Moodle gardent ainsi qui a fait quoi et quand sur
 * chaque stage, ce que la seule lecture de la table ne permet pas de reconstituer.
 *
 * L'objet est la saisie, l'utilisateur lié (relateduserid) l'étudiant, et « other » porte
 * toujours les statuts du stage et de la convention après l'action, plus les données propres
 * à chaque événement.
 *
 * @package mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class entry_event_base extends \core\event\base {
    /** @var string Lettre CRUD de l'événement : c, r, u ou d. */
    const CRUD = 'u';

    /** @var int Niveau éducatif de l'événement (constantes LEVEL_* de \core\event\base). */
    const EDULEVEL = self::LEVEL_OTHER;

    /**
     * Initialise les données communes.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = static::CRUD;
        $this->data['edulevel'] = static::EDULEVEL;
        $this->data['objecttable'] = 'stage_entry';
    }

    /**
     * Nom lisible de l'événement, tiré de la chaîne de langue « event » suivie du nom de la classe
     * sans tiret bas (evententrycreated, eventconventionsigned...).
     *
     * @return string
     */
    public static function get_name() {
        $short = substr(strrchr(static::class, '\\'), 1);
        return get_string('event' . str_replace('_', '', $short), 'mod_stage');
    }

    /**
     * Page de détail de la saisie concernée.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/stage/entrydetail.php', [
            'id' => $this->contextinstanceid,
            'entryid' => $this->objectid,
        ]);
    }

    /**
     * L'étudiant doit toujours être renseigné.
     *
     * @return void
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' (student) must be set.');
        }
    }

    /**
     * Correspondance de l'objet lors d'une restauration de journaux.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'stage_entry', 'restore' => 'stage_entry'];
    }

    /**
     * « other » ne contient que des statuts et des indicateurs : rien à remapper.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }

    /**
     * Phrase type des descriptions : « L'utilisateur #n a <action> le stage #m de l'étudiant #p. »
     *
     * @param string $action Verbe et compléments, à l'accompli.
     * @return string
     */
    protected function describe($action) {
        return "L'utilisateur #{$this->userid} a {$action} le stage #{$this->objectid}"
            . " de l'étudiant #{$this->relateduserid}.";
    }
}
