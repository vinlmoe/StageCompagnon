<?php
// Volume : <n> stages supplémentaires sur la thématique R du site, chacun avec ses réponses au
// questionnaire de demande (copie d'un stage et de ses réponses existants), puis chronométrage
// de stage_get_request_answers_export() : durée, requêtes, mémoire.
// Usage : php volume.php site-<tag>.json <n>
define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/home/user/moodle-stress/moodle/config.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
$site = json_decode(file_get_contents($argv[1]));
$n = (int) ($argv[2] ?? 20000);
$model = $DB->get_record('stage_entry', ['id' => $site->entries[0]->rid]);
$answers = array_values($DB->get_records('stage_answer', ['entryid' => $model->id]));
$users = array_map(fn($e) => $DB->get_field('user', 'id', ['username' => $e->user]), $site->entries);
$t = microtime(true);
$transaction = $DB->start_delegated_transaction();
for ($i = 0; $i < $n; $i++) {
    $entry = clone $model;
    unset($entry->id);
    $entry->userid = $users[$i % count($users)];
    $entry->tutortoken = null;
    $entry->datestart = $model->datestart + ($i % 300) * DAYSECS;
    $entry->id = $DB->insert_record('stage_entry', $entry);
    $rows = [];
    foreach ($answers as $answer) {
        $copy = clone $answer;
        unset($copy->id);
        $copy->entryid = $entry->id;
        $rows[] = $copy;
    }
    $DB->insert_records('stage_answer', $rows);
}
$transaction->allow_commit();
printf("insertion : %d stages, %.1f s\n", $n, microtime(true) - $t);
\core\session\manager::set_user(get_admin());
$theme = $DB->get_record('stage_theme', ['id' => $site->themer]);
$t = microtime(true); $q = $DB->perf_get_queries(); $m = memory_get_usage();
$entries = stage_get_filtered_entries($site->stageid, ['themeid' => $theme->id], 'student', 'ASC');
$export = stage_get_request_answers_export($theme, $entries);
printf("export pré-stage (calcul) : %d lignes, %.2f s, %d requêtes, %.0f Mo de pic\n", count($export['rows']),
    microtime(true) - $t, $DB->perf_get_queries() - $q, memory_get_peak_usage() / 1048576);
