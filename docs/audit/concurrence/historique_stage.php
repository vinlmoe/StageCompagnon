<?php
// Suite des événements et champs d'un ou plusieurs stages : php historique_stage.php <id log> <stage>...
define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/home/user/moodle-stress/moodle/config.php');
foreach (array_slice($argv, 2) as $id) {
    $e = $DB->get_record('stage_entry', ['id' => $id]);
    echo "== $id status=$e->status devetime=$e->devetime teachertime=$e->teachertime teacherid=$e->teacherid canceltime=$e->canceltime tutortoken=" . ($e->tutortoken ? 'oui' : 'non') . "\n";
    foreach ($DB->get_records_sql("SELECT l.id, l.eventname, l.timecreated, u.username, l.other FROM {logstore_standard_log} l
        LEFT JOIN {user} u ON u.id = l.userid WHERE l.objectid = ? AND l.component = 'mod_stage' AND l.id > ?
        AND l.objecttable = 'stage_entry' ORDER BY l.id", [$id, (int) $argv[1]]) as $l) {
        echo '   ', date('H:i:s', $l->timecreated), ' ', str_pad(substr($l->eventname, 17), 26), ' ', $l->username ?: '(maître de stage)', "\n";
    }
}
