<?php
// Site des scénarios « types de question » : une thématique Q dont les quatre listes (étudiant,
// enseignant, maître de stage, demande) contiennent les huit types de question, une thématique R
// (pré-stage) avec questionnaire de demande et check-list des prérequis, des stages signés et
// commencés sur Q (moitié avec gabarit anglais), des stages sans convention sur R.
//
// Usage : php setup.php <étudiants>  ; écrit site-<tag>.json.
define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/home/user/moodle-stress/moodle/config.php');
require_once($CFG->libdir . "/testing/generator/lib.php");
require_once($CFG->libdir . "/testing/classes/util.php");
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stage/tests/generator/lib.php');
\core\session\manager::set_user(get_admin());
$n = (int) ($argv[1] ?? 100);
$nt = 10;
$gen = new testing_data_generator();
$course = $gen->create_course(['fullname' => 'Promotion questions', 'shortname' => 'QST' . time()]);
$stage = $gen->create_module('stage', ['course' => $course->id, 'name' => 'Stages questions']);
$DB->set_field('stage', 'tutorevaluationenabled', 1, ['id' => $stage->id]);
$cmid = $stage->cmid; $stage = $DB->get_record('stage', ['id' => $stage->id]); $stage->cmid = $cmid;
$sg = $gen->get_plugin_generator('mod_stage');
$tq = $sg->create_theme($stage, ['name' => 'Thème Q', 'tutorevaluationenabled' => 1]);
$tr = $sg->create_theme($stage, ['name' => 'Thème R (pré-stage)']);
$tplfr = $sg->create_convention_template($stage, ['name' => 'Gabarit FR', 'lang' => 'fr']);
$tplen = $sg->create_convention_template($stage, ['name' => 'Template EN', 'lang' => 'en']);

// Les huit types, avec des options contenant virgule, apostrophe, guillemets et balise.
$types = [
    ['choice', 'Choix unique', "Oui, souvent\nParfois\nJamais", "Yes, often\nSometimes\nNever"],
    ['checkbox', 'Domaines abordés', "Chirurgie\nMédecine, interne\nImagerie \"IRM\"\n<b>Urgences</b>",
        "Surgery\nMedicine, internal\nImaging \"MRI\"\n<b>Emergency</b>"],
    ['yesno', 'Recommanderiez-vous ce stage ?', null, null],
    ['rating', 'Accueil', null, null],
    ['number', 'Nombre de consultations', null, null],
    ['date', 'Date du premier jour', null, null],
    ['shorttext', 'Ville', null, null],
    ['text', 'Commentaire', null, null],
];
$questions = [];
foreach (['student', 'teacher', 'tutor', 'request'] as $evaltype) {
    $qids = [];
    foreach ($types as $i => [$qtype, $name, $options, $optionsen]) {
        $record = (object) ['stageid' => $stage->id, 'themeid' => 0, 'evaltype' => $evaltype, 'qtype' => $qtype,
            'name' => "$name ($evaltype)", 'options' => $options, 'required' => $qtype === 'text' ? 0 : 1,
            'sortorder' => $i, 'timecreated' => time(), 'timemodified' => time()];
        if ($evaltype === 'tutor') {
            $record->nameen = "EN $name";
            $record->optionsen = $optionsen;
        }
        $qids[] = $DB->insert_record('stage_question', $record);
        $questions[$evaltype][] = ['id' => end($qids), 'qtype' => $qtype, 'options' => $options, 'optionsen' => $optionsen];
    }
    $themes = $evaltype === 'request' ? [$tq, $tr] : [$tq];
    $sg->create_evallist($stage, $evaltype, $qids, $themes, "Liste $evaltype");
}
$sg->create_checklist_item($tr, ['name' => 'Vaccination antirabique', 'description' => 'À jour']);
$sg->create_checklist_item($tr, ['name' => 'Assurance responsabilité civile']);

$tag = $course->id;
$roles = $DB->get_records_menu('role', null, '', 'shortname, id');
$teachers = [];
for ($i = 1; $i <= $nt; $i++) {
    $teachers[$i] = $gen->create_user(['username' => "t{$tag}_$i", 'password' => 'Ens1!xxx', 'email' => "t{$tag}_$i@example.test",
        'firstname' => "Ens$i", 'lastname' => "PROF$i"]);
    $gen->enrol_user($teachers[$i]->id, $course->id, $roles['teacher']);
}
// Responsable de la thématique R : t1 ; t2 ne l'est d'aucune.
stage_set_theme_teachers($tr->id, [$teachers[1]->id]);
for ($i = 1; $i <= 3; $i++) {
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
    $teacher = $teachers[1 + $i % $nt];
    stage_set_student_teachers($stage->id, $u->id, [$teacher->id]);
    $e = $sg->create_entry($stage, $u->id, $tq, ['datestart' => $start, 'dateend' => $end,
        'conventionstatus' => STAGE_CONVENTION_SIGNVET, 'declaredduration' => 8]);
    $lang = $i % 2 ? 'fr' : 'en';
    $DB->set_field('stage_entry', 'conventiontemplateid', $lang === 'fr' ? $tplfr->id : $tplen->id, ['id' => $e->id]);
    $DB->insert_record('stage_convention_detail', (object) ['entryid' => $e->id, 'tutorname' => "MS $i",
        'tutoremail' => "ms{$tag}_$i@example.test", 'timecreated' => time(), 'timemodified' => time()]);
    $e = $DB->get_record('stage_entry', ['id' => $e->id]);
    $url = stage_get_tutor_eval_url($stage, $e);
    $er = $sg->create_entry($stage, $u->id, $tr, ['datestart' => make_timestamp(2027, 1, 4),
        'dateend' => make_timestamp(2027, 1, 15), 'declaredduration' => 10]);
    $entries[] = ['id' => (int) $e->id, 'rid' => (int) $er->id, 'user' => "s{$tag}_$i",
        'teacher' => "t{$tag}_" . (1 + $i % $nt), 'teacherid' => (int) $teacher->id,
        'lang' => $lang, 'token' => $url->get_param('token')];
}
$info = ['courseid' => $course->id, 'stageid' => $stage->id, 'cmid' => $stage->cmid, 'tag' => $tag,
    'themeq' => (int) $tq->id, 'themer' => (int) $tr->id, 'tplfr' => (int) $tplfr->id,
    'questions' => $questions, 'entries' => $entries];
file_put_contents(__DIR__ . "/site-$tag.json", json_encode($info, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "site-$tag.json stage={$stage->id} cm={$stage->cmid} entries=" . count($entries) . "\n";
