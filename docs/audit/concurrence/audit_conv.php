<?php
// Audit du test des conventions : suite des événements de chaque stage depuis un identifiant de
// journal, actions concurrentes appliquées toutes les deux, action sur stage annulé, état final.
define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/home/user/moodle-stress/moodle/config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
$site = json_decode(file_get_contents($argv[1]), true);
$since = (int) $argv[2];
$names = [];
foreach (get_defined_constants() as $k => $v) {
    if (strpos($k, 'STAGE_CONVENTION_') === 0 && is_int($v)) {
        $names[$v] = substr($k, 17);
    }
}
$res = ['séquences' => [], 'anomalies' => [], 'statuts finaux' => []];
foreach ($site['entries'] as $se) {
    $e = $DB->get_record('stage_entry', ['id' => $se['id']]);
    $seq = [];
    $cancelled = false;
    $pendingdone = [];
    foreach ($DB->get_records_sql("SELECT id, eventname FROM {logstore_standard_log} WHERE objectid = ? AND id > ?
            AND component = 'mod_stage' AND objecttable = 'stage_entry' ORDER BY id", [$se['id'], $since]) as $l) {
        $ev = substr($l->eventname, strrpos($l->eventname, '\\') + 1);
        $ev = str_replace(['convention_', 'entry_'], ['conv.', ''], $ev);
        if ($cancelled && strpos($ev, 'conv.') === 0) {
            $res['anomalies']["$ev sur un stage annulé"][] = $se['id'];
        }
        if ($ev === 'cancelled') {
            $cancelled = true;
        }
        $seq[] = $ev;
    }
    $key = implode(' → ', $seq);
    $res['séquences'][$key] = ($res['séquences'][$key] ?? 0) + 1;
    if (in_array('conv.teacher_validated', $seq) && count(array_keys($seq, 'conv.rejected')) >= 1
            && array_search('conv.rejected', $seq) < array_search('conv.teacher_validated', $seq)) {
        $res['anomalies']['validation après refus, sans nouvelle demande'][] = $se['id'];
    }
    if (count(array_keys($seq, 'conv.rejected')) > 1) {
        $res['anomalies']['convention refusée deux fois'][] = $se['id'];
    }
    if (in_array('conv.edited', $seq) && array_search('conv.rejected', $seq) !== false
            && array_search('conv.rejected', $seq) > array_search('conv.edited', $seq)) {
        $res['anomalies']['refus après validation DEVE (étudiant déjà prévenu)'][] = $se['id'];
    }
    $st = ($names[(int) $e->conventionstatus] ?? $e->conventionstatus) . ((int) $e->status === STAGE_STATUS_ANNULE ? ' (stage annulé)' : '');
    $res['statuts finaux'][$st] = ($res['statuts finaux'][$st] ?? 0) + 1;
}
arsort($res['séquences']);
foreach ($res['anomalies'] as $k => $ids) {
    $res['anomalies'][$k] = count($ids) . ' : ' . implode(', ', array_slice($ids, 0, 5));
}
echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
