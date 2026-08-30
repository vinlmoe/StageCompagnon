# Gestion des stages (mod_stage)

Module d'activité Moodle pour le suivi des stages étudiants : enregistrement
des stages, génération et suivi des conventions, auto-évaluation par
l'étudiant, évaluation par l'enseignant référent et validation finale par la
scolarité (DEVE).

- **Prérequis** : Moodle 4.0 ou supérieur, PHP 7.4+.
- **Dépendance incluse** : FPDI 2.6 (MIT), dans `thirdparty/vendor`, utilisée
  pour l'assemblage des conventions PDF.

## 1. Installation

Copier le dossier `mod/stage` dans `<moodle>/mod/stage`, puis lancer la mise à
jour de la base :

```bash
php admin/cli/upgrade.php
```

Ou, depuis l'interface, se connecter en administrateur : Moodle détecte le
plugin et propose de l'installer. Vérifier ensuite sa présence dans
**Administration du site > Plugins > Activités**.

Si vous reconstruisez le dossier `thirdparty` vous-même :

```bash
cd mod/stage/thirdparty && composer require setasign/fpdi:^2.6
```

Sans cette librairie, toutes les autres fonctions restent utilisables : seule
la génération des conventions affiche un message d'erreur explicite.

## 2. Rôles et capacités

Le module s'appuie sur les archétypes de rôles Moodle standard
(voir `db/access.php`) :

| Rôle Moodle | Rôle fonctionnel | Droits dans l'activité |
|---|---|---|
| Étudiant (`student`) | Étudiant | Consulter ses stages, demander une convention, s'auto-évaluer |
| Enseignant non-éditeur (`teacher`) | Enseignant référent | Évaluer les stages des étudiants qui lui sont attribués |
| Enseignant éditeur (`editingteacher`) ou Manager | DEVE | Tout le reste : thématiques, conventions, référents, validation finale |

Pour distinguer la DEVE des enseignants éditeurs de contenu, dupliquer le rôle
« Enseignant » (**Administration du site > Utilisateurs > Permissions >
Définir les rôles**), le renommer « DEVE » et n'y cocher que les capacités
`mod/stage:*` (`managethemes`, `validatedeve`, `manageteachers`, `viewall`,
`evaluateteacher`, `view`).

### Navigation

La DEVE et les enseignants référents arrivent directement sur le **tableau de
pilotage** ; la page « Mes stages » est réservée aux étudiants. Une barre de
liens commune donne accès, selon les capacités, à : Enregistrer des stages,
Conventions, Validation DEVE, Validation enseignant, Pilotage, Export Excel et
**Administration** (thématiques, gabarits de convention, référents, import
depuis un autre cours).

## 3. Mise en place d'un cours

1. Créer un cours dédié (par exemple « Stages – 5<sup>e</sup> année »).
2. Y inscrire les étudiants, les enseignants référents et les personnels DEVE
   avec les rôles du tableau ci-dessus.
3. Ajouter une activité **Gestion des stages**.

Pour un nouveau cours reprenant une configuration existante, utiliser
**Administration > Importer depuis un autre cours** : copie les thématiques,
les gabarits de convention (avec leur PDF), les logos et les informations
d'établissement d'une autre instance du module. Seules les instances où vous
pouvez gérer les thématiques sont proposées comme source. Thématiques et
gabarits s'ajoutent à l'existant ; logos et informations d'établissement le
remplacent.

## 4. Thématiques

Depuis **Administration > Gérer les thématiques** :

- **Ajouter une thématique** pour chaque type de stage possible, avec son
  année d'étude, son caractère obligatoire et sa durée requise.
- Le tableau permet de modifier en masse le caractère obligatoire, la durée et
  l'année d'étude de toutes les lignes en une seule soumission.
- La colonne **Visible** est un interrupteur : une thématique désactivée n'est
  plus proposée à l'enregistrement d'un stage, mais reste visible ici et sur
  les stages déjà enregistrés dessus.
- **Questions d'évaluation** (lien par thématique) : définir des questions
  (choix multiples ou commentaire libre) qui remplacent le commentaire libre
  générique dans le formulaire d'auto-évaluation de l'étudiant et/ou celui de
  l'enseignant. Une question peut être réutilisée sur plusieurs thématiques.

## 5. Enregistrement des stages

Quatre voies, toutes accessibles depuis **Enregistrer des stages** :

| Méthode | Statut de convention initial |
|---|---|
| Unitaire (un étudiant, un stage) | Non demandée |
| En masse (une thématique, plusieurs étudiants) | Signée (StageVet) |
| Import CSV générique | Non demandée |
| Import d'un export StageVet | Signée (StageVet) |

Un étudiant déjà titulaire d'un stage sur la même thématique avec les mêmes
dates est écarté et signalé, sans créer de doublon.

Le statut **Signée (StageVet)** marque les stages déjà conventionnés hors du
module : ils n'apparaissent pas dans le circuit de gestion des conventions et
l'auto-évaluation est ouverte immédiatement.

### Import CSV générique

Colonnes attendues, séparées par des points-virgules ou des virgules, ligne
d'en-tête facultative :

```
email;theme;structure;datestart;dateend;duration
```

`email` doit correspondre à un étudiant inscrit au cours, `theme` au nom exact
d'une thématique existante, les dates sont au format `AAAA-MM-JJ` (facultatives)
et `duration` est la durée déclarée en jours.

### Import d'un export StageVet

Importe le fichier CSV exporté par StageVet sans retraitement préalable. Les
colonnes sont reconnues par leur en-tête, donc leur ordre est indifférent.
L'étudiant est identifié par courriel si la colonne est renseignée, sinon par
nom et prénom (comparaison insensible aux accents et à la casse).

Les noms de thématiques doivent correspondre exactement aux intitulés StageVet
(par exemple `THEME LIBRE / A2, A3, A4, A5`) : créez-les au préalable. Le
rapport d'import liste, groupés par valeur et avec leurs numéros de ligne, les
étudiants et les thématiques introuvables.

### Auto-enregistrement par l'étudiant

Les stages chez les vétérinaires français sont conventionnés par StageVet.
Pour les autres, l'étudiant dispose sur son tableau de bord d'un bouton
**Faire une demande de convention (hors StageVet)** qui enregistre le stage et
soumet la demande de convention en un seul formulaire. Il doit avoir au moins
un enseignant référent attribué et un gabarit de convention disponible.

## 6. Enseignants référents

**Administration > Attribuer les enseignants référents** : une ligne par
étudiant, jusqu'à deux référents chacun, avec recherche par nom, filtre sur les
étudiants sans référent et enregistrement en masse.

L'import CSV de cette page attend :

```
studentemail;teacher1email;teacher2email
```

Chaque ligne remplace l'attribution existante de l'étudiant ; le second
référent est facultatif.

## 7. Conventions de stage

Circuit complet, de la demande étudiante au document signé. **L'auto-évaluation
n'est ouverte qu'une fois la convention signée.**

### Configuration (Administration > Gabarits de convention)

- **Paramètres généraux** : option « Exiger la validation de l'enseignant
  référent avant transmission à la DEVE ».
- **Gabarits** : nom, langue (français ou anglais) et PDF des articles
  juridiques. Plusieurs gabarits peuvent coexister ; l'étudiant en choisit un.
  Un gabarit utilisé par une demande ne peut plus être supprimé.
- **Établissement d'enseignement** : nom, adresse, représentant et sa qualité,
  téléphone et courriel, affichés en tête de toutes les conventions.
- **Logos** : deux images PNG placées en haut de la première page.

### Demande par l'étudiant

Formulaire couvrant les informations que la DEVE ne connaît pas déjà :
langue et gabarit, enseignant référent (choisi parmi ceux qui lui sont
attribués — son courriel est repris automatiquement de son compte), situation
et type de stage, coordonnées de l'étudiant, organisme d'accueil, tuteur,
modalités particulières, gratification et congés.

Tous les champs sont obligatoires à l'exception du lieu du stage, à renseigner
uniquement s'il diffère de l'adresse de l'organisme. Un rappel indique que la
demande doit être déposée au moins deux semaines avant le stage, quatre pour un
stage à l'étranger.

### Validation par l'enseignant référent (optionnelle)

Si l'option est activée, la demande passe d'abord au statut **En attente de
l'enseignant référent** et n'est pas encore visible par la DEVE. Les référents
de l'étudiant reçoivent un courriel et retrouvent la demande en tête de leur
page de validation. Ils disposent du formulaire complet, éditable, et peuvent
la valider (transmission à la DEVE) ou la refuser avec un motif renvoyé à
l'étudiant.

### Traitement par la DEVE

La page **Conventions** liste les demandes en cours, avec recherche par nom,
tri par colonne et affichage des plus récentes en premier.

1. **Générer la convention** ouvre la demande, entièrement éditable.
   - *Valider* enregistre les corrections, passe la convention au statut
     **Éditée** et télécharge immédiatement le PDF.
   - *Refuser* (motif obligatoire) la renvoie à l'étudiant pour correction.
2. **Marquer signée**, une fois le document signé retourné. Le PDF signé peut
   être joint à cette étape — facultatif, mais s'il est fourni il devient
   téléchargeable par l'étudiant, son enseignant référent et la DEVE. Cette
   étape ouvre l'auto-évaluation et l'évaluation.

Le PDF produit reprend la première page générée à partir des données du stage,
suivie des pages du gabarit choisi.

### Annulation

Depuis **Enregistrer des stages**, la DEVE peut annuler un stage à tout moment,
avec un motif obligatoire. La saisie est conservée en l'état ; seul le statut
passe définitivement à **Annulé**.

## 8. Suivi et validation

- **Étudiant** : avancement des thématiques obligatoires par année d'étude et
  liste de ses stages, avec le statut de convention et l'accès à
  l'auto-évaluation. Une auto-évaluation soumise n'est plus modifiable, sauf
  réinitialisation par la DEVE.
- **Enseignant référent** : liste restreinte à ses étudiants (recherche, filtres,
  tri, pagination). Il valide l'évaluation ou la marque non validée avec un
  motif.
- **DEVE** : validation unitaire (durée retenue et commentaire) ou en masse,
  possibilité de marquer non validé, et **Réinitialiser** pour rouvrir une
  saisie déjà évaluée. Le tableau de pilotage donne la vue d'ensemble de tous
  les étudiants avec accès au détail de chacun.

## 9. Exports et API

**Exporter en Excel** produit un `.xlsx` de tous les stages de l'activité :
étudiant, thématique, structure d'accueil, dates, durées déclarée et retenue,
statut, référents et commentaires.

Deux fonctions de service web sont regroupées dans le service prédéfini
« Gestion des stages (mod_stage) » :

| Fonction | Capacité | Description |
|---|---|---|
| `mod_stage_register_entries` | `mod/stage:registerstages` | Enregistre un ou plusieurs stages. Paramètres : `cmid` et `entries[]` (`userid`, `themeid`, `structure`, `datestart`, `dateend`, `declaredduration`). |
| `mod_stage_get_my_stages` | `mod/stage:submit` | Renvoie les stages de l'utilisateur authentifié pour l'activité. |

Pour les utiliser : activer les web services et le protocole REST
(**Administration du site > Serveur web**), activer le service « Gestion des
stages (mod_stage) », y ajouter un compte de service et générer un jeton.

```
POST https://votre-moodle/webservice/rest/server.php
     ?wstoken=JETON&wsfunction=mod_stage_register_entries&moodlewsrestformat=json
```

## Tests unitaires

Le plugin embarque une suite PHPUnit (`mod/stage/tests/`), au format standard
des tests Moodle : elle s'exécute avec l'environnement PHPUnit de Moodle, pas
seule.

**Mise en place (une fois par site de développement)**, depuis la racine de
l'installation Moodle :

```bash
php admin/tool/phpunit/cli/init.php
```

Cette commande crée la base de données de test et initialise l'environnement
PHPUnit ; à relancer après toute modification du schéma du plugin
(`db/install.xml`, `db/upgrade.php`).

**Exécution** de la suite complète du plugin :

```bash
vendor/bin/phpunit --testsuite mod_stage_testsuite
```

Ou un seul fichier :

```bash
vendor/bin/phpunit mod/stage/tests/periods_test.php
```

**Contenu de la suite** :

| Fichier | Couvre |
|---|---|
| `periods_test.php` | Cohérence des plages de dates (`stage_validate_periods`) et leur statut de source unique des dates du stage (`stage_save_entry_periods`, `stage_register_entry`). |
| `year_progress_test.php` | Bilan annuel d'un étudiant : thématique bornée à une plage d'années (vérifiée cumulativement à sa dernière année), mobilité internationale intégrée à l'année où elle est due, stages complémentaires exclus du décompte. |
| `promotion_test.php` | Bilan de promotion : années échues uniquement, classement des étudiants en défaut par sévérité puis ancienneté du retard. |
| `transfer_test.php` | Transfert d'un étudiant vers une autre instance : blocages (aucun stage, non-inscrit, thématique sans correspondance), rapprochement tolérant des noms, effets réels du transfert. |
| `helpers_test.php` | Petites fonctions utilitaires pures (libellés, normalisation de nom, rendu d'actions/badges). |

`tests/generator/lib.php` fournit un générateur de données de test
(`mod_stage_generator`), utilisable comme n'importe quel générateur Moodle :

```php
$generator = $this->getDataGenerator()->get_plugin_generator('mod_stage');
$theme = $generator->create_theme($stage, ['name' => 'Thématique', 'mandatory' => 1]);
$entry = $generator->create_entry($stage, $userid, $theme);
```

Cette suite couvre la logique métier la plus récemment modifiée et la plus
sensible aux régressions silencieuses (dates dérivées des plages, bilans de
validation, transfert inter-cours) ; elle n'est pas exhaustive sur l'ensemble
du plugin. Étendre `tests/` au même format à mesure que de nouvelles règles
métier sont ajoutées.

## Licence

GNU GPL v3 ou ultérieure, comme Moodle.
