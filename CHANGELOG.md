# Historique des versions

Une section par version de module, la plus récente en tête. Les dates sont celles du commit
qui a fixé le numéro dans `version.php`. Avant la version 0.5.0, les messages de commit
étaient succincts : les entrées correspondantes sont reconstituées d'après le contenu des
changements. À tenir à jour à chaque modification de `version.php`.

## Non publié

### Dépôt
- Ajout du texte de la licence (`LICENSE`, GNU GPL v3), de cet historique et de `.editorconfig`.
- `.DS_Store` retiré du suivi Git.
- Moodle 4.3 minimum (`requires = 2023100900`) pour les deux modules, et donc PHP 8.0 : avant
  Moodle 4.3, les noms de champs sont limités à 30 caractères, et
  `establishmentrepresentativetitle` (32) et `conventionrequireteachervalidation` (34) les
  dépassent ; le module ne s'installait pas sur Moodle 4.0 à 4.2.
- CI : JIT désactivé (`opcache.jit=disable`) ; son avertissement au démarrage de PHP faisait
  échouer l'installation de Moodle par `moodle-plugin-ci`.
- CI : les gabarits Mustache sont vérifiés sur leurs copies installées dans `moodle/` ; le
  vérificateur de Moodle refuse les fichiers situés hors de son dossier.
- CI : le job Moodle tourne désormais sur deux combinaisons, Moodle 4.3 avec PHP 8.0 et
  Moodle 4.5 avec PHP 8.2 ; le contrôle PHPDoc est bloquant ; le workflow peut être lancé à la
  main (`workflow_dispatch`). La couverture de code est mesurée (pcov) sur la combinaison la plus
  récente, résumée dans le journal du job et publiée en artefact Clover.
- Tests : cas tabulaires portés en `dataProvider` (filtre de statut, chevauchements de plages).
- CI : la suite PHPUnit tourne aussi sur MariaDB (Moodle 4.5, PHP 8.2), pour le SQL écrit par le
  module lui-même.
- CI : scénarios Behat joués sans JavaScript (serveur web intégré de PHP, profil `default`) sur
  la combinaison Moodle 4.5 / PHP 8.2.

### Les deux modules
- Nouvelle icône : mallette dont l'empreinte de patte est évidée, donc visible quelle que soit la
  couleur que Moodle applique au pictogramme ; l'ancienne, à pastille blanche, perdait l'empreinte.
  Les activités passent de la finalité « administration » (rose dans Moodle 4.4 et 4.5) à
  « communication » (orange, la couleur d'origine du module).

### mod_stage
- Restauration d'un export global : le téléphone du maître de stage reprenait celui de
  l'étudiant, les deux colonnes portant le même intitulé (« Téléphone », « Phone ») ; chaque
  colonne ne renseigne plus qu'un champ, dans l'ordre de la feuille.
- Listes d'évaluation : nouveau type de question « Note (de 1 à 5) », saisie par boutons radio,
  affichée en étoiles et reprise dans la colonne numérique de l'export Excel des évaluations.
  Scénarios Behat (`tests/behat/evaluation_rating.feature`) : création d'une question notée,
  notation par l'étudiant lue par l'enseignant référent, réinitialisation d'un stage validé.
  Générateur Behat de questions d'évaluation et page « Evaluation lists ».
- Réinitialisation d'un stage : la décision de la DEVE (validation ou non-validation, auteur,
  date, commentaire, durée retenue) est annulée avec elle ; la fiche et les exports ne la
  présentent plus comme acquise.
- Demande de convention : les formulaires signalent une valeur trop longue pour la base
  (gratification : 20 caractères, téléphones : 30, autres champs : 255) au lieu de faire échouer
  l'enregistrement ; le stage, sa demande, ses informations de convention, ses plages et sa
  check-list sont enregistrés ensemble, un échec ne laissant plus de stage à la demande vide ;
  les imports StageVet et la restauration tronquent ces valeurs. Scénario Behat
  (`tests/behat/student_register.feature`) : enregistrement d'un stage avec demande de convention
  par l'étudiant, gratification trop longue refusée sans rien enregistrer, puis corrigée ;
  générateur Behat de gabarits de convention.
- Convention en PDF : les dates (naissance, début et fin, plages) sont écrites dans la langue du
  gabarit, et non plus dans celle de la DEVE qui génère la convention ; le format de date était
  demandé par un appel à get_string() invalide, dont l'avertissement empêchait la génération en
  mode débogage.
- Événements Moodle : consultation de l'activité et chaque transition du circuit (stage
  enregistré, auto-évaluation, évaluations de l'enseignant et du maître de stage, validation,
  non-validation, réinitialisation, annulation ; convention demandée, validée par l'enseignant,
  éditée, signée, refusée, dispense modifiée) laissent désormais une trace dans les journaux,
  rattachée au module, à la saisie et à l'étudiant, avec les statuts résultants.
- Tableaux de pilotage de `mod_stage` et `mod_stagesynthesis` rendus par des fonctions communes
  (formulaire de recherche, tri, colonnes, tableau paginé) au lieu de deux copies du même code.
  Au passage, la recherche courante n'est plus doublement échappée dans le champ de recherche.
- Premiers gabarits Mustache (`templates/`) et classes de sortie (`classes/output/`) : filtres
  des listes, tableau de pilotage, badges de statut, boutons d'action. Les fonctions de rendu
  existantes y délèguent ; le contrôle Mustache de la CI porte désormais sur de vrais gabarits.
- Scénarios Behat du circuit (`tests/behat/circuit.feature`) : auto-évaluation de l'étudiant,
  évaluation et non-validation par l'enseignant référent, validation DEVE avec durée retenue,
  tableau de pilotage, validation d'une demande de convention par l'enseignant référent. Générateur Behat (thématiques, saisies, attributions) et navigation vers
  les pages de gestion.
- Paramètres de requête lus avec leur type exact au lieu de `PARAM_RAW` : filtres de statut
  (nouvelle fonction `stage_optional_status_param`, qui conserve la distinction entre
  « tous les statuts » et le statut 0), boutons d'action et commentaires en texte brut.
- Suppression de stages en lot par la DEVE (`delete_entries.php`, bouton « Supprimer des
  stages » de la liste des stages), pour défaire un import erroné : sélection par cases à cocher
  ou par filtres, dont un filtre par jour d'enregistrement qui retrouve les stages d'un même
  import ; écran d'avertissement récapitulatif, puis double confirmation (case à cocher et nombre
  exact de stages à saisir). Chaque stage supprimé laisse un événement `entry_deleted` dans les
  journaux.
- Import historique : colonnes facultatives « Stage à l'étranger » et « Pays » dans chaque bloc,
  pour que les stages à l'étranger comptent dans l'obligation de mobilité internationale.
- `VERSION` de `db/install.xml` aligné sur le dernier point de sauvegarde.
- Mobilité d'études : dans le volet d'un étudiant (tableau de pilotage), la DEVE peut cocher
  « L'étudiant a effectué une mobilité d'études » et en indiquer le lieu (obligatoire). Une
  mobilité d'études validée remplit l'obligation de mobilité internationale sans jour de stage
  à l'étranger ; elle est affichée avec son lieu dans le bilan de l'étudiant et dans l'export
  Excel du bilan de promotion. Nouvelle table `stage_study_mobility` (version 2026102600),
  sauvegardée avec les données utilisateur et prise en charge par le fournisseur RGPD.
- Import Excel des mobilités d'études (`study_mobility_import.php`, lien dans l'administration
  de l'activité) : classeur XLSX à colonnes Email, Lieu et, facultativement, Mobilité d'études
  (oui/non), reconnues par leur intitulé ; modèle téléchargeable listant les étudiants inscrits ;
  erreurs signalées ligne par ligne sans bloquer les autres.
- Pilotage : après une action lancée depuis le volet d'un étudiant, retour sur ce volet et non
  plus sur la liste.
- Test de charge (`docs/audit/`) et corrections qui en découlent :
  - Export Excel : la colonne « Évalué par » était toujours vide (variable non définie) ; plages
    de dates, référents et enseignants responsables lus en une requête chacun au lieu d'une par
    stage, étudiant ou thématique ; limites de temps et de mémoire relevées.
  - Invitations des maîtres de stage : une seule tentative automatique. Plus d'invitation
    au-delà de 60 jours après la fin du stage (après son début s'il n'a pas de date de fin) ;
    activer l'évaluation sur une promotion qui a un historique invitait d'un coup tous les
    anciens stages. Un échec (adresse invalide, envoi refusé) est enregistré
    (`tutorrequestfailtime`, version 2026103101, sauvegardé avec le cours et exporté au titre
    du RGPD) et n'est plus jamais retenté par la tâche planifiée : la DEVE en est avertie sur le
    détail du stage et relance à la main, un envoi réussi effaçant l'échec. 200 tentatives au
    plus par passage. Adresse contrôlée dans les formulaires de convention, et envoyée sans les
    espaces qui l'entourent.
  - Listes (enregistrement, validation DEVE, enseignant référent, thématique, conventions,
    suppression) paginées en SQL au lieu de charger toute la promotion.
  - Volet de suivi de l'étudiant : données chargées une fois (cache du bilan de promotion) au
    lieu de plusieurs centaines de requêtes ; documents d'objectifs repérés en une requête.
  - Suppression des stages (réinitialisation, suppression de l'activité, RGPD), téléchargement
    groupé des rapports et lectures par identifiants faits par paquets : plus de liste `IN`
    dépassant les limites de MSSQL et d'Oracle, une requête de fichiers par paquet au lieu
    d'une par stage.
  - Verrou par activité sur les écritures en lot : création de stages (formulaires, création en
    masse, service web, imports CSV, StageVet et historique, doublons relus sous le verrou) et
    attribution des enseignants (référents, responsables, import historique). Attributions
    écrites par différences. Validation et rejet DEVE conditionnés au statut « non annulé »
    dans la requête même, en une transaction avec le commentaire et l'événement. Le jeton du
    maître de stage n'est plus remplacé quand l'affichage et la tâche planifiée le créent en
    même temps.
  - Plages de dates limitées à 366 jours (formulaires, service web, imports CSV et StageVet) ;
    une plage existante inchangée reste acceptée dans les formulaires de la DEVE et de
    l'enseignant. Import StageVet : mises à jour et créations en une transaction, doublons
    probables cherchés sans relire la base à chaque ligne. Événements : saisie et module de
    cours lus en une requête. Index sur la date de début des stages.
  - Listes : le tri à rebours par étudiant s'applique au nom comme au prénom (seul le prénom
    était trié à rebours). Message de doublon propre à l'étudiant.
  - Import historique : la prévisualisation est conservée dans un fichier temporaire propre à
    l'utilisateur et à l'activité (valable un jour) au lieu de la session, qu'un gros classeur
    alourdissait.
  - Import StageVet : les invitations des enseignants référents partent par une tâche ad hoc
    (`send_teacher_eval_requests`) au lieu de la requête web ; le compte rendu de l'import dit
    toujours qui sera invité et qui n'a pas de référent.
  - Import CSV des référents sous le verrou de l'activité, en une transaction.
- Import StageVet : le référent de la convention est le tuteur de l'export (enseignant référent
  de l'école) reconnu par son adresse ou son nom ; à défaut, l'enseignant référent attribué à
  l'étudiant. Les tuteurs sans compte reconnu ne bloquent plus rien et ne sont plus ignorés en
  silence : le compte rendu les liste avec le référent retenu et propose de choisir le bon
  compte enseignant, appliqué aux stages créés et aux lignes encore à arbitrer. Les étapes de
  l'import sont conservées dans un fichier temporaire au lieu de la session.

### mod_stagesynthesis
- Événement Moodle de consultation de l'activité.
- La clé du filtre de thématique combiné s'écrit `stagecmid-themeid` (au lieu de `:`) et est
  lue en `PARAM_ALPHANUMEXT`. Seuls les liens de filtre mis en favori avant ce changement sont
  concernés : ils reviennent à « toutes les thématiques ».

## Documentation — 2026-10-25
- Captures d'écran des trois guides refaites (guide de paramétrage 1.4, guides étudiant et
  enseignant référent 1.1) sur un site de démonstration reconstitué par script, après le passage
  des listes et tableaux aux gabarits Mustache. Outils rejouables dans `docs/guides/captures/` :
  jeu de données, plan de captures et pilote de navigateur.

## Documentation — 2026-10-16
- Guide de paramétrage illustré (LaTeX et PDF), versions 1.0 à 1.3 : mise en place d'une
  promotion, circuit des stages, fin d'année et diplomation, redoublement, rentrée, courriels.
- Guides étudiant et enseignant référent (version 1.0), mise en page commune `guide-commun.sty`.
- Correction de la documentation sur le moment réel de l'invitation du maître de stage
  (tâche planifiée quotidienne à partir du premier jour du stage).

## mod_stage 0.5.1 — 2026-10-03
- Correction d'une régression du courriel « convention prête » et de dates écrasées lors du
  dernier passage de l'audit.
- Pilotage : le bilan d'une promotion est calculé en une dizaine de requêtes grâce au cache de
  lecture `mod_stage\local\progress_cache` (plus de 16 000 requêtes auparavant pour
  180 étudiants). Bénéficie aussi au pilotage de `mod_stagesynthesis` et à l'export PDF.

## mod_stage 0.5.0 et mod_stagesynthesis 0.2.1 — 2026-10-02
- Thématiques : une seule page pour tous les volets d'une thématique ; toutes les années
  modifiables dans les durées par année.
- Enseignants référents : attribution directement dans le tableau, actions en masse.
- Notifications et conventions : une seule page, un seul enregistrement ; gabarits de
  conventions listés, édités et ajoutés sur une page dédiée.
- Listes d'évaluation nommées, éditées à part et choisies par la thématique ; création depuis
  la thématique et copie à l'import ; version anglaise des questions préservée.
- Plages de stage : suppression de la case « Activer », dates réelles à l'édition, dernière
  plage non supprimable.
- Import StageVet : évaluations de l'étudiant et du maître de stage ; doublons probables
  signalés ; restauration d'export réparée.
- Export Excel : évaluations lisibles et notes importées détaillées.
- Corrections issues de l'audit : imports, transfert et copie entre cours, circuit des stages.
- `mod_stagesynthesis` dépend de `mod_stage` 2026090300 ou plus (cohérence des retours
  depuis `convention_teacher_validate.php`).

## mod_stage 0.4.0 — 2026-09-19 au 2026-09-22
- Objectifs de stage par thématique : documents à télécharger et check-list renseignée lors
  de la demande de convention.
- L'étudiant est prévenu quand sa convention est prête à télécharger.
- Création du lien d'évaluation du maître de stage et envoi de l'invitation suivis séparément
  (version technique 2026092000) : consulter le lien ne bloque plus le cron, un échec d'envoi
  reste éligible à une nouvelle tentative.
- Import StageVet : aucune ligne n'est plus ignorée en silence, la DEVE rattache l'étudiant ;
  durée déclarée renseignée sans convention PDF analysée.
- Première suite de tests unitaires, annotations PHPDoc clarifiées.

## mod_stage 0.3.0 et mod_stagesynthesis 0.2.0 — 2026-09-11
- Sauvegarde et restauration Moodle (`backup/moodle2/`) pour les deux modules.
- Génération de la convention par la DEVE.
- Mise en place de l'intégration continue (`.github/workflows/ci.yml`).

## mod_stage 0.2.6 et mod_stagesynthesis 0.1.5 — 2026-09-06
- Premier module `mod_stagesynthesis` : vue transversale des étudiants d'un enseignant
  référent sur plusieurs promotions.
- Fournisseur de confidentialité (RGPD) de `mod_stage` et premiers tests PHPUnit (accès aux
  conventions, demande de convention papier, import historique, confidentialité).
- Validation DEVE : durée retenue modifiable (2026-08-30).

## mod_stage 0.2.1 — 2026-08-25
- Première publication du dépôt : enregistrement des stages par promotion, thématiques,
  conventions, évaluations, validation par la scolarité, README et logo.
