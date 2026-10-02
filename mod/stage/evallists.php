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
 * Listes d'évaluation de l'activité (DEVE) : vue d'ensemble, création et suppression. Chaque
 * liste s'édite sur sa propre page (evallist_edit.php) ; chaque thématique choisit, pour chacun de
 * ses formulaires d'évaluation, la liste qu'elle utilise (theme_edit.php).
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/stage/lib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$listid = optional_param('listid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('stage', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$stage = $DB->get_record('stage', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/stage:managethemes', $context);

$baseurl = new moodle_url('/mod/stage/evallists.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($stage->name) . ' - ' . get_string('evallists', 'mod_stage'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if ($action === 'delete' && $listid) {
    require_sesskey();
    $list = $DB->get_record('stage_evallist', ['id' => $listid, 'stageid' => $stage->id], '*', MUST_EXIST);
    // Une liste dont les questions ont déjà reçu des réponses n'est pas supprimée : ces réponses
    // disparaîtraient avec elle. On peut en revanche la retirer des thématiques.
    if (stage_count_evallist_exclusive_answers($list->id)) {
        redirect($baseurl, get_string('evallistinuse', 'mod_stage'), null, \core\output\notification::NOTIFY_ERROR);
    }
    stage_delete_evallist($list);
    redirect($baseurl, get_string('evallistdeleted', 'mod_stage'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('evallists', 'mod_stage'));
echo html_writer::link(new moodle_url('/mod/stage/administration.php', ['id' => $cm->id]), get_string('back'));
echo $OUTPUT->box(get_string('evallists_help', 'mod_stage'), 'generalbox mt-2 mb-3');
echo html_writer::link(
    new moodle_url('/mod/stage/evallist_edit.php', ['id' => $cm->id]),
    get_string('evallistadd', 'mod_stage'),
    ['class' => 'btn btn-primary d-block mb-3', 'style' => 'width:fit-content']
);

$lists = stage_get_evallists($stage->id);
if (empty($lists)) {
    echo $OUTPUT->notification(get_string('noevallistsyet', 'mod_stage'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('evallistname', 'mod_stage'),
        get_string('evaltype', 'mod_stage'),
        get_string('evalquestions', 'mod_stage'),
        get_string('evallistthemes', 'mod_stage'),
        get_string('actions', 'mod_stage'),
    ];
    foreach ($lists as $list) {
        $editurl = new moodle_url('/mod/stage/evallist_edit.php', ['id' => $cm->id, 'listid' => $list->id]);
        $deleteurl = new moodle_url($baseurl, ['action' => 'delete', 'listid' => $list->id, 'sesskey' => sesskey()]);
        $themes = array_map(
            fn($theme) => html_writer::link(
                new moodle_url('/mod/stage/theme_edit.php', ['id' => $cm->id, 'themeid' => $theme->id], 'id_questionshdr'),
                format_string($theme->name)
            ),
            stage_get_evallist_themes($list)
        );
        $actions = stage_render_actions([get_string('edit') => $editurl]);
        if (!stage_count_evallist_exclusive_answers($list->id)) {
            $actions .= html_writer::link($deleteurl, get_string('delete'), [
                'class' => 'btn btn-sm btn-outline-danger mr-1 mb-1',
                'onclick' => stage_confirm_onclick(get_string('confirmdeleteevallist', 'mod_stage')),
            ]);
        }
        $table->data[] = [
            html_writer::link($editurl, format_string($list->name)),
            stage_evaltype_label($list->evaltype),
            count(stage_get_evallist_questions($list->id)),
            $themes ? implode(', ', $themes) : html_writer::span(get_string('evallistunusedshort', 'mod_stage'), 'text-muted'),
            $actions,
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
