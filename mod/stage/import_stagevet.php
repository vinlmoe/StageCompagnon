<?php
// This file is part of Moodle - http://moodle.org/
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Import en masse des stages depuis un export CSV de StageVet (conventions déjà générées et
 * signées par StageVet, hors du circuit de gestion de convention de ce plugin), par la DEVE.
 *
 * Colonnes lues par nom d'en-tête (voir $columnmap dans csv_importer::stagevet()), dans l'ordre où
 * StageVet les exporte habituellement : "Nom étudiant", "Prénom étudiant", "Email étudiant"
 * (souvent vides côté StageVet, car issues de la convention PDF : l'étudiant est alors identifié
 * par la colonne "Étudiant" du tableau de bord), "Thème", "Début (convention)",
 * "Fin (convention)", "Durée (convention)", "Organisme (convention)" et l'ensemble des
 * coordonnées de convention (organisme, tuteur, maître de stage, modalités, gratification,
 * congés). Le nom exact d'une thématique déjà créée dans l'activité est requis pour chaque ligne
 * (comme pour import.php), StageVet n'utilisant pas les mêmes intitulés par défaut.
 *
 * L'import se fait en une étape lorsque toutes les lignes se rapprochent d'un étudiant inscrit. Si
 * certaines n'y parviennent pas, elles ne sont jamais ignorées en silence : une seconde étape les
 * liste par libellé, avec leurs numéros de ligne, et la DEVE y désigne l'étudiant inscrit au cours
 * qui leur correspond. Le fichier n'a pas besoin d'être téléversé une seconde fois.
 *
 * Chaque stage importé est enregistré au statut "Enregistré" avec le statut de convention
 * "Signée (SignVet)" (voir STAGE_CONVENTION_SIGNVET) : déjà signé hors de ce plugin, l'
 * auto-évaluation de l'étudiant est immédiatement ouverte. Les informations de convention
 * disponibles dans l'export sont malgré tout enregistrées (stage_convention_detail), à titre de
 * référence consultable, mais ne déclenchent aucune génération de PDF ni gestion de convention
 * dans ce plugin.
 *
 * Les colonnes facultatives « Évaluation par le maître de stage » et « Évaluation par l’étudiant »
 * complètent l'évaluation du stage. Une ligne qui correspond à un stage déjà enregistré (même
 * étudiant, même thématique, plage qui se recoupe) le met à jour au lieu d'en créer un second ; dès
 * que les deux évaluations sont réunies, l'enseignant référent est invité par courriel à évaluer.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:registerstages', $context);

$baseurl = new moodle_url('/mod/stage/import_stagevet.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('importstagevetcsv', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$results = null;
$uploaderror = null;

// Étapes suivantes éventuelles : la DEVE désigne un étudiant inscrit pour chaque libellé que le
// fichier seul n'a pas permis de rapprocher, et tranche les doublons probables. Le CSV de la
// première étape est rejoué sans nouveau téléversement. Les deux arbitrages sont proposés ensemble
// et peuvent être soumis dans n'importe quel ordre : chaque passe reprend toutes les lignes encore
// en attente avec l'ensemble des choix déjà faits, de sorte que trancher l'un ne fasse jamais
// disparaître l'autre.
$resolvestudents = optional_param('resolvestudents', 0, PARAM_INT);
$resolveduplicates = optional_param('resolveduplicates', 0, PARAM_INT);
if (($resolvestudents || $resolveduplicates) && confirm_sesskey()) {
    $pending = $SESSION->stage_stagevet_import[$stage->id] ?? null;

    if (empty($pending['content'])) {
        $uploaderror = get_string('importstagevetexpired', 'mod_stage');
    } else {
        $resolutions = $pending['studentresolutions'] ?? [];
        $decisions = $pending['duplicatedecisions'] ?? [];
        $newchoices = 0;
        if ($resolvestudents) {
            $labels = optional_param_array('resolvelabel', [], PARAM_RAW);
            $userids = optional_param_array('resolveuser', [], PARAM_INT);
            foreach ($labels as $index => $label) {
                $userid = (int) ($userids[$index] ?? 0);
                if ($userid > 0) {
                    $resolutions[$label] = $userid;
                    $newchoices++;
                }
            }
        } else {
            // Lignes qui ressemblaient à un stage déjà enregistré : la DEVE a choisi, pour
            // chacune, de la rattacher à ce stage, de créer un nouveau stage ou de ne pas
            // l'importer.
            foreach (optional_param_array('duplicatedecision', [], PARAM_ALPHANUMEXT) as $line => $decision) {
                if ($decision === 'new' || $decision === 'skip' || ctype_digit((string) $decision)) {
                    $decisions[(int) $line] = $decision;
                    $newchoices++;
                }
            }
        }

        $SESSION->stage_stagevet_import[$stage->id]['studentresolutions'] = $resolutions;
        $SESSION->stage_stagevet_import[$stage->id]['duplicatedecisions'] = $decisions;
        if (!$newchoices) {
            // Aucun choix fait : les arbitrages en attente sont réaffichés tels quels plutôt que
            // de rejouer l'import pour rien.
            $uploaderror = get_string('importstagevetresolvenone', 'mod_stage');
            $results = $pending['results'] ?? null;
        } else {
            $import = \mod_stage\local\csv_importer::stagevet(
                $stage,
                $context,
                $pending['content'],
                $resolutions,
                $decisions,
                $pending['pendinglines'] ?? []
            );
            $results = $import['results'];
            $uploaderror = $import['error'];
        }
    }
} else if (data_submitted() && optional_param('importfile', 0, PARAM_INT) && confirm_sesskey()) {
    unset($SESSION->stage_stagevet_import[$stage->id]);
    $upload = $_FILES['csvfile'] ?? null;

    if (empty($upload) || $upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
        $uploaderror = get_string('importerrorupload', 'mod_stage');
    } else {
        $content = file_get_contents($upload['tmp_name']);
        $import = \mod_stage\local\csv_importer::stagevet($stage, $context, $content);
        $results = $import['results'];
        $uploaderror = $import['error'];
        // Le contenu n'est mis de côté que s'il reste des lignes à arbitrer, et seulement le
        // temps de ces arbitrages.
        $SESSION->stage_stagevet_import[$stage->id] = ['content' => $content];
    }
}

if ($results !== null && !empty($SESSION->stage_stagevet_import[$stage->id]['content'])) {
    $pendinglines = \mod_stage\local\csv_importer::pending_lines($results);
    if ($pendinglines) {
        $SESSION->stage_stagevet_import[$stage->id]['pendinglines'] = $pendinglines;
        $SESSION->stage_stagevet_import[$stage->id]['results'] = (object) [
            'created' => 0,
            'unknownstudents' => $results->unknownstudents,
            'probableduplicates' => $results->probableduplicates,
            'unknownthemes' => [],
            'errors' => [],
        ];
    } else {
        unset($SESSION->stage_stagevet_import[$stage->id]);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importstagevetcsv', 'mod_stage'));
echo html_writer::link(new moodle_url('/mod/stage/register.php', ['id' => $cm->id]), get_string('back'));

if ($uploaderror !== null) {
    echo $OUTPUT->notification($uploaderror, \core\output\notification::NOTIFY_ERROR);
}

if ($results) {
    $summary = [get_string('importresult', 'mod_stage', $results->created)];
    foreach (['updated', 'unchanged', 'evaluations', 'notified', 'notifyskipped'] as $counter) {
        if (!empty($results->$counter)) {
            $summary[] = get_string('importstagevet' . $counter, 'mod_stage', $results->$counter);
        }
    }
    echo $OUTPUT->notification(
        implode(html_writer::empty_tag('br'), $summary),
        \core\output\notification::NOTIFY_SUCCESS
    );
    if (!empty($results->noreferent)) {
        echo $OUTPUT->notification(
            get_string('importstagevetnoreferent', 'mod_stage', s(implode(', ', $results->noreferent))),
            \core\output\notification::NOTIFY_WARNING
        );
    }

    // Rapport groupé : un étudiant ou une thématique manquant sur cent lignes ne doit apparaître
    // qu'une fois, avec la liste des lignes concernées, plutôt que cent messages identiques.
    if (!empty($results->unknownstudents)) {
        echo $OUTPUT->heading(get_string(
            'importstagevetunknownstudentsreport',
            'mod_stage',
            count($results->unknownstudents)
        ), 4);

        $studentoptions = [];
        foreach (stage_get_enrolled_students($context) as $enrolled) {
            $studentoptions[$enrolled->id] = fullname($enrolled) . ' (' . $enrolled->email . ')';
        }
        $replayable = !empty($SESSION->stage_stagevet_import[$stage->id]['content']);

        if (!$studentoptions || !$replayable) {
            // Sans inscrit à proposer, ou une fois le contenu mis de côté expiré, il ne reste
            // qu'à énumérer les lignes concernées pour que la DEVE puisse agir à la source.
            $lines = [];
            foreach ($results->unknownstudents as $name => $linenums) {
                $lines[] = get_string('importstagevetreportline', 'mod_stage', (object) [
                    'value' => $name, 'lines' => implode(', ', $linenums),
                ]);
            }
            echo $OUTPUT->notification(
                implode(html_writer::empty_tag('br'), $lines),
                \core\output\notification::NOTIFY_WARNING
            );
        } else {
            echo $OUTPUT->box(get_string('importstagevetresolve_help', 'mod_stage'), 'generalbox mb-3');
            echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'resolvestudents', 'value' => 1]);

            $resolvetable = new html_table();
            $resolvetable->head = [
                get_string('importstagevetresolvelabel', 'mod_stage'),
                get_string('importstagevetresolvelines', 'mod_stage'),
                get_string('importstagevetresolvestudent', 'mod_stage'),
            ];
            $index = 0;
            foreach ($results->unknownstudents as $name => $linenums) {
                $resolvetable->data[] = [
                    s($name) . html_writer::empty_tag('input', [
                        'type' => 'hidden', 'name' => 'resolvelabel[' . $index . ']', 'value' => $name,
                    ]),
                    s(implode(', ', $linenums)),
                    html_writer::select(
                        $studentoptions,
                        'resolveuser[' . $index . ']',
                        0,
                        ['0' => get_string('importstagevetresolveskip', 'mod_stage')],
                        ['class' => 'form-control']
                    ),
                ];
                $index++;
            }
            echo html_writer::table($resolvetable);
            echo html_writer::tag(
                'button',
                get_string('importstagevetresolveapply', 'mod_stage'),
                ['type' => 'submit', 'class' => 'btn btn-primary']
            );
            echo html_writer::end_tag('form');
        }
    }

    if (!empty($results->probableduplicates) && !empty($SESSION->stage_stagevet_import[$stage->id]['content'])) {
        echo $OUTPUT->heading(get_string(
            'importstagevetduplicatesreport',
            'mod_stage',
            count($results->probableduplicates)
        ), 4);
        echo $OUTPUT->box(get_string('importstagevetduplicates_help', 'mod_stage'), 'generalbox mb-3');
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'resolveduplicates', 'value' => 1]);
        $duptable = new html_table();
        $duptable->head = [
            get_string('importstagevetresolvelines', 'mod_stage'),
            get_string('importstagevetresolvelabel', 'mod_stage'),
            get_string('importstagevetduplicatefile', 'mod_stage'),
            get_string('importstagevetduplicatedecision', 'mod_stage'),
        ];
        $dateformat = get_string('strftimedateshort', 'langconfig');
        foreach ($results->probableduplicates as $line => $duplicate) {
            // Par défaut, le rattachement au premier stage approchant : c'est le plus probable.
            $options = [];
            foreach ($duplicate->candidates as $entryid => $description) {
                $options[$entryid] = get_string('importstagevetduplicateattach', 'mod_stage', $description);
            }
            $options['new'] = get_string('importstagevetduplicatenew', 'mod_stage');
            $options['skip'] = get_string('importstagevetresolveskip', 'mod_stage');
            $duptable->data[] = [
                $line,
                s($duplicate->student),
                s($duplicate->theme) . html_writer::empty_tag('br') . html_writer::span(
                    userdate($duplicate->start, $dateformat) . ' - ' . userdate($duplicate->end, $dateformat),
                    'text-muted'
                ),
                html_writer::select(
                    $options,
                    'duplicatedecision[' . $line . ']',
                    array_key_first($duplicate->candidates),
                    false,
                    ['class' => 'form-control']
                ),
            ];
        }
        echo html_writer::table($duptable);
        echo html_writer::tag(
            'button',
            get_string('importstagevetduplicateapply', 'mod_stage'),
            ['type' => 'submit', 'class' => 'btn btn-primary']
        );
        echo html_writer::end_tag('form');
    }

    if (!empty($results->unknownthemes)) {
        echo $OUTPUT->heading(get_string(
            'importstagevetunknownthemesreport',
            'mod_stage',
            count($results->unknownthemes)
        ), 4);
        $lines = [];
        foreach ($results->unknownthemes as $name => $linenums) {
            $lines[] = get_string('importstagevetreportline', 'mod_stage', (object) [
                'value' => $name, 'lines' => implode(', ', $linenums),
            ]);
        }
        echo $OUTPUT->notification(
            implode(html_writer::empty_tag('br'), $lines),
            \core\output\notification::NOTIFY_WARNING
        );
    }

    if (!empty($results->errors)) {
        echo $OUTPUT->notification(
            implode(html_writer::empty_tag('br'), $results->errors),
            \core\output\notification::NOTIFY_WARNING
        );
    }
}

echo $OUTPUT->box(get_string('importstagevetcsv_help', 'mod_stage'), 'generalbox mb-3');
echo $OUTPUT->box(get_string('importstagevetevalhelp', 'mod_stage'), 'generalbox mb-3');

echo html_writer::start_tag('form', [
    'method' => 'post', 'action' => $baseurl, 'enctype' => 'multipart/form-data',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'importfile', 'value' => 1]);
echo html_writer::empty_tag('input', ['type' => 'file', 'name' => 'csvfile', 'accept' => '.csv', 'required' => 'required']);
echo html_writer::tag(
    'button',
    get_string('import', 'mod_stage'),
    ['type' => 'submit', 'class' => 'btn btn-primary ml-2']
);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
