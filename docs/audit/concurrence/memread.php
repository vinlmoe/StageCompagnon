<?php
define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/home/user/moodle-stress/moodle/config.php');
raise_memory_limit('4G'); ini_set('memory_limit', '4G');
$t = microtime(true);
$cls = '\\mod_stage\\local\\' . $argv[1];
$r = $cls::read($argv[2]);
echo $argv[1], ' ', basename($argv[2]), ' rows=', count($r['records'] ?? $r), ' peak=', round(memory_get_peak_usage() / 1048576), 'MB ', round(microtime(true) - $t, 1), "s\n";
