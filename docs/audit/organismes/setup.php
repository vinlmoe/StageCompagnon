<?php
// Site du banc « organismes proposés » : une promotion dont une thématique propose des organismes
// d'accueil, avec moins de places que d'étudiants (une partie de la promotion restera sans
// affectation), des enseignants référents, quatre comptes DEVE et un gabarit de convention.
//
// Usage : php setup.php <étudiants> [organismes] [vœux à classer]  ; écrit site-<tag>.json.
define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/home/user/moodle/moodle/config.php');
require_once($CFG->libdir . "/testing/generator/lib.php");
require_once($CFG->libdir . "/testing/classes/util.php");
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/tests/generator/lib.php');
\core\session\manager::set_user(get_admin());
$n = (int) ($argv[1] ?? 300);
$noffers = (int) ($argv[2] ?? max(10, intdiv($n, 5)));
$choices = (int) ($argv[3] ?? 5);
$nt = 10;
$gen = new testing_data_generator();
$course = $gen->create_course(['fullname' => 'Promotion organismes', 'shortname' => 'ORG' . time()]);
$stage = $gen->create_module('stage', ['course' => $course->id, 'name' => 'Stages organismes', 'currentstudyyear' => 4]);
$cmid = $stage->cmid;
$sg = $gen->get_plugin_generator('mod_stage');
$theme = $sg->create_theme($stage, ['name' => 'Rurale (organismes proposés)', 'placementenabled' => 1,
    'placementchoices' => $choices, 'placementdeadline' => time() + 30 * DAYSECS, 'placementofflist' => 0]);
$free = $sg->create_theme($stage, ['name' => 'Thème libre']);
$tpl = $sg->create_convention_template($stage, ['name' => 'Gabarit FR', 'lang' => 'fr']);

// Places : 85 % de l'effectif, réparties inégalement (un organisme sur trois n'a qu'une place).
$places = array_fill(0, $noffers, 1);
$left = max(0, (int) round($n * 0.85) - $noffers);
for ($i = 0; $left > 0; $i = ($i + 1) % $noffers) {
    if ($i % 3 !== 0 || $noffers < 3) {
        $places[$i]++;
        $left--;
    }
}
$offers = [];
for ($i = 1; $i <= $noffers; $i++) {
    $offer = $sg->create_placement_offer($theme, [
        'name' => sprintf('Organisme %03d', $i), 'places' => $places[$i - 1],
        'address' => "$i route des Crêtes, 69000 Lyon", 'representative' => "Mme Gérante $i",
        'representativetitle' => 'Gérante', 'service' => 'Élevage', 'phone' => '0478000000',
        'email' => "org$i@example.test", 'tutorname' => "Dr Tuteur $i", 'tutorfunction' => 'Vétérinaire',
        'tutorphone' => '0478000001', 'tutoremail' => "tuteur$i@example.test",
        'description' => "Accueil de mars à juin, organisme $i.",
    ]);
    $offers[] = ['id' => (int) $offer->id, 'places' => (int) $offer->places];
}

$tag = $course->id;
$roles = $DB->get_records_menu('role', null, '', 'shortname, id');
$teachers = [];
for ($i = 1; $i <= $nt; $i++) {
    $teachers[$i] = $gen->create_user(['username' => "ot{$tag}_$i", 'password' => 'Ens1!xxx',
        'email' => "ot{$tag}_$i@example.test", 'firstname' => "Ens$i", 'lastname' => "PROF$i"]);
    $gen->enrol_user($teachers[$i]->id, $course->id, $roles['teacher']);
}
for ($i = 1; $i <= 4; $i++) {
    $u = $gen->create_user(['username' => "odeve{$tag}_$i", 'password' => 'Deve1!xx', 'email' => "odeve{$tag}_$i@example.test"]);
    $gen->enrol_user($u->id, $course->id, $roles['manager']);
}
$students = [];
for ($i = 1; $i <= $n; $i++) {
    $u = $gen->create_user(['username' => "os{$tag}_$i", 'password' => 'Etud1!xx', 'email' => "os{$tag}_$i@example.test",
        'firstname' => "Prénom$i", 'lastname' => "NOM$i"]);
    $gen->enrol_user($u->id, $course->id, $roles['student']);
    $teacher = $teachers[1 + $i % $nt];
    stage_set_student_teachers($stage->id, $u->id, [$teacher->id]);
    $students[] = ['id' => (int) $u->id, 'user' => "os{$tag}_$i", 'teacherid' => (int) $teacher->id];
}
$info = ['courseid' => (int) $course->id, 'stageid' => (int) $stage->id, 'cmid' => (int) $cmid, 'tag' => (int) $tag,
    'theme' => (int) $theme->id, 'themefree' => (int) $free->id, 'tplfr' => (int) $tpl->id, 'choices' => $choices,
    'offers' => $offers, 'students' => $students];
file_put_contents(__DIR__ . "/site-$tag.json", json_encode($info, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "site-$tag.json cm=$cmid thématique={$theme->id} étudiants=$n organismes=$noffers places=" . array_sum($places) . "\n";
