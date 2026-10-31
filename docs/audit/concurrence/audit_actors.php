<?php
// Audit du test multi-acteurs : rejoue, stage par stage, la suite des événements journalisés
// (journal standard, buffersize = 1, donc dans l'ordre des écritures) sur l'automate du circuit,
// signale les transitions que les pages auraient dû refuser, puis compare l'état simulé à l'état
// réellement enregistré (une différence est une mise à jour perdue). Contrôle aussi la cohérence
// des champs (validation sans date, annulation sans auteur...) et les doublons de réponses.
define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/home/user/moodle-stress/moodle/config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
$site = json_decode(file_get_contents($argv[1]), true);
$sinceid = (int) ($argv[2] ?? 0);
$names = [
    STAGE_STATUS_ENREGISTRE => 'enregistré', STAGE_STATUS_EVAL_ETUDIANT => 'éval. étudiant',
    STAGE_STATUS_EVAL_ENSEIGNANT => 'éval. enseignant', STAGE_STATUS_VALIDE_DEVE => 'validé DEVE',
    STAGE_STATUS_NON_VALIDE => 'non validé', STAGE_STATUS_ANNULE => 'annulé',
];
$entries = $DB->get_records('stage_entry', ['stageid' => $site['stageid']]);
$logs = $DB->get_recordset_sql("SELECT id, eventname, objectid, userid, other FROM {logstore_standard_log}
    WHERE component = 'mod_stage' AND objecttable = 'stage_entry' AND id > ? ORDER BY id", [$sinceid]);
$byentry = [];
foreach ($logs as $log) {
    if (isset($entries[$log->objectid])) {
        $byentry[$log->objectid][] = $log;
    }
}
$logs->close();
$violations = [];
$lost = [];
$tutorcount = [];
foreach ($entries as $entry) {
    $state = STAGE_STATUS_ENREGISTRE;
    $tutordone = false;
    foreach ($byentry[$entry->id] ?? [] as $log) {
        $event = substr($log->eventname, strrpos($log->eventname, '\\') + 1);
        $other = $log->other ? (json_decode($log->other, true) ?: @unserialize($log->other) ?: []) : [];
        $before = $state;
        $bad = null;
        switch ($event) {
            case 'entry_student_evaluated':
                $bad = $state !== STAGE_STATUS_ENREGISTRE;
                $state = $state < STAGE_STATUS_EVAL_ETUDIANT ? STAGE_STATUS_EVAL_ETUDIANT : $state;
                break;
            case 'entry_teacher_evaluated':
                $bad = $state !== STAGE_STATUS_EVAL_ETUDIANT;
                $state = $state < STAGE_STATUS_EVAL_ENSEIGNANT ? STAGE_STATUS_EVAL_ENSEIGNANT : $state;
                break;
            case 'entry_rejected':
                $role = $other['role'] ?? '';
                $bad = $role === 'teacher' ? $state !== STAGE_STATUS_EVAL_ETUDIANT : $state === STAGE_STATUS_ANNULE;
                $state = STAGE_STATUS_NON_VALIDE;
                $event .= "($role)";
                break;
            case 'entry_validated':
                $bad = $state === STAGE_STATUS_ANNULE;
                $state = STAGE_STATUS_VALIDE_DEVE;
                break;
            case 'entry_cancelled':
                $bad = $state === STAGE_STATUS_ANNULE;
                $state = STAGE_STATUS_ANNULE;
                break;
            case 'entry_reset':
                $state = STAGE_STATUS_ENREGISTRE;
                break;
            case 'entry_tutor_evaluated':
                $bad = $state === STAGE_STATUS_ANNULE || $tutordone;
                $tutordone = true;
                $tutorcount[$entry->id] = ($tutorcount[$entry->id] ?? 0) + 1;
                break;
            default:
                continue 2;
        }
        if ($bad) {
            $violations[] = "$event alors que le stage était « {$names[$before]} »";
            $where["$event alors que le stage était « {$names[$before]} »"][] = $entry->id;
        }
    }
    if ((int) $entry->status !== $state) {
        $where["perdue"][] = $entry->id;
        $lost[] = "simulé « {$names[$state]} », enregistré « {$names[(int) $entry->status]} »";
    }
}
$fields = [
    'validé sans date DEVE' => $DB->count_records_select('stage_entry', 'stageid = ? AND status = ? AND devetime IS NULL',
        [$site['stageid'], STAGE_STATUS_VALIDE_DEVE]),
    'annulé sans auteur' => $DB->count_records_select('stage_entry', 'stageid = ? AND status = ? AND cancelledby IS NULL',
        [$site['stageid'], STAGE_STATUS_ANNULE]),
    'annulé avec jeton maître de stage' => $DB->count_records_select('stage_entry',
        'stageid = ? AND status = ? AND tutortoken IS NOT NULL', [$site['stageid'], STAGE_STATUS_ANNULE]),
    'non annulé avec date DEVE et statut < validé' => $DB->count_records_select('stage_entry',
        'stageid = ? AND status IN (0, 1, 2) AND devetime IS NOT NULL', [$site['stageid']]),
    'doublons de réponses' => (int) $DB->get_field_sql("SELECT COUNT(*) FROM (SELECT entryid, questionid FROM {stage_answer} a
        JOIN {stage_entry} e ON e.id = a.entryid WHERE e.stageid = ? GROUP BY 1, 2 HAVING COUNT(*) > 1) d", [$site['stageid']]),
    'doublons de jours effectifs' => (int) $DB->get_field_sql("SELECT COUNT(*) FROM (SELECT entryid, workdate
        FROM {stage_entry_workday} w JOIN {stage_entry} e ON e.id = w.entryid WHERE e.stageid = ? GROUP BY 1, 2
        HAVING COUNT(*) > 1) d", [$site['stageid']]),
];
$count = fn($list) => array_count_values($list);
$statuses = [];
foreach ($entries as $entry) {
    $statuses[$names[(int) $entry->status]] = ($statuses[$names[(int) $entry->status]] ?? 0) + 1;
}
echo json_encode([
    'stages' => count($entries),
    'événements' => array_sum(array_map('count', $byentry)),
    'statuts' => $statuses,
    'transitions interdites' => $count($violations),
    'mises à jour perdues' => $count($lost),
    'évaluations maître de stage multiples' => count(array_filter($tutorcount, fn($c) => $c > 1)),
    'cohérence' => $fields,
    'stages concernés' => $where ?? [],
    'dernier log' => (int) $DB->get_field_sql('SELECT MAX(id) FROM {logstore_standard_log}'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
