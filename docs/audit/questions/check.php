<?php
// Invariants après une vague : chaque réponse enregistrée a la forme de son type de question
// (stage_answer_is_valid(), langue du maître de stage comprise), pas de doublon de réponse, pas de
// réponse obligatoire vide sur un stage passé à l'étape suivante.
// Usage : php check.php site-<tag>.json
define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/home/user/moodle-stress/moodle/config.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
$site = json_decode(file_get_contents($argv[1]));
$rs = $DB->get_recordset_sql("SELECT a.id, a.entryid, a.answertext, q.id AS qid, q.qtype, q.evaltype, q.options,
        q.optionsen, q.required, e.conventiontemplateid, e.status
      FROM {stage_answer} a JOIN {stage_question} q ON q.id = a.questionid JOIN {stage_entry} e ON e.id = a.entryid
     WHERE e.stageid = ?", [$site->stageid]);
$bad = []; $n = 0; $bytype = [];
$langs = $DB->get_records_menu('stage_convention_template', ['stageid' => $site->stageid], '', 'id, lang');
foreach ($rs as $a) {
    $n++;
    $bytype[$a->qtype] = ($bytype[$a->qtype] ?? 0) + 1;
    $q = (object) ['id' => $a->qid, 'qtype' => $a->qtype, 'options' => $a->options, 'optionsen' => $a->optionsen, 'name' => ''];
    $lang = $a->evaltype === 'tutor' && ($langs[$a->conventiontemplateid] ?? 'fr') === 'en' ? 'en' : 'fr';
    $value = (string) $a->answertext;
    if ($value === '') {
        if ($a->required) {
            $bad[] = "réponse obligatoire vide : stage {$a->entryid}, question {$a->qid}";
        }
        continue;
    }
    if (!stage_answer_is_valid($q, $value, $lang) || stage_normalize_answer($q, $value) !== $value) {
        $bad[] = "forme invalide ({$a->qtype}) : stage {$a->entryid}, question {$a->qid} : " . json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
$rs->close();
$dups = $DB->count_records_sql("SELECT count(*) FROM (SELECT entryid, questionid FROM {stage_answer} GROUP BY 1, 2 HAVING count(*) > 1) d");
echo json_encode(['answers' => $n, 'bytype' => $bytype, 'invalid' => count($bad), 'duplicates' => $dups,
    'examples' => array_slice($bad, 0, 5)], JSON_UNESCAPED_UNICODE), "\n";
exit($bad || $dups ? 1 : 0);
