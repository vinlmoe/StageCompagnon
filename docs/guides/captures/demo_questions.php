<?php
// Complément du site de démonstration (à lancer après demo_data.php) : les types de question
// ajoutés aux listes d'évaluation, le questionnaire de demande (pré-stage) et la check-list des
// prérequis, pour les captures des nouveautés (plan_questions.json).
//
//   php docs/guides/captures/demo_questions.php /chemin/vers/moodle/config.php
//
// Ajoute en fin de site, sans changer les identifiants produits par demo_data.php :
// - trois questions (cases à cocher, oui/non, nombre) à l'auto-évaluation standard ;
// - une liste « Questionnaire pré-stage » aux huit types, choisie par toutes les thématiques ;
// - les réponses de Léa Moreau (auto-évaluation de son stage en production) et les réponses au
//   questionnaire pré-stage de plusieurs demandes ;
// - deux stages : Manon Bonnet (animaux de compagnie, convention à demander) et Nathan Lambert
//   (animaux de compagnie, signé et terminé, à auto-évaluer).
// Affiche à la fin les identifiants utiles au plan de captures.
define('CLI_SCRIPT', true);
require($argv[1]);
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->dirroot . '/mod/stage/tests/generator/lib.php');
\core\session\manager::set_user(get_admin());
$gen = new testing_data_generator();
$sgen = $gen->get_plugin_generator('mod_stage');

$cm = $DB->get_record_sql("SELECT cm.* FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module AND m.name = 'stage'
    ORDER BY cm.id ASC", [], IGNORE_MULTIPLE);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);
$themes = [];
foreach ($DB->get_records('stage_theme', ['stageid' => $stage->id]) as $theme) {
    $themes[$theme->name] = $theme;
}
$user = fn($username) => $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
$now = time();

// Check-list : des prérequis plutôt que des objectifs (sites créés avant ce changement).
$prereqs = ['Vaccination antirabique à jour', 'Assurance responsabilité civile professionnelle',
    'Formation à la radioprotection suivie'];
foreach (array_values(stage_get_theme_checklist($themes['Animaux de compagnie']->id)) as $i => $item) {
    if (isset($prereqs[$i])) {
        $DB->update_record('stage_theme_checklist', (object) ['id' => $item->id, 'name' => $prereqs[$i],
            'description' => $i === 0 ? 'Certificat à présenter à la structure d\'accueil' : '']);
    }
}

$q = function ($evaltype, $qtype, $name, $options = null, $sort = 1, $required = 1) use ($DB, $stage) {
    return (int) $DB->insert_record('stage_question', (object) [
        'stageid' => $stage->id, 'themeid' => 0, 'evaltype' => $evaltype, 'qtype' => $qtype, 'name' => $name,
        'options' => $options, 'required' => $required, 'sortorder' => $sort,
        'timecreated' => $now = time(), 'timemodified' => $now,
    ]);
};

// Auto-évaluation : trois questions des nouveaux types.
$studentlist = $DB->get_record('stage_evallist', ['stageid' => $stage->id, 'evaltype' => 'student'], '*', IGNORE_MULTIPLE);
$new = [
    $q('student', 'checkbox', 'Quels actes avez-vous pratiqués vous-même ?',
        "Examen clinique\nInjections et prélèvements\nAnesthésie\nChirurgie\nImagerie", 4),
    $q('student', 'number', 'Combien de consultations avez-vous suivies, environ ?', null, 5),
    $q('student', 'yesno', 'Recommanderiez-vous cette structure à un autre étudiant ?', null, 6),
];
foreach ($new as $questionid) {
    stage_add_evallist_question($studentlist->id, $questionid);
}

// Questionnaire pré-stage : les huit types.
$request = [
    $q('request', 'choice', 'Avez-vous déjà effectué un stage dans ce domaine ?', "Oui, plusieurs\nOui, un seul\nNon", 1),
    $q('request', 'checkbox', 'Quelles activités souhaitez-vous découvrir ?',
        "Consultations\nChirurgie\nImagerie\nHospitalisation\nUrgences et gardes", 2),
    $q('request', 'yesno', 'Disposez-vous d\'un véhicule pendant le stage ?', null, 3),
    $q('request', 'rating', 'Votre motivation pour ce stage', null, 4),
    $q('request', 'number', 'Distance entre votre hébergement et la structure (km)', null, 5),
    $q('request', 'date', 'Date à partir de laquelle vous êtes disponible', null, 6),
    $q('request', 'shorttext', 'Ville d\'hébergement pendant le stage', null, 7),
    $q('request', 'text', 'Votre projet professionnel en quelques lignes', null, 8, 0),
];
$requestlist = $sgen->create_evallist($stage, 'request', $request, array_values($themes), 'Questionnaire pré-stage');

$requestquestions = $DB->get_records_list('stage_question', 'id', $request);
$answer = function ($entry, array $values) use ($requestquestions) {
    stage_save_answers($entry->id, $requestquestions, array_combine(array_keys($requestquestions), $values));
};
$entries = $DB->get_records('stage_entry', ['stageid' => $stage->id], 'id ASC');
$find = function ($username, $themename, $structure) use ($entries, $user, $themes) {
    foreach ($entries as $entry) {
        if ($entry->userid == $user($username)->id && $entry->themeid == $themes[$themename]->id && $entry->structure === $structure) {
            return $entry;
        }
    }
    throw new coding_exception("Stage introuvable : $username, $structure");
};
$camille = $find('cleroy', 'Animaux de compagnie', 'Clinique vétérinaire du Parc');
$answer($camille, ['Oui, un seul', "Consultations\nChirurgie\nImagerie", 'yes', '5', '12', '2026-11-09', 'Lyon',
    'Exercer en clientèle canine et féline, avec une orientation vers la chirurgie des tissus mous.']);
$thomas = $find('tpetit', 'Animaux de compagnie', 'Clinique vétérinaire des Cèdres');
$answer($thomas, ['Non', "Consultations\nHospitalisation", 'no', '4', '3.5', '2026-08-10', 'Villeurbanne', '']);
$emma = $find('efournier', 'Animaux de compagnie', 'Clinique vétérinaire de la Saône');
$answer($emma, ['Oui, plusieurs', "Chirurgie\nUrgences et gardes", 'yes', '4', '27', '2026-07-01', 'Mâcon',
    'Rejoindre une structure de référé avec service d\'urgences.']);
$lea = $find('lmoreau', 'Équine', 'Clinique équine de Grosbois');
$answer($lea, ['Oui, un seul', "Chirurgie\nImagerie", 'yes', '5', '8', '2026-11-23', 'Boissy-Saint-Léger',
    'Me spécialiser en médecine sportive équine.']);

// Réponses de Léa Moreau aux nouvelles questions de son auto-évaluation (stage en production).
$leaprod = $find('lmoreau', 'Animaux de production', 'Clinique rurale du Pilat');
$newquestions = $DB->get_records_list('stage_question', 'id', $new);
stage_save_answers($leaprod->id, $newquestions, array_combine(array_keys($newquestions),
    ["Examen clinique\nInjections et prélèvements\nChirurgie", '45', 'yes']));

// Deux stages en animaux de compagnie : une convention à demander, une auto-évaluation à faire.
$day = fn($y, $m, $d) => make_timestamp($y, $m, $d, 12);
$manon = $sgen->create_entry($stage, $user('mbonnet')->id, $themes['Animaux de compagnie'], [
    'structure' => 'Clinique vétérinaire Saint-Roch', 'studyyear' => 5, 'datestart' => $day(2027, 1, 11),
    'dateend' => $day(2027, 1, 29), 'declaredduration' => 15, 'conventionstatus' => STAGE_CONVENTION_NONE,
]);
$nathan = $sgen->create_entry($stage, $user('nlambert')->id, $themes['Animaux de compagnie'], [
    'structure' => 'Clinique vétérinaire des Monts d\'Or', 'studyyear' => 5, 'datestart' => $day(2026, 9, 14),
    'dateend' => $day(2026, 10, 2), 'declaredduration' => 15, 'conventionstatus' => STAGE_CONVENTION_SIGNVET,
]);
$answer($nathan, ['Oui, plusieurs', "Consultations\nImagerie\nUrgences et gardes", 'yes', '4', '6', '2026-09-07',
    'Limonest', '']);

echo json_encode([
    'cmid' => (int) $cm->id, 'studentlist' => (int) $studentlist->id, 'requestlist' => (int) $requestlist->id,
    'themes' => array_map(fn($t) => (int) $t->id, $themes),
    'entries' => ['camille' => (int) $camille->id, 'lea_equine' => (int) $lea->id, 'lea_production' => (int) $leaprod->id,
        'manon' => (int) $manon->id, 'nathan' => (int) $nathan->id],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
