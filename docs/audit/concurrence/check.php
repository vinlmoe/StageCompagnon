<?php
// Invariants après une vague : doublons, stages sans plage, attributions, mobilités, verrous restants.
define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/home/user/moodle-stress/moodle/config.php');
$site = json_decode(file_get_contents($argv[1]), true);
$s = $site['stageid'];
$q = fn($sql) => $DB->get_field_sql($sql, ['s' => $s]);
$r = [
    'entries' => $q("SELECT COUNT(*) FROM {stage_entry} WHERE stageid = :s"),
    'dupentries' => $q("SELECT COUNT(*) FROM (SELECT userid, themeid, COALESCE(datestart,0), COALESCE(dateend,0)
        FROM {stage_entry} WHERE stageid = :s GROUP BY 1,2,3,4 HAVING COUNT(*) > 1) d"),
    'dupextra' => $q("SELECT COALESCE(SUM(c - 1),0) FROM (SELECT COUNT(*) c FROM {stage_entry} WHERE stageid = :s
        GROUP BY userid, themeid, COALESCE(datestart,0), COALESCE(dateend,0) HAVING COUNT(*) > 1) d"),
    'noperiod' => $q("SELECT COUNT(*) FROM {stage_entry} e WHERE stageid = :s AND datestart IS NOT NULL AND datestart > 0
        AND NOT EXISTS (SELECT 1 FROM {stage_entry_period} p WHERE p.entryid = e.id)"),
    'dupperiods' => $q("SELECT COUNT(*) FROM (SELECT p.entryid, p.datestart, p.dateend FROM {stage_entry_period} p
        JOIN {stage_entry} e ON e.id = p.entryid WHERE e.stageid = :s GROUP BY 1,2,3 HAVING COUNT(*) > 1) d"),
    'assignments' => $q("SELECT COUNT(*) FROM {stage_entry_teacher} WHERE stageid = :s"),
    'studentsassigned' => $q("SELECT COUNT(DISTINCT studentid) FROM {stage_entry_teacher} WHERE stageid = :s"),
    'studentsover2' => $q("SELECT COUNT(*) FROM (SELECT studentid FROM {stage_entry_teacher} WHERE stageid = :s
        GROUP BY studentid HAVING COUNT(*) > 2) d"),
    'mobility' => $q("SELECT COUNT(*) FROM {stage_study_mobility} WHERE stageid = :s"),
    'details' => $q("SELECT COUNT(*) FROM {stage_convention_detail} d JOIN {stage_entry} e ON e.id = d.entryid WHERE e.stageid = :s"),
    'orphans' => $DB->get_field_sql("SELECT (SELECT COUNT(*) FROM {stage_convention_detail} d WHERE NOT EXISTS (SELECT 1 FROM {stage_entry} e WHERE e.id = d.entryid))
        + (SELECT COUNT(*) FROM {stage_entry_period} d WHERE NOT EXISTS (SELECT 1 FROM {stage_entry} e WHERE e.id = d.entryid))
        + (SELECT COUNT(*) FROM {stage_answer} d WHERE NOT EXISTS (SELECT 1 FROM {stage_entry} e WHERE e.id = d.entryid))
        + (SELECT COUNT(*) FROM {stage_entry_checklist} d WHERE NOT EXISTS (SELECT 1 FROM {stage_entry} e WHERE e.id = d.entryid))"),
    'adhocqueued' => $DB->count_records('task_adhoc', ['component' => 'mod_stage']),
    'locks' => $DB->get_field_sql("SELECT COUNT(*) FROM pg_locks WHERE locktype = 'advisory'"),
    'idle_in_tx' => $DB->get_field_sql("SELECT COUNT(*) FROM pg_stat_activity WHERE state = 'idle in transaction'"),
];
echo json_encode($r), "\n";
