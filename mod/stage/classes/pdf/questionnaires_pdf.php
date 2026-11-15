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
 * Questionnaires d'un stage en PDF (voir entry_questionnaires_export.php) : l'identité du stage,
 * puis un tableau question / réponse par questionnaire, suivi de son commentaire libre.
 *
 * Le document est écrit en HTML simple (writeHTML de TCPDF) : les réponses sont de longueur
 * imprévisible, et le moteur HTML gère seul le retour à la ligne et les sauts de page dans un
 * tableau. Tout le texte est échappé ici : les sections reçues sont en texte brut.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questionnaires_pdf extends \pdf {
    /**
     * Produit le document complet.
     *
     * @param array $data title, generatedon, identity (libellé => valeur), sections (voir
     *                    stage_get_entry_questionnaires()), labels {question, answer, comment,
     *                    respondent, noanswer, empty}.
     * @return void
     */
    public function generate(array $data) {
        $this->SetCreator('Moodle mod_stage');
        $this->SetTitle($data['title']);
        $this->setPrintHeader(false);
        $this->setPrintFooter(true);
        $this->SetMargins(15, 15, 15);
        $this->SetAutoPageBreak(true, 15);
        $this->AddPage();

        $this->SetFont('freesans', 'B', 15);
        $this->MultiCell(0, 8, $data['title'], 0, 'C');
        $this->SetFont('freesans', '', 9);
        $this->MultiCell(0, 5, $data['generatedon'], 0, 'C');
        $this->Ln(3);

        $this->SetFont('freesans', '', 10);
        $html = '<table cellpadding="3" border="0">';
        foreach ($data['identity'] as $label => $value) {
            $html .= '<tr><td width="35%"><b>' . s($label) . '</b></td><td width="65%">' . s($value) . '</td></tr>';
        }
        $html .= '</table>';
        $this->writeHTML($html, true, false, true, false, '');
        $this->Ln(2);

        if (empty($data['sections'])) {
            $this->SetFont('freesans', 'I', 10);
            $this->MultiCell(0, 5, $data['labels']['empty'], 0, 'L');
            return;
        }

        foreach ($data['sections'] as $section) {
            $this->SetFont('freesans', '', 10);
            $html = '<h3 style="background-color:#003d64;color:#ffffff;"> ' . s($section['title']) . '</h3>';
            $meta = [];
            if ($section['respondent'] !== '') {
                $meta[] = s($data['labels']['respondent'] . ' : ' . $section['respondent']);
            }
            if (!empty($section['time'])) {
                $meta[] = s(userdate($section['time'], get_string('strftimedatetime', 'langconfig')));
            }
            if ($meta) {
                $html .= '<p style="color:#555555;">' . implode(' - ', $meta) . '</p>';
            }
            if (!empty($section['items'])) {
                $html .= '<table cellpadding="4" border="1">'
                    . '<thead><tr style="background-color:#e1e8ee;font-weight:bold;">'
                    . '<th width="45%">' . s($data['labels']['question']) . '</th>'
                    . '<th width="55%">' . s($data['labels']['answer']) . '</th></tr></thead><tbody>';
                foreach ($section['items'] as $item) {
                    $answer = $item['answer'] !== ''
                        ? nl2br(s($item['answer']))
                        : '<i>' . s($data['labels']['noanswer']) . '</i>';
                    $html .= '<tr nobr="true"><td width="45%">' . s($item['question']) . '</td>'
                        . '<td width="55%">' . $answer . '</td></tr>';
                }
                $html .= '</tbody></table>';
            }
            if ($section['comment'] !== '') {
                $html .= '<p><b>' . s($data['labels']['comment']) . ' :</b><br>' . nl2br(s($section['comment'])) . '</p>';
            }
            $this->writeHTML($html, true, false, true, false, '');
            $this->Ln(2);
        }
    }
}
