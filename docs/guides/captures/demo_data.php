<?php
// Jeu de données de démonstration pour les captures d'écran des guides (voir README.md).
//
// Usage, depuis la racine d'un Moodle fraîchement installé où les deux modules sont déployés :
//   php docs/guides/captures/demo_data.php /chemin/vers/config.php
// Le script est à exécuter une seule fois sur un site vide : il crée cours, comptes, activités et
// stages avec les noms qui figurent dans les guides.
define('CLI_SCRIPT', true);
require($argv[1] ?? (__DIR__ . '/../../../../config.php'));
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/mod/stagesynthesis/lib.php');
require_once($CFG->dirroot . '/mod/stagesynthesis/locallib.php');
require_once($CFG->libdir . '/pdflib.php');

$gen = new testing_data_generator();
$sgen = $gen->get_plugin_generator('mod_stage');
$pwd = 'Demo-2026!';
$day = function (int $y, int $m, int $d): int {
    return make_timestamp($y, $m, $d);
};

// Utilisateurs.
$u = [];
foreach ([
    ['cmartin', 'Claire', 'Martin'], ['pdurand', 'Paul', 'Durand'], ['jbernard', 'Julie', 'Bernard'],
    ['cleroy', 'Camille', 'Leroy'], ['efournier', 'Emma', 'Fournier'], ['hroux', 'Hugo', 'Roux'],
    ['lgirard', 'Louis', 'Girard'], ['lmoreau', 'Léa', 'Moreau'], ['mbonnet', 'Manon', 'Bonnet'],
    ['nlambert', 'Nathan', 'Lambert'], ['tpetit', 'Thomas', 'Petit'],
    ['adubois', 'Arthur', 'Dubois'], ['ilefevre', 'Inès', 'Lefèvre'], ['smercier', 'Sarah', 'Mercier'],
] as [$username, $first, $last]) {
    $u[$username] = $gen->create_user([
        'username' => $username, 'firstname' => $first, 'lastname' => $last,
        'email' => $username . '@env-demo.example.com', 'password' => $pwd, 'lang' => 'fr',
    ]);
}
$DB->set_field('user', 'lang', 'fr', ['username' => 'admin']);

// Cours et activités.
$cat = $gen->create_category(['name' => 'Stages']);
$c2030 = $gen->create_course(['fullname' => 'Stages – Promotion 2030', 'shortname' => 'PROMO-2030', 'category' => $cat->id]);
$c2031 = $gen->create_course(['fullname' => 'Stages – Promotion 2031', 'shortname' => 'PROMO-2031', 'category' => $cat->id]);
$cref = $gen->create_course(['fullname' => 'Suivi des référents de stage', 'shortname' => 'SUIVI-REF', 'category' => $cat->id]);

foreach (['cleroy', 'efournier', 'hroux', 'lgirard', 'lmoreau', 'mbonnet', 'nlambert', 'tpetit'] as $s) {
    $gen->enrol_user($u[$s]->id, $c2030->id, 'student');
}
foreach (['adubois', 'ilefevre', 'smercier'] as $s) {
    $gen->enrol_user($u[$s]->id, $c2031->id, 'student');
}
foreach ([$c2030, $c2031] as $c) {
    $gen->enrol_user($u['cmartin']->id, $c->id, 'editingteacher');
    $gen->enrol_user($u['pdurand']->id, $c->id, 'teacher');
    $gen->enrol_user($u['jbernard']->id, $c->id, 'teacher');
}
$gen->enrol_user($u['cmartin']->id, $cref->id, 'editingteacher');
$gen->enrol_user($u['pdurand']->id, $cref->id, 'teacher');
$gen->enrol_user($u['jbernard']->id, $cref->id, 'teacher');

$intro2030 = 'Suivi des stages de la promotion 2030, de la 1<sup>re</sup> à la 6<sup>e</sup> année.';
$s2030 = $gen->create_module('stage', [
    'course' => $c2030->id, 'name' => 'Stages – Promotion 2030', 'intro' => $intro2030, 'introformat' => FORMAT_HTML,
    'currentstudyyear' => 5, 'tutorevaluationenabled' => 1, 'conventionrequireteachervalidation' => 1,
]);
$s2031 = $gen->create_module('stage', [
    'course' => $c2031->id, 'name' => 'Stages – Promotion 2031',
    'intro' => str_replace('2030', '2031', $intro2030), 'introformat' => FORMAT_HTML,
    'currentstudyyear' => 4, 'tutorevaluationenabled' => 1, 'conventionrequireteachervalidation' => 1,
]);
$cm2030 = get_coursemodule_from_instance('stage', $s2030->id);
$cm2031 = get_coursemodule_from_instance('stage', $s2031->id);
$ctx2030 = context_module::instance($cm2030->id);

foreach ([$s2030, $s2031] as $s) {
    $DB->update_record('stage', (object) [
        'id' => $s->id,
        'establishmentname' => 'École nationale vétérinaire (ENV)',
        'establishmentaddress' => '1 avenue Bourgelat, 69280 Marcy-l\'Étoile',
        'establishmentrepresentative' => 'Pr Hélène Vidal',
        'establishmentrepresentativetitle' => 'Directrice',
        'establishmentphone' => '04 78 87 25 25',
        'establishmentemail' => 'deve@env-demo.example.com',
        'establishmentsignatory' => 'Pr Hélène Vidal, Directrice',
        'requiredabroaddays' => 20, 'abroadbeforeyear' => 5,
        'abroadrule' => 'Au moins 20 jours de stage à l\'étranger avant la fin de la 5e année.',
    ]);
    stage_set_year_requirement($s->id, 5, 60);
    stage_set_year_requirement($s->id, 4, 30);
}

// Thématiques (identiques dans les deux promotions).
$themes = [];
foreach ([$s2030, $s2031] as $s) {
    $t = [];
    $t['libre'] = $sgen->create_theme($s, ['name' => 'Thème libre', 'mandatory' => 0, 'minstudyyear' => 1, 'maxstudyyear' => 6,
        'requiredduration' => 0, 'tutorevaluationenabled' => 1, 'reportmode' => 0, 'sortorder' => 1,
        'description' => 'Stage libre dans toute structure en lien avec la profession.']);
    foreach ([1 => 5, 2 => 5, 3 => 10] as $year => $days) {
        $DB->insert_record('stage_theme_duration', (object) ['themeid' => $t['libre']->id, 'studyyear' => $year,
            'requiredduration' => $days, 'timecreated' => time(), 'timemodified' => time()]);
    }
    $t['compagnie'] = $sgen->create_theme($s, ['name' => 'Animaux de compagnie', 'mandatory' => 1, 'minstudyyear' => 4, 'maxstudyyear' => 5,
        'requiredduration' => 20, 'tutorevaluationenabled' => 1, 'reportmode' => 1, 'sortorder' => 2,
        'description' => 'Clinique canine et féline : consultations, chirurgie de convenance, hospitalisation.']);
    $t['production'] = $sgen->create_theme($s, ['name' => 'Animaux de production', 'mandatory' => 1, 'minstudyyear' => 4, 'maxstudyyear' => 5,
        'requiredduration' => 15, 'tutorevaluationenabled' => 1, 'reportmode' => 0, 'sortorder' => 3,
        'description' => 'Clientèle rurale : bovins, ovins, caprins, porcs.']);
    $t['equine'] = $sgen->create_theme($s, ['name' => 'Équine', 'mandatory' => 1, 'minstudyyear' => 5, 'maxstudyyear' => 5,
        'requiredduration' => 10, 'tutorevaluationenabled' => 1, 'reportmode' => 0, 'sortorder' => 4,
        'description' => 'Clinique ou clientèle équine.']);
    $t['sante'] = $sgen->create_theme($s, ['name' => 'Santé publique vétérinaire', 'mandatory' => 1, 'minstudyyear' => 5, 'maxstudyyear' => 5,
        'requiredduration' => 10, 'tutorevaluationenabled' => 1, 'reportmode' => 0, 'sortorder' => 5,
        'description' => 'Services vétérinaires, abattoirs, laboratoires, hygiène alimentaire.']);
    stage_set_theme_teachers($t['production']->id, [$u['jbernard']->id]);
    foreach (['Réaliser un examen clinique complet', 'Assister à une chirurgie de convenance', 'Participer aux consultations vaccinales'] as $i => $item) {
        $sgen->create_checklist_item($t['compagnie'], ['name' => $item, 'sortorder' => $i + 1]);
    }
    $fs = get_file_storage();
    $themes[$s->id] = $t;

    // Questions et listes d'évaluation.
    $q = function ($evaltype, $qtype, $name, $nameen, $options = '', $optionsen = '', $sort = 1) use ($DB, $s) {
        return $DB->insert_record('stage_question', (object) [
            'stageid' => $s->id, 'themeid' => 0, 'evaltype' => $evaltype, 'qtype' => $qtype, 'name' => $name, 'nameen' => $nameen,
            'options' => $options, 'optionsen' => $optionsen, 'required' => 1, 'sortorder' => $sort,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    };
    $sq = [
        $q('student', 'rating', 'Comment évaluez-vous l\'accueil et l\'encadrement reçus ?', 'How would you rate the welcome and supervision?', '', '', 1),
        $q('student', 'choice', 'Avez-vous pu pratiquer des actes techniques ?', 'Were you able to perform technical procedures?',
            "Oui, régulièrement\nParfois\nNon", "Yes, regularly\nSometimes\nNo", 2),
        $q('student', 'text', 'Qu\'avez-vous appris pendant ce stage ?', 'What did you learn during this internship?', '', '', 3),
    ];
    $tq = [
        $q('tutor', 'rating', 'Ponctualité et assiduité', 'Punctuality and attendance', '', '', 1),
        $q('tutor', 'rating', 'Qualité du travail et initiative', 'Quality of work and initiative', '', '', 2),
        $q('tutor', 'text', 'Appréciation générale', 'Overall assessment', '', '', 3),
    ];
    $eq = [
        $q('teacher', 'rating', 'Avis global', 'Overall opinion', '', '', 1),
        $q('teacher', 'text', 'Commentaire pour la DEVE', 'Comment for the academic office', '', '', 2),
    ];
    $sgen->create_evallist($s, 'student', $sq, [$t['compagnie'], $t['production'], $t['equine'], $t['sante']], 'Auto-évaluation standard');
    $sgen->create_evallist($s, 'tutor', $tq, [$t['compagnie'], $t['production'], $t['equine'], $t['sante']], 'Évaluation par le maître de stage');
    $sgen->create_evallist($s, 'teacher', $eq, [$t['compagnie'], $t['production']], 'Avis de l\'enseignant référent');

    // Gabarits de convention, avec un PDF de deux pages.
    foreach ([['Convention standard (France)', 'fr'], ['Internship agreement (English)', 'en']] as [$name, $lang]) {
        $tid = $DB->insert_record('stage_convention_template', (object) [
            'stageid' => $s->id, 'name' => $name, 'lang' => $lang, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $pdf = new pdf();
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);
        for ($p = 1; $p <= 2; $p++) {
            $pdf->AddPage();
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(0, 10, $name . ' - page ' . $p, 0, 1);
        }
        $fs->create_file_from_string([
            'contextid' => context_module::instance(get_coursemodule_from_instance('stage', $s->id)->id)->id,
            'component' => 'mod_stage', 'filearea' => 'conventiontemplate', 'itemid' => $tid,
            'filepath' => '/', 'filename' => 'gabarit-' . $lang . '.pdf',
        ], $pdf->Output('', 'S'));
    }
    // Un courriel personnalisé.
    stage_save_email_template($s->id, 'conventionready',
        'Votre convention de stage est prête',
        "Bonjour {{student}},\n\nVotre convention pour le stage « {{theme}} » est prête à être téléchargée depuis Moodle.\n\nLa DEVE");
}

// Référents (promotion 2030).
$sgen->assign_teacher($s2030, $u['mbonnet']->id, $u['jbernard']->id);
$sgen->assign_teacher($s2030, $u['lgirard']->id, $u['jbernard']->id);
$sgen->assign_teacher($s2030, $u['cleroy']->id, $u['pdurand']->id);
$sgen->assign_teacher($s2030, $u['hroux']->id, $u['pdurand']->id);
$sgen->assign_teacher($s2030, $u['lmoreau']->id, $u['pdurand']->id);
$sgen->assign_teacher($s2030, $u['tpetit']->id, $u['pdurand']->id);
$sgen->assign_teacher($s2031, $u['adubois']->id, $u['pdurand']->id);
$sgen->assign_teacher($s2031, $u['ilefevre']->id, $u['jbernard']->id);

$t = $themes[$s2030->id];
$tpl = $DB->get_record('stage_convention_template', ['stageid' => $s2030->id, 'lang' => 'fr']);
$entry = function ($student, $theme, $year, $structure, $start, $end, $days, array $more = []) use ($sgen, $s2030, $u, $DB, $day) {
    $e = $sgen->create_entry($s2030, $u[$student]->id, $theme, [
        'structure' => $structure, 'studyyear' => $year,
        'datestart' => $day(...$start), 'dateend' => $day(...$end), 'declaredduration' => $days,
        'conventionstatus' => $more['conventionstatus'] ?? STAGE_CONVENTION_NONE,
        'abroad' => $more['abroad'] ?? 0, 'country' => $more['country'] ?? '',
    ]);
    unset($more['conventionstatus'], $more['abroad'], $more['country']);
    if ($more) {
        $more['id'] = $e->id;
        $DB->update_record('stage_entry', (object) $more);
    }
    return $DB->get_record('stage_entry', ['id' => $e->id]);
};
$detail = function ($entryid, $teacher, array $over = []) use ($DB, $u) {
    stage_save_convention_detail($entryid, (object) ($over + [
        'referentteacherid' => $u[$teacher]->id, 'yearsituation' => 'normal', 'stagetype' => 'obligatoire',
        'studentbirthdate' => make_timestamp(2004, 5, 12), 'studentaddress' => '8 rue des Écoles, 69005 Lyon',
        'studentphone' => '06 12 34 56 78', 'hostaddress' => '14 route de la Plaine, 42410 Pélussin',
        'hostrepresentative' => 'Dr Antoine Morel', 'hostrepresentativetitle' => 'Vétérinaire associé',
        'hostservice' => 'Clinique', 'hostphone' => '04 74 87 10 10', 'hostemail' => 'contact@clinique-demo.example.com',
        'hostlocation' => 'Pélussin', 'tutorname' => 'Dr Antoine Morel', 'tutorfunction' => 'Vétérinaire',
        'tutorphone' => '04 74 87 10 10', 'tutoremail' => 'a.morel@clinique-demo.example.com', 'gratificationamount' => '0',
    ]));
};

$deve = $u['cmartin']->id;
$now = time();
// Manon Bonnet : annulé.
$e = $entry('mbonnet', $t['production'], 5, 'GAEC des Trois Vallons', [2026, 2, 2], [2026, 2, 13], 10,
    ['conventionstatus' => STAGE_CONVENTION_SIGNED]);
stage_cancel_entry($e, $deve, 'Stage interrompu à la demande de la structure d\'accueil.');
// Louis Girard : évalué par l'étudiant.
$e = $entry('lgirard', $t['sante'], 5, 'DDPP du Rhône', [2026, 9, 7], [2026, 9, 18], 10,
    ['conventionstatus' => STAGE_CONVENTION_SIGNED, 'studentselfeval' => 'Participation aux inspections en abattoir et aux contrôles en restauration collective.']);
stage_apply_student_eval($e);
// Hugo Roux : validé DEVE, à l'étranger.
$e = $entry('hroux', $t['production'], 5, 'Veterinary Rural Practice, Galway', [2026, 6, 1], [2026, 6, 26], 20,
    ['conventionstatus' => STAGE_CONVENTION_SIGNED, 'abroad' => 1, 'country' => 'Irlande',
     'studentselfeval' => 'Suivi de troupeaux laitiers et interventions obstétricales.',
     'teacherid' => $u['pdurand']->id, 'teachereval' => 'Très bon stage, rapport complet.', 'teachertime' => $now - 20 * DAYSECS]);
stage_apply_student_eval($e);
stage_apply_teacher_eval($e, $u['pdurand']->id, 'Très bon stage, rapport complet.');
stage_apply_deve_validation($e, $deve, 20, 'Validé.');
// Camille Leroy : thème libre enregistré, sans convention.
$entry('cleroy', $t['libre'], 5, 'Refuge SPA de Brignais', [2026, 12, 14], [2027, 1, 8], 20);
// Camille Leroy : animaux de compagnie, convention éditée.
$e = $entry('cleroy', $t['compagnie'], 5, 'Clinique vétérinaire du Parc', [2026, 11, 16], [2026, 12, 4], 15,
    ['conventionstatus' => STAGE_CONVENTION_REQUESTED, 'conventiontemplateid' => $tpl->id,
     'conventionrequesttime' => $now - 12 * DAYSECS]);
$detail($e->id, 'pdurand', ['hostaddress' => '22 avenue du Parc, 69006 Lyon', 'hostlocation' => 'Lyon',
    'hostrepresentative' => 'Dr Sophie Garnier', 'tutorname' => 'Dr Sophie Garnier', 'tutoremail' => 's.garnier@clinique-demo.example.com']);
stage_convention_mark_edited($e, $deve);
// Thomas Petit : santé publique, convention demandée.
$e = $entry('tpetit', $t['sante'], 5, 'Laboratoire départemental d\'analyses', [2026, 11, 23], [2026, 12, 4], 10,
    ['conventionstatus' => STAGE_CONVENTION_REQUESTED, 'conventiontemplateid' => $tpl->id,
     'conventionrequesttime' => $now - 2 * DAYSECS]);
$detail($e->id, 'pdurand', ['hostaddress' => '1 rue du Laboratoire, 69500 Bron', 'hostlocation' => 'Bron',
    'hostrepresentative' => 'Dr Nadia Benali', 'hostrepresentativetitle' => 'Directrice', 'tutorname' => 'Dr Nadia Benali',
    'hostservice' => 'Microbiologie alimentaire']);
// Thomas Petit : animaux de compagnie, évalué par l'enseignant.
$e = $entry('tpetit', $t['compagnie'], 5, 'Clinique vétérinaire des Cèdres', [2026, 8, 17], [2026, 9, 4], 15,
    ['conventionstatus' => STAGE_CONVENTION_SIGNED, 'studentselfeval' => 'Consultations, chirurgie de convenance et imagerie.']);
stage_apply_student_eval($e);
stage_apply_teacher_eval($e, $u['pdurand']->id, 'Stage conforme aux objectifs, bonne implication.');
// Léa Moreau : équine, demande en attente de l'enseignant référent.
$e = $entry('lmoreau', $t['equine'], 5, 'Clinique équine de Grosbois', [2026, 11, 30], [2026, 12, 11], 10,
    ['conventionstatus' => STAGE_CONVENTION_TEACHERPENDING, 'conventiontemplateid' => $tpl->id,
     'conventionrequesttime' => $now - DAYSECS]);
$detail($e->id, 'pdurand', ['hostaddress' => 'Domaine de Grosbois, 94470 Boissy-Saint-Léger', 'hostlocation' => 'Boissy-Saint-Léger',
    'hostrepresentative' => 'Dr Marc Lefranc', 'tutorname' => 'Dr Marc Lefranc', 'hostservice' => 'Chirurgie équine']);
// Léa Moreau : animaux de compagnie, 4e année, validé (StageVet).
$e = $entry('lmoreau', $t['compagnie'], 4, 'Clinique vétérinaire des Tilleuls', [2025, 7, 7], [2025, 7, 18], 10,
    ['conventionstatus' => STAGE_CONVENTION_SIGNVET, 'studentselfeval' => 'Premier stage en clinique : consultations et soins.']);
stage_apply_student_eval($e);
stage_apply_teacher_eval($e, $u['pdurand']->id, 'Bon premier stage.');
stage_apply_deve_validation($e, $deve, 10, '');
// Léa Moreau : animaux de production, évalué par l'étudiant, maître de stage ayant répondu.
$e = $entry('lmoreau', $t['production'], 5, 'Clinique rurale du Pilat', [2026, 9, 21], [2026, 10, 9], 15,
    ['conventionstatus' => STAGE_CONVENTION_SIGNED]);
$detail($e->id, 'pdurand');
$dates = [];
for ($d = make_timestamp(2026, 9, 21); $d <= make_timestamp(2026, 10, 9); $d += DAYSECS) {
    if ((int) date('N', $d) <= 5) {
        $dates[] = $d;
    }
}
stage_set_entry_workdays($e->id, $dates);
$sq = $DB->get_records('stage_question', ['stageid' => $s2030->id, 'evaltype' => 'student'], 'sortorder');
$answers = [];
foreach ($sq as $qq) {
    $answers[$qq->id] = $qq->qtype === 'rating' ? '4' : ($qq->qtype === 'choice' ? 'Oui, régulièrement' : 'Suivi de troupeaux laitiers, prophylaxie et obstétrique. Stage très formateur.');
}
stage_save_answers($e->id, $sq, $answers);
stage_apply_student_eval($e);
$tq = $DB->get_records('stage_question', ['stageid' => $s2030->id, 'evaltype' => 'tutor'], 'sortorder');
$answers = [];
foreach ($tq as $qq) {
    $answers[$qq->id] = $qq->qtype === 'rating' ? '5' : 'Étudiante sérieuse, autonome et très appréciée de la clientèle.';
}
stage_save_answers($e->id, $tq, $answers);
stage_apply_tutor_eval($e);
$DB->set_field('stage_entry', 'tutorrequesttime', $now - 15 * DAYSECS, ['id' => $e->id]);
// Emma Fournier : un stage enregistré (sera transférée vers 2031).
$entry('efournier', $t['compagnie'], 4, 'Clinique vétérinaire de la Saône', [2026, 7, 6], [2026, 7, 24], 15,
    ['conventionstatus' => STAGE_CONVENTION_SIGNVET]);
// Promotion 2031 : un stage pour Arthur Dubois.
$t31 = $themes[$s2031->id];
$sgen->create_entry($s2031, $u['adubois']->id, $t31['compagnie'], [
    'structure' => 'Clinique vétérinaire du Lac', 'studyyear' => 4,
    'datestart' => $day(2026, 6, 15), 'dateend' => $day(2026, 7, 3), 'declaredduration' => 15,
    'conventionstatus' => STAGE_CONVENTION_SIGNVET,
]);

// Synthèse.
$synth = $gen->create_module('stagesynthesis', [
    'course' => $cref->id, 'name' => 'Suivi de mes étudiants',
    'intro' => 'Vue unique de vos étudiants référés, toutes promotions confondues.', 'introformat' => FORMAT_HTML,
]);
stagesynthesis_set_links($synth->id, [$cm2030->id, $cm2031->id]);
$cmsynth = get_coursemodule_from_instance('stagesynthesis', $synth->id);


// Ajustements du site pour les captures : nom court « ENV », tiroirs fermés, pas de visite guidée,
// sections vides supprimées.
require_once($CFG->dirroot . '/course/lib.php');
$DB->set_field('course', 'shortname', 'ENV', ['id' => SITEID]);
$DB->set_field('course', 'fullname', 'École nationale vétérinaire', ['id' => SITEID]);
set_config('usertours', 0);
set_config('enableusertours', 0);
foreach ($DB->get_records_select('user', 'deleted = 0 AND id > 1') as $user) {
    set_user_preference('drawer-open-index', 0, $user);
    set_user_preference('drawer-open-block', 0, $user);
}
foreach ($DB->get_records_select('course', 'id <> ?', [SITEID]) as $course) {
    foreach ($DB->get_records('course_sections', ['course' => $course->id], 'section DESC') as $section) {
        if ($section->section > 0 && empty($section->sequence)) {
            course_delete_section($course, $section, true);
        }
    }
}

// Exigence de 4e année retirée : le guide montre 60 jours en 5e année.
$DB->delete_records('stage_year_requirement', ['studyyear' => 4]);

// Promotion 2032 : activité vide, cible de l'import de paramétrage (captures 13 et 13c).
$c2032 = $gen->create_course(['fullname' => 'Stages – Promotion 2032', 'shortname' => 'PROMO-2032', 'category' => $cat->id]);
$s2032 = $gen->create_module('stage', ['course' => $c2032->id, 'name' => 'Stages – Promotion 2032',
    'intro' => str_replace('2030', '2032', $intro2030), 'introformat' => FORMAT_HTML, 'currentstudyyear' => 1]);
foreach ($DB->get_records('course_sections', ['course' => $c2032->id], 'section DESC') as $section) {
    if ($section->section > 0 && empty($section->sequence)) {
        course_delete_section($c2032, $section, true);
    }
}
$gen->enrol_user($u['cmartin']->id, $c2032->id, 'editingteacher');

// Camille Leroy : un stage terminé, convention signée, à auto-évaluer (capture 64).
$entry('cleroy', $t['production'], 5, 'Cabinet vétérinaire des Monts du Lyonnais', [2026, 10, 5], [2026, 10, 23], 15,
    ['conventionstatus' => STAGE_CONVENTION_SIGNED]);

// Emma Fournier est inscrite dans la promotion 2031 : le transfert (captures 31 à 33) l'exige.
$gen->enrol_user($u['efournier']->id, $c2031->id, 'student');

// Paul Durand responsable de la thématique « Animaux de compagnie » (capture 05c), et son
// document d'objectifs (captures 05d et 62).
stage_set_theme_teachers($t['compagnie']->id, [$u['pdurand']->id]);
$pdf = new pdf();
$pdf->SetPrintHeader(false);
$pdf->SetPrintFooter(false);
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 11);
$pdf->Write(0, "Objectifs du stage « Animaux de compagnie »\n\nRéaliser un examen clinique complet, assister à une chirurgie"
    . " de convenance, participer aux consultations vaccinales.");
$fs->create_file_from_string([
    'contextid' => $ctx2030->id, 'component' => 'mod_stage', 'filearea' => STAGE_THEME_OBJECTIVE_FILEAREA,
    'itemid' => $t['compagnie']->id, 'filepath' => '/', 'filename' => 'objectifs-animaux-de-compagnie.pdf',
], $pdf->Output('', 'S'));

purge_all_caches();

echo "Site de démonstration prêt.\n";
echo "Modules : stage 2030 = cm {$cm2030->id}, stage 2031 = cm {$cm2031->id}, synthèse = cm {$cmsynth->id},"
    . " stage 2032 = cm " . get_coursemodule_from_instance('stage', $s2032->id)->id . "\n";
echo "Comptes (mot de passe $pwd) : " . implode(', ', array_keys($u)) . "\n";
