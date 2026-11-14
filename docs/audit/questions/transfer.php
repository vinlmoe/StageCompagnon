<?php
// Transfert entre promotions : une question de même nom mais de type incompatible dans la
// promotion cible ne reçoit pas les réponses (comptées comme abandonnées) ; un type compatible
// (choix -> cases à cocher) les reçoit.
// Usage : php transfer.php
define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/home/user/moodle-stress/moodle/config.php');
require_once($CFG->libdir . "/testing/generator/lib.php");
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/tests/generator/lib.php');
\core\session\manager::set_user(get_admin());
$gen = new testing_data_generator();
$sg = $gen->get_plugin_generator('mod_stage');
$mk = function ($label) use ($gen, $sg, $DB) {
    $course = $gen->create_course(['shortname' => $label . time() . rand()]);
    $stage = $gen->create_module('stage', ['course' => $course->id]);
    $theme = $sg->create_theme($stage, ['name' => 'Canine']);
    return [$course, $stage, $theme];
};
$q = function ($stage, $qtype, $name, $options = null) use ($DB) {
    return (int) $DB->insert_record('stage_question', (object) ['stageid' => $stage->id, 'themeid' => 0,
        'evaltype' => 'student', 'qtype' => $qtype, 'name' => $name, 'options' => $options, 'required' => 0,
        'sortorder' => 0, 'timecreated' => time(), 'timemodified' => time()]);
};
[$c1, $s1, $t1] = $mk('SRC');
[$c2, $s2, $t2] = $mk('DST');
$src = [$q($s1, 'checkbox', 'Domaines', "A\nB"), $q($s1, 'choice', 'Lieu', "Lyon\nParis"), $q($s1, 'text', 'Bilan')];
$dst = [$q($s2, 'choice', 'Domaines', "A\nB"), $q($s2, 'checkbox', 'Lieu', "Lyon\nParis"), $q($s2, 'yesno', 'Bilan')];
$sg->create_evallist($s1, 'student', $src, [$t1]);
$sg->create_evallist($s2, 'student', $dst, [$t2]);
$user = $gen->create_user();
foreach ([$c1, $c2] as $c) {
    $gen->enrol_user($user->id, $c->id, 'student');
}
$entry = $sg->create_entry($s1, $user->id, $t1);
$questions = $DB->get_records_list('stage_question', 'id', $src);
stage_save_answers($entry->id, $questions, [$src[0] => "A\nB", $src[1] => 'Lyon', $src[2] => 'Très bien']);
$plan = stage_plan_student_transfer($DB->get_record('stage', ['id' => $s1->id]), $DB->get_record('stage', ['id' => $s2->id]), $user->id);
$map = $plan->questionmap[$t1->id] ?? [];
$ok = ($map[$src[0]] ?? null) === null && (int) ($map[$src[1]] ?? 0) === $dst[1] && ($map[$src[2]] ?? null) === null
    && $plan->droppedanswers === 2;
echo json_encode(['map' => $map, 'dropped' => $plan->droppedanswers, 'warnings' => $plan->warnings,
    'blockers' => $plan->blockers], JSON_UNESCAPED_UNICODE), "\n";
echo $ok ? "ok    transfert : seuls les types compatibles reçoivent les réponses\n"
    : "ECHEC transfert : correspondances inattendues\n";
exit($ok ? 0 : 1);
