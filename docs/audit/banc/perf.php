<?php
// Banc de performance : ancien code contre code corrigé, sur la base de stress.
require __DIR__ . '/boot.php';
require $CFG->dirroot . '/mod/stage/locallib.php';
require __DIR__ . '/old.php';
function bench($label, $fn) {
    global $DB; gc_collect_cycles(); $q = $DB->queries; $m0 = memory_get_usage(); memory_reset_peak_usage(); $t = hrtime(true);
    try { $r = $fn(); $err = ''; } catch (Throwable $e) { $r = null; $err = 'ERREUR : ' . substr($e->getMessage(), 0, 70); }
    printf("%-58s %7d req. %8.0f ms %7.1f Mo %s\n", $label, $DB->queries - $q, (hrtime(true) - $t) / 1e6, (memory_get_peak_usage() - $m0) / 1048576, $err);
    return $r;
}
echo "--- Liste des stages en attente (deve.php), promotion de 10 000 stages, page 1\n";
$f = ['statuslt' => 3];
bench('avant : tout charger puis découper', fn() => array_slice(old_stage_get_filtered_entries(20, $f, 'student', 'ASC'), 0, 40));
bench('après : COUNT + page de 40 en SQL', fn() => [stage_count_filtered_entries(20, $f), stage_get_filtered_entries(20, $f, 'student', 'ASC', null, 0, 40)]);
echo "--- Bouton « télécharger les rapports » (theme_stages.php), thématique sans rapport\n";
$DB->pdo->exec("DELETE FROM mdl_files WHERE itemid IN (SELECT id FROM mdl_stage_entry WHERE themeid = 1141) AND filearea = 'report'");
bench('avant : une lecture de zone par stage', function () { foreach (old_stage_get_filtered_entries(20, ['themeid' => 1141]) as $e) { if (get_file_storage()->get_area_files(1020, 'mod_stage', 'report', $e->id)) { return true; } } return false; });
bench('après : une requête', fn() => stage_filtered_entries_have_reports(new fake_context(1020), 20, ['themeid' => 1141]));
echo "--- Export Excel : plages, référents et responsables de 10 000 stages\n";
$entries = $DB->get_records('stage_entry', ['stageid' => 20], 'timecreated ASC');
bench('avant : par stage, étudiant et thématique', function () use ($entries) {
    $ref = []; foreach ($entries as $e) { if (!isset($ref[$e->userid])) { $ref[$e->userid] = stage_get_student_teachers(20, $e->userid); } stage_get_or_seed_entry_periods($e); }
    foreach (stage_get_themes(20) as $t) { stage_get_theme_teachers($t->id); } });
bench('après : une requête chacun', function () use ($entries) {
    global $DB; [$insql, $p] = stage_entry_ids_subquery(20, 'x'); $by = [];
    foreach ($DB->get_recordset_select('stage_entry_period', "entryid $insql", $p, 'entryid ASC, datestart ASC') as $pe) { $by[(int) $pe->entryid][] = $pe; }
    stage_get_stage_student_teachers(20); stage_get_stage_theme_teachers(20);
    foreach ($entries as $e) { stage_get_or_seed_entry_periods($e, $by[(int) $e->id] ?? []); } });
echo "--- Volet de suivi d'un étudiant (bilans par thématique, année, mobilité, stages complémentaires)\n";
$volet = function () { stage_get_student_progress(20, 4999); stage_get_student_year_progress(20, 4999); stage_get_student_abroad_progress(20, 4999); stage_get_student_complementary_days(20, 4999); stage_get_student_entries(20, 4999); };
bench('avant : sans cache', $volet);
bench('après : cache amorcé pour l\'étudiant', function () use ($volet) { \mod_stage\local\progress_cache::prime(20, [4999]); try { $volet(); } finally { \mod_stage\local\progress_cache::clear(); } });
echo "--- Jours de création (filtre de delete_entries.php)\n";
bench('avant', fn() => old_stage_get_entry_creation_days(20));
bench('après', fn() => stage_get_entry_creation_days(20));
echo "--- Suppression de toute une promotion (réinitialisation du cours), annulée ensuite\n";
$ids19 = $DB->get_fieldset_sql('SELECT id FROM mdl_stage_entry WHERE stageid = 19');
foreach (['avant' => 'old_stage_delete_entries', 'après' => 'stage_delete_entries'] as $l => $fn) {
    $DB->pdo->beginTransaction();
    bench("$l : 10 000 stages et leurs fichiers", fn() => $fn($ids19, new fake_context(1019)));
    $DB->pdo->rollBack();
}
// PostgreSQL refuse plus de 65 535 paramètres : c'est le cas de l'ancienne liste IN au-delà.
$many = array_merge($ids19, range(10000000, 10070000));
foreach (['avant' => 'old_stage_delete_entries', 'après' => 'stage_delete_entries'] as $l => $fn) {
    $DB->pdo->beginTransaction();
    bench("$l : 80 000 identifiants (limite PostgreSQL)", fn() => $fn($many));
    $DB->pdo->rollBack();
}
echo "--- Tâche d'invitation des maîtres de stage (sélection)\n";
bench('avant : toutes les saisies éligibles', fn() => count(old_stage_get_entries_needing_tutor_request()));
bench('après : plafond de 60 jours après la fin', fn() => count(stage_get_entries_needing_tutor_request()));
