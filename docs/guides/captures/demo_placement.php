<?php
// Complément du site de démonstration (à lancer après demo_data.php et demo_questions.php) : la
// thématique « Animaux de production » de la promotion 2030 devient une thématique à organismes
// proposés, pour les captures 90 à 97 (plan_placement.json).
//
//   php docs/guides/captures/demo_placement.php /chemin/vers/moodle/config.php
//
// - deux vœux à classer, clôture dans un an, demandes hors liste admises ;
// - trois organismes : GAEC des Monts (période d'accueil imposée), Cabinet vétérinaire des Monts du
//   Lyonnais, Clinique équine de Grosbois ;
// - vœux d'Emma Fournier, Manon Bonnet et Louis Girard, puis un tour d'appariement lancé par Claire
//   Martin (DEVE) : Emma Fournier obtient le GAEC, Manon Bonnet le cabinet, Louis Girard la clinique
//   équine. Léa Moreau, qui a déjà un stage sur la thématique, n'est pas appariée : Claire Martin
//   l'affecte à la clinique équine, et son stage est rattaché à cette affectation ;
// - les vœux sont ensuite rouverts, comme le permet la fiche de la thématique.
// Rejouable : les organismes, vœux, affectations et tours de la thématique sont d'abord effacés.
define('CLI_SCRIPT', true);
require($argv[1]);
require_once($CFG->dirroot . '/mod/stage/locallib.php');
\core\session\manager::set_user(get_admin());

$cm = $DB->get_record_sql("SELECT cm.* FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module AND m.name = 'stage'
    ORDER BY cm.id ASC", [], IGNORE_MULTIPLE);
$theme = $DB->get_record('stage_theme', ['stageid' => $cm->instance, 'name' => 'Animaux de production'], '*', MUST_EXIST);
$user = fn($username) => (int) $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);

stage_placement_delete_themes_data([$theme->id]);
$DB->update_record('stage_theme', (object) [
    'id' => $theme->id, 'placementenabled' => 1, 'placementchoices' => 2, 'placementofflist' => 1,
    'placementmode' => STAGE_PLACEMENT_MODE_DRAW, 'placementdeadline' => make_timestamp(2027, 11, 17, 7, 27),
    'placementmatchedtime' => 0, 'placementremindertime' => 0,
]);
$theme = $DB->get_record('stage_theme', ['id' => $theme->id], '*', MUST_EXIST);

$gaec = stage_placement_save_offer($theme->id, [
    'name' => 'GAEC des Monts', 'places' => 1, 'address' => '12 route des Crêtes, 69610 Aveize',
    'representative' => 'Mme Anne Vial', 'representativetitle' => 'Gérante', 'service' => 'Élevage',
    'phone' => '04 78 00 00 10', 'email' => 'gaec@example.com', 'siret' => '73282932000074',
    'description' => 'Élevage laitier, 80 vaches. Accueil de mars à juin.', 'tutorname' => 'Dr Paul Roche',
    'tutorfunction' => 'Vétérinaire sanitaire', 'tutorphone' => '04 78 00 00 11', 'tutoremail' => 'roche@example.com',
    'datestart' => make_timestamp(2027, 3, 1), 'dateend' => make_timestamp(2027, 3, 12),
]);
$cabinet = stage_placement_save_offer($theme->id, ['name' => 'Cabinet vétérinaire des Monts du Lyonnais', 'places' => 2]);
$equine = stage_placement_save_offer($theme->id, [
    'name' => 'Clinique équine de Grosbois', 'places' => 3, 'location' => 'Boissy-Saint-Léger',
]);

$wishlists = [['efournier', [$gaec, $cabinet]], ['mbonnet', [$gaec, $cabinet]], ['lgirard', [$gaec, $equine]]];
foreach ($wishlists as [$username, $wishes]) {
    $error = stage_placement_save_wishes($theme, $user($username), $wishes);
    if ($error !== null) {
        throw new moodle_exception($error, 'mod_stage');
    }
}
$result = stage_placement_run_matching($theme, [$user('efournier'), $user('mbonnet'), $user('lgirard')], false, $user('cmartin'));

// Léa Moreau : affectée par la DEVE, son stage sur la thématique rattaché à l'affectation.
$entryid = $DB->get_field_sql(
    'SELECT MAX(id) FROM {stage_entry} WHERE stageid = ? AND themeid = ? AND userid = ?',
    [$cm->instance, $theme->id, $user('lmoreau')]
);
if ($entryid) {
    $error = stage_placement_set_assignment($theme, $user('lmoreau'), $equine, $user('cmartin'));
    if ($error !== null) {
        throw new moodle_exception($error, 'mod_stage');
    }
    $DB->set_field('stage_entry', 'structure', 'Clinique équine de Grosbois', ['id' => $entryid]);
    stage_placement_link_entry(stage_placement_get_assignment($theme->id, $user('lmoreau'))->id, $entryid);
}
// Vœux rouverts (tour complémentaire possible), comme le fait la case de la fiche de thématique.
$DB->set_field('stage_theme', 'placementmatchedtime', 0, ['id' => $theme->id]);

echo "cm={$cm->id} thématique={$theme->id} tour={$result->roundid} affectés=" . count($result->assigned)
    . " stage de Léa Moreau=" . (int) $entryid . "\n";
