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
 * Génère la page 1 de la convention de stage (informations propres au stage), structurée en
 * sections à bandeau coloré à partir des données de la base.
 *
 * Les pages suivantes (articles juridiques, texte fixe) sont réimportées d'un PDF gabarit par
 * stage_build_convention_pdf(), via FPDI. \pdf et la classe d'import FPDI étendent toutes deux
 * TCPDF par des chemins distincts et ne peuvent pas être combinées dans une seule classe : la
 * page 1 est donc produite ici isolément, puis assemblée avec le gabarit par l'appelant.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class convention_pdf extends \pdf {

    /** @var array Couleur du bandeau de section (RGB). */
    const BAND_COLOR = [0, 61, 100];

    /** @var array Couleur du texte sur bandeau (RGB). */
    const BAND_TEXT_COLOR = [255, 255, 255];

    /** @var string Langue de la convention (gabarit choisi par l'étudiant), 'fr' ou 'en'. */
    protected $lang = 'fr';

    /**
     * Raccourci vers get_string forçant la langue de la convention plutôt que la langue de
     * session de l'utilisateur qui génère le PDF (généralement la DEVE) : la page 1 doit être
     * dans la même langue que le gabarit choisi par l'étudiant pour ses pages 2 à 4.
     *
     * @param string $id
     * @param mixed $a
     * @return string
     */
    protected function str($id, $a = null) {
        return get_string($id, 'mod_stage', $a, $this->lang);
    }

    /**
     * Génère la page 1 de la convention à partir des données préparées par convention.php.
     *
     * @param array $stagedata Voir convention.php pour la structure exacte attendue :
     *                         establishment{name, address, representative, representativetitle,
     *                         phone, email} (informations de l'établissement d'enseignement,
     *                         éditables par la DEVE, voir stage_get_establishment_info()),
     *                         hoststructure, yearlabel, stagetypelabel,
     *                         host{address, representative, representativetitle, service,
     *                         phone, email, location}, student{fullname, email, birthdate,
     *                         address, phone}, theme{name}, dates{start, end},
     *                         duration{declared} (retained et statuslabel sont sans objet à ce
     *                         stade : la convention se signe avant toute évaluation, la durée
     *                         retenue et le statut de la saisie y sont donc encore à leur valeur
     *                         initiale), referentteacher{name, email}, tutor{name, function, phone, email},
     *                         modalities{night, sunday, holiday, homebased (bool), other
     *                         (texte)}, gratification (texte), leave{has (bool), days,
     *                         modalities (texte)}.
     * @param string|null $logoleftpath Chemin d'un fichier PNG (logo haut gauche), ou null.
     * @param string|null $logorightpath Chemin d'un fichier PNG (logo haut droit), ou null.
     * @param string $lang Langue de la convention ('fr' ou 'en'), celle du gabarit choisi.
     * @param bool $withsignatures Ajoute en bas de page 1 un cadre de signatures (stagiaire,
     *                             maître de stage, responsable de l'organisme d'accueil,
     *                             enseignant.e référent.e, établissement), pour une convention
     *                             imprimée et signée à la main.
     * @return void
     */
    public function generate_page1(array $stagedata, $logoleftpath = null, $logorightpath = null, $lang = 'fr',
            $withsignatures = false) {
        $this->lang = $lang;

        $this->SetCreator('Moodle mod_stage');
        $this->SetAuthor($stagedata['establishment']['name'] ?? 'VetAgro Sup');
        $this->SetTitle($this->str('conventiontitle'));
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetMargins(15, 15, 15);
        $this->SetAutoPageBreak(true, 15);
        $this->AddPage();

        // Logos DEVE (mêmes pour tous les stages), haut gauche / haut droit de la page 1.
        // Hauteur fixe, largeur calculée automatiquement (0) pour respecter le ratio du PNG.
        if ($logoleftpath) {
            $this->Image($logoleftpath, 15, 10, 0, 18, 'PNG');
        }
        if ($logorightpath) {
            // On mesure l'image pour calculer sa largeur à hauteur fixe (18mm) et la positionner
            // alignée à droite dans la marge.
            $size = @getimagesize($logorightpath);
            $ratio = ($size && $size[1] > 0) ? $size[0] / $size[1] : 1;
            $logowidth = 18 * $ratio;
            $pagewidth = $this->getPageWidth();
            $this->Image($logorightpath, $pagewidth - 15 - $logowidth, 10, $logowidth, 18, 'PNG');
        }
        if ($logoleftpath || $logorightpath) {
            $this->SetY(32);
        }

        $this->SetFont('freesans', 'B', 15);
        $this->Cell(0, 9, $this->str('conventiontitle'), 0, 1, 'C');
        $this->SetFont('freesans', '', 10);
        $this->Cell(0, 6, $stagedata['yearlabel'] . ' - ' . $stagedata['stagetypelabel'], 0, 1, 'C');
        $this->Ln(2);

        $this->section_heading($this->str('conventionestablishment'));
        $this->field_row($this->str('conventionestablishmentname'), $stagedata['establishment']['name']);
        $this->field_row($this->str('conventionestablishmentaddress'), $stagedata['establishment']['address']);
        $this->field_row($this->str('conventionestablishmentrepresentative'),
            $stagedata['establishment']['representative']);
        $this->field_row($this->str('conventionestablishmentrepresentativetitle'),
            $stagedata['establishment']['representativetitle']);
        $this->field_row($this->str('conventionestablishmentphone'), $stagedata['establishment']['phone']);
        $this->field_row($this->str('conventionestablishmentemail'), $stagedata['establishment']['email']);
        $this->Ln(3);

        $this->section_heading($this->str('conventionhoststructure'));
        $this->field_row($this->str('conventionhoststructurename'), $stagedata['hoststructure']);
        $this->field_row($this->str('conventionhostaddress'), $stagedata['host']['address']);
        $this->field_row($this->str('conventionhostrepresentative'), $stagedata['host']['representative']);
        $this->field_row($this->str('conventionhostrepresentativetitle'), $stagedata['host']['representativetitle']);
        $this->field_row($this->str('conventionhostservice'), $stagedata['host']['service']);
        $this->field_row($this->str('conventionhostphone'), $stagedata['host']['phone']);
        $this->field_row($this->str('conventionhostemail'), $stagedata['host']['email']);
        $this->field_row($this->str('conventionhostlocation'), $stagedata['host']['location']);
        $this->Ln(3);

        $this->section_heading($this->str('conventionstudent'));
        $this->field_row(get_string('fullname', '', null, $this->lang), $stagedata['student']['fullname']);
        $this->field_row($this->str('conventionbirthdate'), $stagedata['student']['birthdate']);
        $this->field_row($this->str('conventionstudentaddress'), $stagedata['student']['address']);
        $this->field_row($this->str('conventionstudentphone'), $stagedata['student']['phone']);
        $this->field_row(get_string('email', '', null, $this->lang), $stagedata['student']['email']);
        $this->Ln(3);

        $this->section_heading($this->str('conventionthemeduration'));
        $this->field_row($this->str('theme'), $stagedata['theme']['name']);
        $this->field_row($this->str('datestart'), $stagedata['dates']['start']);
        $this->field_row($this->str('dateend'), $stagedata['dates']['end']);
        if (!empty($stagedata['periods']) && count($stagedata['periods']) > 1) {
            $this->field_row($this->str('periods'), implode("\n", $stagedata['periods']));
        }
        $this->field_row($this->str('declaredduration'), $stagedata['duration']['declared']);
        $this->Ln(3);

        $this->section_heading($this->str('conventionsupervision'));
        $this->field_row($this->str('conventionreferentteacher'),
            !empty($stagedata['referentteacher']['name']) ? $stagedata['referentteacher']['name'] : '-');
        $this->field_row($this->str('conventionreferentteacherstatus'), $this->str('conventionreferentteacherstatusvalue'));
        $this->field_row($this->str('conventionreferentteacheremail'),
            !empty($stagedata['referentteacher']['email']) ? $stagedata['referentteacher']['email'] : '-');
        $this->field_row($this->str('conventiontutorname'), $stagedata['tutor']['name']);
        $this->field_row($this->str('conventiontutorfunction'), $stagedata['tutor']['function']);
        $this->field_row($this->str('conventiontutorphone'), $stagedata['tutor']['phone']);
        $this->field_row($this->str('conventiontutoremail'), $stagedata['tutor']['email']);
        $this->Ln(3);

        $this->section_heading($this->str('conventionmodalities'));
        $this->checkbox_row($this->str('conventionnightpresence'), !empty($stagedata['modalities']['night']));
        $this->checkbox_row($this->str('conventionsundaypresence'), !empty($stagedata['modalities']['sunday']));
        $this->checkbox_row($this->str('conventionholidaypresence'), !empty($stagedata['modalities']['holiday']));
        $this->checkbox_row($this->str('conventionhomebased'), !empty($stagedata['modalities']['homebased']));
        if (!empty($stagedata['modalities']['other'])) {
            $this->field_row($this->str('conventionothermodality'), $stagedata['modalities']['other']);
        }
        $this->Ln(3);

        $this->section_heading($this->str('conventiongratification'));
        $this->field_row($this->str('conventiongratification'), $stagedata['gratification'] ?: '-');
        $this->Ln(3);

        $this->section_heading($this->str('conventionleave'));
        $this->checkbox_row($this->str('conventionhasleave'), !empty($stagedata['leave']['has']));
        if (!empty($stagedata['leave']['has'])) {
            $this->field_row($this->str('conventionleavedays'), $stagedata['leave']['days']);
            if (!empty($stagedata['leave']['modalities'])) {
                $this->field_row($this->str('conventionleavemodalities'), $stagedata['leave']['modalities']);
            }
        }

        if ($withsignatures) {
            $this->signature_block($stagedata);
        }
    }

    /**
     * Cadre de signatures en bas de la page 1 : une case par signataire (stagiaire, maître de
     * stage, responsable de l'organisme d'accueil, enseignant.e référent.e, personne ayant
     * délégation de signature du chef d'établissement), réparties sur deux colonnes, avec le nom
     * déjà connu préaffiché quand il est disponible. Chaque case comporte une ligne de date à
     * remplir à la main : une convention n'engage qu'à compter de sa signature, et chaque
     * signataire signe à sa propre date. Réservé aux conventions imprimées destinées à être
     * signées à la main (voir generate_page1(), $withsignatures).
     *
     * @param array $stagedata Voir generate_page1() : utilisé ici pour préremplir les noms déjà
     *                         connus (stagiaire, tuteur, enseignant.e référent.e, signataire de
     *                         l'établissement).
     * @return void
     */
    protected function signature_block(array $stagedata) {
        $this->Ln(3);
        $this->section_heading($this->str('conventionsignatures'));

        $boxes = [
            [$this->str('conventionsignaturestudent'), $stagedata['student']['fullname'] ?? '', false],
            [$this->str('conventionsignaturetutor'), $stagedata['tutor']['name'] ?? '', false],
            [$this->str('conventionsignaturehostrepresentative'), $stagedata['host']['representative'] ?? '', false],
            [$this->str('conventionsignaturereferentteacher'), $stagedata['referentteacher']['name'] ?? '', false],
            [$this->str('conventionsignatureestablishment'), $stagedata['establishment']['signatory'] ?? '', true],
        ];

        $cols = 2;
        $gap = 4;
        $lineheight = 5;
        // Espace laissé libre sous les mentions pour la date et la signature manuscrite.
        $signspace = 22;
        $left = $this->getMargins()['left'];
        $pagewidth = $this->getPageWidth() - $left - $this->getMargins()['right'];
        $boxwidth = ($pagewidth - $gap * ($cols - 1)) / $cols;

        // Les hauteurs de libellé et de mention sont mesurées, non supposées : la mention de
        // délégation, plus longue que les autres, passe sur deux lignes et le cadre serait sinon
        // tracé par-dessus. La hauteur retenue est celle de la case la plus haute de la rangée,
        // pour que les cadres d'une même rangée s'alignent.
        $this->SetFont('freesans', '', 9);
        foreach ($boxes as $i => [$label, $name, $delegation]) {
            if ($delegation) {
                // La signature de l'établissement se fait par délégation du chef d'établissement,
                // jamais directement en son nom : la mention doit l'indiquer explicitement, avec
                // ou sans le nom de la personne déléguée.
                $text = ($name !== '' && $name !== '-')
                    ? $this->str('conventionsignaturedelegationname', $name)
                    : $this->str('conventionsignaturedelegation');
            } else {
                $text = ($name !== '' && $name !== '-') ? $this->str('conventionsignaturename', $name) : '';
            }
            $boxes[$i][3] = $text;
            // Compté en nombre de lignes puis multiplié par la hauteur de ligne effectivement
            // utilisée ci-dessous : getStringHeight() renvoie la hauteur propre à la police, plus
            // petite, et le cadre viendrait mordre la dernière ligne de texte.
            $this->SetFont('freesans', 'B', 9);
            $lines = $this->getNumLines($label, $boxwidth);
            if ($text !== '') {
                $this->SetFont('freesans', '', 9);
                $lines += $this->getNumLines($text, $boxwidth);
            }
            $boxes[$i][4] = $lines * $lineheight;
        }
        $rowtextheight = [];
        foreach ($boxes as $i => $box) {
            $row = intdiv($i, $cols);
            $rowtextheight[$row] = max($rowtextheight[$row] ?? 0, $box[4]);
        }

        $y = $this->GetY();
        foreach ($boxes as $i => [$label, $name, $delegation, $text, $textheight]) {
            $col = $i % $cols;
            $rowheight = $rowtextheight[intdiv($i, $cols)] + 1 + $signspace;
            if ($col === 0) {
                $this->ensure_space($rowheight + 6);
                $y = $this->GetY();
            }
            $x = $left + $col * ($boxwidth + $gap);

            $this->SetFont('freesans', 'B', 9);
            $this->MultiCell($boxwidth, $lineheight, $label, 0, 'L', false, 1, $x, $y);
            if ($text !== '') {
                $this->SetFont('freesans', '', 9);
                $this->MultiCell($boxwidth, $lineheight, $text, 0, 'L', false, 1, $x, $this->GetY());
            }

            // La date est dans le cadre plutôt qu'au-dessus : elle fait partie de ce que le
            // signataire remplit, au même titre que sa signature.
            $boxtop = $y + $rowtextheight[intdiv($i, $cols)] + 1;
            $this->Rect($x, $boxtop, $boxwidth, $signspace);
            $this->SetFont('freesans', '', 8);
            $this->MultiCell($boxwidth - 4, 4, $this->str('conventionsignaturedate'), 0, 'L', false, 0,
                $x + 2, $boxtop + 1.5);

            if ($col === $cols - 1 || $i === count($boxes) - 1) {
                $this->SetY($y + $rowheight + 6);
            }
        }
    }

    /**
     * Ligne à case à cocher : dessine une case (cochée ou non) suivie d'un libellé, pour les
     * options activables de la convention (modalités particulières, congés...).
     *
     * Le libellé passe sur plusieurs lignes si nécessaire, et la ligne entière est réservée
     * d'avance (ensure_space()) pour qu'un saut de page ne sépare pas la case de son texte.
     *
     * @param string $label
     * @param bool $checked
     * @return void
     */
    protected function checkbox_row($label, $checked) {
        $size = 4;
        $textx = $this->GetX() + $size + 2;
        $textwidth = $this->getPageWidth() - $this->getMargins()['right'] - $textx;

        $this->SetFont('freesans', '', 10);
        $height = max($this->getStringHeight($textwidth, $label), $size + 2);
        $this->ensure_space($height);

        $x = $this->GetX();
        $y = $this->GetY();

        $this->Rect($x, $y + 1, $size, $size, $checked ? 'DF' : 'D', [], $checked ? self::BAND_COLOR : [0, 0, 0]);
        if ($checked) {
            $this->SetDrawColor(255, 255, 255);
            $this->Line($x + 0.8, $y + 1 + 2, $x + 1.6, $y + 1 + 3.2);
            $this->Line($x + 1.6, $y + 1 + 3.2, $x + 3.2, $y + 1 + 0.8);
            $this->SetDrawColor(0, 0, 0);
        }

        $this->SetFont('freesans', '', 10);
        $this->MultiCell($textwidth, $height, $label, 0, 'L', false, 1, $textx, $y);
    }

    /**
     * Bandeau coloré de titre de section.
     *
     * @param string $label
     * @return void
     */
    protected function section_heading($label) {
        // Évite qu'un bandeau de section se retrouve seul en bas de page, sans aucune des lignes
        // qui le suivent.
        $this->ensure_space(8 + 6);
        $this->SetFillColor(...self::BAND_COLOR);
        $this->SetTextColor(...self::BAND_TEXT_COLOR);
        $this->SetFont('freesans', 'B', 12);
        $this->Cell(0, 8, ' ' . $label, 0, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(1);
    }

    /**
     * Ligne libellé / valeur sous une section.
     *
     * Libellé et valeur passent sur plusieurs lignes si le texte dépasse la largeur de sa
     * colonne, et la ligne entière est réservée d'avance (ensure_space()) pour ne pas être
     * coupée par un saut de page.
     *
     * @param string $label
     * @param string $value
     * @return void
     */
    protected function field_row($label, $value) {
        $value = (string) $value;
        $labelwidth = 55;
        $pagewidth = $this->getPageWidth() - $this->getMargins()['left'] - $this->getMargins()['right'];
        $valuewidth = $pagewidth - $labelwidth;

        $this->SetFont('freesans', 'B', 10);
        $labelheight = $this->getStringHeight($labelwidth, $label);
        $this->SetFont('freesans', '', 10);
        $valueheight = $this->getStringHeight($valuewidth, $value);
        $rowheight = max($labelheight, $valueheight, 6);

        $this->ensure_space($rowheight);

        $x = $this->GetX();
        $y = $this->GetY();

        $this->SetFont('freesans', 'B', 10);
        $this->MultiCell($labelwidth, $rowheight, $label, 0, 'L', false, 0, $x, $y);
        $this->SetFont('freesans', '', 10);
        $this->MultiCell($valuewidth, $rowheight, $value, 0, 'L', false, 1, $x + $labelwidth, $y);
    }

    /**
     * S'assure qu'il reste au moins $height mm avant le bas de la page (marge de saut incluse),
     * et provoque un saut de page manuel sinon. Le saut automatique de TCPDF interviendrait au
     * milieu d'une ligne dessinée en plusieurs appels, séparant par exemple une case de son
     * libellé.
     *
     * @param float $height
     * @return void
     */
    protected function ensure_space($height) {
        $bottom = $this->getPageHeight() - $this->getBreakMargin();
        if ($this->GetY() + $height > $bottom) {
            $this->AddPage();
        }
    }
}
