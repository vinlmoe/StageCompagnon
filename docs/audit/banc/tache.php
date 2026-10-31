<?php
// Tâche d'invitation des maîtres de stage : une adresse invalide est marquée en échec une fois,
// puis n'est plus jamais reprise automatiquement ; elle n'occupe pas le lot des envois.
namespace core\task {
abstract class scheduled_task {}
}
namespace {
require __DIR__ . '/boot.php';
require $CFG->dirroot . '/mod/stage/locallib.php';
require $CFG->dirroot . '/mod/stage/classes/task/send_tutor_evaluation_requests.php';
$DB->pdo->beginTransaction();
$now = time();
// 300 stages récents éligibles : 10 adresses entourées d'espaces, 240 invalides, 50 valides.
$DB->pdo->exec("UPDATE mdl_stage_entry SET datestart = $now - 86400 * (1 + id % 20), dateend = $now + 86400, status = 0,
    conventionstatus = 3, tutorrequesttime = 0, tutorrequestfailtime = 0, tutortime = NULL, tutorbypassed = 0
    WHERE id IN (SELECT id FROM mdl_stage_entry WHERE stageid = 20 ORDER BY id LIMIT 300)");
$DB->pdo->exec("UPDATE mdl_stage_theme SET tutorevaluationenabled = 1 WHERE stageid = 20");
$DB->pdo->exec("INSERT INTO mdl_stage_convention_detail(entryid, tutoremail, timecreated, timemodified)
    SELECT id, 'tuteur' || id || '@ex.fr', 0, 0 FROM mdl_stage_entry e WHERE stageid = 20
    AND NOT EXISTS (SELECT 1 FROM mdl_stage_convention_detail d WHERE d.entryid = e.id)");
$recent = "SELECT id FROM mdl_stage_entry WHERE stageid = 20 AND datestart > $now - 30 * 86400 ORDER BY datestart, id";
$DB->pdo->exec("UPDATE mdl_stage_convention_detail SET tutoremail = ' tuteur@ex.fr ' WHERE entryid IN ($recent LIMIT 10)");
$DB->pdo->exec("UPDATE mdl_stage_convention_detail SET tutoremail = 'invalide' WHERE entryid IN ($recent OFFSET 10 LIMIT 240)");
printf("Avant le premier passage : %d invitations dues\n", count(stage_get_entries_needing_tutor_request()));
(new \mod_stage\task\send_tutor_evaluation_requests())->execute();
printf("Marquées en échec : %d ; dues au passage suivant : %d\n",
    $DB->count_records_sql('SELECT COUNT(1) FROM {stage_entry} WHERE tutorrequestfailtime > 0'),
    count(stage_get_entries_needing_tutor_request()));
printf("Adresse entourée d'espaces acceptée : %s\n", var_export(stage_tutor_email_valid(' tuteur@ex.fr '), true));
$DB->pdo->rollBack();
}
