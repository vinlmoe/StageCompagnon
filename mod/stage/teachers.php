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
 * Attribution des enseignants référents aux étudiants (DEVE), directement dans le tableau.
 *
 * Conçue pour un grand nombre d'étudiants et d'enseignants (recherche par nom, affichage
 * paginé, filtre "sans référent") : chaque ligne porte un sélecteur avec recherche, et toute la
 * page s'enregistre en une fois, sans ouvrir une page par étudiant. Une action en masse ajoute,
 * remplace ou retire un enseignant pour les étudiants cochés, ou pour tous ceux que le filtre
 * retient (toutes pages confondues). Pour une attribution depuis un fichier, voir aussi
 * teachers_import.php (import CSV/Excel).
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$id = required_param('id', PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$onlyunassigned = optional_param('onlyunassigned', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:manageteachers', $context);

$baseurl = new moodle_url('/mod/stage/teachers.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('manageteachers', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$allstudents = stage_get_enrolled_students($context);
$teachers = stage_get_potential_teachers($context);
$teacheroptions = [];
foreach ($teachers as $teacher) {
    $teacheroptions[(int) $teacher->id] = fullname($teacher);
}

// Toutes les affectations de l'activité en une requête, regroupées par étudiant.
$assignments = [];
$rawassignments = $DB->get_records('stage_entry_teacher', ['stageid' => $stage->id], '', 'id, studentid, teacherid');
foreach ($rawassignments as $assignment) {
    $assignments[$assignment->studentid][$assignment->teacherid] = true;
}

// Filtre (recherche par nom, étudiants sans référent) appliqué avant la pagination.
$students = $allstudents;
if ($search !== '') {
    $needle = core_text::strtolower($search);
    $students = array_filter($students, function ($student) use ($needle) {
        return core_text::strpos(core_text::strtolower(fullname($student)), $needle) !== false;
    });
}
if ($onlyunassigned) {
    $students = array_filter($students, function ($student) use ($assignments) {
        return empty($assignments[$student->id]);
    });
}
$listurl = new moodle_url($baseurl, ['search' => $search, 'onlyunassigned' => $onlyunassigned]);
$returnurl = new moodle_url($listurl, ['page' => $page]);

// Enregistrement du tableau : les lignes de la page soumise, puis l'éventuelle action en masse.
if (data_submitted() && confirm_sesskey() && !empty($teacheroptions)) {
    $wanted = [];
    // Seuls les étudiants inscrits sont acceptés, et seuls les enseignants du cours : un id
    // arbitraire soumis à la main ne doit ni créer d'affectation, ni donner accès à un étudiant.
    foreach (optional_param_array('rowstudentids', [], PARAM_INT) as $studentid) {
        if (!isset($allstudents[$studentid])) {
            continue;
        }
        $selected = optional_param_array('teachers_' . $studentid, [], PARAM_INT);
        // Une affectation à un enseignant qui n'a plus le rôle n'apparaît pas dans le sélecteur :
        // enregistrer la page ne doit pas la supprimer à l'insu de la DEVE.
        $hidden = array_diff(array_keys($assignments[$studentid] ?? []), array_keys($teacheroptions));
        $wanted[$studentid] = array_values(array_merge(
            array_intersect($selected, array_keys($teacheroptions)),
            $hidden
        ));
    }

    $bulkteacher = optional_param('bulkteacher', 0, PARAM_INT);
    $bulkmode = optional_param('bulkmode', 'add', PARAM_ALPHA);
    $bulkapply = optional_param('bulkapply', '', PARAM_RAW) !== '';
    if ($bulkapply && !isset($teacheroptions[$bulkteacher])) {
        // Rien n'est enregistré : les modifications du tableau auraient été appliquées sans
        // l'action en masse que la DEVE pensait lancer.
        redirect($returnurl, get_string('bulkteachermissing', 'mod_stage'), null, \core\output\notification::NOTIFY_ERROR);
    }
    if ($bulkapply) {
        $targets = optional_param('bulkallfiltered', 0, PARAM_INT)
            ? array_keys($students)
            : optional_param_array('bulkstudentids', [], PARAM_INT);
        foreach ($targets as $studentid) {
            if (!isset($allstudents[$studentid])) {
                continue;
            }
            $current = $wanted[$studentid] ?? array_keys($assignments[$studentid] ?? []);
            if ($bulkmode === 'replace') {
                $current = [$bulkteacher];
            } else if ($bulkmode === 'remove') {
                $current = array_diff($current, [$bulkteacher]);
            } else {
                $current[] = $bulkteacher;
            }
            $wanted[$studentid] = array_values(array_unique(array_map('intval', $current)));
        }
    }

    foreach ($wanted as $studentid => $teacherids) {
        stage_set_student_teachers($stage->id, $studentid, $teacherids);
    }
    // Le filtre "sans référent" peut faire disparaître les étudiants qui viennent d'être pourvus :
    // on revient à la première page plutôt que sur une page désormais vide.
    redirect(
        $onlyunassigned ? $listurl : $returnurl,
        get_string('teachersassigned', 'mod_stage'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

[$pagestudents, $pagingbarhtml] = stage_paginate($students, $page, $listurl);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manageteachers', 'mod_stage'));
echo html_writer::link(new moodle_url('/mod/stage/administration.php', ['id' => $cm->id]), get_string('back'));

echo html_writer::link(
    new moodle_url('/mod/stage/teachers_import.php', ['id' => $cm->id]),
    get_string('importteacherscsv', 'mod_stage'),
    ['class' => 'btn btn-secondary d-block mt-2 mb-3', 'style' => 'width:fit-content']
);

if (empty($allstudents)) {
    echo $OUTPUT->notification(get_string('nostudents', 'mod_stage'), 'info');
} else if (empty($teachers)) {
    echo $OUTPUT->notification(get_string('noteachers', 'mod_stage'), 'info');
} else {
    // Recherche par nom d'étudiant + filtre "sans référent".
    echo html_writer::start_tag('form', ['method' => 'get', 'action' => $baseurl, 'class' => 'form-inline stage-filters mb-3']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
    echo html_writer::empty_tag('input', [
        'type' => 'text', 'name' => 'search', 'value' => s($search),
        'placeholder' => get_string('searchstudent', 'mod_stage'), 'class' => 'form-control mr-2',
    ]);
    echo html_writer::start_tag('label', ['class' => 'mr-2']);
    echo html_writer::checkbox('onlyunassigned', 1, (bool) $onlyunassigned, ' ' . get_string('onlyunassigned', 'mod_stage'));
    echo html_writer::end_tag('label');
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => get_string('search'),
        'class' => 'btn btn-secondary mr-2',
    ]);
    echo html_writer::link($baseurl, get_string('resetfilters', 'mod_stage'), ['class' => 'btn btn-link']);
    echo html_writer::end_tag('form');

    if (empty($students)) {
        echo $OUTPUT->notification(get_string('nostudents', 'mod_stage'), 'info');
    } else {
        $posturl = new moodle_url($listurl, ['page' => $page]);
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $posturl, 'id' => 'stage-teachers-form']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

        // Action en masse : un enseignant à ajouter, à mettre à la place des référents actuels ou
        // à retirer, pour les étudiants cochés ou pour tous ceux que le filtre retient.
        echo html_writer::start_div('card card-body bg-light mb-3');
        echo html_writer::tag('strong', get_string('bulkteacherassign', 'mod_stage'), ['class' => 'mb-2']);
        echo html_writer::start_div('form-inline');
        echo html_writer::select(
            [
                'add' => get_string('bulkteachermode_add', 'mod_stage'),
                'replace' => get_string('bulkteachermode_replace', 'mod_stage'),
                'remove' => get_string('bulkteachermode_remove', 'mod_stage'),
            ],
            'bulkmode',
            'add',
            false,
            ['class' => 'form-control mr-2 mb-1', 'aria-label' => get_string('bulkteacherassign', 'mod_stage')]
        );
        echo html_writer::select(
            $teacheroptions,
            'bulkteacher',
            '',
            ['' => get_string('choosedots')],
            ['class' => 'form-control mr-2 mb-1', 'aria-label' => get_string('bulkteacher', 'mod_stage')]
        );
        // html_writer::checkbox() pose déjà son propre <label> : pas de second label englobant.
        echo html_writer::span(html_writer::checkbox(
            'bulkallfiltered',
            1,
            false,
            ' ' . get_string('bulkallfiltered', 'mod_stage', count($students))
        ), 'mr-2 mb-1');
        echo html_writer::empty_tag('input', [
            'type' => 'submit', 'name' => 'bulkapply', 'value' => get_string('bulkteacherapply', 'mod_stage'),
            'class' => 'btn btn-secondary mb-1',
        ]);
        echo html_writer::end_div();
        echo html_writer::div(get_string('bulkteacherassign_help', 'mod_stage'), 'text-muted small mt-1');
        echo html_writer::end_div();

        $selectall = html_writer::checkbox('', 1, false, '', [
            'id' => 'stage-teachers-selectall', 'title' => get_string('selectall'),
        ]);
        $table = new html_table();
        $table->attributes['class'] = 'generaltable stage-teachers-table';
        $table->head = [
            $selectall,
            get_string('student', 'mod_stage'),
            get_string('currentreferentteachers', 'mod_stage'),
        ];
        $table->colclasses = ['', '', 'w-50'];
        foreach ($pagestudents as $student) {
            $currentids = array_keys($assignments[$student->id] ?? []);
            $options = '';
            foreach ($teacheroptions as $teacherid => $teachername) {
                $attributes = ['value' => $teacherid];
                if (in_array($teacherid, $currentids)) {
                    $attributes['selected'] = 'selected';
                }
                $options .= html_writer::tag('option', s($teachername), $attributes);
            }
            $select = html_writer::tag('select', $options, [
                'id' => 'stage-teachers-' . $student->id,
                'name' => 'teachers_' . $student->id . '[]',
                'multiple' => 'multiple',
                'class' => 'form-control stage-teacher-select',
                'aria-label' => get_string('currentreferentteachers', 'mod_stage') . ' - ' . fullname($student),
            ]);
            $rowid = html_writer::empty_tag('input', [
                'type' => 'hidden', 'name' => 'rowstudentids[]', 'value' => $student->id,
            ]);
            $checkbox = html_writer::checkbox('bulkstudentids[]', $student->id, false, '', [
                'class' => 'stage-teachers-rowcheck',
                'aria-label' => fullname($student),
            ]);
            $name = fullname($student);
            if (empty($currentids)) {
                $name .= ' ' . html_writer::span(get_string('withoutreferent', 'mod_stage'), 'badge badge-warning');
            }
            $table->data[] = [$checkbox . $rowid, $name, $select];
        }
        echo html_writer::table($table);

        echo html_writer::empty_tag('input', [
            'type' => 'submit', 'value' => get_string('savechanges'), 'class' => 'btn btn-primary mt-2',
        ]);
        echo html_writer::end_tag('form');

        echo $pagingbarhtml;

        // Sélecteurs avec recherche (même composant que les formulaires Moodle, qui n'en prend
        // qu'un à la fois) et case "tout cocher".
        foreach ($pagestudents as $student) {
            $PAGE->requires->js_call_amd('core/form-autocomplete', 'enhance', [
                '#stage-teachers-' . $student->id, false, '', get_string('search'), false, true,
                get_string('noreferentteacher', 'mod_stage'),
            ]);
        }
        $js = <<<'JS'
(function() {
    var all = document.getElementById('stage-teachers-selectall');
    if (!all) {
        return;
    }
    all.addEventListener('change', function() {
        document.querySelectorAll('.stage-teachers-rowcheck').forEach(function(box) {
            box.checked = all.checked;
        });
    });
})();
JS;
        echo html_writer::script($js);
    }
}

echo $OUTPUT->footer();
