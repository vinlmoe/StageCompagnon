<?php
require __DIR__ . '/boot.php';
require $CFG->dirroot . '/mod/stage/locallib.php';
$DB->pdo->beginTransaction();
$e = $DB->get_record('stage_entry', ['id' => 190001]);
$DB->pdo->exec('UPDATE mdl_stage_entry SET status = 1 WHERE id = 190001');
stage_apply_deve_validation($e, 7, 12, 'Très bien');
$r = $DB->get_record('stage_entry', ['id' => 190001]);
echo "validation normale : statut $r->status, durée $r->retainedduration, commentaire « $r->devecomment »\n";
// Annulation enregistrée par un autre utilisateur après la lecture de la saisie par la DEVE.
$e = $DB->get_record('stage_entry', ['id' => 190002]);
$DB->pdo->exec('UPDATE mdl_stage_entry SET status = 1 WHERE id = 190002');
$stale = clone $e; $stale->status = 1;
$DB->pdo->exec('UPDATE mdl_stage_entry SET status = -2 WHERE id = 190002');
try { stage_apply_deve_validation($stale, 7, 12, 'x'); echo "ÉCHEC : validation acceptée\n"; } catch (moodle_exception $ex) { echo "validation d'un stage annulé : refusée (" . $ex->getMessage() . ")\n"; }
try { stage_reject_by_deve($stale, 7, 'motif'); echo "ÉCHEC : rejet accepté\n"; } catch (moodle_exception $ex) { echo "rejet d'un stage annulé : refusé\n"; }
$r = $DB->get_record('stage_entry', ['id' => 190002]);
echo "statut final : $r->status (annulé = -2), commentaire DEVE : « " . ($r->devecomment ?? '') . " »\n";
// Annulation enregistrée entre le contrôle préalable et l'écriture : la condition portée par la
// requête de mise à jour l'emporte, rien n'est écrit.
$stale->deveuserid = 7; $stale->devetime = time(); $stale->retainedduration = 12; $stale->status = 3; $stale->timemodified = time();
$written = stage_update_entry_unless_cancelled($stale, ['deveuserid', 'devetime', 'retainedduration', 'status', 'timemodified']);
$r = $DB->get_record('stage_entry', ['id' => 190002]);
printf("écriture conditionnelle sur un stage annulé : %s, statut %d, valideur %s\n", $written ? 'ÉCRITE' : 'refusée', $r->status, var_export($r->deveuserid, true));
$DB->pdo->rollBack();
