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
 * Courriels des thématiques à organismes proposés : résultat de l'appariement, et relance des
 * étudiants sans vœux complets avant la clôture. Les textes sont personnalisables par la DEVE
 * (stage_get_email_definitions(), clés placementresult et placementreminder), et écrits dans la
 * langue de chaque étudiant.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class placement_notifier {
    /**
     * Annonce à chaque étudiant le résultat d'un tour d'appariement : l'organisme auquel il est
     * affecté, ou l'absence d'affectation.
     *
     * @param \stdClass $stage
     * @param \stdClass $cm Course module.
     * @param \stdClass $theme
     * @param \stdClass $result Retour de stage_placement_run_matching().
     * @return int Nombre de courriels partis.
     */
    public static function results(\stdClass $stage, \stdClass $cm, \stdClass $theme, \stdClass $result): int {
        $userids = array_merge(array_keys($result->assigned), $result->unassigned);
        if (!$userids) {
            return 0;
        }
        $offers = stage_placement_get_offers($theme->id);
        $url = new \moodle_url('/mod/stage/placement.php', ['id' => $cm->id, 'themeid' => $theme->id]);
        $sent = 0;
        foreach (self::students($userids) as $student) {
            $lang = $student->lang ?: null;
            $offerid = $result->assigned[$student->id] ?? 0;
            $outcome = $offerid && isset($offers[$offerid])
                ? get_string_manager()->get_string(
                    'placementresultassigned',
                    'mod_stage',
                    stage_plain_string($offers[$offerid]->name),
                    $lang
                )
                : get_string_manager()->get_string(
                    empty($theme->placementofflist) ? 'placementresultnone' : 'placementresultnoneofflist',
                    'mod_stage',
                    null,
                    $lang
                );
            $text = stage_resolve_email_text($stage->id, 'placementresult', [
                'student' => fullname($student),
                'stage' => stage_plain_string($stage->name),
                'theme' => stage_plain_string($theme->name),
                'result' => $outcome,
                'url' => $url->out(false),
            ], $lang);
            if (email_to_user($student, \core_user::get_noreply_user(), $text->subject, $text->body)) {
                $sent++;
            }
        }
        return $sent;
    }

    /**
     * Relance, avant la clôture, les étudiants inscrits qui n'ont pas de vœux ou dont les vœux sont
     * incomplets, et note la relance sur la thématique : elle n'est faite qu'une fois par campagne.
     * Les étudiants déjà affectés ou qui ont déjà un stage sur la thématique ne sont pas relancés.
     *
     * @param \stdClass $stage
     * @param \stdClass $cm Course module.
     * @param \stdClass $theme
     * @return int Nombre de courriels partis.
     */
    public static function reminders(\stdClass $stage, \stdClass $cm, \stdClass $theme): int {
        global $DB;

        // Notée d'abord : un échec d'envoi ne doit pas faire relancer toute la promotion à chaque heure.
        $DB->set_field('stage_theme', 'placementremindertime', time(), ['id' => $theme->id]);

        $offercount = count(stage_placement_wishable_offers($theme));
        if (!$offercount) {
            return 0;
        }
        $students = get_enrolled_users(\context_module::instance($cm->id), 'mod/stage:submit', 0, 'u.id');
        $wishes = stage_placement_get_all_wishes($theme->id);
        $skip = stage_placement_get_assignments($theme->id) + stage_placement_users_with_entry($theme);
        $targets = [];
        foreach (array_keys($students) as $userid) {
            $count = count($wishes[$userid] ?? []);
            if (!isset($skip[$userid]) && (!$count || stage_placement_missing_wishes($theme, $count, $offercount))) {
                $targets[] = (int) $userid;
            }
        }
        $url = new \moodle_url('/mod/stage/placement.php', ['id' => $cm->id, 'themeid' => $theme->id]);
        $sent = 0;
        foreach (self::students($targets) as $student) {
            $lang = $student->lang ?: null;
            $text = stage_resolve_email_text($stage->id, 'placementreminder', [
                'student' => fullname($student),
                'stage' => stage_plain_string($stage->name),
                'theme' => stage_plain_string($theme->name),
                'deadline' => userdate($theme->placementdeadline, get_string_manager()->get_string(
                    'strftimedaydatetime',
                    'langconfig',
                    null,
                    $lang
                ), \core_date::get_user_timezone($student)),
                'url' => $url->out(false),
            ], $lang);
            if (email_to_user($student, \core_user::get_noreply_user(), $text->subject, $text->body)) {
                $sent++;
            }
        }
        return $sent;
    }

    /**
     * Comptes actifs des étudiants donnés, lus par paquets.
     *
     * @param int[] $userids
     * @return \stdClass[]
     */
    private static function students(array $userids): array {
        global $DB;

        $students = [];
        foreach (array_chunk($userids, STAGE_SQL_CHUNK) as $chunk) {
            [$insql, $inparams] = $DB->get_in_or_equal($chunk);
            $students += $DB->get_records_select('user', "id $insql AND deleted = 0", $inparams);
        }
        return $students;
    }
}
