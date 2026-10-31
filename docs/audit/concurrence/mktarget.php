<?php
define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/home/user/moodle-stress/moodle/config.php');
require_once($CFG->libdir . "/testing/generator/lib.php");
require_once($CFG->libdir . "/testing/classes/util.php");
require_once($CFG->dirroot . '/mod/stage/locallib.php');
\core\session\manager::set_user(get_admin());
$gen = new testing_data_generator();
$src = (int) $argv[1];
foreach (array_keys(stage_get_email_definitions()) as $i => $key) {
    if ($i < 5) { stage_save_email_template($src, $key, "Sujet $key", "Corps $key"); }
}
$course = $gen->create_course(['shortname' => 'CIBLE' . time() . rand(1, 999)]);
$m = $gen->create_module('stage', ['course' => $course->id]);
echo $m->id, "\n";
