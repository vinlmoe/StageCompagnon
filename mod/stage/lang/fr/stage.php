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
 * French strings for mod_stage.
 *
 * @package   mod_stage
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Gestion des stages';
$string['modulename'] = 'Gestion des stages';
$string['modulenameplural'] = 'Gestions des stages';
$string['modulename_help'] = "Ce module permet aux étudiants de déclarer leurs stages par thématique, de les auto-évaluer, "
    . "aux enseignants référents de les évaluer, et à la DEVE de gérer les thématiques obligatoires et de valider "
    . "définitivement les stages, en masse ou un par un.";
$string['pluginadministration'] = 'Administration de la gestion des stages';
$string['stagename'] = "Nom de l'activité";

// Capabilities.
$string['stage:addinstance'] = 'Ajouter une activité de gestion des stages';
$string['stage:view'] = "Voir l'activité de gestion des stages";
$string['stage:submit'] = 'Saisir ses propres stages';
$string['stage:evaluateteacher'] = 'Évaluer les stages en tant que référent';
$string['stage:registerstages'] = 'Enregistrer les stages des étudiants';
$string['stage:managethemes'] = 'Gérer les thématiques de stage';
$string['stage:validatedeve'] = 'Valider définitivement les stages (DEVE)';
$string['stage:viewall'] = 'Voir tous les stages de tous les étudiants';
$string['stage:manageteachers'] = 'Attribuer les enseignants référents';

// Navigation / actions.
$string['managethemes'] = 'Gérer les thématiques';
$string['administration'] = 'Administration';
$string['importfromcourse'] = 'Importer depuis un autre cours';
$string['importfromcourse_help'] = "Copiez les thématiques, gabarits de convention, logos, textes de courriels "
    . "et/ou informations d'établissement d'une autre instance de l'activité (généralement dans un autre cours) "
    . "vers celle-ci, pour éviter de tout ressaisir à chaque nouveau cours. Seules les instances sur lesquelles "
    . "vous avez vous-même le droit de gérer les thématiques sont proposées comme source. Les éléments importés "
    . "s'ajoutent à ceux déjà présents ici (les thématiques et gabarits ne sont pas fusionnés avec les "
    . "existants ; les logos, textes de courriels et informations d'établissement déjà renseignés sont "
    . "remplacés).";
$string['importsource'] = 'Instance source';
$string['importthemes'] = 'Thématiques';
$string['importtemplates'] = 'Gabarits de convention';
$string['importlogos'] = 'Logos';
$string['importemails'] = 'Textes des courriels';
$string['importemails_help'] = "Copie les sujets et corps de courriels personnalisés dans l'instance source "
    . "(page « Notifications »). Chaque courriel personnalisé dans la source remplace celui de cette instance ; "
    . "les courriels que la source n'a pas personnalisés sont laissés tels quels ici.";
$string['importestablishment'] = "Informations de l'établissement d'enseignement";
$string['importnothingselected'] = 'Sélectionnez au moins un élément à importer.';
$string['noimportsources'] = "Aucune autre instance de l'activité sur laquelle vous pouvez gérer les "
    . 'thématiques n\'a été trouvée.';
$string['importdone'] = 'Import terminé : {$a->themes} thématique(s), {$a->templates} gabarit(s) de convention, '
    . '{$a->logos} logo(s), {$a->emails} texte(s) de courriel, établissement {$a->establishmenttext}.';
$string['importdoneestablishmentyes'] = 'importé';
$string['importdoneestablishmentno'] = 'non importé';
$string['manageteachers'] = 'Attribuer les enseignants référents';
$string['devevalidation'] = 'Validation DEVE';
$string['teachervalidation'] = 'Validation enseignant';
$string['mystages'] = 'Mes stages';
$string['registerstages'] = 'Enregistrer des stages';
$string['importcsv'] = 'Importer un fichier CSV';
$string['exportexcel'] = 'Exporter en Excel';
$string['globalimport'] = 'Restaurer un export global';
$string['globalimport_help'] = "Importez ici le fichier XLSX produit par « Exporter en Excel ». Une prévisualisation précède toujours la restauration. Les étudiants sont rapprochés par courriel et les thématiques par leur nom. Les stages déjà présents sont ignorés. Les pièces jointes, rapports, jours effectifs détaillés et réponses aux questionnaires ne peuvent pas être recréés, car l’export global ne contient pas leur contenu complet.";
$string['globalimportpreviewbutton'] = 'Analyser la sauvegarde';
$string['globalimportpreview'] = 'Prévisualisation : {$a} stage(s) prêt(s) à restaurer';
$string['globalimportconfirm'] = 'Confirmer la restauration';
$string['globalimportdone'] = '{$a} stage(s) restauré(s).';
$string['globalimportinvalid'] = 'Ce fichier ne contient pas la feuille « Stages » d’un export global compatible.';
$string['globalimportexpired'] = 'La prévisualisation a expiré. Analysez de nouveau le fichier.';
$string['globalimportunknownstudent'] = 'Ligne {$a->line} : étudiant « {$a->value} » introuvable, stage ignoré.';
$string['globalimportunknowntheme'] = 'Ligne {$a->line} : thématique « {$a->value} » introuvable, stage ignoré.';
$string['globalimportduplicate'] = 'Ligne {$a} : ce stage existe déjà, ligne ignorée.';
$string['allstages'] = 'Stages';
$string['promotionreport'] = 'Bilan de promotion';
$string['promotionreportpdf'] = 'Bilan de promotion (PDF)';
$string['promotiongeneratedon'] = 'Édité le {$a}';
$string['promotionsummary'] = '{$a->total} étudiant(s) : {$a->failed} en défaut sur une année échue, {$a->uptodate} à jour.';
$string['promotionfailedheading'] = 'Étudiants ne validant pas une année échue';
$string['promotionfailedheading_help'] = "Classés du plus en retard au moins en retard : d'abord par nombre d'années non validées, puis par ancienneté du retard.";
$string['promotionuptodateheading'] = 'Étudiants à jour';
$string['promotionnofailed'] = 'Aucun : toute la promotion valide les années échues.';
$string['promotionnouptodate'] = 'Aucun étudiant ne valide encore toutes les années échues.';
$string['promotionyeardone'] = 'OK';
$string['promotionyearfailed'] = 'NON';
$string['promotionuptodate'] = 'À jour';
$string['promotionfailedyears'] = 'Années non validées';
$string['promotioncoldays'] = 'Jours retenus';
$string['promotioncolthemes'] = 'Thématiques';
$string['promotionlegend'] = "OK : année validée. NON : année non validée. - : aucun objectif défini pour cet étudiant cette année-là. Seules l'année d'étude courante du stage et les précédentes sont prises en compte.";
$string['import'] = 'Importer';
$string['importcsv_help'] = "Importez un fichier CSV (enregistré depuis Excel via « Enregistrer sous > CSV »), avec les "
    . 'colonnes suivantes, séparées par des points-virgules ou des virgules, avec une ligne d\'en-tête facultative : '
    . '<code>email;theme;structure;datestart;dateend;duration</code>. Le champ <em>email</em> doit correspondre à un '
    . "étudiant inscrit au cours, <em>theme</em> au nom exact d'une thématique existante, les dates au format "
    . 'AAAA-MM-JJ (facultatives), et <em>duration</em> à la durée déclarée en jours.';
$string['importresult'] = '{$a} stage(s) importé(s) avec succès.';
$string['importerrorupload'] = "Le fichier n'a pas pu être téléversé. Vérifiez sa taille et réessayez.";
$string['importerrorunknownemail'] = 'Ligne {$a->line} : aucun étudiant inscrit avec l\'adresse "{$a->email}".';
$string['importerrorunknowntheme'] = 'Ligne {$a->line} : thématique "{$a->theme}" introuvable.';
$string['importerrorduplicate'] = 'Ligne {$a->line} : "{$a->email}" a déjà un stage sur la thématique "{$a->theme}" '
    . 'avec ces mêmes dates, ligne ignorée.';
$string['importstagevetcsv'] = 'Importer un export StageVet (CSV)';
$string['importstagevetcsv_help'] = "Importez directement le fichier CSV exporté depuis StageVet (menu export de "
    . "StageVet, sans modification). Les colonnes sont reconnues par leur en-tête (« Nom étudiant », "
    . "« Prénom étudiant », « Thème », « Début (convention)/Fin (convention) », coordonnées de l'organisme et du "
    . "tuteur, modalités, gratification...), dans l'ordre où StageVet les fournit habituellement. L'étudiant est "
    . "identifié par courriel si la colonne « Email étudiant » est renseignée, sinon par nom/prénom (comparaison "
    . "insensible aux accents et à la casse) parmi les étudiants inscrits au cours. "
    . "L'année du stage est définie par la colonne « Année étudiant (convention) », ou à défaut par « Année "
    . "d'étude ». Le « tuteur » de l'export est rapproché de l'enseignant référent Moodle, tandis que le « maître "
    . "de stage » est enregistré comme encadrant dans la structure d'accueil. Le nom de thématique doit "
    . "correspondre exactement à une thématique déjà créée dans cette "
    . "activité : StageVet n'utilisant pas les "
    . "mêmes intitulés par défaut, créez au préalable des thématiques portant les mêmes noms que ceux utilisés "
    . "dans StageVet (ex. « THEME LIBRE / A2, A3, A4, A5 »). Chaque stage importé est enregistré avec le statut de "
    . 'convention "Signée (StageVet)" (déjà signée hors de ce plugin) : les coordonnées de convention disponibles '
    . "dans l'export sont tout de même enregistrées à titre de référence, sans déclencher de génération de PDF. "
    . "Les dates de début et de fin de l'export constituent l'unique plage de dates du stage importé : une ligne "
    . "sans dates exploitables est signalée et ignorée.";
$string['importstagevetnoheader'] = "Le fichier ne semble pas avoir de ligne d'en-tête reconnaissable. Vérifiez qu'il "
    . "s'agit bien d'un export StageVet non modifié.";
$string['importstageveterrornotheme'] = 'Ligne {$a} : aucune thématique renseignée.';
$string['importstageveterrordates'] = 'Ligne {$a->line} ({$a->student}) : dates de début et de fin absentes ou incohérentes. Ces dates constituent l\'unique plage du stage : la ligne est ignorée.';
$string['importstagevetunknownstudentsreport'] = '{$a} étudiant(s) introuvable(s) parmi les inscrits au cours';
$string['importstagevetunknownthemesreport'] = '{$a} thématique(s) introuvable(s)';
$string['importstagevetreportline'] = '{$a->value} (ligne(s) {$a->lines})';
$string['historicalimport'] = 'Importer un ancien suivi Excel';
$string['historicalimport_desc'] = 'Reprendre les stages et stages EP validés dans l’ancien classeur de suivi, '
    . 'après ajout des adresses de courriel des étudiants.';
$string['historicalimport_help'] = 'Importe uniquement les stages validés des feuilles « Stages - validation ER » '
    . 'et « Stage EP - validation ER » de l’ancien classeur. Ajoutez auparavant une colonne intitulée exactement '
    . '« Email » sur la ligne 2 de chacune de ces deux feuilles et renseignez-la pour chaque étudiant. Les stages '
    . 'sont créés directement au statut validé DEVE ; le nombre de jours devient la durée déclarée et retenue. '
    . 'Les EP sont enregistrées comme stages complémentaires dans la thématique choisie ci-dessous. La feuille '
    . '« EP - saisie DEVE », constituée d’enseignements académiques en crédits, n’est pas importée. Une '
    . 'prévisualisation est toujours affichée avant insertion.';
$string['historicalimportfile'] = 'Classeur historique (.xlsx)';
$string['historicalimporteptheme'] = 'Thématique Moodle pour les stages EP';
$string['historicalimportpreviewbutton'] = 'Analyser et prévisualiser';
$string['historicalimportpreview'] = 'Prévisualisation : {$a} stage(s) prêt(s) à importer';
$string['historicalimportconfirm'] = 'Confirmer l’import de ces stages';
$string['historicalimportwarnings'] = '{$a} avertissement(s) à vérifier';
$string['historicalimportdone'] = '{$a} ancien(s) stage(s) importé(s) et validé(s).';
$string['historicalimportcomment'] = 'Validation reprise du suivi Excel historique.';
$string['historicalimportnodates'] = 'Dates non reconnues — texte original conservé';
$string['historicalimportnozip'] = 'L’extension PHP ZIP nécessaire à la lecture des fichiers XLSX est indisponible.';
$string['historicalimportinvalidfile'] = 'Le fichier ne peut pas être lu comme un classeur XLSX valide.';
$string['historicalimportnosheets'] = 'Les feuilles historiques attendues sont absentes du classeur.';
$string['historicalimportmissingemail'] = 'Feuille « {$a} » : aucune colonne « Email » trouvée sur la ligne 2.';
$string['historicalimportrownoemail'] = '{$a->sheet}, ligne {$a->line} ({$a->student}) : email manquant, ligne ignorée.';
$string['historicalimportnotheme'] = '{$a->sheet}, ligne {$a->line} : intitulé de thématique absent.';
$string['historicalimportdateswarning'] = '{$a->sheet}, ligne {$a->line} : dates non reconnues dans « {$a->value} ».';
$string['historicalimportunknownemail'] = '{$a->source} : aucun étudiant inscrit avec l’adresse « {$a->email} ».';
$string['historicalimportunknownteacher'] = '{$a->source} : enseignant référent « {$a->teacher} » non reconnu ; '
    . 'le stage sera importé sans référent.';
$string['historicalimportunknowntheme'] = '{$a->source} : thématique Moodle « {$a->theme} » introuvable.';
$string['historicalimportduplicate'] = '{$a} : stage déjà présent ou répété dans le classeur, ignoré.';
$string['historicalimportselecteptheme'] = 'Choisissez la thématique Moodle dans laquelle importer les stages EP.';
$string['historicalimportexpired'] = 'La prévisualisation a expiré. Téléversez et analysez de nouveau le classeur.';
$string['historicalimportmapthemes'] = 'Associer les thématiques non reconnues';
$string['historicalimportmapthemes_help'] = 'Choisissez une thématique Moodle pour chaque intitulé de colonne Excel. '
    . 'Le choix sera appliqué à tous les stages provenant de cette colonne.';
$string['historicalimportexceltheme'] = 'Intitulé de la colonne Excel';
$string['historicalimportmoodletheme'] = 'Thématique Moodle correspondante';
$string['historicalimportapplymapping'] = 'Appliquer les correspondances';
$string['historicalimportmapallthemes'] = 'Une correspondance doit être choisie pour chaque thématique non reconnue.';
$string['importteacherscsv'] = 'Importer un fichier CSV';
$string['importteacherscsv_help'] = "Importez un fichier CSV (enregistré depuis Excel via « Enregistrer sous > CSV »), avec "
    . 'les colonnes suivantes, séparées par des points-virgules ou des virgules, avec une ligne d\'en-tête facultative : '
    . '<code>studentemail;teacher1email;teacher2email</code>. Le champ <em>studentemail</em> doit correspondre à un '
    . "étudiant inscrit au cours, <em>teacher1email</em> à un enseignant référent potentiel inscrit au cours ; "
    . '<em>teacher2email</em> est facultatif (second référent). Chaque ligne remplace l\'attribution existante de '
    . "l'étudiant.";
$string['importteachersresult'] = '{$a} étudiant(s) mis à jour avec succès.';
$string['importerrorunknownteacher'] = 'Ligne {$a->line} : aucun enseignant référent potentiel avec l\'adresse "{$a->email}".';
$string['errorduplicateentry'] = 'Cet étudiant a déjà un stage enregistré sur cette thématique avec ces mêmes dates.';
$string['registerstage'] = 'Enregistrer un stage';
$string['editstage'] = 'Modifier un stage';
$string['registerstageandconvention'] = 'Faire une demande de convention (hors StageVet)';
$string['registerstageandconvention_help'] = "Pour un stage non pris en charge par StageVet, enregistrez-le "
    . "vous-même et demandez sa convention en une seule fois. La DEVE traitera ensuite votre demande de "
    . "convention (édition puis signature) ; l'auto-évaluation ne sera possible qu'une fois la convention signée.";
$string['stageandconventionregistered'] = 'Le stage a été enregistré et la demande de convention envoyée à la DEVE.';
$string['bulkregisterstages'] = 'Enregistrer des stages en masse';
$string['bulkregisterselected'] = 'Enregistrer pour les étudiants cochés';
$string['bulkregistersignvethelp'] = "Les stages enregistrés en masse sont considérés comme déjà signés sur "
    . 'StageVet : leur statut de convention passe automatiquement à "Signée (StageVet)", sans passer par le '
    . "circuit de gestion de convention de ce plugin (pas de gabarit ni de PDF à générer ou téléverser). "
    . "L'auto-évaluation de l'étudiant est immédiatement ouverte.";
$string['selectstudents'] = 'Sélectionner les étudiants concernés';
$string['selfeval'] = "Auto-évaluer mon stage";
$string['registeredbydeve'] = "Les stages et conventions avec les vétérinaires français sont générés par StageVet. "
    . "Pour demander une convention pour un autre type de stage, faites la demande ci-dessous. Une fois le stage "
    . "terminé, faites votre auto-évaluation et demandez l'évaluation par l'enseignant. Les demandes de "
    . 'convention doivent être faites au minimum 2 semaines avant le stage, ou 4 semaines pour un stage à '
    . "l'étranger.";
$string['allmystages'] = 'Tous mes stages';
$string['mandatorythemes'] = 'Thématiques obligatoires';
$string['actions'] = 'Actions';

// Fields.
$string['theme'] = 'Thématique';
$string['structure'] = "Structure d'accueil";
$string['datestart'] = 'Date de début';
$string['dateend'] = 'Date de fin';
$string['declaredduration'] = 'Durée déclarée (jours)';
$string['retainedduration'] = 'Durée retenue (jours)';
$string['requiredduration'] = 'Durée requise (jours)';
$string['requiredduration_help'] = "Durée totale requise pour valider cette thématique, quelle que soit l'année d'étude (0 = non utilisé). Alternative à la définition d'une durée par année (page « Durées par année ») : ne renseignez que l'une des deux méthodes, pas les deux. Pour une thématique bornée à une plage d'années (ex : A2 à A4), cette durée est vérifiée à la dernière année de la plage, sur l'ensemble des saisies cumulées de la thématique.";
$string['studyyear'] = "Année d'étude";
$string['studyyear_unspecified'] = 'Non spécifiée (toutes années)';
$string['studyyear_n'] = '{$a}e année';
$string['minstudyyear'] = "Année d'étude minimum";
$string['maxstudyyear'] = "Année d'étude maximum";
$string['studyyearrange_error'] = "L'année minimum doit être inférieure ou égale à l'année maximum.";
$string['currentstudyyear'] = 'Année d\'étude courante des étudiants';
$string['currentstudyyear_help'] = "Année d'étude (N) des étudiants inscrits à ce cours. Sert de référence pour les stages qu'ils peuvent déclarer en convention : année N (normale), N-1 (dette) ou N+1 (anticipation). Laisser sur « Non spécifiée » pour ne pas restreindre le choix.";
$string['abroad'] = "Stage à l'étranger";
$string['requiredabroaddays'] = "Jours de mobilité internationale requis";
$string['requiredabroaddays_help'] = "Nombre de jours de stage à l'étranger que chaque étudiant doit cumuler au total sur l'ensemble de ses stages (0 = aucune obligation). Seuls les stages marqués « Stage à l'étranger » et les stages obligatoires (hors stages complémentaires) comptent dans ce bilan.";
$string['abroadtotal'] = 'Mobilité internationale';
$string['abroadbeforeyear'] = "Année avant laquelle la mobilité est requise";
$string['abroaddaysrequired'] = 'Jours à l\'étranger requis';
$string['abroaddaysretained'] = "Jours à l'étranger retenus";
$string['themeabroaddays_help'] = "Nombre de jours de stage à l'étranger requis pour cette thématique (0 = aucune obligation), cumulés sur l'ensemble des stages effectués sur cette thématique (obligatoires ET complémentaires, contrairement à la durée requise ci-dessus qui exclut les stages complémentaires). Pour une thématique bornée à une plage d'années, vérifié à sa dernière année comme la durée requise.";
$string['abroadrule'] = 'Règle de mobilité internationale (affichée aux étudiants)';
$string['abroadrule_help'] = "Texte libre précisant les conditions de mobilité internationale pour cette thématique (ex : pays éligibles, durée minimale continue, organismes partenaires...). Affiché aux étudiants lors de l'enregistrement d'un stage sur cette thématique et dans leur bilan.";
$string['themeabroadsaved'] = 'Les paramètres de mobilité internationale ont été enregistrés.';
$string['country'] = 'Pays';
$string['workdays'] = 'Jours de stage effectifs';
$string['workdays_help'] = "Cochez, parmi les plages de dates de ce stage, les jours effectivement travaillés. Rappel : la réglementation impose au moins un jour de repos par semaine.";
$string['restdayrule'] = 'Rappel : au moins un jour de repos est requis chaque semaine.';
$string['restdaywarning'] = "Attention : au moins une semaine sélectionnée ne comporte aucun jour de repos.";
$string['periods'] = 'Plages de stage';
$string['stagesummary'] = 'Le stage';
$string['conventionfollowup'] = "Suivi de la convention";
$string['stagestoevaluate'] = 'Stages à évaluer';
$string['adminsectionrequirements'] = "Ce que les étudiants doivent faire";
$string['adminsectionconventions'] = 'Conventions de stage';
$string['adminsectionnotifications'] = 'Notifications';
$string['notifications_desc'] = "Activer l'évaluation par le maître de stage et personnaliser le "
    . "texte des e-mails envoyés par l'activité.";
$string['adminsectionteachers'] = 'Encadrement des étudiants';
$string['adminsectionsetup'] = "Mise en route de l'activité";
$string['adminsectionpage'] = 'Page';
$string['adminsectionpurpose'] = 'À quoi elle sert';
$string['managethemes_desc'] = "Les thématiques de stage proposées aux étudiants : leur nom, leur caractère obligatoire ou non, les années d'étude concernées et la durée requise pour chacune.";
$string['manageyearrequirements_desc'] = "La durée totale de stage exigée pour chaque année d'étude, toutes thématiques confondues, ainsi que l'obligation de mobilité internationale.";
$string['conventiontemplates_desc'] = "Les gabarits PDF proposés aux étudiants au moment de leur demande de convention, les logos et les informations de l'établissement qui figurent en première page.";
$string['manageteachers_desc'] = "L'attribution des enseignants référents aux étudiants : chaque étudiant doit en avoir un pour pouvoir demander sa convention.";
$string['importfromcourse_desc'] = "Récupérer thématiques, gabarits, logos et informations d'établissement depuis une autre instance de l'activité, pour ne pas tout ressaisir à chaque nouveau cours.";
$string['transferstudent'] = 'Transférer un étudiant';
$string['transferstudent_desc'] = "Déplacer un étudiant et tous ses stages vers une autre instance de l'activité (redoublement, changement de promotion, réorientation), pour que son bilan le suive au lieu de rester dans le cours qu'il quitte.";
$string['transferstudent_help'] = "Déplace un étudiant et tous ses stages vers une autre instance de l'activité, généralement dans un autre cours. Les stages sont déplacés et non copiés : ils disparaissent de ce cours-ci. Un récapitulatif de ce qui sera transféré vous sera présenté avant toute modification.";
$string['transfertarget'] = 'Activité de destination';
$string['transfertarget_help'] = "Seules les instances de l'activité sur lesquelles vous avez vous-même le droit d'enregistrer des stages sont proposées. L'étudiant doit déjà être inscrit au cours correspondant.";
$string['transfersource'] = 'Activité de départ';
$string['transferpreview'] = 'Préparer le transfert';
$string['transfersummary'] = 'Transfert à effectuer';
$string['transferentries'] = 'Stages qui seront transférés';
$string['transferentrycount'] = 'Nombre de stages';
$string['transferreportcount'] = 'Documents de rapport de stage transférés';
$string['transferconfirm'] = 'Confirmer le transfert';
$string['transferirreversible'] = "Le transfert n'est pas réversible : pour ramener l'étudiant dans ce cours, il faudra refaire un transfert en sens inverse depuis l'activité de destination.";
$string['transferdone'] = '{$a->count} stage(s) de {$a->student} transféré(s) vers « {$a->target} ».';
$string['transfernotargets'] = "Aucune autre instance de l'activité sur laquelle vous pouvez enregistrer des stages n'a été trouvée.";
$string['transfernoentries'] = "Cet étudiant n'a aucun stage dans cette activité : il n'y a rien à transférer.";
$string['transfernotenrolled'] = "L'étudiant n'est pas inscrit au cours « {\$a} ». Inscrivez-le d'abord : sans inscription, ses stages n'apparaîtraient dans aucun tableau de bord de la destination.";
$string['transferunmatchedthemes'] = "Ces thématiques n'existent pas dans l'activité de destination : {\$a}. Créez-les-y d'abord (avec exactement le même nom), ou utilisez « Importer depuis un autre cours » : sans elles, les stages concernés perdraient leur rattachement et fausseraient le bilan de l'étudiant.";
$string['transferunmatchedtemplates'] = "Ces gabarits de convention n'existent pas dans l'activité de destination : {\$a}. Les stages concernés seront transférés sans gabarit : leur convention déjà signée reste disponible, mais sa regénération en PDF nécessitera d'en rechoisir un.";
$string['transferdroppedanswers'] = "{\$a} réponse(s) d'évaluation seront supprimées : les questions correspondantes n'existent pas dans les thématiques de destination.";
$string['transferreferentteachers'] = "L'attribution des enseignants référents ({\$a}) n'est pas transférée : elle est propre au cours. Pensez à attribuer un enseignant référent à l'étudiant dans le cours de destination.";
$string['rejectstageheading'] = 'Refuser la saisie';
$string['rejectstageheading_help'] = "Renvoie la saisie à l'étudiant pour correction. Le motif ci-dessous lui est transmis : il est obligatoire.";
$string['dates'] = 'Dates';
$string['periodstart'] = 'Début';
$string['periodend'] = 'Fin';
$string['addperiod'] = 'Ajouter une plage';
$string['removeperiod'] = 'Retirer';
$string['periods_help'] = "Un stage peut comporter plusieurs plages de dates non contiguës (ex : deux séjours séparés). L'étudiant choisira ses jours de stage effectifs parmi ces plages lors de son auto-évaluation.";
$string['periodsrequired'] = "Renseignez au moins une plage de dates : les dates du stage en sont déduites.";
$string['periodendbeforestart'] = "La date de fin d'une plage ne peut pas précéder sa date de début.";
$string['periodstartinpast'] = "Une convention ne peut pas être demandée pour un stage déjà commencé : la date "
    . "de début doit être aujourd'hui ou plus tard. Si le stage a déjà commencé, adressez-vous à la DEVE.";
$string['selfevalnotstartedyet'] = "Votre stage n'a pas encore commencé : l'auto-évaluation sera accessible à "
    . 'partir de sa date de début.';
$string['selfevalfrom'] = 'Auto-évaluation à partir du {$a}';
$string['periodsoverlap'] = 'Deux plages de dates se recoupent ({$a->first} et {$a->second}). Les mêmes journées seraient comptées deux fois : corrigez-les pour qu\'elles ne se chevauchent pas.';
$string['conventionsignaturedate'] = 'Date : ............................';
$string['noperiodsdefined'] = "Aucune plage de dates n'a été définie pour ce stage.";
$string['workdayssaved'] = 'Les jours de stage effectifs ont été enregistrés.';
$string['totalrequiredduration'] = 'Durée totale requise (jours)';
$string['managethemedurations'] = 'Durées par année';
$string['durationperyear'] = 'Par année (voir Durées par année)';
$string['durationflatignored'] = "Une durée unique de {\$a} jour(s) est définie sur cette thématique (voir sa fiche) : elle est utilisée à la place des durées par année ci-dessous, qui sont ignorées.";
$string['themedurationssaved'] = 'Durées enregistrées.';
$string['manageyearrequirements'] = 'Durées totales requises par année';
$string['yearrequirementssaved'] = 'Durées totales enregistrées.';
$string['yearrequirements_help'] = "Durée totale de stage obligatoire requise pour chaque année d'étude, toutes thématiques confondues. Les stages complémentaires ne comptent pas dans ce bilan.";
$string['yeartotals'] = "Bilan par année d'étude";
$string['validatedyears'] = 'Validées : {$a}';
$string['status'] = 'Statut';
$string['mandatory'] = 'Obligatoire';
$string['sortorder'] = 'Ordre';
$string['studentselfeval'] = "Auto-évaluation de l'étudiant";
$string['teachereval'] = "Évaluation de l'enseignant";
$string['devecomment'] = 'Commentaire DEVE';
$string['student'] = 'Étudiant';
$string['referentteachers'] = 'Enseignants référents';
$string['currentreferentteachers'] = 'Enseignant(s) référent(s) actuel(s)';
$string['noreferentteacher'] = 'Aucun';
$string['availableteachers'] = 'Enseignants disponibles';
$string['selectedteachers'] = 'Enseignants référents sélectionnés';
$string['addselected'] = 'Ajouter';
$string['removeselected'] = 'Retirer';

// Statuses.
$string['status_enregistre'] = 'Enregistré';
$string['status_evaletudiant'] = 'Évalué par l\'étudiant';
$string['status_evalenseignant'] = "Évalué par l'enseignant";
$string['status_validedeve'] = 'Validé DEVE';
$string['status_nonvalide'] = 'Non validé';
$string['status_annule'] = 'Annulé';
$string['themedone'] = 'Complété';
$string['themetodo'] = 'À compléter';

// Actions / buttons.
$string['addtheme'] = 'Ajouter une thématique';
$string['evalquestions'] = "Questions d'évaluation";
$string['addquestion'] = 'Ajouter une question';
$string['evaltype'] = "Formulaire concerné";
$string['evaltype_student'] = "Auto-évaluation de l'étudiant";
$string['evaltype_teacher'] = "Évaluation de l'enseignant";
$string['qtype'] = 'Type de question';
$string['qtype_choice'] = 'Choix multiples';
$string['qtype_text'] = 'Commentaire libre';
$string['questionlabel'] = 'Intitulé de la question';
$string['questionlabelen'] = 'Intitulé de la question (anglais, maître de stage)';
$string['choiceoptions'] = 'Options du QCM (une par ligne)';
$string['choiceoptionsen'] = 'Options du QCM en anglais (une par ligne, maître de stage)';
$string['questionlangen'] = 'Version anglaise';
$string['questionlangen_help'] = "Si la convention de l'étudiant est en anglais, le maître de stage voit "
    . "cette version anglaise à la place de la version française. Laissez vide pour utiliser une traduction "
    . 'automatique littérale de la version française n\'est pas prévue : sans traduction, le texte français '
    . "sera utilisé par défaut.";
$string['choiceoptionsrequired'] = 'Veuillez saisir au moins une option, une par ligne.';
$string['questionrequired'] = 'Réponse obligatoire';
$string['questionsaved'] = 'La question a été enregistrée.';
$string['questiondeleted'] = 'La question a été retirée de cette thématique.';
$string['questionattached'] = 'La question a été associée à cette thématique.';
$string['noanswer'] = 'Non renseigné';
$string['noquestionsyet'] = "Aucune question définie pour ce formulaire : un commentaire libre générique sera utilisé.";
$string['confirmdeletequestion'] = "Retirer cette question de cette thématique ? Si elle n'est utilisée par aucune autre thématique, elle sera supprimée avec les réponses associées.";
$string['assignedthemes'] = 'Thématiques concernées';
$string['assignedthemes_help'] = "Sélectionnez une ou plusieurs thématiques : la même question (intitulé, options) sera utilisée pour chacune d'elles, ce qui évite de la recréer.";
$string['themesrequired'] = 'Veuillez sélectionner au moins une thématique.';
$string['reusequestion'] = 'Associer';
$string['selectexistingquestion'] = 'Réutiliser une question existante...';
$string['savebulkchanges'] = 'Enregistrer les modifications';
$string['toggle'] = 'Basculer obligatoire';
$string['evaluate'] = 'Évaluer';
$string['viewevaluation'] = "Voir l'évaluation";
$string['validate'] = 'Valider';
$string['selectall'] = 'Tout sélectionner';
$string['bulkvalidateselected'] = 'Valider la sélection';
$string['pilotage'] = 'Tableau de pilotage';
$string['viewdetails'] = 'Voir le détail';
$string['pendingstages'] = 'Stages en attente';
$string['totalretainedshort'] = 'Durée totale retenue';
$string['searchstudent'] = "Rechercher un étudiant...";
$string['allthemes'] = 'Toutes les thématiques';
$string['allstatuses'] = 'Toutes les étapes';
$string['resetfilters'] = 'Réinitialiser';
$string['registeredon'] = "Date d'enregistrement";
$string['markinvalid'] = 'Marquer non validé';
$string['rejectcomment'] = 'Motif de non-validation';
$string['entrynoteditable'] = "Cette saisie a déjà été évaluée et n'est plus modifiable. "
    . "Seule la DEVE peut la réinitialiser pour permettre une nouvelle saisie.";
$string['resetentry'] = 'Réinitialiser (autoriser une nouvelle saisie)';
$string['entryreset'] = 'La saisie a été réinitialisée : une nouvelle auto-évaluation est possible.';
$string['confirmresetentry'] = "Réinitialiser cette saisie ? L'étudiant et l'enseignant référent pourront à nouveau la modifier.";
$string['cancelentry'] = 'Annuler ce stage';
$string['confirmcancelentry'] = "Annuler ce stage ? La saisie sera conservée telle quelle, mais son statut passera "
    . 'à "Annulé" de façon définitive. Merci de préciser le motif ci-dessous.';
$string['cancelcomment'] = "Motif de l'annulation";
$string['cancelledby'] = 'Annulé par';
$string['canceltime'] = "Date d'annulation";
$string['stagecancelled'] = 'Le stage a été annulé.';
$string['evaluatedby'] = 'Évalué par';
$string['onlyunassigned'] = 'Étudiants sans référent uniquement';
$string['selfevalnotifsubject'] = 'Auto-évaluation de stage à évaluer - {$a}';
$string['selfevalnotifbody'] = "{\$a->student} vient de s'auto-évaluer pour son stage \"{\$a->stage}\". "
    . "Vous pouvez consulter et évaluer cette saisie ici : {\$a->url}";
$string['generateconvention'] = 'Générer la convention';
$string['viewconvention'] = 'Consulter la convention';
$string['downloadstarting'] = 'Le téléchargement démarre. Vous allez être ramené à la page précédente.';
$string['downloadrestart'] = 'Relancer le téléchargement';
$string['backtolist'] = 'Revenir à la liste';
$string['includesignatureblock'] = "Ajouter un cadre de signatures (stagiaire, maître de stage, "
    . "responsable de l'organisme d'accueil, enseignant.e référent.e, établissement) en bas de la "
    . 'première page, pour une convention imprimée destinée à être signée à la main.';
$string['conventionsignatures'] = 'Signatures';
$string['conventionsignaturestudent'] = 'Le/la stagiaire';
$string['conventionsignaturetutor'] = 'Le maître de stage';
$string['conventionsignaturehostrepresentative'] = "Le/la responsable de l'organisme d'accueil";
$string['conventionsignaturereferentteacher'] = "L'enseignant.e référent.e";
$string['conventionsignatureestablishment'] = "L'établissement d'enseignement";
$string['conventionsignaturename'] = 'Nom : {$a}';
$string['conventionsignaturedelegation'] = 'Par délégation du chef d\'établissement';
$string['conventionsignaturedelegationname'] = 'Par délégation du chef d\'établissement : {$a}';
$string['conventionestablishmentsignatory'] = 'Personne ayant délégation de signature';
$string['conventionestablishmentsignatory_help'] = "Nom de la personne ayant délégation de signature du chef "
    . "d'établissement (à défaut d'une signature par le chef d'établissement lui-même). Préaffiché dans le "
    . "cadre de signatures de la convention imprimée, quand cette option est cochée lors de la génération "
    . 'de la convention.';
$string['conventiontitle'] = 'Convention de stage';
$string['conventionestablishment'] = "Établissement d'enseignement";
$string['conventionestablishmentname'] = 'Nom';
$string['conventionestablishmentaddress'] = 'Adresse';
$string['conventionestablishmentrepresentative'] = 'Représenté par';
$string['conventionestablishmentrepresentativetitle'] = 'Qualité du représentant / de la représentante';
$string['conventionestablishmentphone'] = 'Téléphone';
$string['conventionestablishmentemail'] = 'Courriel';
$string['conventionestablishment_help'] = "Ces informations sont affichées sur la page 1 de toutes les "
    . 'conventions de ce stage. Laissez un champ vide si non applicable ; le nom est "VetAgro Sup" par défaut '
    . 'tant que rien n\'est renseigné ici.';
$string['conventionestablishmentsaved'] = "Les informations de l'établissement ont été enregistrées.";
$string['conventionhoststructure'] = "Structure d'accueil";
$string['conventionhoststructurename'] = 'Structure';
$string['conventionstudent'] = 'Le/la stagiaire';
$string['conventionthemeduration'] = 'Thématique et durée';
$string['conventionsupervision'] = 'Encadrement';
$string['conventiontutor'] = "Tuteur en structure d'accueil";
$string['conventiontemplatemissing'] = "Le fichier PDF du gabarit de convention sélectionné est "
    . 'introuvable. Téléversez-le à nouveau depuis la page des gabarits de convention avant de '
    . 'générer cette convention.';
$string['conventionfpdimissing'] = "La librairie FPDI nécessaire à la génération des conventions "
    . '(mod/stage/thirdparty/vendor) est introuvable sur ce site. Contactez un administrateur.';

// Conventions : demande, gabarits, logos, workflow DEVE.
$string['conventions'] = 'Conventions de stage';
$string['requestconvention'] = 'Demander la convention';
$string['requestconvention_help'] = "Choisissez le modèle de convention correspondant à votre stage. "
    . "La DEVE traitera ensuite votre demande (édition puis signature) ; l'auto-évaluation ne sera "
    . 'possible qu\'une fois la convention signée.';
$string['conventionalreadyrequested'] = 'La convention de ce stage a déjà été demandée.';
$string['conventionrequested'] = 'La demande de convention a été envoyée à la DEVE.';
$string['conventionnotedited'] = "La convention de ce stage n'est pas (ou plus) au statut « éditée » : elle n'est "
    . 'donc pas accessible depuis ce lien.';
$string['conventionnotemplatechosen'] = "Aucun modèle de convention n'a été choisi pour ce stage.";
$string['conventionpaperrequest'] = 'Convention papier (cadre de signatures)';
$string['conventionpaperrequest_help'] = "Cochez si un exemplaire imprimé de la convention, avec un cadre de "
    . "signatures à remplir à la main, est nécessaire (par exemple si l'organisme d'accueil ne peut pas signer "
    . 'électroniquement). Sinon, la convention signée électroniquement suffit.';
$string['conventionpaperrequestedbystudentonly'] = "Convention papier demandée par l'étudiant.";
$string['conventionpaperrequestedbyteacheronly'] = "Convention papier demandée par l'enseignant référent.";
$string['conventionpaperrequestedbyboth'] = "Convention papier demandée par l'étudiant et par l'enseignant référent.";
$string['conventionnotsignedyet'] = "La convention de stage doit être signée par la DEVE avant de pouvoir vous "
    . 'auto-évaluer. Consultez le statut de votre convention sur votre tableau de bord.';
$string['conventionstatus'] = 'Statut de la convention';
$string['conventionstatus_none'] = 'Non demandée';
$string['conventionstatus_requested'] = 'Demandée';
$string['conventionstatus_edited'] = 'Éditée';
$string['conventionstatus_signed'] = 'Signée';
$string['conventionstatus_signvet'] = 'Signée (StageVet)';
$string['conventionstatus_rejected'] = 'Refusée';
$string['conventionmarksigned'] = 'Marquer signée';
$string['conventionmarkedsigned'] = "La convention a été marquée comme signée : l'étudiant et l'enseignant "
    . 'référent peuvent maintenant procéder aux évaluations. Si un PDF signé a été fourni, il est '
    . "téléchargeable par l'étudiant depuis son tableau de bord.";
$string['conventionsignedfile'] = 'Convention signée (PDF)';
$string['conventionsignedfile_help'] = "Facultatif : téléversez le PDF de la convention effectivement signée "
    . "(scan du document papier). S'il est fourni, l'étudiant pourra le télécharger depuis son tableau de bord. "
    . "Dans tous les cas, valider ce formulaire fait passer le stage aux évaluations.";
$string['conventionsignedfilemissing'] = "Le PDF de la convention signée n'a pas été trouvé.";
$string['downloadsignedconvention'] = 'Télécharger la convention signée';
$string['noconventionrequests'] = 'Aucune demande de convention pour le moment.';
$string['conventionreview'] = 'Générer la convention';
$string['conventionreviewfor'] = "Demande de convention de {\$a} : vérifiez et complétez si besoin les "
    . 'informations ci-dessous avant de valider, ou refusez la demande avec un commentaire pour que '
    . "l'étudiant puisse la corriger.";
$string['conventionteachervalidatefor'] = "Demande de convention de {\$a} : vérifiez et corrigez si besoin les "
    . "informations ci-dessous, puis validez pour la transmettre à la DEVE, ou refusez-la avec un commentaire "
    . "pour que l'étudiant puisse la corriger.";
$string['conventionnotrequested'] = "Cette convention n'est pas (ou plus) en attente de revue.";
$string['validateconvention'] = 'Valider';
$string['rejectconvention'] = 'Refuser';
$string['conventionrejectcomment'] = 'Commentaire (envoyé à l\'étudiant en cas de refus)';
$string['conventionrejected'] = "La demande de convention a été refusée. L'étudiant en a été informé par courriel.";
$string['conventionrejectedwithcomment'] = 'Refusée : {$a}';
$string['conventionrejectedby'] = 'Refusée par';
$string['conventionvalidatedby'] = "Validée par l'enseignant.e référent.e";
$string['conventioneditedby'] = 'Éditée par';
$string['conventionsignedby'] = 'Signée par';
// Libellés de colonnes propres à l'export Excel (export.php) : les dates des étapes déjà
// nommées ci-dessus, et les informations qui n'apparaissent nulle part ailleurs sous forme de
// colonne.
$string['conventionteachervalidatedby'] = "Validée par l'enseignant.e référent.e";
$string['conventionrequesttime'] = 'Date de demande de convention';
$string['conventionteachervalidatetime'] = 'Date de validation enseignant.e';
$string['conventionedittime'] = "Date d'édition de la convention";
$string['conventionsigntime'] = 'Date de signature de la convention';
$string['conventionrejecttime'] = 'Date de refus de la convention';
$string['workdayscount'] = 'Nombre de jours effectifs sélectionnés';
$string['teachervalidationtime'] = "Date d'évaluation enseignant.e";
$string['tutorevaltime'] = "Date d'évaluation du maître de stage";
$string['tutorevalbypassedcolumn'] = 'Évaluation du maître de stage ignorée';
$string['reportfilescount'] = 'Nombre de documents déposés';
$string['devevalidatedby'] = 'Validé par la DEVE';
$string['devevalidationtime'] = 'Date de validation DEVE';
$string['timecreated'] = "Date d'enregistrement";
$string['exportanswers'] = 'Réponses aux questionnaires';
$string['exportentryid'] = 'N° de stage';
$string['answer'] = 'Réponse';
$string['conventionrejectedexplain'] = 'Votre demande de convention a été refusée par la DEVE, pour le motif suivant : '
    . '"{$a}". Merci de corriger votre demande ci-dessous et de la soumettre à nouveau.';
$string['conventionrejectednotifsubject'] = 'Convention de stage refusée : {$a}';
$string['conventionrejectednotifbody'] = "Votre demande de convention pour le stage \"{\$a->stage}\" a été refusée "
    . "par la DEVE, pour le motif suivant :\n\n{\$a->comment}\n\n"
    . "Merci de corriger votre demande et de la soumettre à nouveau :\n{\$a->url}";
$string['noreferentteacheryet'] = "Aucun enseignant référent ne vous a encore été attribué pour ce stage. "
    . 'Contactez la DEVE.';
$string['conventionvalidatedpdferror'] = 'La demande a été validée, mais la génération du PDF a échoué : {$a} '
    . 'Vous pourrez retélécharger la convention depuis cette liste une fois le problème résolu.';
$string['conventionstatus_teacherpending'] = "En attente de l'enseignant référent";
$string['conventionstatus_exempt'] = 'Sans convention';
$string['exemptfromconvention'] = 'Dispenser de convention';
$string['exemptfromconvention_help'] = "Si coché, ce stage ne nécessite aucune convention : son statut de convention passe directement à « Sans convention » et l'auto-évaluation de l'étudiant est immédiatement ouverte, sans attendre de demande ni de signature.";
$string['conventionrequireteachervalidation'] = "Exiger la validation de l'enseignant.e référent.e avant transmission à la DEVE";
$string['conventionrequireteachervalidation_help'] = "Si activé, une demande de convention soumise par un étudiant "
    . "doit d'abord être validée par l'un de ses enseignants référents avant d'apparaître dans la liste des "
    . "demandes à traiter par la DEVE. L'enseignant peut aussi refuser la demande avec un commentaire, renvoyé "
    . "à l'étudiant pour correction, exactement comme un refus par la DEVE.";
$string['conventionsettingssaved'] = 'Les paramètres généraux des conventions ont été enregistrés.';
$string['conventionteachervalidation'] = 'Demandes de convention à valider';
$string['conventionteachervalidate'] = 'Valider la demande';
$string['conventionteachervalidated'] = "La demande de convention a été validée et transmise à la DEVE.";
$string['conventionteacherpendingnotifsubject'] = 'Convention de stage à valider : {$a}';
$string['conventionteacherpendingnotifbody'] = 'Un.e étudiant.e que vous encadrez a soumis une demande de '
    . "convention pour le stage \"{\$a->stage}\", qui attend votre validation avant transmission à la DEVE :\n"
    . "{\$a->url}";
$string['noconventionteachervalidations'] = "Aucune demande de convention en attente de votre validation.";
$string['generalsettings'] = 'Paramètres généraux';
$string['conventiontemplates'] = 'Gabarits de convention';
$string['addconventiontemplate'] = 'Ajouter un gabarit';
$string['conventionrequestdate'] = 'Date de la demande';
$string['conventiontemplatename'] = 'Nom du gabarit';
$string['conventiontemplatefile'] = 'Fichier PDF (articles, pages 2 à 4)';
$string['conventiontemplatefilerequired'] = 'Veuillez sélectionner un fichier PDF.';
$string['conventiontemplatesaved'] = 'Le gabarit a été enregistré.';
$string['conventiontemplatedeleted'] = 'Le gabarit a été supprimé.';
$string['conventiontemplateinuse'] = 'Impossible de supprimer : ce gabarit est utilisé par au moins une demande de convention.';
$string['confirmdeleteconventiontemplate'] = 'Supprimer ce gabarit de convention ?';
$string['noconventiontemplatesyet'] = "Aucun gabarit de convention n'a encore été créé par la DEVE.";
$string['conventionlogos'] = 'Logos de la convention';
$string['conventionlogos_help'] = "Ces deux logos (PNG) sont affichés en haut de la page 1 de toutes les "
    . 'conventions de ce stage : à gauche et à droite.';
$string['conventionlogoleft'] = 'Logo en haut à gauche';
$string['conventionlogoright'] = 'Logo en haut à droite';
$string['conventionlogossaved'] = 'Les logos ont été enregistrés.';
$string['conventionlang'] = 'Langue de la convention';
$string['conventionlang_fr'] = 'Français (standard)';
$string['conventionlang_en'] = 'Anglais';
$string['conventiontemplatelangmismatch'] = "Le gabarit sélectionné ne correspond pas à la langue choisie.";

// Convention : informations complémentaires demandées à l'étudiant.
$string['conventionyearsituation'] = "Situation";
$string['conventionyearsituation_normal'] = 'Année normale';
$string['conventionyearsituation_redoublant'] = 'Redoublant.e';
$string['conventionyearsituation_detteue'] = "Dette d'UE";
$string['conventionstagetype'] = 'Type de stage';
$string['conventionstagetype_obligatoire'] = 'Stage obligatoire';
$string['conventionstagetype_complementaire'] = 'Stage complémentaire (EP)';
$string['conventionreferentteacher'] = 'Enseignant.e référent.e';
$string['conventionreferentteacherstatus'] = 'Statut';
$string['conventionreferentteacherstatusvalue'] = 'Enseignant';
$string['conventionreferentteacheremail'] = 'Courriel';
$string['conventionbirthdate'] = 'Date de naissance';
$string['conventionstudentaddress'] = 'Adresse';
$string['conventionstudentphone'] = 'Téléphone';
$string['conventionhostaddress'] = "Adresse de l'organisme";
$string['conventionhostrepresentative'] = "Représenté par";
$string['conventionhostrepresentativetitle'] = 'Qualité du représentant / de la représentante';
$string['conventionhostservice'] = 'Service dans lequel le stage sera effectué';
$string['conventionhostphone'] = "Téléphone de l'organisme";
$string['conventionhostemail'] = "Courriel de l'organisme";
$string['conventionhostlocation'] = "Lieu du stage (si différent de l'adresse de l'organisme)";
$string['conventionhostlocation_help'] = "À remplir uniquement si le stage se déroule à une adresse différente de celle de l'organisme d'accueil.";
$string['conventiontutorname'] = 'Nom et prénom du tuteur / de la tutrice de stage';
$string['conventiontutorfunction'] = 'Fonction';
$string['conventiontutorphone'] = 'Téléphone';
$string['conventiontutoremail'] = 'Courriel';
$string['conventionmodalities'] = 'Modalités particulières du stage (art. 3.2)';
$string['conventionnightpresence'] = 'Présence de nuit';
$string['conventionsundaypresence'] = 'Présence le dimanche';
$string['conventionholidaypresence'] = 'Présence les jours fériés';
$string['conventionhomebased'] = 'Stage au domicile';
$string['conventionothermodality'] = 'Autre modalité particulière';
$string['conventiongratification'] = 'Montant de la gratification (par mois, en euros)';
$string['conventionleave'] = "Congés et autorisations d'absence (art. 10.1)";
$string['conventionhasleave'] = "Ce stage comporte des congés ou autorisations d'absence";
$string['conventionleavedays'] = 'Nombre de jours de congés';
$string['conventionleavemodalities'] = "Modalités de congés et d'autorisation d'absence";

// Messages.
$string['stagesaved'] = 'Le stage a été enregistré.';
$string['themesaved'] = 'La thématique a été enregistrée.';
$string['themedeleted'] = 'La thématique a été supprimée.';
$string['themevisibilitytoggled'] = "L'activation de la thématique a été mise à jour.";
$string['themevisible_help'] = "Cliquez sur Oui/Non dans la colonne « Visible » pour activer ou désactiver une "
    . "thématique pour ce cours. Une thématique désactivée n'est plus proposée à l'enregistrement d'un stage "
    . "(par la DEVE ou par l'étudiant), mais reste affichée ici et sur les stages déjà enregistrés dessus.";
$string['themeinuse'] = 'Impossible de supprimer : des stages utilisent cette thématique.';
$string['bulkthemessaved'] = 'Les thématiques ont été mises à jour.';
$string['teachersassigned'] = 'Les enseignants référents ont été mis à jour.';
$string['evalsaved'] = "L'évaluation a été enregistrée.";
$string['bulkvalidated'] = '{$a} stage(s) ont été validés.';
$string['bulkregistered'] = '{$a} stage(s) ont été enregistrés.';
$string['bulkduplicatesskipped'] = 'Déjà enregistrés sur cette thématique avec ces mêmes dates, ignorés : {$a}';
$string['nothemesyet'] = "Aucune thématique n'a encore été créée.";
$string['nomandatorythemes'] = 'Aucune thématique obligatoire pour le moment.';
$string['nostages'] = "Aucun stage n'a été déclaré.";
$string['nostudents'] = "Aucun étudiant inscrit à ce cours.";
$string['noteachers'] = "Aucun enseignant référent potentiel n'est inscrit à ce cours.";
$string['noassignedstudents'] = "Aucun étudiant ne vous est attribué pour l'instant.";
$string['nopendingstages'] = 'Aucun stage en attente de validation.';
$string['confirmdeletetheme'] = 'Supprimer cette thématique ?';
$string['totalretained'] = 'Durée totale retenue : {$a} jours';
$string['totalcomplementary'] = 'Dont stages complémentaires (EP) : {$a} jours (hors décompte)';
$string['complementarystages'] = 'Stages complémentaires (EP)';
$string['summary'] = 'Synthèse';
$string['summaryitem'] = 'Indicateur';
$string['summaryvalue'] = 'Valeur';
$string['summarytotaldays'] = 'Durée totale retenue';
$string['summaryyearsdone'] = 'Années validées';
$string['summarythemesdone'] = 'Thématiques obligatoires validées';
$string['summaryabroaddays'] = "Mobilité internationale";
$string['summarycomplementarydays'] = 'Dont stages complémentaires (EP, hors décompte)';
$string['progressofdays'] = '{$a->retained} / {$a->required} jours';
$string['retaineddaysonly'] = '{$a} jours';
$string['remainingduration'] = 'Reste à faire (jours)';
$string['objective'] = 'Objectif';
$string['yeartotalobjective'] = "Durée totale de l'année";
$string['completebyyear'] = 'À compléter au plus tard en';
$string['numstages'] = '{$a} stage(s) déclaré(s)';

// Headings.
$string['evaluatestage'] = 'Évaluer le stage de {$a}';
$string['validatestage'] = 'Valider le stage de {$a}';

// Évaluation du maître de stage et personnalisation des e-mails.
$string['tutorevaluationenabled'] = 'Activer l\'évaluation par le maître de stage';
$string['tutorevaluationenabled_help'] = "Si activé, le maître de stage (encadrant en entreprise, sans compte "
    . "Moodle) reçoit par courriel un lien à jeton unique lui permettant de répondre au questionnaire "
    . "d'évaluation défini pour la thématique du stage, dès que l'étudiant s'auto-évalue. Sa réponse est "
    . "ensuite affichée à l'enseignant référent et à la DEVE au moment de leur propre évaluation.";
$string['emailkeyselfeval'] = "Notification d'auto-évaluation (à l'enseignant référent)";
$string['emailkeyteacherpending'] = "Notification de convention à valider (à l'enseignant référent)";
$string['emailkeystudentrejected'] = "Notification de convention refusée (à l'étudiant)";
$string['emailkeytutorrequest'] = "Invitation à évaluer le stage (au maître de stage)";
$string['tutorevalnotifsubject'] = 'Évaluation du stage de {$a}';
$string['tutorevalnotifbody'] = "Vous encadrez actuellement {\$a->student} dans le cadre de son stage "
    . "\"{\$a->stage}\". Merci de bien vouloir évaluer ce stage en suivant ce lien, qui ne nécessite pas de "
    . "compte :\n{\$a->url}";
$string['emailkeyconventionreminder'] = "Rappel de convention non signée (à l'étudiant)";
$string['conventionremindernotifsubject'] = 'Convention de stage non signée : {$a}';
$string['conventionremindernotifbody'] = "Bonjour {\$a->student},\n\nVotre stage \"{\$a->theme}\" commence le "
    . "{\$a->datestart}, soit dans moins de {\$a->days} jours, et sa convention n'est toujours pas signée. Sans "
    . "convention signée, le stage ne peut pas débuter.\n\nMerci de régulariser votre demande de convention au "
    . "plus vite :\n{\$a->url}";
$string['taskconventionreminders'] = 'Relance des conventions de stage non signées';
$string['tasktutorevaluationrequests'] = 'Envoi des invitations à évaluer le stage aux maîtres de stage';
$string['reportmode'] = 'Rapport de stage';
$string['reportmode_help'] = "Dépôt de documents demandé à l'étudiant lors de son auto-évaluation, sur le "
    . "modèle d'un devoir. Les documents déposés sont consultables par la DEVE, par l'enseignant référent de "
    . "l'étudiant et par les enseignants responsables de la thématique. « Obligatoire » empêche l'étudiant de "
    . "soumettre son auto-évaluation tant qu'il n'a déposé aucun document.";
$string['reportmode_none'] = 'Aucun';
$string['reportmode_optional'] = 'Facultatif';
$string['reportmode_required'] = 'Obligatoire';
$string['reportfiles'] = 'Rapport de stage';
$string['reportfiles_help'] = "Déposez ici votre rapport de stage et les éventuelles pièces jointes. Vous "
    . "pouvez y revenir tant que vous n'avez pas soumis votre auto-évaluation.";
$string['savereportfiles'] = 'Enregistrer les documents';
$string['reportfilessaved'] = 'Les documents de votre rapport de stage ont été enregistrés.';
$string['noreportfiles'] = 'Aucun document déposé.';
$string['reportfilemissing'] = "Ce document n'existe pas ou n'est plus disponible.";
$string['reportrequirednotice'] = "Le dépôt d'au moins un document est nécessaire pour pouvoir soumettre "
    . 'votre auto-évaluation.';
$string['reportrequiredmissing'] = "Votre auto-évaluation n'a pas été soumise : le dépôt d'au moins un "
    . "document est exigé pour cette thématique. Déposez votre rapport de stage ci-dessous, puis soumettez à "
    . 'nouveau votre auto-évaluation.';
$string['downloadallreports'] = 'Télécharger tous les rapports (.zip)';
$string['reportszipname'] = 'rapports-de-stage-{$a}';
$string['noreportstozip'] = "Aucun document n'a été déposé pour cette thématique.";
$string['reportszipfailed'] = "L'archive des rapports de stage n'a pas pu être construite.";
$string['themeteachers'] = 'Enseignants responsables';
$string['themeteachers_help'] = "Les enseignants responsables d'une thématique accèdent à tous les stages "
    . "faits sur cette thématique (onglet « Stages par thématique ») et aux rapports qui y sont déposés, quels "
    . "que soient les enseignants référents des étudiants concernés. Ils n'évaluent pas les stages : "
    . "l'évaluation reste le rôle de l'enseignant référent de chaque étudiant.";
$string['themeteacherssaved'] = 'Les enseignants responsables de la thématique ont été enregistrés.';
$string['themeteacherscount'] = '{$a} enseignant(s)';
$string['mythemestages'] = 'Stages par thématique';
$string['nostagesfortheme'] = 'Aucun stage ne correspond à cette thématique.';
$string['evaltype_tutor'] = 'Maître de stage';
$string['tutorevaluationenabledtheme'] = 'Évaluation par le maître de stage pour cette thématique';
$string['tutorevaluationenabledtheme_help'] = "N'a d'effet que si l'évaluation par le maître de stage est "
    . 'aussi activée globalement pour cette activité (page « Notifications »). Permet, une fois cette option '
    . "globale activée, de ne la proposer que pour certaines thématiques plutôt que pour toutes.";
$string['tutorevalheading'] = 'Évaluation du maître de stage';
$string['notutoreval'] = "Le maître de stage n'a pas encore répondu à son questionnaire d'évaluation.";
$string['tutorevallink'] = "Lien d'évaluation à transmettre au maître de stage";
$string['tutorevalresend'] = "Relancer le maître de stage par courriel";
$string['tutorevalresent'] = 'Le courriel de relance a été envoyé au maître de stage.';
$string['tutorevalresentfailed'] = "Le courriel n'a pas pu être envoyé : les coordonnées du maître de stage sont inconnues.";
$string['tutorevalbypass'] = "Ignorer cette évaluation (ne plus bloquer la validation)";
$string['confirmtutorevalbypass'] = "Ignorer l'évaluation du maître de stage pour ce stage ? "
    . "La validation par la DEVE ne sera plus bloquée par son absence de réponse.";
$string['tutorevalbypassed'] = "L'évaluation du maître de stage a été ignorée pour ce stage.";
$string['tutorevalbypassednotice'] = "L'évaluation du maître de stage a été ignorée par la DEVE et ne "
    . 'bloque plus la validation de ce stage.';
$string['tutorevalpagetitle'] = 'Évaluation du stage';
$string['tutorevalinvalidtoken'] = "Ce lien d'évaluation n'est plus valide.";
$string['tutorevalalreadysubmitted'] = 'Votre évaluation a bien été enregistrée, merci.';
$string['tutorevalsubmit'] = 'Envoyer mon évaluation';
$string['tutorevalintro'] = "Vous encadrez {\$a->student} dans le cadre de son stage \"{\$a->stage}\". "
    . "Merci de bien vouloir répondre au questionnaire d'évaluation ci-dessous.";
$string['tutorevalstudentlabel'] = 'Étudiant';
$string['tutorevaldateslabel'] = 'Dates du stage';
$string['notifications'] = 'Notifications et e-mails';
$string['notificationssettings'] = 'Personnalisation des e-mails envoyés';
$string['notificationssettings_help'] = "Pour chaque e-mail envoyé par l'activité, vous pouvez remplacer le "
    . "sujet et le corps par un texte personnalisé. Laissez les deux champs vides pour revenir au texte par "
    . "défaut. Le texte personnalisé n'est pas une chaîne de langue : utilisez la syntaxe {{variable}} (double "
    . "accolades) pour insérer les variables disponibles, listées sous chaque e-mail.";
$string['notificationssaved'] = 'Les e-mails personnalisés ont été enregistrés.';
$string['emailsubject'] = 'Sujet';
$string['emailbody'] = 'Corps du message';
$string['emailavailablevars'] = 'Variables disponibles : {$a}';
$string['emailresettodefault'] = 'Laissez les deux champs vides pour utiliser le texte par défaut.';

// Confidentialité (RGPD).
$string['supervisedstages'] = 'Stages suivis ou traités';
$string['answers'] = 'Réponses';
$string['conventiondetails'] = 'Détail de la convention';
$string['privacy:metadata:core_files'] = 'Fichiers rattachés à un stage : la convention signée et le rapport de stage.';
$string['privacy:metadata:stage_entry'] = "Un enregistrement par stage déclaré par ou pour un étudiant, avec ses évaluations et l'état de sa convention.";
$string['privacy:metadata:stage_entry:userid'] = "L'étudiant à qui le stage appartient.";
$string['privacy:metadata:stage_entry:themeid'] = 'La thématique du stage.';
$string['privacy:metadata:stage_entry:studyyear'] = "L'année d'étude au titre de laquelle le stage compte.";
$string['privacy:metadata:stage_entry:structure'] = "La structure d'accueil.";
$string['privacy:metadata:stage_entry:country'] = "Le pays, pour un stage à l'étranger.";
$string['privacy:metadata:stage_entry:datestart'] = 'La date de début du stage.';
$string['privacy:metadata:stage_entry:dateend'] = 'La date de fin du stage.';
$string['privacy:metadata:stage_entry:status'] = "L'avancement du stage dans le circuit de validation.";
$string['privacy:metadata:stage_entry:studentselfeval'] = "L'auto-évaluation rédigée par l'étudiant.";
$string['privacy:metadata:stage_entry:teacherid'] = "L'enseignant référent attribué au stage.";
$string['privacy:metadata:stage_entry:teachereval'] = "L'évaluation rédigée par l'enseignant référent.";
$string['privacy:metadata:stage_entry:tutoreval'] = "L'évaluation rédigée par le maître de stage.";
$string['privacy:metadata:stage_entry:deveuserid'] = 'Le membre de la DEVE ayant validé le stage.';
$string['privacy:metadata:stage_entry:devecomment'] = 'Le commentaire rédigé par la DEVE.';
$string['privacy:metadata:stage_entry:conventionstatus'] = "L'état de la convention de stage.";
$string['privacy:metadata:stage_entry:cancelcomment'] = "Le motif indiqué lors de l'annulation du stage.";
$string['privacy:metadata:stage_entry:timecreated'] = "La date d'enregistrement du stage.";
$string['privacy:metadata:stage_convention_detail'] = 'Les informations recueillies pour établir la convention de stage.';
$string['privacy:metadata:stage_convention_detail:studentbirthdate'] = "La date de naissance de l'étudiant.";
$string['privacy:metadata:stage_convention_detail:studentaddress'] = "L'adresse postale de l'étudiant.";
$string['privacy:metadata:stage_convention_detail:studentphone'] = "Le numéro de téléphone de l'étudiant.";
$string['privacy:metadata:stage_convention_detail:referentteacherid'] = "L'enseignant référent nommé dans la convention.";
$string['privacy:metadata:stage_convention_detail:tutorname'] = 'Le nom du maître de stage.';
$string['privacy:metadata:stage_convention_detail:tutorfunction'] = 'La fonction du maître de stage.';
$string['privacy:metadata:stage_convention_detail:tutorphone'] = 'Le numéro de téléphone du maître de stage.';
$string['privacy:metadata:stage_convention_detail:tutoremail'] = "L'adresse de courriel du maître de stage.";
$string['privacy:metadata:stage_convention_detail:gratificationamount'] = "Le montant de la gratification versée à l'étudiant.";
$string['privacy:metadata:stage_entry_period'] = "Les périodes composant un stage réparti sur plusieurs dates.";
$string['privacy:metadata:stage_entry_period:datestart'] = 'La date de début de la période.';
$string['privacy:metadata:stage_entry_period:dateend'] = 'La date de fin de la période.';
$string['privacy:metadata:stage_entry_workday'] = 'Les jours travaillés, pour les stages comptés jour par jour.';
$string['privacy:metadata:stage_entry_workday:workdate'] = 'Un jour travaillé.';
$string['privacy:metadata:stage_answer'] = 'Les réponses apportées aux questionnaires de stage.';
$string['privacy:metadata:stage_answer:questionid'] = 'La question à laquelle il est répondu.';
$string['privacy:metadata:stage_answer:answertext'] = 'La réponse apportée.';
$string['privacy:metadata:stage_entry_teacher'] = 'Le rattachement de chaque étudiant à son enseignant référent.';
$string['privacy:metadata:stage_entry_teacher:studentid'] = "L'étudiant suivi.";
$string['privacy:metadata:stage_entry_teacher:teacherid'] = "L'enseignant référent.";
$string['privacy:metadata:stage_theme_teacher'] = 'Les enseignants responsables de chaque thématique de stage.';
$string['privacy:metadata:stage_theme_teacher:themeid'] = 'La thématique de stage.';
$string['privacy:metadata:stage_theme_teacher:teacherid'] = 'L\'enseignant responsable de la thématique.';
