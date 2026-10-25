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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Générateur Behat de mod_stage : thématiques, saisies et attributions de référent créées depuis
 * les tableaux « the following "mod_stage > ..." exist » des scénarios.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

/**
 * Générateur Behat de mod_stage.
 *
 * @package    mod_stage
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_stage_generator extends behat_generator_base {
    /**
     * Entités créables : « themes », « entries » et « teacher assignments ».
     *
     * L'activité est désignée par son nom ou son idnumber, les utilisateurs par leur username et
     * la thématique d'une saisie par son nom.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'themes' => [
                'singular' => 'theme',
                'datagenerator' => 'theme',
                'required' => ['stage', 'name'],
                'switchids' => ['stage' => 'stageid'],
            ],
            'entries' => [
                'singular' => 'entry',
                'datagenerator' => 'entry',
                'required' => ['stage', 'user', 'theme'],
                'switchids' => ['stage' => 'stageid', 'user' => 'userid'],
            ],
            'teacher assignments' => [
                'singular' => 'teacher assignment',
                'datagenerator' => 'teacher_assignment',
                'required' => ['stage', 'student', 'teacher'],
                'switchids' => ['stage' => 'stageid', 'student' => 'studentid', 'teacher' => 'teacherid'],
            ],
        ];
    }

    /**
     * Identifiant d'instance d'une activité « Gestion des stages », par nom ou idnumber.
     *
     * @param string $identifier
     * @return int
     */
    protected function get_stage_id(string $identifier): int {
        return (int) $this->get_cm_by_activity_name('stage', $identifier)->instance;
    }

    /**
     * Identifiant d'un étudiant, par username.
     *
     * @param string $username
     * @return int
     */
    protected function get_student_id(string $username): int {
        return (int) $this->get_user_id($username);
    }

    /**
     * Identifiant d'un enseignant, par username.
     *
     * @param string $username
     * @return int
     */
    protected function get_teacher_id(string $username): int {
        return (int) $this->get_user_id($username);
    }

    /**
     * Crée une thématique.
     *
     * @param array $data
     * @return void
     */
    protected function process_theme(array $data): void {
        $this->componentdatagenerator->create_theme($data['stageid'], $data);
    }

    /**
     * Crée une saisie de stage.
     *
     * Colonnes facultatives : structure, datestart et dateend (horodatage ou expression comprise
     * par strtotime), declaredduration, studyyear, abroad, country, conventionstatus (code ou nom :
     * none, requested, edited, signed, signvet, teacherpending, exempt, rejected), status (code ou
     * nom : registered, selfassessed, teacherevaluated, validated, notvalidated, cancelled),
     * studentselfeval, teachereval, tutorname, tutoremail, et conventiondetail : « complete » remplit
     * la demande de convention avec des valeurs de test pour tous les champs exigés à la
     * validation (le référent étant le premier enseignant attribué à l'étudiant).
     *
     * @param array $data
     * @return void
     */
    protected function process_entry(array $data): void {
        global $DB;

        $theme = $DB->get_record('stage_theme', ['stageid' => $data['stageid'], 'name' => $data['theme']], '*', MUST_EXIST);
        unset($data['theme']);

        foreach (['datestart', 'dateend'] as $field) {
            if (isset($data[$field]) && !is_numeric($data[$field])) {
                $data[$field] = strtotime($data[$field]);
            }
        }
        if (isset($data['conventionstatus']) && !is_numeric($data['conventionstatus'])) {
            $codes = [
                'none' => STAGE_CONVENTION_NONE, 'requested' => STAGE_CONVENTION_REQUESTED,
                'edited' => STAGE_CONVENTION_EDITED, 'signed' => STAGE_CONVENTION_SIGNED,
                'signvet' => STAGE_CONVENTION_SIGNVET, 'teacherpending' => STAGE_CONVENTION_TEACHERPENDING,
                'exempt' => STAGE_CONVENTION_EXEMPT, 'rejected' => STAGE_CONVENTION_REJECTED,
            ];
            $data['conventionstatus'] = $codes[strtolower($data['conventionstatus'])];
        }

        $after = [];
        if (isset($data['status'])) {
            $codes = [
                'registered' => STAGE_STATUS_ENREGISTRE, 'selfassessed' => STAGE_STATUS_EVAL_ETUDIANT,
                'teacherevaluated' => STAGE_STATUS_EVAL_ENSEIGNANT, 'validated' => STAGE_STATUS_VALIDE_DEVE,
                'notvalidated' => STAGE_STATUS_NON_VALIDE, 'cancelled' => STAGE_STATUS_ANNULE,
            ];
            $after['status'] = is_numeric($data['status']) ? (int) $data['status'] : $codes[strtolower($data['status'])];
            unset($data['status']);
        }
        foreach (['studentselfeval', 'teachereval'] as $field) {
            if (isset($data[$field])) {
                $after[$field] = $data[$field];
                unset($data[$field]);
            }
        }
        $detail = [];
        if (($data['conventiondetail'] ?? '') === 'complete') {
            $detail = [
                'yearsituation' => 'normal', 'stagetype' => 'obligatoire',
                'studentbirthdate' => mktime(0, 0, 0, 6, 15, 2003), 'studentaddress' => '12 rue des Lilas, Lyon',
                'studentphone' => '0600000001', 'hostaddress' => '4 place du Marché, Lyon',
                'hostrepresentative' => 'Dr Paul Roux', 'hostrepresentativetitle' => 'Director',
                'hostservice' => 'Small animals', 'hostphone' => '0400000002', 'hostemail' => 'clinic@example.com',
                'hostlocation' => 'Lyon', 'tutorname' => 'Dr Marie Petit', 'tutorfunction' => 'Veterinarian',
                'tutorphone' => '0400000003', 'tutoremail' => 'tutor@example.com', 'gratificationamount' => '0',
            ];
        }
        unset($data['conventiondetail']);
        foreach (['tutorname', 'tutoremail'] as $field) {
            if (isset($data[$field])) {
                $detail[$field] = $data[$field];
                unset($data[$field]);
            }
        }

        $entry = $this->componentdatagenerator->create_entry($data['stageid'], $data['userid'], $theme, $data);

        if (!empty($after)) {
            $after['id'] = $entry->id;
            $DB->update_record('stage_entry', (object) $after);
        }
        if (!empty($detail)) {
            if (!isset($detail['referentteacherid'])) {
                $teachers = stage_get_student_teachers($data['stageid'], $data['userid']);
                $detail['referentteacherid'] = $teachers ? (int) reset($teachers)->id : 0;
            }
            stage_save_convention_detail($entry->id, (object) $detail);
        }
    }

    /**
     * Attribue un enseignant référent à un étudiant.
     *
     * @param array $data
     * @return void
     */
    protected function process_teacher_assignment(array $data): void {
        $this->componentdatagenerator->assign_teacher($data['stageid'], $data['studentid'], $data['teacherid']);
    }
}
