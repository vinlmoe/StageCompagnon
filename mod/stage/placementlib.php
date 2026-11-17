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
        $DB->delete_records('stage_placement_wish', ['offerid' => $offerid]);
        $DB->delete_records('stage_placement_offer', ['id' => $offerid]);
        $transaction->allow_commit();
    } finally {
        $lock->release();
    }
    return true;
}

/**
 * Supprime tout ce qui se rattache aux organismes proposés de thématiques : organismes, vœux et
 * affectations (suppression d'une thématique ou de l'activité).
 *
 * @param int[] $themeids
 * @return void
 */
function stage_placement_delete_themes_data(array $themeids) {
    global $DB;

    foreach (array_chunk(array_values($themeids), STAGE_SQL_CHUNK) as $chunk) {
        [$insql, $inparams] = $DB->get_in_or_equal($chunk);
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
    }
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
        $offers = stage_placement_get_offers($theme->id);
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
    return [];
}

/**
 * Apparie les étudiants aux organismes proposés d'une thématique.
 *
 * Les étudiants qui ont des vœux et pas encore d'affectation sont tirés au sort ; dans cet ordre,
 * chacun reçoit le mieux classé de ses vœux où il reste une place. Un étudiant dont tous les vœux
 * sont complets reste sans affectation. Avec cette règle, personne n'a intérêt à classer ses vœux
 * autrement que selon ses préférences réelles.
 *
 * Seuls les étudiants sans affectation sont traités, dans la limite des places restantes : rejouer
 * l'appariement ne défait rien et sert de tour complémentaire (places ajoutées, affectations
 * retirées par la DEVE). Les étudiants qui ne sont plus inscrits au cours sont ignorés.
 *
 * @param stdClass $theme
 * @param int[]|null $order Ordre de passage imposé (tests) ; tirage au sort par défaut.
 * @param bool $onlyifdue Appariement automatique (tâche planifiée) : il n'a lieu que si, au moment
 *        de le faire, la thématique propose toujours des organismes, que ses vœux sont clos et
 *        qu'elle n'a pas déjà été appariée. La DEVE peut avoir repoussé la date entre-temps.
 * @return stdClass {assigned: userid => offerid, unassigned: userid[], skipped: bool}
 */
function stage_placement_run_matching(stdClass $theme, ?array $order = null, $onlyifdue = false) {
    global $DB;

    $result = (object) ['assigned' => [], 'unassigned' => [], 'skipped' => false];
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

        $cm = get_coursemodule_from_instance('stage', $theme->stageid, 0, false, IGNORE_MISSING);
        if ($cm) {
            $enrolled = get_enrolled_users(context_module::instance($cm->id), 'mod/stage:submit', 0, 'u.id');
            $wishes = array_intersect_key($wishes, $enrolled);
        }

        $candidates = array_keys($wishes);
        if ($order !== null) {
            $position = array_flip(array_values($order));
            usort($candidates, fn($a, $b) => ($position[$a] ?? PHP_INT_MAX) <=> ($position[$b] ?? PHP_INT_MAX) ?: $a <=> $b);
        } else {
            shuffle($candidates);
        }

        $transaction = $DB->start_delegated_transaction();
        $now = time();
        foreach ($candidates as $userid) {
            $assigned = false;
            foreach ($wishes[$userid] as $index => $offerid) {
                if (($remaining[$offerid] ?? 0) < 1) {
                    continue;
                }
                $DB->insert_record('stage_placement_assign', (object) [
                    'themeid' => $theme->id,
                    'userid' => $userid,
                    'offerid' => $offerid,
                    'wishrank' => $index + 1,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
                $remaining[$offerid]--;
                $result->assigned[$userid] = $offerid;
                $assigned = true;
                break;
            }
            if (!$assigned) {
                $result->unassigned[] = $userid;
            }
        }
        if (empty($theme->placementmatchedtime)) {
            $DB->set_field('stage_theme', 'placementmatchedtime', $now, ['id' => $theme->id]);
        }
        $transaction->allow_commit();
    } finally {
        $lock->release();
    }
    return $result;
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
 * Annonce à chaque étudiant le résultat de l'appariement : l'organisme auquel il est affecté, ou
 * l'absence d'affectation.
 *
 * @param stdClass $stage
 * @param stdClass $cm Course module.
 * @param stdClass $theme
 * @param stdClass $result Retour de stage_placement_run_matching().
 * @return int Nombre de courriels partis.
 */
function stage_placement_notify_results(stdClass $stage, stdClass $cm, stdClass $theme, stdClass $result) {
    global $DB;

    $userids = array_merge(array_keys($result->assigned), $result->unassigned);
    if (!$userids) {
        return 0;
    }
    $offers = stage_placement_get_offers($theme->id);
    $url = new moodle_url('/mod/stage/placement.php', ['id' => $cm->id, 'themeid' => $theme->id]);
    $sent = 0;
    foreach (array_chunk($userids, STAGE_SQL_CHUNK) as $chunk) {
        [$insql, $inparams] = $DB->get_in_or_equal($chunk);
        $students = $DB->get_records_select('user', "id $insql AND deleted = 0", $inparams);
        foreach ($students as $student) {
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
            if (email_to_user($student, core_user::get_noreply_user(), $text->subject, $text->body)) {
                $sent++;
            }
        }
    }
    return $sent;
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
 * @return array champ => message d'erreur
 */
function stage_placement_request_errors($theme, array $data, $assignment, $existingentry = false) {
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

/**
 * Libellé d'un vœu satisfait : « vœu n° 2 », ou « hors vœux » pour une affectation décidée par la
 * DEVE en dehors des vœux de l'étudiant.
 *
 * @param int $wishrank
 * @return string
 */
function stage_placement_rank_label($wishrank) {
    return $wishrank > 0
        ? get_string('placementwishrank', 'mod_stage', (int) $wishrank)
        : get_string('placementoutsidewishes', 'mod_stage');
}

/**
 * Lieu d'un organisme proposé tel qu'il est montré dans les listes : son lieu de stage, à défaut
 * son adresse, suivi de son pays s'il est à l'étranger.
 *
 * @param stdClass $offer
 * @return string Texte brut.
 */
function stage_placement_offer_place(stdClass $offer) {
    $place = trim((string) $offer->location) !== '' ? $offer->location : (string) $offer->address;
    $parts = array_filter([trim((string) $place), trim((string) $offer->country)], fn($part) => $part !== '');
    return implode(' — ', $parts);
}

/**
 * Encart « Organismes proposés » du tableau de bord de l'étudiant : pour chaque thématique qui en
 * propose, où en sont ses vœux, son affectation et sa demande de convention, avec l'action à faire.
 *
 * @param stdClass $stage
 * @param stdClass $cm Course module.
 * @param int $userid
 * @return string HTML, vide si aucune thématique visible ne propose d'organismes.
 */
function stage_placement_render_student_section(stdClass $stage, stdClass $cm, $userid) {
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

    $out = $OUTPUT->heading(get_string('placementoffers', 'mod_stage'), 4);
    foreach ($themes as $theme) {
        $state = stage_placement_state($theme);
        $assignment = $assignments[(int) $theme->id] ?? null;
        $wishesurl = new moodle_url('/mod/stage/placement.php', ['id' => $cm->id, 'themeid' => $theme->id]);
        $lines = [];
        $actions = [];

        if ($assignment) {
            $offer = $DB->get_record('stage_placement_offer', ['id' => $assignment->offerid]);
            $lines[] = get_string('placementassignedto', 'mod_stage', (object) [
                'name' => $offer ? format_string($offer->name) : '-',
                'rank' => stage_placement_rank_label($assignment->wishrank),
            ]);
            if (stage_placement_assignment_usable($assignment)) {
                $actions[get_string('placementcompleterequest', 'mod_stage')] = new moodle_url(
                    '/mod/stage/student_register.php',
                    ['id' => $cm->id, 'placement' => $theme->id]
                );
            } else {
                $lines[] = get_string('placementrequestdone', 'mod_stage');
            }
            $actions[get_string('placementviewoffers', 'mod_stage')] = $wishesurl;
        } else if ($state === STAGE_PLACEMENT_OPEN) {
            $wishcount = count(stage_placement_get_wishes($theme->id, $userid));
            $lines[] = !empty($theme->placementdeadline)
                ? get_string('placementopenuntil', 'mod_stage', userdate($theme->placementdeadline))
                : get_string('placementopen', 'mod_stage');
            $lines[] = $wishcount
                ? get_string('placementwishessaved', 'mod_stage', $wishcount)
                : get_string('placementnowishes', 'mod_stage');
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
        if (!empty($theme->placementofflist) && !$assignment && $state !== STAGE_PLACEMENT_MATCHED) {
            $lines[] = get_string('placementofflistinfo', 'mod_stage');
        }

        $body = html_writer::tag('strong', format_string($theme->name));
        foreach ($lines as $line) {
            $body .= html_writer::div($line);
        }
        $body .= html_writer::div(stage_render_actions($actions, 'btn btn-sm btn-primary mr-1 mb-1', ''), 'mt-2');
        $out .= html_writer::div($body, 'stage-placement-card border rounded p-3 mb-3');
    }
    return $out;
}

/**
 * Tableau récapitulatif des affectations d'une thématique, pour l'export : un étudiant par ligne,
 * avec ses vœux dans l'ordre, l'organisme attribué et le rang du vœu satisfait.
 *
 * @param stdClass $theme
 * @param array $students Étudiants à lister (id => utilisateur).
 * @return array {columns: string[], rows: array[]}
 */
function stage_placement_get_export(stdClass $theme, array $students) {
    $offers = stage_placement_get_offers($theme->id);
    $wishes = stage_placement_get_all_wishes($theme->id);
    $assignments = stage_placement_get_assignments($theme->id);
    $choices = max(1, (int) $theme->placementchoices);
    foreach ($wishes as $userwishes) {
        $choices = max($choices, count($userwishes));
    }

    $columns = [get_string('lastname'), get_string('firstname'), get_string('email')];
    for ($rank = 1; $rank <= $choices; $rank++) {
        $columns[] = get_string('placementwishrank', 'mod_stage', $rank);
    }
    $columns[] = get_string('placementassignment', 'mod_stage');
    $columns[] = get_string('placementsatisfiedwish', 'mod_stage');

    $rows = [];
    foreach ($students as $student) {
        $row = [$student->lastname, $student->firstname, $student->email];
        $userwishes = $wishes[$student->id] ?? [];
        for ($index = 0; $index < $choices; $index++) {
            $offerid = $userwishes[$index] ?? 0;
            $row[] = isset($offers[$offerid]) ? stage_plain_string($offers[$offerid]->name) : '';
        }
        $assignment = $assignments[$student->id] ?? null;
        $row[] = $assignment && isset($offers[$assignment->offerid])
            ? stage_plain_string($offers[$assignment->offerid]->name) : '';
        $row[] = $assignment ? stage_placement_rank_label($assignment->wishrank) : '';
        $rows[] = $row;
    }
    return ['columns' => $columns, 'rows' => $rows];
}
