<?php
// Site du test multi-acteurs : stages signés, commencés, référents attribués, maîtres de stage
// invités (jeton), thématique A sans questionnaire, thématique B avec questionnaires.
define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/home/user/moodle-stress/moodle/config.php');
require_once($CFG->libdir . "/testing/generator/lib.php");
require_once($CFG->libdir . "/testing/classes/util.php");
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/tests/generator/lib.php');
\core\session\manager::set_user(get_admin());
$n = (int) ($argv[1] ?? 200);
$nt = 20;
$gen = new testing_data_generator();
$course = $gen->create_course(['fullname' => 'Promotion acteurs', 'shortname' => 'ACT' . time()]);
$stage = $gen->create_module('stage', ['course' => $course->id, 'name' => 'Stages acteurs']);
$DB->set_field('stage', 'tutorevaluationenabled', 1, ['id' => $stage->id]);
$cmid = $stage->cmid; $stage = $DB->get_record('stage', ['id' => $stage->id]); $stage->cmid = $cmid;
$sg = $gen->get_plugin_generator('mod_stage');
$ta = $sg->create_theme($stage, ['name' => 'Thème A', 'tutorevaluationenabled' => 1]);
$tb = $sg->create_theme($stage, ['name' => 'Thème B', 'tutorevaluationenabled' => 1]);
foreach (['student', 'teacher', 'tutor'] as $type) {
    $qids = [];
    for ($q = 1; $q <= 3; $q++) {
        $qids[] = $DB->insert_record('stage_question', (object) ['stageid' => $stage->id, 'themeid' => 0,
            'evaltype' => $type, 'qtype' => 'text', 'name' => "Q$q $type", 'required' => 1, 'sortorder' => $q,
            'timecreated' => time(), 'timemodified' => time()]);
    }
    $sg->create_evallist($stage, $type, $qids, [$tb], "Liste $type");
}
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
$start = make_timestamp(2026, 9, 1);
$end = make_timestamp(2026, 9, 10);
$entries = [];
for ($i = 1; $i <= $n; $i++) {
    $u = $gen->create_user(['username' => "s{$tag}_$i", 'password' => 'Etud1!xx', 'email' => "s{$tag}_$i@example.test",
        'firstname' => "Prénom$i", 'lastname' => "NOM$i"]);
    $gen->enrol_user($u->id, $course->id, $roles['student']);
    stage_set_student_teachers($stage->id, $u->id, [$teachers[1 + $i % $nt]->id]);
    foreach ([$ta, $tb] as $theme) {
        $e = $sg->create_entry($stage, $u->id, $theme, ['datestart' => $start, 'dateend' => $end,
            'conventionstatus' => STAGE_CONVENTION_SIGNVET, 'declaredduration' => 8]);
        $DB->insert_record('stage_convention_detail', (object) ['entryid' => $e->id, 'tutorname' => "MS $i",
            'tutoremail' => "ms{$tag}_$i@example.test", 'timecreated' => time(), 'timemodified' => time()]);
        $url = stage_get_tutor_eval_url($stage, $e);
        $entries[] = ['id' => (int) $e->id, 'user' => "s{$tag}_$i", 'teacher' => "t{$tag}_" . (1 + $i % $nt),
            'theme' => $theme->name, 'token' => $url->get_param('token')];
    }
}
$info = ['courseid' => $course->id, 'stageid' => $stage->id, 'cmid' => $stage->cmid, 'tag' => $tag,
    'nstudents' => $n, 'entries' => $entries, 'workdays' => [$start, $start + DAYSECS, $start + 2 * DAYSECS]];
file_put_contents(__DIR__ . "/actors-$tag.json", json_encode($info, JSON_UNESCAPED_UNICODE));
echo "actors-$tag.json stage={$stage->id} cm={$stage->cmid} entries=" . count($entries) . "\n";
