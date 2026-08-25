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
$string['importfromcourse_help'] = "Copiez les thématiques, gabarits de convention, logos et/ou informations "
    . "d'établissement d'une autre instance de l'activité (généralement dans un autre cours) vers celle-ci, "
    . "pour éviter de tout ressaisir à chaque nouveau cours. Seules les instances sur lesquelles vous avez "
    . "vous-même le droit de gérer les thématiques sont proposées comme source. Les éléments importés "
    . "s'ajoutent à ceux déjà présents ici (les thématiques et gabarits ne sont pas fusionnés avec les "
    . "existants ; les logos et informations d'établissement déjà renseignés sont remplacés).";
$string['importsource'] = 'Instance source';
$string['importthemes'] = 'Thématiques';
$string['importtemplates'] = 'Gabarits de convention';
$string['importlogos'] = 'Logos';
$string['importestablishment'] = "Informations de l'établissement d'enseignement";
$string['importnothingselected'] = 'Sélectionnez au moins un élément à importer.';
$string['noimportsources'] = "Aucune autre instance de l'activité sur laquelle vous pouvez gérer les "
    . 'thématiques n\'a été trouvée.';
$string['importdone'] = 'Import terminé : {$a->themes} thématique(s), {$a->templates} gabarit(s) de convention, '
    . '{$a->logos} logo(s), établissement {$a->establishmenttext}.';
$string['importdoneestablishmentyes'] = 'importé';
$string['importdoneestablishmentno'] = 'non importé';
$string['manageteachers'] = 'Attribuer les enseignants référents';
$string['devevalidation'] = 'Validation DEVE';
$string['teachervalidation'] = 'Validation enseignant';
$string['mystages'] = 'Mes stages';
$string['registerstages'] = 'Enregistrer des stages';
$string['importcsv'] = 'Importer un fichier CSV';
$string['exportexcel'] = 'Exporter en Excel';
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
    . "insensible aux accents et à la casse) parmi les étudiants inscrits au cours. Le nom de thématique doit "
    . "correspondre exactement à une thématique déjà créée dans cette activité : StageVet n'utilisant pas les "
    . "mêmes intitulés par défaut, créez au préalable des thématiques portant les mêmes noms que ceux utilisés "
    . "dans StageVet (ex. « THEME LIBRE / A2, A3, A4, A5 »). Chaque stage importé est enregistré avec le statut de "
    . 'convention "Signée (StageVet)" (déjà signée hors de ce plugin) : les coordonnées de convention disponibles '
    . "dans l'export sont tout de même enregistrées à titre de référence, sans déclencher de génération de PDF.";
$string['importstagevetnoheader'] = "Le fichier ne semble pas avoir de ligne d'en-tête reconnaissable. Vérifiez qu'il "
    . "s'agit bien d'un export StageVet non modifié.";
$string['importstageveterrornotheme'] = 'Ligne {$a} : aucune thématique renseignée.';
$string['importstagevetunknownstudentsreport'] = '{$a} étudiant(s) introuvable(s) parmi les inscrits au cours';
$string['importstagevetunknownthemesreport'] = '{$a} thématique(s) introuvable(s)';
$string['importstagevetreportline'] = '{$a->value} (ligne(s) {$a->lines})';
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
$string['studyyear'] = "Année d'étude";
$string['studyyear_unspecified'] = 'Non spécifiée (toutes années)';
$string['studyyear_n'] = '{$a}e année';
$string['status'] = 'Statut';
$string['mandatory'] = 'Obligatoire';
$string['sortorder'] = 'Ordre';
$string['studentselfeval'] = "Auto-évaluation de l'étudiant";
$string['teachereval'] = "Évaluation de l'enseignant";
$string['devecomment'] = 'Commentaire DEVE';
$string['student'] = 'Étudiant';
$string['referentteachers'] = 'Enseignants référents';

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
$string['choiceoptions'] = 'Options du QCM (une par ligne)';
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
$string['stagecancelled'] = 'Le stage a été annulé.';
$string['onlyunassigned'] = 'Étudiants sans référent uniquement';
$string['selfevalnotifsubject'] = 'Auto-évaluation de stage à évaluer - {$a}';
$string['selfevalnotifbody'] = "{\$a->student} vient de s'auto-évaluer pour son stage \"{\$a->stage}\". "
    . "Vous pouvez consulter et évaluer cette saisie ici : {\$a->url}";
$string['generateconvention'] = 'Générer la convention';
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
$string['conventionnotemplatechosen'] = "Aucun modèle de convention n'a été choisi pour ce stage.";
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
$string['numstages'] = '{$a} stage(s) déclaré(s)';

// Headings.
$string['evaluatestage'] = 'Évaluer le stage de {$a}';
$string['validatestage'] = 'Valider le stage de {$a}';
