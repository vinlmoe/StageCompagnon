<?php
// Jeu de données du stress test : un cours, une activité, N étudiants, M enseignants, D DEVE.
define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/home/user/moodle-stress/moodle/config.php');
require_once($CFG->libdir . "/testing/generator/lib.php");
require_once($CFG->libdir . "/testing/classes/util.php");
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/tests/generator/lib.php');
\core\session\manager::set_user(get_admin());
$nstudents = (int) ($argv[1] ?? 1500);
$nteachers = (int) ($argv[2] ?? 60);
$ndeve = 6;
$gen = new testing_data_generator();
$t0 = microtime(true);
$course = $gen->create_course(['shortname' => 'PROMO' . time(), 'fullname' => 'Promotion stress']);
$stage = $gen->create_module('stage', ['course' => $course->id, 'name' => 'Stages stress']);
$sg = $gen->get_plugin_generator('mod_stage');
$themes = [];
foreach (['Clinique canine', 'Clinique équine', 'Rurale', 'Industrie', 'Laboratoire', 'Faune sauvage',
          'Santé publique', 'Recherche', 'Clinique début de cursus', 'NAC'] as $i => $name) {
    $themes[] = $sg->create_theme($stage, ['name' => $name, 'sortorder' => $i]);
}
$studentrole = $DB->get_field('role', 'id', ['shortname' => 'student']);
$teacherrole = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
$managerrole = $DB->get_field('role', 'id', ['shortname' => 'manager']);
$tag = $course->id;
for ($i = 1; $i <= $nstudents; $i++) {
    $u = $gen->create_user(['username' => "s{$tag}_$i", 'email' => "s{$tag}_$i@example.test",
        'firstname' => "Prénom$i", 'lastname' => "NOM$i"]);
    $gen->enrol_user($u->id, $course->id, $studentrole);
}
for ($i = 1; $i <= $nteachers; $i++) {
    $u = $gen->create_user(['username' => "t{$tag}_$i", 'email' => "t{$tag}_$i@example.test",
        'firstname' => "Ens$i", 'lastname' => "PROF$i"]);
    $gen->enrol_user($u->id, $course->id, $teacherrole);
}
for ($i = 1; $i <= $ndeve; $i++) {
    $u = $gen->create_user(['username' => "deve{$tag}_$i", 'password' => 'Deve1!xx', 'email' => "deve{$tag}_$i@example.test"]);
    $gen->enrol_user($u->id, $course->id, $managerrole);
    role_assign($managerrole, $u->id, context_system::instance()->id);
}
$info = ['courseid' => $course->id, 'stageid' => $stage->id, 'cmid' => $stage->cmid, 'tag' => $tag,
    'nstudents' => $nstudents, 'nteachers' => $nteachers, 'ndeve' => $ndeve,
    'themes' => array_map(fn($t) => $t->name, $themes)];
file_put_contents(__DIR__ . "/site-$tag.json", json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode($info, JSON_UNESCAPED_UNICODE), "\n", round(microtime(true) - $t0, 1), " s\n";
