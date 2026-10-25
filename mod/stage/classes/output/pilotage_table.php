<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_stage\output;

use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/stage/locallib.php');

/**
 * Tableau de pilotage paginé (gabarit mod_stage/pilotage_table), construit à partir de colonnes
 * (voir stage_pilotage_columns()) et de lignes déjà filtrées et triées.
 *
 * @package mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pilotage_table implements renderable, templatable {
    /** @var array clé => ['head' => en-tête (HTML), 'cell' => fonction (ligne) => cellule (HTML)]. */
    private $columns;

    /** @var stdClass[] Lignes de pilotage, filtrées et triées. */
    private $rows;

    /** @var int Page courante (0-indexée). */
    private $page;

    /** @var moodle_url URL de la liste avec ses filtres, pour la barre de pagination. */
    private $listurl;

    /**
     * Prépare le tableau à partir de ses colonnes et de ses lignes.
     *
     * @param array $columns clé => ['head' => en-tête, 'cell' => fonction (ligne) => cellule], dans l'ordre d'affichage.
     * @param stdClass[] $rows Lignes déjà filtrées et triées.
     * @param int $page Page courante.
     * @param moodle_url $listurl URL de la liste avec ses filtres.
     */
    public function __construct(array $columns, array $rows, $page, moodle_url $listurl) {
        $this->columns = $columns;
        $this->rows = $rows;
        $this->page = (int) $page;
        $this->listurl = $listurl;
    }

    /**
     * Données du gabarit : en-têtes, cellules rendues de la page courante, barre de pagination.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        [$rows, $pagingbar] = stage_paginate($this->rows, $this->page, $this->listurl);

        $data = new stdClass();
        $data->head = [];
        foreach ($this->columns as $column) {
            $data->head[] = ['html' => $column['head']];
        }
        $data->rows = [];
        foreach ($rows as $row) {
            $cells = [];
            foreach ($this->columns as $column) {
                $cells[] = ['html' => call_user_func($column['cell'], $row)];
            }
            $data->rows[] = ['cells' => $cells];
        }
        $data->pagingbar = $pagingbar;
        return $data;
    }
}
