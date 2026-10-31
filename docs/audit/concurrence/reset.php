<?php
define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/home/user/moodle-stress/moodle/config.php');
$site = json_decode(file_get_contents($argv[1]), true);
$s = $site['stageid'];
$ids = "SELECT id FROM {stage_entry} WHERE stageid = $s";
foreach (['stage_entry_period', 'stage_convention_detail', 'stage_answer', 'stage_entry_checklist', 'stage_entry_workday'] as $t) {
    $DB->execute("DELETE FROM {{$t}} WHERE entryid IN ($ids)");
}
$DB->delete_records('stage_entry', ['stageid' => $s]);
$DB->delete_records('stage_entry_teacher', ['stageid' => $s]);
$DB->delete_records('stage_study_mobility', ['stageid' => $s]);
$DB->delete_records('task_adhoc', ['component' => 'mod_stage']);
echo "reset\n";
