<?php
$pdo = new PDO('pgsql:host=127.0.0.1;dbname=stress', 'postgres', 'postgres');
$m0 = memory_get_usage(); $t = hrtime(true);
// Équivalent de get_records_sql() : un objet par ligne, indexé par id.
$rows = [];
foreach ($pdo->query("SELECT e.* FROM mdl_stage_entry e JOIN mdl_user u ON u.id=e.userid WHERE e.stageid=20 ORDER BY e.timecreated DESC, e.id DESC", PDO::FETCH_OBJ) as $r) { $rows[$r->id] = $r; }
printf("stage_get_filtered_entries (promotion de 10 000 saisies) : %d lignes, %.0f ms, %.1f Mo PHP\n", count($rows), (hrtime(true)-$t)/1e6, (memory_get_usage()-$m0)/1048576);
$page = array_slice(array_values($rows), 0, 40);
printf("... pour n'afficher que %d lignes (STAGE_LIST_PERPAGE)\n", count($page));
