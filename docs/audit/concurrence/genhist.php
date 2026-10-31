<?php
define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/home/user/moodle-stress/moodle/config.php');
require_once($CFG->libdir . '/excellib.class.php');
$site = json_decode(file_get_contents($argv[1]), true);
$tag = $site['tag'];
$dir = __DIR__ . "/files-$tag";
@mkdir($dir);
$wb = new MoodleExcelWorkbook('-');
ob_start();
$sh = $wb->add_worksheet('Stages - validation ER');
$blocks = ['Clinique canine', 'Rurale', 'Laboratoire'];
foreach (['NOM', 'Prénom', 'Email', 'NOM', 'Prénom'] as $c => $v) {
    $sh->write_string(1, $c, $v);
}
foreach ($blocks as $b => $theme) {
    $c = 5 + 4 * $b;
    $sh->write_string(0, $c, "$theme\n5 jours");
    foreach (['Lieu et date', 'Validation par l’ER', 'Durée (en jours)', 'Année d’études'] as $k => $v) {
        $sh->write_string(1, $c + $k, $v);
    }
}
for ($i = 1; $i <= $site['nstudents']; $i++) {
    $r = $i + 1;
    $sh->write_string($r, 0, "NOM$i");
    $sh->write_string($r, 1, "Prénom$i");
    $sh->write_string($r, 2, "s{$tag}_$i@example.test");
    foreach ($blocks as $b => $theme) {
        $c = 5 + 4 * $b;
        $d = 1 + $b * 5;
        $sh->write_string($r, $c, sprintf('%02d au %02d/0%d/2023 Clinique %d', $d, $d + 4, 4 + $b, $i));
        $sh->write_number($r, $c + 1, 1);
        $sh->write_number($r, $c + 2, 5);
        $sh->write_number($r, $c + 3, 2);
    }
}
$wb->close();
file_put_contents("$dir/historical.xlsx", ob_get_clean());
echo "ok\n";
