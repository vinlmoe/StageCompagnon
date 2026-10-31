<?php
// Site du test des conventions : demandes en attente de l'enseignant référent, deux référents par
// étudiant, stages à venir.
define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/home/user/moodle-stress/moodle/config.php');
require_once($CFG->libdir . "/testing/generator/lib.php");
require_once($CFG->libdir . "/testing/classes/util.php");
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/tests/generator/lib.php');
\core\session\manager::set_user(get_admin());
$n = (int) ($argv[1] ?? 150);
$nt = 20;
$gen = new testing_data_generator();
$course = $gen->create_course(['fullname' => 'Promotion conventions', 'shortname' => 'CONV' . time()]);
$stage = $gen->create_module('stage', ['course' => $course->id, 'name' => 'Stages conventions']);
$DB->set_field('stage', 'conventionrequireteachervalidation', 1, ['id' => $stage->id]);
$sg = $gen->get_plugin_generator('mod_stage');
$theme = $sg->create_theme($stage, ['name' => 'Clinique']);
$template = $sg->create_convention_template($stage);
$tag = $course->id;
$roles = $DB->get_records_menu('role', null, '', 'shortname, id');
$teachers = [];
for ($i = 1; $i <= $nt; $i++) {
    $teachers[$i] = $gen->create_user(['username' => "t{$tag}_$i", 'password' => 'Ens1!xxx', 'email' => "t{$tag}_$i@example.test"]);
    $gen->enrol_user($teachers[$i]->id, $course->id, $roles['editingteacher']);
}
for ($i = 1; $i <= 6; $i++) {
    $u = $gen->create_user(['username' => "deve{$tag}_$i", 'password' => 'Deve1!xx', 'email' => "deve{$tag}_$i@example.test"]);
    $gen->enrol_user($u->id, $course->id, $roles['manager']);
}
$entries = [];
for ($i = 1; $i <= $n; $i++) {
    $u = $gen->create_user(['username' => "s{$tag}_$i", 'password' => 'Etud1!xx', 'email' => "s{$tag}_$i@example.test"]);
    $gen->enrol_user($u->id, $course->id, $roles['student']);
    $t1 = $teachers[1 + $i % $nt];
    $t2 = $teachers[1 + ($i + 7) % $nt];
    stage_set_student_teachers($stage->id, $u->id, [$t1->id, $t2->id]);
    $e = $sg->create_entry($stage, $u->id, $theme, ['datestart' => make_timestamp(2026, 12, 1),
        'dateend' => make_timestamp(2026, 12, 10), 'declaredduration' => 8]);
    $DB->insert_record('stage_convention_detail', (object) ['entryid' => $e->id, 'referentteacherid' => $t1->id,
        'yearsituation' => 'N', 'stagetype' => 'obligatoire', 'studentbirthdate' => make_timestamp(2002, 5, 4),
        'studentaddress' => '1 rue A', 'studentphone' => '0600000000', 'hostaddress' => '2 rue B',
        'hostrepresentative' => 'Dr X', 'hostrepresentativetitle' => 'Gérant', 'hostservice' => 'Clinique',
        'hostphone' => '0100000000', 'hostemail' => "org$i@example.test", 'hostlocation' => 'Lyon',
        'tutorname' => "MS $i", 'tutorfunction' => 'Vétérinaire', 'tutorphone' => '0611111111',
        'tutoremail' => "ms{$tag}_$i@example.test", 'nightpresence' => 0, 'sundaypresence' => 0, 'holidaypresence' => 0,
        'homebased' => 0, 'othermodality' => '', 'hasleave' => 0, 'gratificationamount' => '0',
        'timecreated' => time(), 'timemodified' => time()]);
    stage_request_convention($e, $template->id, true);
    $entries[] = ['id' => (int) $e->id, 'user' => "s{$tag}_$i", 't1' => "t{$tag}_" . (1 + $i % $nt),
        't2' => "t{$tag}_" . (1 + ($i + 7) % $nt)];
}
$info = ['courseid' => $course->id, 'stageid' => $stage->id, 'cmid' => $stage->cmid, 'tag' => $tag, 'entries' => $entries];
file_put_contents(__DIR__ . "/conv-$tag.json", json_encode($info));
echo "conv-$tag.json stage={$stage->id} cm={$stage->cmid}\n";
