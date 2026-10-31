<?php
// Équivalence des fonctions corrigées avec leur version d'origine, sur la base de stress.
require __DIR__ . '/boot.php';
require $CFG->dirroot . '/mod/stage/locallib.php';
require __DIR__ . '/old.php';
$ok = 0; $ko = 0;
function check($label, $cond, $detail = '') { global $ok, $ko; if ($cond) { $ok++; } else { $ko++; echo "ÉCHEC : $label $detail\n"; } }
$ids = fn($a) => array_map('intval', array_keys($a));

// 1. Listes paginées en SQL = liste complète découpée en PHP.
$restrict = range(4751, 4790);
foreach ([[], ['search' => 'nom48'], ['themeid' => 1150], ['status' => 3], ['statuslt' => 3, 'status' => 1]] as $filters) {
    foreach (['student', 'theme', 'status', 'duration', 'timecreated'] as $sort) {
        foreach (['ASC', 'DESC'] as $dir) {
            foreach ([null, $restrict] as $r) {
                $all = old_stage_get_filtered_entries(20, $filters, $sort, $dir, $r);
                check("count " . json_encode($filters), stage_count_filtered_entries(20, $filters, $r) === count($all));
                foreach ([0, 3, 1000] as $page) {
                    if ($sort === 'student' && $dir === 'DESC') {
                        // Correction volontaire : le nom est maintenant trié à rebours, lui aussi.
                        $new = array_values(stage_get_filtered_entries(20, $filters, $sort, $dir, $r, $page * 40, 40));
                        $names = array_map(fn($e) => $DB->get_field('user', 'lastname', ['id' => $e->userid]), $new);
                        $sorted = $names; usort($sorted, fn($a, $b) => strcmp($b, $a));
                        check("tri étudiant DESC page $page", $names === $sorted && count($new) === count(array_slice($all, $page * 40, 40)));
                        continue;
                    }
                    $old = array_slice(array_values($all), $page * 40, 40);
                    $new = stage_get_filtered_entries(20, $filters, $sort, $dir, $r, $page * 40, 40);
                    // Tri sur des colonnes à égalités (thématique, statut) : départagé par e.id, comme avant.
                    check("page $page " . json_encode($filters) . " $sort $dir", array_map(fn($e) => (int) $e->id, $old) === $ids($new));
                }
            }
        }
    }
}
foreach (['', 'nom1'] as $search) {
    foreach (['student', 'theme', 'status', 'requested'] as $sort) {
        $all = old_stage_get_convention_entries(20, $search, $sort, 'DESC');
        check('count conventions', stage_count_convention_entries(20, $search) === count($all));
        if ($sort === 'student') {
            continue; // Tri à rebours corrigé, voir plus haut.
        }
        $old = array_slice(array_values($all), 80, 40);
        check("conventions $search $sort", array_map(fn($e) => (int) $e->id, $old) === $ids(stage_get_convention_entries(20, $search, $sort, 'DESC', 80, 40)));
    }
}
// 2. Rapports : présence détectée en SQL = boucle sur chaque saisie.
foreach ([1150, 1151, 1160] as $themeid) {
    $f = ['themeid' => $themeid];
    $expected = false; foreach (old_stage_get_filtered_entries(20, $f) as $e) { if (get_file_storage()->get_area_files(1020, 'mod_stage', 'report', $e->id)) { $expected = true; break; } }
    check("rapports thème $themeid", stage_filtered_entries_have_reports(new fake_context(1020), 20, $f) === $expected);
}
// 3. Jours de création.
foreach ([1, 20] as $sid) { check("jours de création $sid", stage_get_entry_creation_days($sid) == old_stage_get_entry_creation_days($sid)); }
// 4. Chargement par identifiants (paquets + tri PHP) = requête unique.
$someids = $DB->get_fieldset_sql('SELECT id FROM mdl_stage_entry WHERE stageid = 20 AND id % 3 = 0');
$old = old_stage_load_entries_by_id(20, $someids); $new = stage_load_entries_by_id(20, $someids);
check('load_entries_by_id ordre', $ids($old) === $ids($new), count($old) . ' / ' . count($new));
check('load_entries_by_id contenu', array_values(array_map('get_object_vars', $old)) == array_values(array_map('get_object_vars', $new)));
// 5. Doublons probables : stages préchargés = lecture en base.
$fields = 'id, userid, themeid, studyyear, structure, datestart, dateend, status, conventionstatus, studentselfeval, tutoreval, tutortime';
$byuser = []; foreach ($DB->get_records('stage_entry', ['stageid' => 20], 'id ASC', $fields) as $e) { $byuser[$e->userid][$e->id] = $e; }
foreach (range(4751, 4850) as $u) {
    $e = reset($byuser[$u]);
    foreach ([[$e->datestart, $e->dateend], [null, null]] as [$s, $en]) {
        $old = old_stage_find_probable_duplicate_entries(20, $u, $e->themeid, $s, $en, 2);
        $new = stage_find_probable_duplicate_entries(20, $u, $e->themeid, $s, $en, 2, $byuser[$u]);
        check("doublons $u", $ids($old) === $ids($new));
    }
}
// 6. Référents et responsables en une requête = une requête par étudiant / thématique.
$bulk = stage_get_stage_student_teachers(20);
foreach (range(4751, 5000) as $u) { check("référents $u", array_keys($bulk[$u] ?? []) == array_keys(stage_get_student_teachers(20, $u))); }
$bulk = stage_get_stage_theme_teachers(20);
foreach ($DB->get_fieldset_sql('SELECT id FROM mdl_stage_theme WHERE stageid = 20') as $t) { check("responsables $t", array_keys($bulk[$t] ?? []) === array_keys(stage_get_theme_teachers($t))); }
// 7. Plages préchargées = plages lues.
$someentries = $DB->get_records_select('stage_entry', 'stageid = 20 AND id % 7 = 0');
[$insql, $p] = stage_entry_ids_subquery(20, 'x');
$byentry = []; foreach ($DB->get_records_select('stage_entry_period', "entryid $insql", $p, 'entryid ASC, datestart ASC') as $pe) { $byentry[(int) $pe->entryid][] = $pe; }
foreach ($someentries as $e) { check("plages $e->id", array_values(stage_get_or_seed_entry_periods($e)) == stage_get_or_seed_entry_periods($e, $byentry[(int) $e->id] ?? [])); }
// 8. Types de stage par paquets = requête unique (dans la limite de PostgreSQL).
$allids = $DB->get_fieldset_sql('SELECT id FROM mdl_stage_entry WHERE stageid IN (19, 20)');
check('stagetypes', stage_get_entry_stagetypes($allids) == old_stage_get_entry_stagetypes($allids));
// 9. Invitations : sous-ensemble de l'ancienne liste, plafonné.
$old = old_stage_get_entries_needing_tutor_request(); $new = stage_get_entries_needing_tutor_request();
check('invitations sous-ensemble', !array_diff($ids($new), $ids($old)));
$floor = time() - STAGE_TUTOR_REQUEST_MAX_DAYS_AFTER_END * DAYSECS;
check('invitations exclues = stages terminés depuis plus de 60 jours', !array_filter(array_diff_key($old, $new), fn($e) => $e->dateend >= $floor));
printf("Anciennes invitations : %d, nouvelles : %d\n", count($old), count($new));
// 10. Plage trop longue refusée, plage d'un an acceptée.
check('plage 366 j refusée', stage_validate_periods([['datestart' => 1767225600, 'dateend' => 1767225600 + 366 * DAYSECS]]) === 'periodtoolong');
check('plage 365 j acceptée', stage_validate_periods([['datestart' => 1767225600, 'dateend' => 1767225600 + 365 * DAYSECS]]) === null);
check('plage 1 an bissextile acceptée', stage_validate_periods([['datestart' => mktime(0,0,0,1,1,2028), 'dateend' => mktime(0,0,0,12,31,2028)]]) === null);
echo "$ok vérifications réussies, $ko échecs\n";
