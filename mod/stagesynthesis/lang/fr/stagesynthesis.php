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
 * French strings for mod_stagesynthesis.
 *
 * @package   mod_stagesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Suivi des stages (synthèse enseignant)';
$string['modulename'] = 'Suivi des stages';
$string['modulenameplural'] = 'Suivis des stages';
$string['modulename_help'] = "Regroupe, pour chaque enseignant référent, les stages de tous les étudiants qui lui sont "
    . "attribués sur les activités « Gestion des stages » liées à cette activité — même s'ils viennent de plusieurs "
    . "promotions/cours différents. Les activités à faire remonter se choisissent dans l'administration de cette "
    . "activité ; une promotion qui n'est plus suivie peut ainsi être retirée sans toucher à son activité d'origine. "
    . "Les droits (qui est référent de qui) restent gérés dans chaque activité « Gestion des stages » : cette activité "
    . "ne fait qu'en donner une vue regroupée, elle n'accorde aucun droit supplémentaire.";
$string['modulename_link'] = 'mod/stagesynthesis/view';
$string['pluginadministration'] = 'Administration de Suivi des stages';

$string['stagesynthesis:addinstance'] = 'Ajouter une activité de suivi des stages';
$string['stagesynthesis:view'] = "Voir l'activité de suivi des stages";
$string['stagesynthesis:managelinks'] = 'Choisir les activités « Gestion des stages » liées';

$string['stagesynthesisname'] = 'Nom de l\'activité';
$string['linksnotice'] = 'Le choix des activités « Gestion des stages » à faire remonter ici se fait après '
    . 'l\'enregistrement, depuis la page de l\'activité (lien « Gérer les liens »). Comme lier une activité revient '
    . 'à désigner des cours et des promotions extérieurs à celui-ci, ce choix est réservé à qui administre '
    . 'l\'activité : un enseignant non éditeur ne voit que la synthèse de ses propres étudiants.';

$string['managelinks'] = 'Gérer les liens';
$string['managelinks_help'] = 'Cochez les activités « Gestion des stages » dont les étudiants doivent apparaître ici '
    . 'pour leurs enseignants référents. Décochez une activité (par exemple une promotion sortie) pour la retirer de '
    . 'la synthèse sans la supprimer ni modifier ses données.';
$string['linkedcount'] = '{$a} activité(s) « Gestion des stages » liée(s).';
$string['linkssaved'] = 'Liste des activités liées mise à jour.';
$string['linked'] = 'Lié';
$string['noactivities'] = 'Aucune activité « Gestion des stages » n\'existe encore sur cette plateforme.';
$string['hiddencourse'] = 'cours masqué';
$string['hiddenactivity'] = 'activité masquée';

$string['nostudents'] = 'Aucun étudiant ne vous est actuellement attribué comme enseignant référent sur les activités '
    . 'liées.';
$string['student'] = 'Étudiant';

$string['privacy:metadata'] = 'Le plugin Suivi des stages ne stocke aucune donnée personnelle : il affiche uniquement '
    . 'des données déjà présentes dans les activités « Gestion des stages » liées.';
