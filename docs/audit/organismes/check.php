<?php
// Invariants après une vague du banc « organismes proposés ». Aucun des neuf ne doit être violé,
// quel que soit l'entrelacement des requêtes :
//  1. aucun organisme n'a plus d'étudiants affectés que de places ;
//  2. un étudiant a au plus une affectation par thématique ;
//  3. une affectation issue de l'appariement porte sur un vœu de l'étudiant, au rang enregistré ;
//  4. les vœux d'un étudiant sont au nombre demandé, de rangs 1 à n, sans doublon, sur des
//     organismes de la thématique ;
//  5. le stage rattaché à une affectation appartient à l'étudiant et à la thématique ;
//  6. un étudiant n'a pas deux stages non annulés sur la thématique (double envoi) ;
//  7. un stage hors affectation, en France, porte un SIRET valide ;
//  8. tout stage déposé a une demande de convention et son détail ;
//  9. aucun organisme n'est proposé deux fois (même SIRET ou même nom) dans une thématique de
//     l'activité, quels que soient les imports Excel simultanés ou rejoués ;
// 10. chaque tour d'appariement, rejoué à partir de sa trace, donne le résultat enregistré, et
//     chaque affectation issue d'un tour figure dans sa trace.
// Usage : php check.php site-<tag>.json  (code de sortie 1 si un invariant est violé).
define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/home/user/moodle/moodle/config.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
$site = json_decode(file_get_contents($argv[1]));
$theme = $DB->get_record('stage_theme', ['id' => $site->theme], '*', MUST_EXIST);
$offers = stage_placement_get_offers($theme->id);
$assignments = $DB->get_records('stage_placement_assign', ['themeid' => $theme->id]);
$wishes = stage_placement_get_all_wishes($theme->id);
$required = stage_placement_required_wishes($theme, count($offers));
$bad = [];

$peroffer = [];
$peruser = [];
foreach ($assignments as $a) {
    $peroffer[$a->offerid] = ($peroffer[$a->offerid] ?? 0) + 1;
    $peruser[$a->userid] = ($peruser[$a->userid] ?? 0) + 1;
    if (!isset($offers[$a->offerid])) {
        $bad[] = "affectation {$a->id} : organisme {$a->offerid} hors thématique";
    }
    if ($a->assignedby === null) {
        $rank = array_search((int) $a->offerid, $wishes[$a->userid] ?? [], true);
        if ($rank === false || $rank + 1 !== (int) $a->wishrank) {
            $bad[] = "affectation {$a->id} : pas le vœu n° {$a->wishrank} de l'étudiant {$a->userid}";
        }
    }
    if ($a->entryid) {
        $entry = $DB->get_record('stage_entry', ['id' => $a->entryid]);
        if (!$entry || $entry->userid != $a->userid || $entry->themeid != $theme->id) {
            $bad[] = "affectation {$a->id} : stage {$a->entryid} d'un autre étudiant ou d'une autre thématique";
        }
    }
}
$overflow = 0;
foreach ($offers as $offer) {
    if (($peroffer[$offer->id] ?? 0) > $offer->places) {
        $overflow++;
        $bad[] = "organisme {$offer->id} : {$peroffer[$offer->id]} affectés pour {$offer->places} place(s)";
    }
}
foreach ($peruser as $userid => $count) {
    if ($count > 1) {
        $bad[] = "étudiant $userid : $count affectations";
    }
}
$rows = $DB->get_records('stage_placement_wish', ['themeid' => $theme->id], 'userid, wishrank');
$ranks = [];
foreach ($rows as $w) {
    $ranks[$w->userid][] = (int) $w->wishrank;
    if (!isset($offers[$w->offerid])) {
        $bad[] = "vœu {$w->id} : organisme {$w->offerid} hors thématique";
    }
}
foreach ($ranks as $userid => $list) {
    if ($list !== range(1, $required) || count(array_unique($wishes[$userid])) !== $required) {
        $bad[] = "étudiant $userid : vœux incohérents (rangs " . implode(',', $list) . ")";
    }
}

$entries = $DB->get_records_select('stage_entry', 'stageid = ? AND themeid = ?', [$site->stageid, $theme->id]);
$linked = array_column($assignments, 'entryid', 'entryid');
$active = [];
$offlist = 0;
foreach ($entries as $e) {
    if ((int) $e->status !== STAGE_STATUS_ANNULE) {
        $active[$e->userid] = ($active[$e->userid] ?? 0) + 1;
    }
    $detail = stage_get_convention_detail($e->id);
    if (!$detail || (int) $e->conventionstatus === STAGE_CONVENTION_NONE) {
        $bad[] = "stage {$e->id} : déposé sans demande de convention ou sans détail";
        continue;
    }
    if (!isset($linked[$e->id])) {
        $offlist++;
        if (!$e->abroad && !stage_siret_valid((string) $detail->siret)) {
            $bad[] = "stage {$e->id} : hors liste en France sans SIRET valide";
        }
    }
}
foreach ($active as $userid => $count) {
    if ($count > 1) {
        $bad[] = "étudiant $userid : $count stages actifs sur la thématique";
    }
}
$duplicates = 0;
foreach ($DB->get_fieldset_select('stage_theme', 'id', 'stageid = ?', [$site->stageid]) as $themeid) {
    $keys = [];
    foreach (stage_placement_get_offers($themeid) as $offer) {
        $key = stage_placement_offer_key($offer->name, $offer->siret);
        if (isset($keys[$key])) {
            $duplicates++;
            $bad[] = "organisme {$offer->id} : doublon de l'organisme {$keys[$key]} (« {$offer->name} »)";
        }
        $keys[$key] = $offer->id;
    }
}
$rounds = stage_placement_get_rounds($theme->id);
$traced = [];
foreach ($rounds as $round) {
    $replay = stage_placement_replay_round($round->id);
    if (!$replay->identical) {
        $bad[] = "tour {$round->id} : " . count($replay->differences) . " résultat(s) différents au rejeu";
    }
    foreach ($DB->get_records_select('stage_placement_draw', 'roundid = ? AND offerid IS NOT NULL', [$round->id]) as $draw) {
        $traced[$draw->userid] = (int) $draw->offerid;
    }
}
foreach ($assignments as $a) {
    if ($a->assignedby === null && ($traced[$a->userid] ?? 0) !== (int) $a->offerid) {
        $bad[] = "affectation {$a->id} : absente de la trace des tours";
    }
}
echo json_encode([
    'règle' => stage_placement_mode($theme),
    'tours' => count($rounds),
    'organismes' => count($offers),
    'organismes en double' => $duplicates,
    'places' => array_sum(array_column($offers, 'places')),
    'étudiants avec vœux' => count($wishes),
    'affectations' => count($assignments),
    'dont manuelles' => count(array_filter($assignments, fn($a) => $a->assignedby !== null)),
    'vœu satisfait (rang => nombre)' => array_count_values(array_map(fn($a) => (int) $a->wishrank, $assignments)),
    'organismes complets' => count(array_filter($offers, fn($o) => ($peroffer[$o->id] ?? 0) >= $o->places)),
    'organismes en dépassement' => $overflow,
    'stages déposés' => count($entries),
    'dont rattachés à une affectation' => count(array_filter($linked)),
    'dont hors liste' => $offlist,
    'invariants violés' => count($bad),
    'exemples' => array_slice($bad, 0, 8),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
exit($bad ? 1 : 0);
