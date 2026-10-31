<?php
// Un import (ou une procédure) exécuté dans son propre processus ; une ligne JSON en sortie.
define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/home/user/moodle-stress/moodle/config.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
[$self, $sitefile, $scenario] = $argv;
$args = array_slice($argv, 3);
$site = json_decode(file_get_contents($sitefile), true);
$stage = $DB->get_record('stage', ['id' => $site['stageid']], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('stage', $stage->id, 0, false, MUST_EXIST);
$context = context_module::instance($cm->id);
$deve = $DB->get_record('user', ['username' => "deve{$site['tag']}_" . (1 + getmypid() % 6)]);
\core\session\manager::set_user($deve ?: get_admin());
// Démarrage synchronisé : tous les workers attendent la même seconde.
if (!empty(getenv('STARTAT'))) {
    while (microtime(true) < (float) getenv('STARTAT')) {
        usleep(2000);
    }
}
$q0 = $DB->perf_get_queries();
$t0 = microtime(true);
$out = ['scenario' => $scenario, 'args' => $args, 'pid' => getmypid()];
try {
    switch ($scenario) {
        case 'entries':
            $r = \mod_stage\local\csv_importer::entries($stage, $context, file_get_contents($args[0]));
            $out['created'] = $r['results']->created ?? null;
            $out['errors'] = count($r['results']->errors ?? []);
            $out['uploaderror'] = $r['error'];
            break;
        case 'teachers':
            $r = \mod_stage\local\csv_importer::teachers($stage, $context, file_get_contents($args[0]));
            $out['assigned'] = $r['results']->assigned ?? null;
            $out['errors'] = count($r['results']->errors ?? []);
            $out['uploaderror'] = $r['error'];
            break;
        case 'stagevet':
            $r = \mod_stage\local\csv_importer::stagevet($stage, $context, file_get_contents($args[0]));
            $res = $r['results'];
            foreach ((array) $res as $k => $v) {
                $out[$k] = is_array($v) ? count($v) : $v;
            }
            $out['uploaderror'] = $r['error'];
            break;
        case 'mobility':
            $read = \mod_stage\local\study_mobility_importer::read($args[0]);
            $records = $read['records'] ?? $read;
            $r = \mod_stage\local\study_mobility_importer::import($stage, $context, $records, $USER->id);
            $out['validated'] = $r->validated;
            $out['errors'] = count($r->errors);
            break;
        case 'ws':
            // Lot de N stages sur une plage fixe (mêmes lots dans tous les workers : doublons attendus).
            $n = (int) $args[0];
            $themeids = array_keys(stage_get_themes($stage->id));
            $students = array_keys(stage_get_enrolled_students($context));
            $entries = [];
            for ($i = 0; $i < $n; $i++) {
                $entries[] = ['userid' => $students[$i % count($students)], 'themeid' => $themeids[$i % count($themeids)],
                    'structure' => 'WS', 'datestart' => make_timestamp(2026, 1, 5 + (int) ($args[1] ?? 0)), 'dateend' => make_timestamp(2026, 1, 9 + (int) ($args[1] ?? 0)),
                    'declaredduration' => 5, 'studyyear' => 3, 'abroad' => 0, 'country' => ''];
            }
            $r = \mod_stage\external\register_entries::execute($cm->id, $entries);
            $out['created'] = count($r['created'] ?? $r['entryids'] ?? []);
            $out['raw'] = array_map(fn($v) => is_array($v) ? count($v) : $v, $r);
            break;
        case 'bulk':
            $themeids = array_keys(stage_get_themes($stage->id, true));
            $students = array_slice(array_keys(stage_get_enrolled_students($context)), 0, (int) $args[0]);
            $r = stage_bulk_register_entries($stage, $context, $students, (object) [
                'themeid' => $themeids[0], 'studyyear' => 3, 'abroad' => 0, 'country' => '', 'structure' => 'Bulk',
                'datestart' => make_timestamp(2026, 2, 2), 'dateend' => make_timestamp(2026, 2, 6), 'declaredduration' => 5]);
            $out['created'] = $r->created;
            $out['duplicates'] = count($r->duplicates);
            $out['error'] = $r->error;
            break;
        case 'task':
            $class = '\\mod_stage\\task\\' . $args[0];
            ob_start();
            $task = new $class();
            $task->execute();
            $out['output'] = trim(ob_get_clean());
            break;
        case 'adhoc':
            ob_start();
            $n = 0;
            while ($task = \core\task\manager::get_next_adhoc_task(time())) {
                try {
                    $task->execute();
                    \core\task\manager::adhoc_task_complete($task);
                } catch (Throwable $e) {
                    \core\task\manager::adhoc_task_failed($task);
                    $out['taskerrors'][] = get_class($e) . ': ' . $e->getMessage();
                }
                $n++;
            }
            $out['adhoc'] = $n;
            $out['output'] = substr(trim(ob_get_clean()), -300);
            break;
        case 'adminimport':
            $src = $DB->get_record('stage', ['id' => (int) $args[0]], '*', MUST_EXIST);
            $tgt = $DB->get_record('stage', ['id' => (int) $args[1]], '*', MUST_EXIST);
            $srcctx = context_module::instance(get_coursemodule_from_instance('stage', $src->id)->id);
            $tgtctx = context_module::instance(get_coursemodule_from_instance('stage', $tgt->id)->id);
            $r = stage_import_from_stage($src, $srcctx, $tgt, $tgtctx, ['themes' => 1, 'templates' => 1, 'logos' => 1,
                'emails' => 1, 'establishment' => 1]);
            $out['result'] = $r;
            break;
        case 'bulkdelete':
            if (!empty($args[0])) { usleep((int) $args[0] * 1000); }
            $ids = $DB->get_fieldset_select('stage_entry', 'id', 'stageid = ?', [$stage->id]);
            $out['deleted'] = stage_bulk_delete_entries($stage->id, $context, $ids);
            break;
        default:
            throw new coding_exception("scénario inconnu $scenario");
    }
    $out['ok'] = true;
} catch (Throwable $e) {
    $out['ok'] = false;
    $out['exception'] = get_class($e) . ': ' . $e->getMessage();
    if (!empty($e->debuginfo)) {
        $out['debuginfo'] = substr($e->debuginfo, 0, 400);
    }
    $out['where'] = basename($e->getFile()) . ':' . $e->getLine();
}
$out['seconds'] = round(microtime(true) - $t0, 2);
$out['queries'] = $DB->perf_get_queries() - $q0;
$out['memMB'] = round(memory_get_peak_usage() / 1048576, 1);
echo json_encode($out, JSON_UNESCAPED_UNICODE), "\n";
