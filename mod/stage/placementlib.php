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

/**
 * Thématiques à organismes proposés.
 *
 * Pour les thématiques qui l'activent, l'établissement propose une liste d'organismes d'accueil
 * (saisis, importés d'un classeur ou repris de stages précédents). Chaque étudiant classe un
 * nombre fixé de vœux jusqu'à une date de clôture ; l'appariement affecte alors chaque étudiant à
 * l'un de ses vœux, dans la limite des places de chaque organisme. L'étudiant affecté dépose sa
 * demande de convention avec les coordonnées de l'organisme préremplies. Selon le réglage de la
 * thématique, une demande hors des organismes proposés reste possible : le SIRET de l'organisme
 * y est alors obligatoire, sauf pour un stage à l'étranger.
 *
 * Ce fichier est chargé par locallib.php.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Champs d'un organisme proposé et champ du formulaire de demande de convention que chacun
 * préremplit.
 *
 * @return string[] Champ de stage_placement_offer => champ du formulaire de demande.
 */
function stage_placement_offer_fields() {
    return [
        'name' => 'structure',
        'address' => 'hostaddress',
        'representative' => 'hostrepresentative',
        'representativetitle' => 'hostrepresentativetitle',
        'service' => 'hostservice',
        'phone' => 'hostphone',
        'email' => 'hostemail',
        'location' => 'hostlocation',
        'siret' => 'siret',
        'country' => 'country',
        'tutorname' => 'tutorname',
        'tutorfunction' => 'tutorfunction',
        'tutorphone' => 'tutorphone',
        'tutoremail' => 'tutoremail',
    ];
}

/**
 * Longueur maximale de chaque champ texte court d'un organisme proposé, lue dans le schéma.
 *
 * @return int[] Champ => nombre maximal de caractères.
 */
function stage_placement_offer_max_lengths() {
    global $DB;

    $lengths = [];
    foreach ($DB->get_columns('stage_placement_offer') as $name => $column) {
        if ($column->meta_type === 'C' && $column->max_length > 0) {
            $lengths[$name] = (int) $column->max_length;
        }
    }
    return $lengths;
}

/**
 * Où en est une thématique à organismes proposés.
 *
 * @param stdClass $theme
 * @param int|null $now Instant de référence (maintenant par défaut).
 * @return string Une des constantes STAGE_PLACEMENT_*.
 */
function stage_placement_state(stdClass $theme, $now = null) {
    if (empty($theme->placementenabled)) {
        return STAGE_PLACEMENT_OFF;
    }
    if (!empty($theme->placementmatchedtime)) {
        return STAGE_PLACEMENT_MATCHED;
    }
    $now = $now ?? time();
    if (!empty($theme->placementdeadline) && (int) $theme->placementdeadline <= $now) {
        return STAGE_PLACEMENT_CLOSED;
    }
    return STAGE_PLACEMENT_OPEN;
}

/**
 * Organismes proposés d'une thématique, par ordre alphabétique.
 *
 * @param int $themeid
 * @return array id => enregistrement stage_placement_offer.
 */
function stage_placement_get_offers($themeid) {
    global $DB;

    return $DB->get_records('stage_placement_offer', ['themeid' => $themeid], 'name ASC, id ASC');
}

/**
 * Clé de rapprochement d'un organisme : son SIRET s'il en a un, sinon son nom normalisé. Sert à
 * ne pas proposer deux fois le même organisme (import rejoué, reprise de stages précédents).
 *
 * @param string $name
 * @param string|null $siret
 * @return string
 */
function stage_placement_offer_key($name, $siret = '') {
    $siret = stage_normalize_siret($siret);
    if ($siret !== '') {
        return 'siret:' . $siret;
    }
    // Un nom sans lettre ni chiffre (« ??? ») se normalise en chaîne vide : il est alors comparé tel
    // quel, pour ne pas confondre entre eux tous les noms de ce genre.
    $normalized = stage_normalize_name($name);
    return 'name:' . ($normalized !== '' ? $normalized : core_text::strtolower(trim((string) $name)));
}

/**
 * Index d'organismes pour stage_placement_find_offer() : par SIRET et par nom normalisé. Un import
 * de plusieurs milliers de lignes cherche chaque ligne parmi les organismes déjà connus ; sans
 * index, la recherche serait quadratique.
 *
 * @param array $offers Organismes (objets portant id, name, siret).
 * @return array {siret: SIRET => organisme, name: clé du nom => organismes}
 */
function stage_placement_index_offers(array $offers) {
    $index = ['siret' => [], 'name' => []];
    foreach ($offers as $offer) {
        stage_placement_index_add($index, $offer);
    }
    return $index;
}

/**
 * Ajoute un organisme à un index (voir stage_placement_index_offers()).
 *
 * @param array $index Index, complété en place.
 * @param stdClass $offer Organisme portant id, name, siret.
 * @return void
 */
function stage_placement_index_add(array &$index, stdClass $offer) {
    $siret = stage_normalize_siret($offer->siret ?? '');
    if ($siret !== '' && !isset($index['siret'][$siret])) {
        $index['siret'][$siret] = $offer;
    }
    $index['name'][stage_placement_offer_key($offer->name)][] = $offer;
}

/**
 * Cherche, dans un index d'organismes, celui qui correspond à un nom et à un SIRET : même SIRET, ou
 * à défaut même nom. Deux organismes de même nom qui portent chacun un SIRET différent sont deux
 * établissements distincts (deux sites d'une même clinique) : ils ne sont jamais rapprochés.
 *
 * @param array $index Index de stage_placement_index_offers().
 * @param string $name
 * @param string|null $siret
 * @return stdClass|null L'organisme correspondant, ou null.
 */
function stage_placement_find_offer(array $index, $name, $siret = '') {
    $siret = stage_normalize_siret($siret);
    if ($siret !== '' && isset($index['siret'][$siret])) {
        return $index['siret'][$siret];
    }
    foreach ($index['name'][stage_placement_offer_key($name)] ?? [] as $offer) {
        if ($siret === '' || stage_normalize_siret($offer->siret ?? '') === '') {
            return $offer;
        }
    }
    return null;
}

/**
 * Vrai si un pays saisi désigne la France : un organisme ou un stage « à l'étranger, en France »
 * n'est pas à l'étranger.
 *
 * @param string|null $country
 * @return bool
 */
function stage_placement_country_is_france($country) {
    return in_array(stage_normalize_name($country), ['france', 'fr', 'republique francaise', 'france metropolitaine'], true);
}

/**
 * Nettoie les données d'un organisme proposé avant enregistrement : champs connus seulement,
 * espaces retirés, SIRET normalisé, longueurs ramenées à celles de la table, au moins une place.
 *
 * @param stdClass|array $data
 * @return stdClass
 */
function stage_placement_clean_offer($data) {
    $data = (object) (array) $data;
    $lengths = stage_placement_offer_max_lengths();
    $clean = new stdClass();
    foreach (array_keys(stage_placement_offer_fields()) as $field) {
        if (!property_exists($data, $field)) {
            continue;
        }
        $value = trim((string) $data->$field);
        if ($field === 'siret') {
            $value = stage_normalize_siret($value);
        }
        // Le pays ne sert qu'à signaler un organisme à l'étranger : « France » n'en est pas un.
        if ($field === 'country' && stage_placement_country_is_france($value)) {
            $value = '';
        }
        if (isset($lengths[$field]) && core_text::strlen($value) > $lengths[$field]) {
            $value = core_text::substr($value, 0, $lengths[$field]);
        }
        $clean->$field = $value;
    }
    if (property_exists($data, 'places')) {
        $clean->places = max(1, (int) $data->places);
    }
    // Période d'accueil imposée par l'organisme (0 : libre).
    foreach (['datestart', 'dateend'] as $field) {
        if (property_exists($data, $field)) {
            $clean->$field = max(0, (int) $data->$field);
        }
    }
    if (property_exists($data, 'description')) {
        $clean->description = trim((string) $data->description);
    }
    return $clean;
}

/**
 * Crée ou met à jour un organisme proposé.
 *
 * @param int $themeid
 * @param stdClass|array $data Champs de l'organisme (voir stage_placement_offer_fields()), places, description.
 * @param int $offerid Organisme à mettre à jour, ou 0 pour en créer un.
 * @return int Identifiant de l'organisme.
 */
function stage_placement_save_offer($themeid, $data, $offerid = 0) {
    global $DB;

    $record = stage_placement_clean_offer($data);
    $record->timemodified = time();
    if ($offerid) {
        $record->id = $DB->get_field('stage_placement_offer', 'id', ['id' => $offerid, 'themeid' => $themeid], MUST_EXIST);
        $DB->update_record('stage_placement_offer', $record);
        return (int) $record->id;
    }
    $record->themeid = $themeid;
    $record->timecreated = $record->timemodified;
    if (!isset($record->places)) {
        $record->places = 1;
    }
    return (int) $DB->insert_record('stage_placement_offer', $record);
}

/**
 * Ajoute à une thématique les organismes qui n'y figurent pas encore (même SIRET, ou à défaut même
 * nom : voir stage_placement_offer_key()).
 *
 * @param int $themeid
 * @param array $offers Organismes à ajouter (objets ou tableaux, voir stage_placement_save_offer()).
 * @return int Nombre d'organismes ajoutés.
 */
function stage_placement_add_offers($themeid, array $offers) {
    global $DB;

    // Sous le verrou de l'activité : deux envois simultanés (double clic) ajouteraient deux fois
    // les mêmes organismes, chacun ne voyant pas ceux de l'autre.
    $lock = stage_get_activity_lock($DB->get_field('stage_theme', 'stageid', ['id' => $themeid], MUST_EXIST));
    try {
        $transaction = $DB->start_delegated_transaction();
        $known = stage_placement_index_offers(stage_placement_get_offers($themeid));
        $added = 0;
        foreach ($offers as $offer) {
            $offer = stage_placement_clean_offer($offer);
            $name = $offer->name ?? '';
            if ($name === '' || stage_placement_find_offer($known, $name, $offer->siret ?? '')) {
                continue;
            }
            $offer->id = stage_placement_save_offer($themeid, $offer);
            stage_placement_index_add($known, $offer);
            $added++;
        }
        $transaction->allow_commit();
    } finally {
        $lock->release();
    }
    return $added;
}

/**
 * Supprime un organisme proposé et les vœux qui le citent. Refusé si un étudiant y est affecté :
 * l'affectation doit d'abord être retirée ou déplacée.
 *
 * @param int $themeid
 * @param int $offerid
 * @return bool False si l'organisme a des étudiants affectés : rien n'a été supprimé.
 */
function stage_placement_delete_offer($themeid, $offerid) {
    global $DB;

    $stageid = $DB->get_field('stage_theme', 'stageid', ['id' => $themeid]);
    if (!$stageid || !$DB->record_exists('stage_placement_offer', ['id' => $offerid, 'themeid' => $themeid])) {
        return true;
    }
    // Sous le verrou de l'activité : un appariement ou un enregistrement de vœux en cours pourrait
    // sinon citer l'organisme entre le contrôle et la suppression.
    $lock = stage_get_activity_lock($stageid);
    try {
        if ($DB->record_exists('stage_placement_assign', ['offerid' => $offerid])) {
            return false;
        }
        $transaction = $DB->start_delegated_transaction();
        $users = $DB->get_fieldset_select('stage_placement_wish', 'userid', 'offerid = ?', [$offerid]);
        $DB->delete_records('stage_placement_wish', ['offerid' => $offerid]);
        $DB->delete_records('stage_placement_offer', ['id' => $offerid]);
        // Les vœux suivants remontent d'un rang : pas de trou dans le classement de l'étudiant.
        foreach ($users as $userid) {
            $rank = 0;
            $rows = $DB->get_records('stage_placement_wish', ['themeid' => $themeid, 'userid' => $userid], 'wishrank ASC, id ASC');
            foreach ($rows as $row) {
                $DB->set_field('stage_placement_wish', 'wishrank', ++$rank, ['id' => $row->id]);
            }
        }
        $transaction->allow_commit();
    } finally {
        $lock->release();
    }
    return true;
}

/**
 * Supprime tout ce qui se rattache aux organismes proposés de thématiques : organismes, vœux,
 * affectations et trace des tours d'appariement (suppression d'une thématique ou de l'activité).
 *
 * @param int[] $themeids
 * @return void
 */
function stage_placement_delete_themes_data(array $themeids) {
    global $DB;

    foreach (array_chunk(array_values($themeids), STAGE_SQL_CHUNK) as $chunk) {
        [$insql, $inparams] = $DB->get_in_or_equal($chunk);
        stage_placement_delete_rounds("themeid $insql", $inparams);
        foreach (['stage_placement_assign', 'stage_placement_wish', 'stage_placement_offer'] as $table) {
            $DB->delete_records_select($table, "themeid $insql", $inparams);
        }
    }
}

/**
 * Supprime les vœux et les affectations des étudiants d'une activité, et rouvre les vœux de ses
 * thématiques, sans date de clôture : la promotion suivante repart d'une campagne vierge, dont la
 * date reste à fixer. Les organismes proposés, qui relèvent du paramétrage, sont conservés.
 *
 * @param int $stageid
 * @return void
 */
function stage_placement_reset_userdata($stageid) {
    global $DB;

    $themeids = $DB->get_fieldset_select('stage_theme', 'id', 'stageid = ?', [$stageid]);
    foreach (array_chunk($themeids, STAGE_SQL_CHUNK) as $chunk) {
        [$insql, $inparams] = $DB->get_in_or_equal($chunk);
        $DB->delete_records_select('stage_placement_assign', "themeid $insql", $inparams);
        $DB->delete_records_select('stage_placement_wish', "themeid $insql", $inparams);
        stage_placement_delete_rounds("themeid $insql", $inparams);
    }
    $DB->set_field('stage_theme', 'placementremindertime', 0, ['stageid' => $stageid]);
    // La date de clôture, déjà passée, est effacée avec l'appariement : sans cela la tâche planifiée
    // refermerait la campagne dans l'heure, avant que la nouvelle promotion ait saisi un vœu.
    $DB->set_field('stage_theme', 'placementmatchedtime', 0, ['stageid' => $stageid]);
    $DB->set_field('stage_theme', 'placementdeadline', 0, ['stageid' => $stageid]);
}

/**
 * Supprime les vœux et les affectations d'étudiants dans une activité (suppression de données
 * personnelles, transfert vers une autre promotion). Les places qu'ils occupaient sont libérées.
 *
 * @param int $stageid
 * @param int[] $userids
 * @return void
 */
function stage_placement_delete_users_data($stageid, array $userids) {
    global $DB;

    $userids = array_values(array_filter(array_unique(array_map('intval', $userids))));
    foreach (array_chunk($userids, STAGE_SQL_CHUNK) as $chunk) {
        [$usersql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'pu');
        $params['stageid'] = $stageid;
        $themes = "themeid IN (SELECT id FROM {stage_theme} WHERE stageid = :stageid)";
        $DB->delete_records_select('stage_placement_assign', "$themes AND userid $usersql", $params);
        $DB->delete_records_select('stage_placement_wish', "$themes AND userid $usersql", $params);
        // Leur place dans la trace des tours d'appariement ; le reste de la trace demeure.
        $DB->delete_records_select(
            'stage_placement_draw',
            "roundid IN (SELECT r.id FROM {stage_placement_round} r JOIN {stage_theme} t ON t.id = r.themeid
                          WHERE t.stageid = :stageid) AND userid $usersql",
            $params
        );
    }
}

/**
 * Supprime des tours d'appariement et leur trace.
 *
 * @param string $select Condition sur stage_placement_round.
 * @param array $params Paramètres de la condition.
 * @return void
 */
function stage_placement_delete_rounds($select, array $params) {
    global $DB;

    $roundids = $DB->get_fieldset_select('stage_placement_round', 'id', $select, $params);
    foreach (array_chunk($roundids, STAGE_SQL_CHUNK) as $chunk) {
        [$insql, $inparams] = $DB->get_in_or_equal($chunk);
        $DB->delete_records_select('stage_placement_draw', "roundid $insql", $inparams);
        $DB->delete_records_select('stage_placement_round', "id $insql", $inparams);
    }
}

/**
 * Vœux d'un étudiant pour une thématique, du premier au dernier.
 *
 * @param int $themeid
 * @param int $userid
 * @return int[] Identifiants des organismes, dans l'ordre de préférence.
 */
function stage_placement_get_wishes($themeid, $userid) {
    global $DB;

    $wishes = $DB->get_records('stage_placement_wish', ['themeid' => $themeid, 'userid' => $userid], 'wishrank ASC, id ASC');
    return array_values(array_map(fn($wish) => (int) $wish->offerid, $wishes));
}

/**
 * Vœux de tous les étudiants d'une thématique.
 *
 * @param int $themeid
 * @return array userid => identifiants des organismes, dans l'ordre de préférence.
 */
function stage_placement_get_all_wishes($themeid) {
    global $DB;

    $byuser = [];
    $wishes = $DB->get_recordset('stage_placement_wish', ['themeid' => $themeid], 'userid ASC, wishrank ASC, id ASC');
    foreach ($wishes as $wish) {
        $byuser[(int) $wish->userid][] = (int) $wish->offerid;
    }
    $wishes->close();
    return $byuser;
}

/**
 * Nombre de vœux que chaque étudiant doit classer : celui fixé pour la thématique, ramené au
 * nombre d'organismes proposés s'il y en a moins.
 *
 * @param stdClass $theme
 * @param int $offercount Nombre d'organismes proposés.
 * @return int
 */
function stage_placement_required_wishes(stdClass $theme, $offercount) {
    return max(0, min(max(1, (int) $theme->placementchoices), (int) $offercount));
}

/**
 * Organismes que l'étudiant peut classer. Avant le premier appariement, tous les organismes proposés.
 * Vœux rouverts après un appariement (tour complémentaire) : seuls ceux qui ont encore une place
 * libre, puisque l'appariement ne peut rien attribuer d'autre.
 *
 * @param stdClass $theme
 * @return array id => organisme.
 */
function stage_placement_wishable_offers(stdClass $theme) {
    global $DB;

    $offers = stage_placement_get_offers($theme->id);
    if (!$DB->record_exists('stage_placement_round', ['themeid' => $theme->id])) {
        return $offers;
    }
    $remaining = stage_placement_remaining_places($offers, stage_placement_get_assignments($theme->id));
    return array_filter($offers, fn($offer) => $remaining[(int) $offer->id] > 0);
}

/**
 * Nombre de vœux qui manquent à un étudiant qui en a enregistré : des organismes ont pu être
 * ajoutés depuis, ou supprimés. 0 si ses vœux sont complets, ou s'il n'en a aucun.
 *
 * @param stdClass $theme
 * @param int $wishcount Vœux enregistrés par l'étudiant.
 * @param int $offercount Organismes qu'il peut classer (voir stage_placement_wishable_offers()).
 * @return int
 */
function stage_placement_missing_wishes(stdClass $theme, $wishcount, $offercount) {
    return $wishcount > 0 ? max(0, stage_placement_required_wishes($theme, $offercount) - (int) $wishcount) : 0;
}

/**
 * L'étudiant renonce à l'organisme qui lui a été attribué : la place est libérée pour un autre, et
 * ses vœux sont retirés pour qu'un tour complémentaire ne le replace pas. Il peut ensuite déposer
 * une demande hors liste, si la thématique l'autorise. Impossible une fois sa demande de convention
 * déposée à partir de l'affectation.
 *
 * @param stdClass $theme
 * @param int $userid
 * @return string|null Identifiant de la chaîne d'erreur, ou null si le renoncement est enregistré.
 */
function stage_placement_renounce(stdClass $theme, $userid) {
    global $DB;

    $lock = stage_get_activity_lock($theme->stageid);
    try {
        $assignment = stage_placement_get_assignment($theme->id, $userid);
        if (!$assignment) {
            return 'placementnotusable';
        }
        if (!stage_placement_assignment_usable($assignment)) {
            return 'placementrequestalreadydone';
        }
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('stage_placement_assign', ['id' => $assignment->id]);
        $DB->delete_records('stage_placement_wish', ['themeid' => $theme->id, 'userid' => $userid]);
        $transaction->allow_commit();
    } finally {
        $lock->release();
    }
    stage_placement_trigger_event(\mod_stage\event\placement_assignment_renounced::class, $theme, $userid, 0, [
        'offerid' => (int) $assignment->offerid,
    ]);
    return null;
}

/**
 * Enregistre les vœux d'un étudiant, qui remplacent les précédents. Une liste vide retire les vœux.
 *
 * @param stdClass $theme
 * @param int $userid
 * @param int[] $offerids Organismes choisis, du premier au dernier vœu.
 * @return string|null Identifiant de la chaîne d'erreur, ou null si les vœux sont enregistrés.
 */
function stage_placement_save_wishes(stdClass $theme, $userid, array $offerids) {
    global $DB;

    $offerids = array_values(array_map('intval', $offerids));
    $lock = stage_get_activity_lock($theme->stageid);
    try {
        // Relue sous le verrou : l'appariement, qui le prend aussi, peut avoir eu lieu entre
        // l'affichage du formulaire et son envoi.
        $theme = $DB->get_record('stage_theme', ['id' => $theme->id], '*', MUST_EXIST);
        if (stage_placement_state($theme) !== STAGE_PLACEMENT_OPEN) {
            return 'placementwishesclosed';
        }
        if ($DB->record_exists('stage_placement_assign', ['themeid' => $theme->id, 'userid' => $userid])) {
            return 'placementalreadyassigned';
        }
        $offers = stage_placement_wishable_offers($theme);
        if ($offerids) {
            if (count(array_unique($offerids)) !== count($offerids)) {
                return 'placementwishesduplicate';
            }
            if (array_diff($offerids, array_keys($offers))) {
                return 'placementwishesunknown';
            }
            if (count($offerids) !== stage_placement_required_wishes($theme, count($offers))) {
                return 'placementwishesincomplete';
            }
        }
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('stage_placement_wish', ['themeid' => $theme->id, 'userid' => $userid]);
        $now = time();
        foreach ($offerids as $index => $offerid) {
            $DB->insert_record('stage_placement_wish', (object) [
                'themeid' => $theme->id,
                'userid' => $userid,
                'offerid' => $offerid,
                'wishrank' => $index + 1,
                'timecreated' => $now,
            ]);
        }
        $transaction->allow_commit();
    } finally {
        $lock->release();
    }
    stage_placement_trigger_event(\mod_stage\event\placement_wishes_saved::class, $theme, $userid, 0, [
        'count' => count($offerids),
    ]);
    return null;
}

/**
 * Affectation d'un étudiant pour une thématique.
 *
 * @param int $themeid
 * @param int $userid
 * @return stdClass|false Enregistrement stage_placement_assign.
 */
function stage_placement_get_assignment($themeid, $userid) {
    global $DB;

    return $DB->get_record('stage_placement_assign', ['themeid' => $themeid, 'userid' => $userid]);
}

/**
 * Affectations d'une thématique.
 *
 * @param int $themeid
 * @return array userid => enregistrement stage_placement_assign.
 */
function stage_placement_get_assignments($themeid) {
    global $DB;

    $byuser = [];
    foreach ($DB->get_records('stage_placement_assign', ['themeid' => $themeid]) as $assignment) {
        $byuser[(int) $assignment->userid] = $assignment;
    }
    return $byuser;
}

/**
 * Affectation à laquelle une saisie de stage est rattachée.
 *
 * @param int $entryid
 * @return stdClass|false
 */
function stage_placement_get_entry_assignment($entryid) {
    global $DB;

    return $DB->get_record('stage_placement_assign', ['entryid' => $entryid], '*', IGNORE_MULTIPLE);
}

/**
 * Vrai si l'étudiant peut encore déposer une demande de convention à partir de son affectation :
 * aucune saisie n'en découle, ou celle qui en découlait a été supprimée ou annulée.
 *
 * @param stdClass $assignment
 * @return bool
 */
function stage_placement_assignment_usable(stdClass $assignment) {
    global $DB;

    if (empty($assignment->entryid)) {
        return true;
    }
    $status = $DB->get_field('stage_entry', 'status', ['id' => $assignment->entryid]);
    return $status === false || (int) $status === STAGE_STATUS_ANNULE;
}

/**
 * Même contrôle que stage_placement_assignment_usable(), sur l'affectation relue en base : à
 * appeler sous le verrou de l'activité, juste avant d'enregistrer le stage qui en découle.
 *
 * @param int $assignmentid
 * @return bool False si l'affectation a été retirée ou a déjà servi.
 */
function stage_placement_assignment_usable_now($assignmentid) {
    global $DB;

    $assignment = $DB->get_record('stage_placement_assign', ['id' => $assignmentid]);
    return $assignment && stage_placement_assignment_usable($assignment);
}

/**
 * Rattache à une affectation la saisie de stage déposée à partir d'elle.
 *
 * @param int $assignmentid
 * @param int $entryid
 * @return void
 */
function stage_placement_link_entry($assignmentid, $entryid) {
    global $DB;

    $DB->update_record('stage_placement_assign', (object) [
        'id' => $assignmentid, 'entryid' => $entryid, 'timemodified' => time(),
    ]);
}

/**
 * Places encore libres de chaque organisme d'une thématique.
 *
 * @param array $offers Organismes de la thématique (voir stage_placement_get_offers()).
 * @param array $assignments Affectations de la thématique (voir stage_placement_get_assignments()).
 * @return int[] offerid => places libres (jamais négatif).
 */
function stage_placement_remaining_places(array $offers, array $assignments) {
    $remaining = [];
    foreach ($offers as $offer) {
        $remaining[(int) $offer->id] = (int) $offer->places;
    }
    foreach ($assignments as $assignment) {
        if (isset($remaining[(int) $assignment->offerid])) {
            $remaining[(int) $assignment->offerid] = max(0, $remaining[(int) $assignment->offerid] - 1);
        }
    }
    return $remaining;
}

/**
 * Affecte un étudiant à un organisme, ou retire son affectation (décision de la DEVE).
 *
 * @param stdClass $theme
 * @param int $userid
 * @param int $offerid Organisme, ou 0 pour retirer l'affectation.
 * @param int $byuserid Membre de la DEVE qui décide.
 * @return string|null Identifiant de la chaîne d'erreur, ou null si l'affectation est enregistrée.
 */
function stage_placement_set_assignment(stdClass $theme, $userid, $offerid, $byuserid) {
    $errors = stage_placement_set_assignments($theme, [$userid => $offerid], $byuserid);
    return $errors[$userid] ?? null;
}

/**
 * Affecte, déplace ou retire plusieurs étudiants en une fois (décision de la DEVE).
 *
 * Le lot est appliqué en entier ou pas du tout. Les places des étudiants déplacés ou retirés sont
 * libérées avant de contrôler celles des organismes d'arrivée : deux étudiants peuvent ainsi
 * échanger leurs organismes. Un étudiant dont la demande de convention est déjà déposée à partir
 * de son affectation ne peut être ni déplacé ni retiré : le stage doit d'abord être annulé.
 *
 * @param stdClass $theme
 * @param array $changes userid => organisme (0 pour retirer l'affectation).
 * @param int $byuserid Membre de la DEVE qui décide.
 * @return string[] userid => identifiant de la chaîne d'erreur ; vide si tout le lot est enregistré.
 */
function stage_placement_set_assignments(stdClass $theme, array $changes, $byuserid) {
    global $DB;

    $errors = [];
    $lock = stage_get_activity_lock($theme->stageid);
    try {
        $offers = stage_placement_get_offers($theme->id);
        $assignments = stage_placement_get_assignments($theme->id);

        // Changements effectifs seulement, places des étudiants concernés libérées.
        $effective = [];
        $kept = $assignments;
        foreach ($changes as $userid => $offerid) {
            $userid = (int) $userid;
            $offerid = (int) $offerid;
            $existing = $assignments[$userid] ?? null;
            if ((int) ($existing->offerid ?? 0) === $offerid) {
                continue;
            }
            if ($existing && !stage_placement_assignment_usable($existing)) {
                $errors[$userid] = 'placementassignmentused';
                continue;
            }
            if ($offerid && !isset($offers[$offerid])) {
                $errors[$userid] = 'placementwishesunknown';
                continue;
            }
            $effective[$userid] = $offerid;
            unset($kept[$userid]);
        }
        $remaining = stage_placement_remaining_places($offers, $kept);
        foreach ($effective as $userid => $offerid) {
            if (!$offerid) {
                continue;
            }
            if ($remaining[$offerid] < 1) {
                $errors[$userid] = 'placementofferfull';
                continue;
            }
            $remaining[$offerid]--;
        }
        if ($errors) {
            return $errors;
        }

        $transaction = $DB->start_delegated_transaction();
        $now = time();
        // Les retraits et les départs d'abord : l'index unique (thématique, étudiant) n'est pas en
        // cause, mais l'ordre garde les places cohérentes à chaque instant de la transaction.
        foreach ($effective as $userid => $offerid) {
            $existing = $assignments[$userid] ?? null;
            if (!$offerid) {
                $DB->delete_records('stage_placement_assign', ['id' => $existing->id]);
                continue;
            }
            $rank = array_search($offerid, stage_placement_get_wishes($theme->id, $userid), true);
            $record = (object) [
                'offerid' => $offerid,
                'wishrank' => $rank === false ? 0 : $rank + 1,
                'assignedby' => $byuserid,
                'timemodified' => $now,
            ];
            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record('stage_placement_assign', $record);
            } else {
                $record->themeid = $theme->id;
                $record->userid = $userid;
                $record->timecreated = $now;
                $DB->insert_record('stage_placement_assign', $record);
            }
        }
        $transaction->allow_commit();
    } finally {
        $lock->release();
    }
    foreach ($effective as $userid => $offerid) {
        stage_placement_trigger_event(\mod_stage\event\placement_assignment_changed::class, $theme, $userid, 0, [
            'offerid' => $offerid,
            'previousofferid' => (int) ($assignments[$userid]->offerid ?? 0),
        ]);
    }
    return [];
}

/**
 * Règles d'appariement proposées, pour le réglage de la thématique.
 *
 * @return string[] Code => libellé.
 */
function stage_placement_mode_options() {
    return [
        STAGE_PLACEMENT_MODE_DRAW => get_string('placementmodedraw', 'mod_stage'),
        STAGE_PLACEMENT_MODE_MAXPLACED => get_string('placementmodemaxplaced', 'mod_stage'),
    ];
}

/**
 * Règle d'appariement d'une thématique, ramenée au tirage au sort si la valeur est inconnue.
 *
 * @param stdClass $theme
 * @return string
 */
function stage_placement_mode(stdClass $theme) {
    $mode = (string) ($theme->placementmode ?? '');
    return array_key_exists($mode, stage_placement_mode_options()) ? $mode : STAGE_PLACEMENT_MODE_DRAW;
}

/**
 * Étudiants qui ont déjà un stage en cours (non annulé) sur une thématique : enregistré par la
 * DEVE, déposé hors liste ou à partir d'une affectation. L'appariement ne leur attribue pas de
 * place qu'ils n'utiliseraient pas.
 *
 * @param stdClass $theme
 * @return int[] Identifiants des étudiants, en clés.
 */
function stage_placement_users_with_entry(stdClass $theme) {
    global $DB;

    $userids = $DB->get_fieldset_select(
        'stage_entry',
        'DISTINCT userid',
        'stageid = :stageid AND themeid = :themeid AND status <> :cancelled',
        ['stageid' => $theme->stageid, 'themeid' => $theme->id, 'cancelled' => STAGE_STATUS_ANNULE]
    );
    return array_fill_keys(array_map('intval', $userids), true);
}

/**
 * Apparie les étudiants aux organismes proposés d'une thématique, selon sa règle (voir
 * \mod_stage\local\placement_matcher).
 *
 * Seuls passent les étudiants inscrits qui ont des vœux, pas encore d'affectation, et pas déjà de
 * stage en cours sur la thématique. Rejouer l'appariement ne défait rien : c'est un tour
 * complémentaire sur les places restantes.
 *
 * Chaque tour est consigné (stage_placement_round, stage_placement_draw) : graine et ordre de
 * passage, places de départ, vœux de chaque étudiant à ce moment et résultat. Le tour peut ainsi
 * être rejoué à l'identique (stage_placement_replay_round()), pour justifier une affectation.
 *
 * @param stdClass $theme
 * @param int[]|null $order Ordre de passage imposé (tests) ; tirage au sort par défaut.
 * @param bool $onlyifdue Appariement automatique (tâche planifiée) : il n'a lieu que si, au moment
 *        de le faire, la thématique propose toujours des organismes, que ses vœux sont clos et
 *        qu'elle n'a pas déjà été appariée. La DEVE peut avoir repoussé la date entre-temps.
 * @param int|null $runby Membre de la DEVE qui lance le tour (null : tâche planifiée).
 * @return stdClass {assigned: userid => offerid, unassigned: userid[], skipped: bool, roundid: int}
 */
function stage_placement_run_matching(stdClass $theme, ?array $order = null, $onlyifdue = false, $runby = null) {
    global $DB;

    $result = (object) ['assigned' => [], 'unassigned' => [], 'skipped' => false, 'roundid' => 0];
    $lock = stage_get_activity_lock($theme->stageid);
    try {
        // Relue sous le verrou : la thématique reçue peut dater de plusieurs minutes.
        $theme = $DB->get_record('stage_theme', ['id' => $theme->id], '*', MUST_EXIST);
        if ($onlyifdue && stage_placement_state($theme) !== STAGE_PLACEMENT_CLOSED) {
            $result->skipped = true;
            return $result;
        }
        $offers = stage_placement_get_offers($theme->id);
        $assignments = stage_placement_get_assignments($theme->id);
        $remaining = stage_placement_remaining_places($offers, $assignments);
        $wishes = array_diff_key(stage_placement_get_all_wishes($theme->id), $assignments);
        $wishes = array_diff_key($wishes, stage_placement_users_with_entry($theme));

        $cm = get_coursemodule_from_instance('stage', $theme->stageid, 0, false, IGNORE_MISSING);
        if ($cm) {
            $enrolled = get_enrolled_users(context_module::instance($cm->id), 'mod/stage:submit', 0, 'u.id');
            $wishes = array_intersect_key($wishes, $enrolled);
        }

        $seed = 0;
        if ($order !== null) {
            $order = array_values(array_intersect(array_map('intval', $order), array_keys($wishes)));
            $order = array_merge($order, array_values(array_diff(array_keys($wishes), $order)));
        } else {
            $seed = random_int(1, 0x7fffffff);
            $order = \mod_stage\local\placement_matcher::order(array_keys($wishes), $seed);
        }
        $mode = stage_placement_mode($theme);
        $matched = \mod_stage\local\placement_matcher::match($mode, $order, $wishes, $remaining);

        $transaction = $DB->start_delegated_transaction();
        $now = time();
        $roundid = $DB->insert_record('stage_placement_round', (object) [
            'themeid' => $theme->id,
            'mode' => $mode,
            'seed' => $seed,
            'places' => json_encode($remaining),
            'runby' => $runby ?: null,
            'candidates' => count($order),
            'assigned' => count($matched),
            'timecreated' => $now,
        ]);
        $assignrows = [];
        $drawrows = [];
        foreach ($order as $index => $userid) {
            [$offerid, $rank] = $matched[$userid] ?? [null, 0];
            $drawrows[] = (object) [
                'roundid' => $roundid,
                'userid' => $userid,
                'position' => $index + 1,
                'wishes' => core_text::substr(implode(',', $wishes[$userid]), 0, 255),
                'offerid' => $offerid,
                'wishrank' => $rank,
            ];
            if ($offerid) {
                $assignrows[] = (object) [
                    'themeid' => $theme->id,
                    'userid' => $userid,
                    'offerid' => $offerid,
                    'wishrank' => $rank,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ];
                $result->assigned[$userid] = $offerid;
            } else {
                $result->unassigned[] = $userid;
            }
        }
        $DB->insert_records('stage_placement_draw', $drawrows);
        $DB->insert_records('stage_placement_assign', $assignrows);
        if (empty($theme->placementmatchedtime)) {
            $DB->set_field('stage_theme', 'placementmatchedtime', $now, ['id' => $theme->id]);
        }
        $transaction->allow_commit();
        $result->roundid = (int) $roundid;
    } finally {
        $lock->release();
    }
    stage_placement_trigger_event(\mod_stage\event\placement_matching_run::class, $theme, null, $result->roundid, [
        'mode' => $mode,
        'candidates' => count($order),
        'assigned' => count($result->assigned),
    ]);
    return $result;
}

/**
 * Rejoue un tour d'appariement à partir de sa trace : ordre de passage, vœux et places de départ.
 *
 * @param int $roundid
 * @return stdClass {identical: bool, differences: userid[]} Les étudiants dont le résultat rejoué
 *         diffère de celui qui a été enregistré.
 */
function stage_placement_replay_round($roundid) {
    global $DB;

    $round = $DB->get_record('stage_placement_round', ['id' => $roundid], '*', MUST_EXIST);
    $draws = $DB->get_records('stage_placement_draw', ['roundid' => $round->id], 'position ASC');
    $order = [];
    $wishes = [];
    foreach ($draws as $draw) {
        $order[] = (int) $draw->userid;
        $wishes[(int) $draw->userid] = $draw->wishes === null || $draw->wishes === ''
            ? [] : array_map('intval', explode(',', $draw->wishes));
    }
    $places = array_map('intval', (array) json_decode((string) $round->places, true));
    $replayed = \mod_stage\local\placement_matcher::match($round->mode, $order, $wishes, $places);
    $differences = [];
    foreach ($draws as $draw) {
        $expected = $replayed[$draw->userid] ?? [null, 0];
        if ((int) $draw->offerid !== (int) $expected[0] || (int) $draw->wishrank !== (int) $expected[1]) {
            $differences[] = (int) $draw->userid;
        }
    }
    return (object) ['identical' => !$differences, 'differences' => $differences];
}

/**
 * Tours d'appariement d'une thématique, du plus récent au plus ancien.
 *
 * @param int $themeid
 * @return array id => enregistrement stage_placement_round.
 */
function stage_placement_get_rounds($themeid) {
    global $DB;

    return $DB->get_records('stage_placement_round', ['themeid' => $themeid], 'timecreated DESC, id DESC');
}

/**
 * Déclenche un événement des organismes proposés, dans le contexte du module de la thématique.
 *
 * @param string $class Classe d'événement (\mod_stage\event\placement_*).
 * @param stdClass $theme
 * @param int|null $relateduserid Étudiant concerné, s'il y en a un.
 * @param int $objectid Objet de l'événement (voir la classe) ; la thématique par défaut.
 * @param array $other Données propres à l'événement.
 * @return void
 */
function stage_placement_trigger_event($class, stdClass $theme, $relateduserid = null, $objectid = 0, array $other = []) {
    $cm = get_coursemodule_from_instance('stage', $theme->stageid, 0, false, IGNORE_MISSING);
    if (!$cm) {
        return;
    }
    $params = [
        'context' => context_module::instance($cm->id),
        'objectid' => $objectid ?: $theme->id,
        'other' => ['themeid' => (int) $theme->id] + $other,
    ];
    if ($relateduserid) {
        $params['relateduserid'] = $relateduserid;
    }
    $class::create($params)->trigger();
}

/**
 * Thématiques dont les vœux sont clos et dont l'appariement reste à faire (tâche planifiée).
 *
 * @param int|null $now
 * @return array id => enregistrement stage_theme.
 */
function stage_placement_get_themes_to_match($now = null) {
    global $DB;

    return $DB->get_records_select(
        'stage_theme',
        'placementenabled = 1 AND placementmatchedtime = 0 AND placementdeadline > 0 AND placementdeadline <= :now',
        ['now' => $now ?? time()],
        'id ASC'
    );
}

/**
 * Thématiques dont la clôture des vœux approche et dont les étudiants n'ont pas encore été relancés
 * (tâche planifiée) : vœux ouverts, date de clôture dans moins de STAGE_PLACEMENT_REMINDER_DAYS jours.
 *
 * @param int|null $now
 * @return array id => enregistrement stage_theme.
 */
function stage_placement_get_themes_to_remind($now = null) {
    global $DB;

    $now = $now ?? time();
    return $DB->get_records_select(
        'stage_theme',
        'placementenabled = 1 AND placementmatchedtime = 0 AND placementremindertime = 0
         AND placementdeadline > :now AND placementdeadline <= :limit',
        ['now' => $now, 'limit' => $now + STAGE_PLACEMENT_REMINDER_DAYS * DAYSECS],
        'id ASC'
    );
}

/**
 * Valeurs qui préremplissent la demande de convention de l'étudiant affecté à un organisme. Seuls
 * les champs renseignés pour l'organisme sont fournis : les autres restent à saisir.
 *
 * @param stdClass $offer
 * @return array Champ du formulaire de demande => valeur.
 */
function stage_placement_prefill(stdClass $offer) {
    $data = [];
    foreach (stage_placement_offer_fields() as $offerfield => $formfield) {
        $value = trim((string) ($offer->$offerfield ?? ''));
        if ($value !== '') {
            $data[$formfield] = $value;
        }
    }
    $data['abroad'] = isset($data['country']) ? 1 : 0;
    // Période d'accueil imposée : elle devient la plage de dates du stage, modifiable.
    if (!empty($offer->datestart) && !empty($offer->dateend)) {
        $data['perioddatestart'] = [(int) $offer->datestart];
        $data['perioddateend'] = [(int) $offer->dateend];
    }
    return $data;
}

/**
 * Contrôle d'une demande de convention au regard des organismes proposés de sa thématique.
 *
 * - Thématique sans organismes proposés : rien à contrôler.
 * - Demande déposée à partir de l'affectation de l'étudiant : elle doit porter sur l'organisme
 *   attribué (même nom, et même SIRET si l'organisme en a un). Les autres coordonnées, préremplies,
 *   restent corrigeables.
 * - Sinon la demande est « hors liste ». Elle est refusée si la thématique ne l'autorise pas, sauf
 *   pour un stage déjà enregistré (par la DEVE, qui en a décidé ainsi). Quand elle est admise, le
 *   SIRET de l'organisme est obligatoire, sauf pour un stage à l'étranger : le pays doit alors être
 *   indiqué, et ne pas être la France.
 *
 * @param stdClass|null $theme Thématique retenue pour le stage.
 * @param array $data Données soumises (structure, abroad, country, siret).
 * @param stdClass|null|false $assignment Affectation à partir de laquelle la demande est déposée.
 * @param bool $existingentry Le stage est déjà enregistré sur cette thématique.
 * @param int $userid Étudiant qui dépose une nouvelle demande hors liste : s'il a une affectation
 *        encore inutilisée sur la thématique, il doit d'abord y renoncer (0 : pas de contrôle).
 * @return array champ => message d'erreur
 */
function stage_placement_request_errors($theme, array $data, $assignment, $existingentry = false, $userid = 0) {
    global $DB;

    if (!$theme || empty($theme->placementenabled)) {
        return [];
    }
    if ($assignment && (int) $assignment->themeid === (int) $theme->id) {
        $offer = $DB->get_record('stage_placement_offer', ['id' => $assignment->offerid]);
        if (!$offer) {
            return [];
        }
        if (stage_placement_offer_key($data['structure'] ?? '') !== stage_placement_offer_key($offer->name)) {
            return ['structure' => get_string('placementstructuremismatch', 'mod_stage', format_string($offer->name))];
        }
        $siret = stage_normalize_siret($data['siret'] ?? '');
        if ((string) $offer->siret !== '' && $siret !== '' && $siret !== (string) $offer->siret) {
            return ['siret' => get_string('placementsiretmismatch', 'mod_stage', s($offer->siret))];
        }
        return [];
    }
    if (empty($theme->placementofflist) && !$existingentry) {
        return ['themeid' => get_string('placementrequired', 'mod_stage')];
    }
    // Une place attribuée et une demande hors liste : l'étudiant garderait une place qu'il
    // n'utilisera pas. Il renonce d'abord à son affectation (page des organismes).
    if ($userid && !$existingentry) {
        $held = stage_placement_get_assignment($theme->id, $userid);
        if ($held && stage_placement_assignment_usable($held)) {
            $offername = $DB->get_field('stage_placement_offer', 'name', ['id' => $held->offerid]);
            return ['themeid' => get_string('placementrenouncefirst', 'mod_stage', format_string((string) $offername))];
        }
    }
    if (stage_normalize_siret($data['siret'] ?? '') !== '') {
        return [];
    }
    if (empty($data['abroad'])) {
        return ['siret' => get_string('placementsiretrequired', 'mod_stage')];
    }
    // « À l'étranger » ne dispense du SIRET que si le pays est indiqué, et n'est pas la France.
    $country = trim((string) ($data['country'] ?? ''));
    if ($country === '' || stage_placement_country_is_france($country)) {
        return ['country' => get_string('placementcountryrequired', 'mod_stage')];
    }
    return [];
}

/**
 * Affectation dont découle la demande de convention d'un stage déjà enregistré : celle à laquelle
 * il est rattaché ou, pour un stage enregistré par la DEVE chez l'organisme attribué à l'étudiant,
 * l'affectation encore inutilisée de l'étudiant sur cette thématique. Un stage enregistré chez un
 * autre organisme ne consomme pas l'affectation : sa demande est hors liste.
 *
 * @param stdClass $entry Saisie de stage.
 * @return stdClass|null
 */
function stage_placement_assignment_for_entry(stdClass $entry) {
    global $DB;

    $linked = stage_placement_get_entry_assignment($entry->id);
    if ($linked) {
        return $linked;
    }
    $unused = stage_placement_get_assignment($entry->themeid, $entry->userid);
    if (!$unused || !stage_placement_assignment_usable($unused)) {
        return null;
    }
    $offername = $DB->get_field('stage_placement_offer', 'name', ['id' => $unused->offerid]);
    if ($offername === false || stage_placement_offer_key($offername) !== stage_placement_offer_key($entry->structure)) {
        return null;
    }
    return $unused;
}

/**
 * Organismes d'accueil des stages déjà enregistrés dans une activité, pour les reprendre comme
 * organismes proposés. Chaque organisme (même SIRET, ou à défaut même nom) n'apparaît qu'une fois,
 * avec les coordonnées de son stage le plus récent. Les stages annulés sont ignorés.
 *
 * @param int $stageid Activité source.
 * @param int $themeid Thématique source, ou 0 pour toutes.
 * @return array clé => stdClass {key, count, lastdate, et les champs d'un organisme proposé}
 */
function stage_placement_get_past_organisations($stageid, $themeid = 0) {
    global $DB;

    $params = ['stageid' => $stageid, 'cancelled' => STAGE_STATUS_ANNULE];
    $themesql = '';
    if ($themeid) {
        $themesql = 'AND e.themeid = :themeid';
        $params['themeid'] = $themeid;
    }
    $sql = "SELECT e.id, e.structure, e.abroad, e.country, e.datestart,
                   d.hostaddress, d.hostrepresentative, d.hostrepresentativetitle, d.hostservice, d.hostphone,
                   d.hostemail, d.hostlocation, d.siret, d.tutorname, d.tutorfunction, d.tutorphone, d.tutoremail
              FROM {stage_entry} e
         LEFT JOIN {stage_convention_detail} d ON d.entryid = e.id
             WHERE e.stageid = :stageid AND e.status <> :cancelled $themesql
          ORDER BY e.datestart DESC, e.id DESC";
    $organisations = [];
    $entries = $DB->get_recordset_sql($sql, $params);
    foreach ($entries as $entry) {
        $name = trim((string) $entry->structure);
        if ($name === '') {
            continue;
        }
        $key = md5(stage_placement_offer_key($name, $entry->siret));
        if (isset($organisations[$key])) {
            $organisations[$key]->count++;
            continue;
        }
        $organisations[$key] = (object) [
            'key' => $key,
            'count' => 1,
            'lastdate' => (int) $entry->datestart,
            'name' => $name,
            'address' => (string) $entry->hostaddress,
            'representative' => (string) $entry->hostrepresentative,
            'representativetitle' => (string) $entry->hostrepresentativetitle,
            'service' => (string) $entry->hostservice,
            'phone' => (string) $entry->hostphone,
            'email' => (string) $entry->hostemail,
            'location' => (string) $entry->hostlocation,
            'siret' => (string) $entry->siret,
            'country' => $entry->abroad ? (string) $entry->country : '',
            'tutorname' => (string) $entry->tutorname,
            'tutorfunction' => (string) $entry->tutorfunction,
            'tutorphone' => (string) $entry->tutorphone,
            'tutoremail' => (string) $entry->tutoremail,
        ];
    }
    $entries->close();
    uasort($organisations, fn($a, $b) => strcmp(stage_normalize_name($a->name), stage_normalize_name($b->name)));
    return $organisations;
}

/**
 * Copie d'une thématique à l'autre les organismes proposés (import de thématiques depuis une autre
 * activité). Ni les vœux ni les affectations ne suivent : ils appartiennent à la promotion source.
 *
 * @param int $sourcethemeid
 * @param int $targetthemeid
 * @return void
 */
function stage_placement_copy_offers($sourcethemeid, $targetthemeid) {
    foreach (stage_placement_get_offers($sourcethemeid) as $offer) {
        stage_placement_save_offer($targetthemeid, $offer);
    }
}
