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
$DB->pdo->rollBack();
