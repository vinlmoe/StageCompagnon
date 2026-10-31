<?php
// Fichiers d'import : CSV entrées, CSV référents, CSV StageVet, XLSX mobilités.
define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/home/user/moodle-stress/moodle/config.php');
require_once($CFG->libdir . '/excellib.class.php');
$site = json_decode(file_get_contents($argv[1]), true);
$tag = $site['tag'];
$dir = __DIR__ . "/files-$tag";
@mkdir($dir);
$themes = $site['themes'];
$n = $site['nstudents'];
$day = fn($i) => sprintf('%02d', 1 + $i % 27);
// Entrées : deux fichiers qui se recoupent pour moitié (lignes 1..N et N/2..1.5N mod N).
foreach (['A' => 0, 'B' => intdiv($n, 2)] as $name => $offset) {
    $csv = "email;theme;structure;datestart;dateend;duration;studyyear\n";
    for ($k = 0; $k < $n; $k++) {
        $i = 1 + ($k + $offset) % $n;
        $t = $themes[$i % count($themes)];
        $csv .= "s{$tag}_$i@example.test;$t;Clinique $i;2026-03-{$day($i)};2026-04-{$day($i)};10;3\n";
    }
    file_put_contents("$dir/entries_$name.csv", $csv);
}
// Référents : deux fichiers contradictoires (enseignant pair / impair).
foreach (['A' => 0, 'B' => 1] as $name => $shift) {
    $csv = "studentemail,teacher1email,teacher2email\n";
    for ($i = 1; $i <= $n; $i++) {
        $t1 = 1 + ($i + $shift) % $site['nteachers'];
        $t2 = 1 + ($i + $shift + 7) % $site['nteachers'];
        $csv .= "s{$tag}_$i@example.test,t{$tag}_$t1@example.test,t{$tag}_$t2@example.test\n";
    }
    file_put_contents("$dir/teachers_$name.csv", $csv);
}
// StageVet : mêmes stages que entries_A (doublons exacts) pour la première moitié, nouveaux
// (autre plage) pour la seconde, avec évaluations.
$h = ['Étudiant', 'Email étudiant', "Année d'étude", 'Thème', 'Organisme', 'Début stage', 'Fin stage',
    'Jours effectifs', 'EVALUATION MAITRE DE STAGE', 'EVALUATION ETUDIANT'];
$csv = implode(';', $h) . "\n";
for ($i = 1; $i <= $n; $i++) {
    $t = $themes[$i % count($themes)];
    $d = $i <= $n / 2 ? ["{$day($i)}/03/2026", "{$day($i)}/04/2026"] : ["{$day($i)}/09/2026", "{$day($i)}/10/2026"];
    $csv .= implode(';', ["NOM$i Prénom$i", "s{$tag}_$i@example.test", '3', $t, "Clinique $i", $d[0], $d[1], '10',
        "\"Très bien; sérieux\nligne 2\"", 'Bon stage']) . "\n";
}
file_put_contents("$dir/stagevet.csv", $csv);
// Mobilités : deux classeurs qui se recoupent totalement (même étudiants, lieux différents).
foreach (['A', 'B'] as $name) {
    $wb = new MoodleExcelWorkbook('-');
    $file = "$dir/mobility_$name.xlsx";
    ob_start();
    $sheet = $wb->add_worksheet('Mobilites');
    foreach (['Email', 'Lieu', 'Validé'] as $c => $v) {
        $sheet->write_string(0, $c, $v);
    }
    for ($i = 1; $i <= $n; $i++) {
        $sheet->write_string($i, 0, "s{$tag}_$i@example.test");
        $sheet->write_string($i, 1, "Université $name $i");
        $sheet->write_string($i, 2, 'oui');
    }
    $wb->close();
    file_put_contents($file, ob_get_clean());
}
echo "ok $dir\n";
