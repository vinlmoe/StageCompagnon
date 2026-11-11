<?php
// Données de l'export général (export.php) : complète les stages créés par setup.php pour que
// chaque colonne des trois feuilles porte une valeur, et que les cas limites y figurent.
//
// - Stages Q : réponses aux trois questionnaires (huit types, dont une réponse hors de la forme
//   attendue sur dix), détail de convention complet (date de naissance avant 1970 pour un sur
//   sept), évaluations, étapes datées, deux plages pour un sur cinq, jours travaillés, rapport
//   déposé pour un sur vingt, validation DEVE pour un sur trois, annulation pour un sur
//   vingt-cinq, évaluations textuelles au format StageVet (notes « N/5 ») : auto-évaluation pour
//   un sur dix (dont la moitié sans aucune réponse aux questionnaires), maître de stage pour un
//   sur quinze.
// - Stages R : réponses au questionnaire de demande.
// - Textes commençant par = + - ou @ (protection des formules), retours à la ligne, guillemets.
//
// Usage : php export_data.php site-<tag>.json  ; idempotent sur les réponses.
define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/home/user/moodle-stress/moodle/config.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
$site = json_decode(file_get_contents($argv[1]));
$stage = $DB->get_record('stage', ['id' => $site->stageid], '*', MUST_EXIST);
$context = context_module::instance($site->cmid);
$fs = get_file_storage();
$teachers = array_values(array_unique(array_map(fn($e) => (int) $e->teacherid, $site->entries)));
$deve = (int) $DB->get_field('user', 'id', ['username' => "deve{$site->tag}_1"]);
$now = time();

$answer = function ($qtype, $i) {
    if ($i % 10 === 7) {
        // Réponse hors de la forme attendue (ancienne saisie, import) : rendue telle quelle.
        return ['choice' => 'Autre', 'checkbox' => 'Chirurgie', 'yesno' => 'peut-être', 'rating' => '6',
            'number' => 'environ 12', 'date' => '31/02/2026', 'shorttext' => '=1+1', 'text' => '- RAS'][$qtype];
    }
    switch ($qtype) {
        case 'choice':
            return ['Oui, souvent', 'Parfois', 'Jamais'][$i % 3];
        case 'checkbox':
            return implode("\n", array_slice(['Chirurgie', 'Médecine, interne', 'Imagerie "IRM"', '<b>Urgences</b>'],
                $i % 3, 1 + $i % 3));
        case 'yesno':
            return $i % 4 ? 'yes' : 'no';
        case 'rating':
            return (string) (1 + $i % 5);
        case 'number':
            return $i % 6 ? (string) ($i % 40) : '12.5';
        case 'date':
            return sprintf('2026-%02d-%02d', 1 + $i % 12, 1 + $i % 28);
        case 'shorttext':
            return $i % 9 ? 'Ville ' . ($i % 50) : '@Lyon';
        default:
            return $i % 5 ? "Commentaire $i :\nligne 2 « guillemets » \"doubles\" <i>balise</i>" : '';
    }
};

$transaction = $DB->start_delegated_transaction();
$entryids = [];
foreach ($site->entries as $i => $e) {
    $entryids[] = $e->id;
    $entryids[] = $e->rid;
}
[$insql, $inparams] = $DB->get_in_or_equal($entryids);
$DB->delete_records_select('stage_answer', "entryid $insql", $inparams);
$DB->delete_records_select('stage_entry_period', "entryid $insql", $inparams);
$DB->delete_records_select('stage_entry_workday', "entryid $insql", $inparams);
$fs->delete_area_files($context->id, 'mod_stage', STAGE_REPORT_FILEAREA);

$answers = [];
foreach ($site->entries as $i => $e) {
    // Stages sans aucune réponse, mais dont l'auto-évaluation importée porte des notes : leurs
    // lignes s'intercalent entre celles des autres stages.
    foreach ($i % 20 === 13 ? [] : ['student', 'teacher', 'tutor'] as $evaltype) {
        foreach ($site->questions->$evaltype as $q) {
            $answers[] = (object) ['entryid' => $e->id, 'questionid' => $q->id, 'answertext' => $answer($q->qtype, $i),
                'timecreated' => $now, 'timemodified' => $now];
        }
    }
    foreach ($site->questions->request as $q) {
        $answers[] = (object) ['entryid' => $e->rid, 'questionid' => $q->id, 'answertext' => $answer($q->qtype, $i + 3),
            'timecreated' => $now, 'timemodified' => $now];
    }
    if (count($answers) > 5000) {
        $DB->insert_records('stage_answer', $answers);
        $answers = [];
    }

    $entry = $DB->get_record('stage_entry', ['id' => $e->id]);
    $start = make_timestamp(2026, 1 + $i % 12, 1 + $i % 20, 8, 30);
    $teacher = $teachers[$i % count($teachers)];
    $entry->datestart = $start;
    $entry->dateend = $start + 9 * DAYSECS;
    $entry->structure = $i % 11 ? "Clinique vétérinaire $i" : '=HYPERLINK("http://example.test")';
    $entry->abroad = (int) ($i % 6 === 0);
    $entry->country = $entry->abroad ? 'BE' : '';
    $entry->declaredduration = 5 + $i % 10;
    $entry->retainedduration = $i % 3 ? 5 + $i % 10 : 0;
    $entry->teacherid = $teacher;
    $entry->teachertime = $start + 12 * DAYSECS;
    $entry->teachereval = $i % 4 ? "Bon stage, étudiant impliqué ($i)." : '';
    $entry->tutoreval = $i % 5 ? "Évaluation du maître de stage $i\nAssiduité : très bonne" : '';
    if ($i % 15 === 4) {
        $entry->tutoreval = "Savoir-être\nPonctualité : 4.5/5\nRecommandation : 5 / 5";
    }
    $entry->tutortime = $i % 5 ? $start + 15 * DAYSECS : 0;
    $entry->tutorbypassed = (int) ($i % 13 === 0);
    $entry->tutorrequesttime = $start + 10 * DAYSECS;
    $entry->conventionremindertime = $i % 2 ? $start - 20 * DAYSECS : 0;
    $entry->conventionrequesttime = $start - 30 * DAYSECS;
    $entry->conventionteachervalidatedby = $teacher;
    $entry->conventionteachervalidatetime = $start - 28 * DAYSECS;
    $entry->conventionthemevalidatedby = $teacher;
    $entry->conventionthemevalidatetime = $start - 27 * DAYSECS;
    $entry->conventioneditedby = $deve;
    $entry->conventionedittime = $start - 25 * DAYSECS;
    $entry->conventionsignedby = $deve;
    $entry->conventionsigntime = $start - 20 * DAYSECS;
    if ($i % 17 === 0) {
        $entry->conventionrejectedby = $teacher;
        $entry->conventionrejecttime = $start - 29 * DAYSECS;
        $entry->conventionrejectcomment = "-- Pièce manquante\nmerci de compléter";
    }
    if ($i % 10 === 3) {
        // Évaluation importée de StageVet : texte brut, une note par ligne.
        $entry->studentselfeval = "Accueil\nQualité de l'accueil : 4/5\nIntégration : 3,5 / 5\nEncadrement\n"
            . "Disponibilité : non renseigné\nAvis global : 5/5\nCommentaires :\nTrès bien.";
    } else {
        $entry->studentselfeval = $i % 4 ? "<p>J'ai appris <b>beaucoup</b> ($i).</p><p>Second paragraphe</p>" : '';
    }
    if ($i % 3 === 0) {
        $entry->status = STAGE_STATUS_VALIDE_DEVE;
        $entry->deveuserid = $deve;
        $entry->devetime = $start + 20 * DAYSECS;
        $entry->devecomment = $i % 2 ? 'Validé' : '+ validé après relance';
    }
    if ($i % 25 === 0) {
        $entry->status = STAGE_STATUS_ANNULE;
        $entry->cancelledby = $deve;
        $entry->canceltime = $start + 2 * DAYSECS;
        $entry->cancelcomment = 'Annulé à la demande de la structure';
    }
    $DB->update_record('stage_entry', $entry);

    if ($i % 5 === 0) {
        $DB->insert_record('stage_entry_period', (object) ['entryid' => $e->id, 'datestart' => $start,
            'dateend' => $start + 3 * DAYSECS, 'timecreated' => $now]);
        $DB->insert_record('stage_entry_period', (object) ['entryid' => $e->id, 'datestart' => $start + 6 * DAYSECS,
            'dateend' => $start + 9 * DAYSECS, 'timecreated' => $now]);
    }
    if ($i % 4 === 0) {
        for ($d = 0; $d < 1 + $i % 7; $d++) {
            $DB->insert_record('stage_entry_workday', (object) ['entryid' => $e->id, 'workdate' => $start + $d * DAYSECS,
                'timecreated' => $now]);
        }
    }
    if ($i % 20 === 0) {
        foreach (['rapport.pdf', 'annexe ' . $i . '.pdf'] as $name) {
            $fs->create_file_from_string(['contextid' => $context->id, 'component' => 'mod_stage',
                'filearea' => STAGE_REPORT_FILEAREA, 'itemid' => $e->id, 'filepath' => '/', 'filename' => $name], 'x');
        }
    }

    $detail = $DB->get_record('stage_convention_detail', ['entryid' => $e->id]);
    if ($detail) {
        $detail->yearsituation = ['', 'normal', 'redoublant', 'inconnue'][$i % 4];
        $detail->referentteacherid = $teacher;
        // Naissance avant 1970 pour un sur sept : horodatage négatif.
        $detail->studentbirthdate = $i % 7 ? make_timestamp(2001, 1 + $i % 12, 1 + $i % 28) : make_timestamp(1965, 3, 14);
        $detail->studentaddress = "$i rue des Écoles\n69000 Lyon";
        $detail->studentphone = $i % 2 ? '06 12 34 56 78' : '+33 6 12 34 56 78';
        $detail->hostaddress = "Route de la clinique, $i";
        $detail->hostrepresentative = "Dr Dupont $i";
        $detail->hostrepresentativetitle = 'Gérant';
        $detail->hostservice = $i % 8 ? 'Canine' : '=Canine';
        $detail->hostphone = '04 78 00 00 00';
        $detail->hostemail = $i % 9 ? "clinique$i@example.test" : '@clinique';
        $detail->siret = sprintf('%014d', 12345678900000 + $i);
        $detail->hostlocation = 'Lyon';
        $detail->tutorfunction = 'Vétérinaire';
        $detail->tutorphone = '04 78 11 11 11';
        $detail->nightpresence = $i % 2;
        $detail->sundaypresence = (int) ($i % 3 === 0);
        $detail->holidaypresence = 0;
        $detail->homebased = (int) ($i % 5 === 0);
        $detail->othermodality = $i % 6 ? '' : 'Gardes le samedi';
        $detail->gratificationamount = $i % 4 ? '' : '600,50';
        $detail->hasleave = (int) ($i % 7 === 0);
        $detail->leavedays = $detail->hasleave ? '2' : '';
        $detail->leavemodalities = $detail->hasleave ? 'À poser une semaine avant' : '';
        $DB->update_record('stage_convention_detail', $detail);
    }
}
if ($answers) {
    $DB->insert_records('stage_answer', $answers);
}
$transaction->allow_commit();
printf("%d stages complétés, %d réponses\n", count($site->entries),
    $DB->count_records_select('stage_answer', "entryid $insql", $inparams));
