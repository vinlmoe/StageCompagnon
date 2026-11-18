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

namespace mod_stage\output;

/**
 * Affichage des thématiques à organismes proposés : libellés (vœu satisfait, lieu, période
 * d'accueil), origine d'une demande de convention, et encart « Organismes proposés » de l'étudiant.
 * Les données viennent de placementlib.php ; cette classe ne fait que les mettre en forme.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class placement_view {
    /**
     * Libellé d'un vœu satisfait : « vœu n° 2 », ou « hors vœux » pour une affectation décidée par la
     * DEVE en dehors des vœux de l'étudiant.
     *
     * @param int $wishrank
     * @return string
     */
    public static function rank_label($wishrank) {
        return $wishrank > 0
            ? get_string('placementwishrank', 'mod_stage', (int) $wishrank)
            : get_string('placementoutsidewishes', 'mod_stage');
    }

    /**
     * Lieu d'un organisme proposé tel qu'il est montré dans les listes : son lieu de stage, à défaut
     * son adresse, suivi de son pays s'il est à l'étranger.
     *
     * @param \stdClass $offer
     * @return string Texte brut.
     */
    public static function offer_place(\stdClass $offer) {
        $place = trim((string) $offer->location) !== '' ? $offer->location : (string) $offer->address;
        $parts = array_filter([trim((string) $place), trim((string) $offer->country)], fn($part) => $part !== '');
        return implode(' — ', $parts);
    }

    /**
     * Période d'accueil imposée par un organisme, en clair, ou chaîne vide si elle est libre.
     *
     * @param \stdClass $offer
     * @return string Texte brut.
     */
    public static function offer_period(\stdClass $offer) {
        if (empty($offer->datestart) || empty($offer->dateend)) {
            return '';
        }
        $format = get_string('strftimedate', 'langconfig');
        return get_string('placementofferperiodvalue', 'mod_stage', (object) [
            'start' => userdate($offer->datestart, $format),
            'end' => userdate($offer->dateend, $format),
        ]);
    }

    /**
     * D'où vient la demande de convention d'un stage sur une thématique à organismes proposés : d'une
     * affectation (organisme, vœu satisfait, appariement ou décision de la DEVE), ou d'une demande hors
     * liste (avec son SIRET). Affiché à ceux qui instruisent la demande et dans le détail du stage.
     *
     * @param \stdClass $entry
     * @return string HTML d'une section libellé/valeur, vide si la thématique ne propose pas d'organismes.
     */
    public static function entry_origin(\stdClass $entry) {
        global $DB;

        $theme = $DB->get_record('stage_theme', ['id' => $entry->themeid]);
        if (!$theme || empty($theme->placementenabled)) {
            return '';
        }
        $assignment = stage_placement_get_entry_assignment($entry->id);
        $label = get_string('placementorigin', 'mod_stage');
        if ($assignment) {
            $offer = $DB->get_record('stage_placement_offer', ['id' => $assignment->offerid]);
            $how = get_string(empty($assignment->assignedby) ? 'placementoriginmatching' : 'placementorigindeve', 'mod_stage');
            $rows = [
                $label => get_string('placementoriginassigned', 'mod_stage'),
                get_string('placementassignment', 'mod_stage') => $offer ? format_string($offer->name) : '-',
                get_string('placementsatisfiedwish', 'mod_stage') =>
                    s(self::rank_label($assignment->wishrank)) . ' — ' . $how,
            ];
        } else {
            $siret = (string) $DB->get_field('stage_convention_detail', 'siret', ['entryid' => $entry->id]);
            $rows = [
                $label => get_string(
                    empty($theme->placementofflist) ? 'placementoriginofflistdeve' : 'placementoriginofflist',
                    'mod_stage'
                ),
                get_string('conventionsiret', 'mod_stage') => $siret !== '' ? s($siret)
                    : get_string($entry->abroad ? 'placementoriginabroad' : 'placementoriginnosiret', 'mod_stage'),
            ];
        }
        return stage_render_detail_section(get_string('placementoffers', 'mod_stage'), $rows);
    }

    /**
     * Encart « Organismes proposés » du tableau de bord de l'étudiant : pour chaque thématique qui en
     * propose, où en sont ses vœux, son affectation et sa demande de convention, avec l'action à faire.
     *
     * @param \stdClass $stage
     * @param \stdClass $cm Course module.
     * @param int $userid
     * @return string HTML, vide si aucune thématique visible ne propose d'organismes.
     */
    public static function student_section(\stdClass $stage, \stdClass $cm, $userid) {
        global $DB, $OUTPUT;

        // Thématiques à organismes proposés visibles, et celles, même masquées depuis, où l'étudiant a
        // déjà une affectation : il doit pouvoir y déposer sa demande. Affectations lues en une requête.
        $assignments = [];
        $sql = "SELECT a.*
                  FROM {stage_placement_assign} a
                  JOIN {stage_theme} t ON t.id = a.themeid
                 WHERE t.stageid = :stageid AND a.userid = :userid";
        foreach ($DB->get_records_sql($sql, ['stageid' => $stage->id, 'userid' => $userid]) as $assignment) {
            $assignments[(int) $assignment->themeid] = $assignment;
        }
        $themes = array_filter(stage_get_themes($stage->id), function ($theme) use ($assignments) {
            return !empty($theme->placementenabled) && (!empty($theme->visible) || isset($assignments[(int) $theme->id]));
        });
        if (!$themes) {
            return '';
        }

        // Stages en cours de l'étudiant sur ces thématiques : une demande hors liste déjà déposée tient
        // lieu de vœux.
        [$insql, $params] = $DB->get_in_or_equal(array_keys($themes), SQL_PARAMS_NAMED);
        $withentry = array_flip($DB->get_fieldset_select(
            'stage_entry',
            'DISTINCT themeid',
            "userid = :userid AND status <> :cancelled AND themeid $insql",
            $params + ['userid' => $userid, 'cancelled' => STAGE_STATUS_ANNULE]
        ));

        $out = $OUTPUT->heading(get_string('placementoffers', 'mod_stage'), 4);
        foreach ($themes as $theme) {
            $state = stage_placement_state($theme);
            $assignment = $assignments[(int) $theme->id] ?? null;
            $wishesurl = new \moodle_url('/mod/stage/placement.php', ['id' => $cm->id, 'themeid' => $theme->id]);
            $lines = [];
            $actions = [];

            if ($assignment) {
                $offer = $DB->get_record('stage_placement_offer', ['id' => $assignment->offerid]);
                $lines[] = get_string('placementassignedto', 'mod_stage', (object) [
                    'name' => $offer ? format_string($offer->name) : '-',
                    'rank' => self::rank_label($assignment->wishrank),
                ]);
                if (stage_placement_assignment_usable($assignment)) {
                    $actions[get_string('placementcompleterequest', 'mod_stage')] = new \moodle_url(
                        '/mod/stage/student_register.php',
                        ['id' => $cm->id, 'placement' => $theme->id]
                    );
                } else {
                    $lines[] = get_string('placementrequestdone', 'mod_stage');
                }
                $actions[get_string('placementviewoffers', 'mod_stage')] = $wishesurl;
            } else if (isset($withentry[(int) $theme->id])) {
                $lines[] = get_string('placementofflistdone', 'mod_stage');
                $actions[get_string('placementviewoffers', 'mod_stage')] = $wishesurl;
            } else if ($state === STAGE_PLACEMENT_OPEN) {
                $wishcount = count(stage_placement_get_wishes($theme->id, $userid));
                $lines[] = !empty($theme->placementdeadline)
                    ? get_string('placementopenuntil', 'mod_stage', userdate($theme->placementdeadline))
                    : get_string('placementopen', 'mod_stage');
                $lines[] = $wishcount
                    ? get_string('placementwishessaved', 'mod_stage', $wishcount)
                    : get_string('placementnowishes', 'mod_stage');
                $missing = stage_placement_missing_wishes($theme, $wishcount, count(stage_placement_wishable_offers($theme)));
                if ($missing) {
                    $lines[] = \html_writer::span(get_string('placementwishesmissing', 'mod_stage', $missing), 'text-danger');
                }
                $actions[get_string($wishcount ? 'placementeditwishes' : 'placementmakewishes', 'mod_stage')] = $wishesurl;
            } else if ($state === STAGE_PLACEMENT_CLOSED) {
                $lines[] = get_string('placementclosedwaiting', 'mod_stage');
                $actions[get_string('placementviewoffers', 'mod_stage')] = $wishesurl;
            } else {
                $lines[] = get_string(
                    empty($theme->placementofflist) ? 'placementresultnone' : 'placementresultnoneofflist',
                    'mod_stage'
                );
                $actions[get_string('placementviewoffers', 'mod_stage')] = $wishesurl;
            }
            if (
                !empty($theme->placementofflist) && !$assignment && $state !== STAGE_PLACEMENT_MATCHED
                    && !isset($withentry[(int) $theme->id])
            ) {
                $lines[] = get_string('placementofflistinfo', 'mod_stage');
            }

            $body = \html_writer::tag('strong', format_string($theme->name));
            foreach ($lines as $line) {
                $body .= \html_writer::div($line);
            }
            $body .= \html_writer::div(stage_render_actions($actions, 'btn btn-sm btn-primary mr-1 mb-1', ''), 'mt-2');
            $out .= \html_writer::div($body, 'stage-placement-card border rounded p-3 mb-3');
        }
        return $out;
    }
}
