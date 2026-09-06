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
 * Affectation des enseignants responsables d'une thématique de stage (DEVE), via la même double
 * liste que teacher_assign.php. Un enseignant responsable de thématique n'évalue pas les stages
 * (c'est le rôle de l'enseignant référent de l'étudiant) : il consulte les stages faits sur sa
 * thématique et les rapports qui y sont déposés (voir theme_stages.php).
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$id = required_param('id', PARAM_INT);
$themeid = required_param('themeid', PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:managethemes', $context);

$theme = $DB->get_record('stage_theme', ['id' => $themeid, 'stageid' => $stage->id], '*', MUST_EXIST);

$returnurl = new moodle_url('/mod/stage/themes.php', ['id' => $cm->id]);
$baseurl = new moodle_url('/mod/stage/theme_teachers.php', ['id' => $cm->id, 'themeid' => $theme->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('themeteachers', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$teachers = stage_get_potential_teachers($context);
$currentids = array_keys(stage_get_theme_teachers($theme->id));

if (data_submitted() && confirm_sesskey()) {
    $selected = optional_param_array('selectedteachers', [], PARAM_INT);
    // Un responsable de thématique doit rester choisi parmi les enseignants du cours : sans ce
    // filtre, un id arbitraire soumis à la main donnerait accès aux rapports de la thématique.
    $selected = array_intersect($selected, array_keys($teachers));
    stage_set_theme_teachers($theme->id, $selected);
    redirect($returnurl, get_string('themeteacherssaved', 'mod_stage'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('themeteachers', 'mod_stage') . ' : ' . format_string($theme->name));
echo html_writer::link($returnurl, get_string('back'));
echo $OUTPUT->notification(get_string('themeteachers_help', 'mod_stage'), 'info');

if (empty($teachers)) {
    echo $OUTPUT->notification(get_string('noteachers', 'mod_stage'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$availableoptions = '';
$selectedoptions = '';
foreach ($teachers as $teacher) {
    $option = html_writer::tag('option', s(fullname($teacher)), ['value' => $teacher->id]);
    if (in_array($teacher->id, $currentids, true)) {
        $selectedoptions .= $option;
    } else {
        $availableoptions .= $option;
    }
}

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl, 'id' => 'stage-teacher-assign-form']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::start_div('row stage-dual-listbox');

echo html_writer::start_div('col-md-5');
echo html_writer::tag('label', get_string('availableteachers', 'mod_stage'), ['for' => 'id_availableteachers']);
echo html_writer::tag('select', $availableoptions, [
    'id' => 'id_availableteachers', 'multiple' => 'multiple', 'size' => 15, 'class' => 'form-control',
]);
echo html_writer::end_div();

echo html_writer::start_div('col-md-2 d-flex flex-column justify-content-center align-items-center');
echo html_writer::tag('button', get_string('addselected', 'mod_stage') . ' »',
    ['type' => 'button', 'id' => 'stage-add-teachers', 'class' => 'btn btn-secondary mb-2 w-100']);
echo html_writer::tag('button', '« ' . get_string('removeselected', 'mod_stage'),
    ['type' => 'button', 'id' => 'stage-remove-teachers', 'class' => 'btn btn-secondary w-100']);
echo html_writer::end_div();

echo html_writer::start_div('col-md-5');
echo html_writer::tag('label', get_string('selectedteachers', 'mod_stage'), ['for' => 'id_selectedteachers']);
echo html_writer::tag('select', $selectedoptions, [
    'id' => 'id_selectedteachers', 'name' => 'selectedteachers[]', 'multiple' => 'multiple', 'size' => 15,
    'class' => 'form-control',
]);
echo html_writer::end_div();

echo html_writer::end_div();

echo html_writer::empty_tag('input', [
    'type' => 'submit', 'value' => get_string('savechanges'), 'class' => 'btn btn-primary mt-3',
]);
echo html_writer::end_tag('form');

// Double liste : déplace les options entre "disponibles" et "sélectionnés" (double-clic ou
// boutons), puis sélectionne toutes les options du second select à la soumission pour qu'elles
// soient bien envoyées (un <select multiple> ne soumet que ses options marquées "selected").
$js = <<<'JS'
(function() {
    var available = document.getElementById('id_availableteachers');
    var selected = document.getElementById('id_selectedteachers');
    var form = document.getElementById('stage-teacher-assign-form');

    function moveSelected(from, to) {
        Array.prototype.slice.call(from.selectedOptions).forEach(function(option) {
            option.selected = false;
            to.appendChild(option);
        });
    }

    document.getElementById('stage-add-teachers').addEventListener('click', function() {
        moveSelected(available, selected);
    });
    document.getElementById('stage-remove-teachers').addEventListener('click', function() {
        moveSelected(selected, available);
    });
    available.addEventListener('dblclick', function(e) {
        if (e.target.tagName === 'OPTION') {
            e.target.selected = false;
            selected.appendChild(e.target);
        }
    });
    selected.addEventListener('dblclick', function(e) {
        if (e.target.tagName === 'OPTION') {
            e.target.selected = false;
            available.appendChild(e.target);
        }
    });
    form.addEventListener('submit', function() {
        Array.prototype.slice.call(selected.options).forEach(function(option) {
            option.selected = true;
        });
    });
})();
JS;
echo html_writer::script($js);

echo $OUTPUT->footer();
