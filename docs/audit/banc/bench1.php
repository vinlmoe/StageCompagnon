<?php
require __DIR__ . '/funcs.php';
function t($label, $fn) { $m0 = memory_get_usage(); $t = hrtime(true); $r = $fn(); printf("%-62s %9.1f ms  mem %+7.1f Mo  %s\n", $label, (hrtime(true)-$t)/1e6, (memory_get_peak_usage()-$m0)/1048576, $r); }
// 1. Analyse du texte d'évaluation (import StageVet) : taille et cas pathologiques.
$normal = str_repeat("Savoir-être\nPonctualité : 4/5\nAutonomie : 3,5/5\nCommentaires :\nTrès bon stage, rien à signaler.\n\n", 200);
t('parse_evaluation_text, 1 000 lignes normales', fn() => count(stage_parse_evaluation_text($normal)) . ' blocs');
foreach ([1000, 5000, 20000] as $n) {
    $colons = str_repeat('a : ', $n) . 'x';
    t("parse_evaluation_text, 1 ligne de $n « a : »", fn() => count(stage_parse_evaluation_text($colons)) . ' blocs');
}
foreach ([2000, 10000, 40000] as $n) {
    $titles = implode("\n", array_fill(0, $n, 'Rubrique')) ;
    t("parse_evaluation_text, $n lignes « Rubrique » (titres candidats)", fn() => count(stage_parse_evaluation_text($titles)) . ' blocs');
    $blank = "Rubrique" . str_repeat("\n\nRubrique", $n) . "\nNote : 4/5";
    t("parse_evaluation_text, $n titres séparés de lignes vides", fn() => count(stage_parse_evaluation_text($blank)) . ' blocs');
}
// 2. Jours d'une plage de dates : une plage aberrante (import, saisie erronée).
foreach ([['1 an', 365], ['10 ans', 3650], ['100 ans', 36500]] as [$l, $d]) {
    $p = (object) ['datestart' => mktime(0,0,0,1,1,2026), 'dateend' => mktime(0,0,0,1,1,2026) + $d*86400];
    t("get_period_days, plage de $l", fn() => count(stage_get_period_days($p)) . ' jours');
}
$p = (object) ['datestart' => 0, 'dateend' => mktime(0,0,0,1,1,2026)];
t('get_period_days, datestart = 0 (1970 → 2026)', fn() => count(stage_get_period_days($p)) . ' jours');
// 3. Règle du jour de repos sur une sélection longue.
$p = (object) ['datestart' => mktime(0,0,0,1,1,2026), 'dateend' => mktime(0,0,0,1,1,2036)];
$days = stage_get_period_days($p); $sel = array_values(array_filter($days, fn($d) => date('N', $d) != 7));
t('workdays_violate_restday_rule, 10 ans sans semaine pleine', fn() => var_export(stage_workdays_violate_restday_rule($sel), true));
// 4. Chevauchement de plages.
$ps = []; for ($i = 0; $i < 100000; $i++) { $ps[] = ['datestart' => 1e9 + $i*10*86400, 'dateend' => 1e9 + $i*10*86400 + 5*86400]; } shuffle($ps);
t('validate_periods, 100 000 plages disjointes', fn() => var_export(stage_validate_periods($ps), true));
// 5. Changement d'heure : le jour du passage à l'heure d'hiver doit être compté une seule fois.
$p = (object) ['datestart' => mktime(12,0,0,10,20,2026), 'dateend' => mktime(12,0,0,11,2,2026)];
$d = stage_get_period_days($p); t('get_period_days autour du 25/10/2026 (heure d hiver)', fn() => count($d) . ' jours, uniques ' . count(array_unique($d)));
