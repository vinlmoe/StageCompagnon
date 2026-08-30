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

namespace mod_stage\pdf;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/pdflib.php');

/**
 * Génère le bilan de promotion en PDF (voir export_pdf.php) : la validation, par étudiant, de
 * l'année d'étude courante et des précédentes, les étudiants en défaut en tête.
 *
 * Contrairement à la convention (voir convention_pdf.php), rien n'est réimporté d'un gabarit : le
 * document est produit entièrement ici, en paysage pour tenir la largeur du tableau, avec un
 * en-tête de colonnes répété à chaque page.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class promotion_pdf extends \pdf {

    /** @var array Couleur du bandeau de section et de l'en-tête de tableau (RGB). */
    const BAND_COLOR = [0, 61, 100];

    /** @var array Fond des lignes d'étudiants en défaut (RGB), pour les repérer d'un coup d'œil. */
    const FAILED_ROW_COLOR = [253, 237, 237];

    /** @var array Largeurs de colonnes en mm : nom, puis une par année, puis les totaux. */
    protected $widths = [];

    /** @var array Libellés des colonnes. */
    protected $headers = [];

    /**
     * Produit le document complet.
     *
     * @param array $data Données préparées par export_pdf.php :
     *                    title, subtitle, generatedon (chaînes d'en-tête),
     *                    summary (ligne de synthèse), yearlabels (libellés des colonnes d'années),
     *                    columns {student, days, themes, status} (libellés des autres colonnes),
     *                    sections (liste de ['heading' => string, 'note' => string,
     *                    'rows' => [[cellules...], ...], 'failed' => bool]),
     *                    legend (légende des symboles).
     * @return void
     */
    public function generate(array $data) {
        $this->SetCreator('Moodle mod_stage');
        $this->SetTitle($data['title']);
        $this->setPrintHeader(false);
        $this->setPrintFooter(true);
        $this->SetMargins(10, 10, 10);
        $this->SetAutoPageBreak(true, 12);
        $this->AddPage('L');

        // Une colonne par année d'étude retenue, encadrée par le nom de l'étudiant à gauche et
        // les totaux à droite. Le nom prend toute la largeur restante : c'est le seul champ dont
        // la longueur est imprévisible.
        $this->headers = array_merge(
            [$data['columns']['student']],
            $data['yearlabels'],
            [$data['columns']['days'], $data['columns']['themes'], $data['columns']['status']]
        );
        $pagewidth = $this->getPageWidth() - $this->getMargins()['left'] - $this->getMargins()['right'];
        $fixed = array_merge(array_fill(0, count($data['yearlabels']), 20), [24, 28, 42]);
        $this->widths = array_merge([$pagewidth - array_sum($fixed)], $fixed);

        $this->SetFont('freesans', 'B', 15);
        $this->Cell(0, 8, $data['title'], 0, 1, 'C');
        $this->SetFont('freesans', '', 10);
        $this->Cell(0, 5, $data['subtitle'], 0, 1, 'C');
        $this->Cell(0, 5, $data['generatedon'], 0, 1, 'C');
        $this->Ln(2);

        $this->SetFont('freesans', 'B', 11);
        $this->MultiCell(0, 5, $data['summary'], 0, 'L');
        $this->Ln(2);

        foreach ($data['sections'] as $section) {
            $this->section_heading($section['heading']);
            if (!empty($section['note'])) {
                $this->SetFont('freesans', '', 9);
                $this->MultiCell(0, 4, $section['note'], 0, 'L');
                $this->Ln(1);
            }
            if (empty($section['rows'])) {
                $this->SetFont('freesans', 'I', 10);
                $this->MultiCell(0, 5, $section['empty'], 0, 'L');
                $this->Ln(3);
                continue;
            }
            $this->table_header();
            foreach ($section['rows'] as $row) {
                $this->table_row($row, !empty($section['failed']));
            }
            $this->Ln(3);
        }

        $this->SetFont('freesans', 'I', 9);
        $this->MultiCell(0, 4, $data['legend'], 0, 'L');
    }

    /**
     * Bandeau coloré de titre de section.
     *
     * @param string $label
     * @return void
     */
    protected function section_heading($label) {
        // Évite qu'un bandeau se retrouve seul en bas de page, sans l'en-tête ni la première
        // ligne du tableau qui le suit.
        $this->ensure_space(8 + 7 + 6);
        $this->SetFillColor(...self::BAND_COLOR);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('freesans', 'B', 12);
        $this->Cell(0, 8, ' ' . $label, 0, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(1);
    }

    /** @var float Hauteur d'une ligne d'en-tête, en mm (deux lignes de texte). */
    const HEADER_HEIGHT = 9;

    /** @var float Hauteur d'une ligne d'étudiant, en mm. */
    const ROW_HEIGHT = 6;

    /**
     * Dessine une ligne du tableau, cellule par cellule.
     *
     * Chaque cellule est rendue en MultiCell à hauteur imposée plutôt qu'en Cell : Cell ne
     * renvoie pas le texte à la ligne et le laisse déborder sur la colonne voisine, ce qui rendait
     * illisibles les en-têtes un peu longs et les noms d'étudiants inhabituels.
     *
     * @param array $cells Cellules, dans l'ordre des en-têtes.
     * @param float $height Hauteur de la ligne, en mm.
     * @return void
     */
    protected function table_line(array $cells, $height) {
        $y = $this->GetY();
        $x = $this->getMargins()['left'];
        foreach ($cells as $i => $cell) {
            $this->MultiCell($this->widths[$i], $height, $cell, 1, $i === 0 ? 'L' : 'C', true, 0,
                $x, $y, true, 0, false, true, $height, 'M');
            $x += $this->widths[$i];
        }
        $this->SetY($y + $height);
    }

    /**
     * Ligne d'en-tête du tableau, réémise à chaque saut de page pour que les colonnes restent
     * identifiables sur toutes les pages.
     *
     * @return void
     */
    protected function table_header() {
        $this->ensure_space(self::HEADER_HEIGHT + self::ROW_HEIGHT);
        $this->SetFont('freesans', 'B', 9);
        $this->SetFillColor(225, 232, 238);
        $this->table_line($this->headers, self::HEADER_HEIGHT);
    }

    /**
     * Ligne d'étudiant.
     *
     * @param array $cells Cellules, dans l'ordre des en-têtes.
     * @param bool $failed Étudiant en défaut : la ligne est teintée pour la repérer.
     * @return void
     */
    protected function table_row(array $cells, $failed) {
        if (!$this->ensure_space(self::ROW_HEIGHT)) {
            // Nouvelle page : l'en-tête du tableau doit précéder les lignes qui suivent.
            $this->table_header();
        }
        $this->SetFont('freesans', '', 9);
        if ($failed) {
            $this->SetFillColor(...self::FAILED_ROW_COLOR);
        } else {
            $this->SetFillColor(255, 255, 255);
        }
        $this->table_line($cells, self::ROW_HEIGHT);
    }

    /**
     * S'assure qu'il reste au moins $height mm avant le bas de la page, et provoque un saut de
     * page manuel sinon : le saut automatique de TCPDF interviendrait au milieu d'une ligne
     * dessinée cellule par cellule.
     *
     * @param float $height
     * @return bool Vrai si la place était déjà disponible, faux si une page a été ajoutée.
     */
    protected function ensure_space($height) {
        if ($this->GetY() + $height <= $this->getPageHeight() - $this->getBreakMargin()) {
            return true;
        }
        $this->AddPage('L');
        return false;
    }
}
