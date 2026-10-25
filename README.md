# StageCompagnon

[![CI](https://github.com/vinlmoe/StageCompagnon/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/vinlmoe/StageCompagnon/actions/workflows/ci.yml)
[![Moodle 4.0+](https://img.shields.io/badge/Moodle-4.0%2B-f98012?logo=moodle&logoColor=white)](https://moodle.org)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)](https://www.php.net)
[![Licence GPL v3+](https://img.shields.io/badge/licence-GPL%20v3%2B-blue)](https://www.gnu.org/licenses/gpl-3.0)

Deux modules d'activité Moodle pour la gestion des stages étudiants en école
vétérinaire : l'enregistrement et la validation des stages promotion par
promotion, et la vue transversale dont un enseignant référent a besoin quand ses
étudiants sont répartis sur plusieurs promotions.

| Module | Rôle | Documentation |
|---|---|---|
| [`mod/stage`](mod/stage) | **Gestion des stages** — le module principal : thématiques, enregistrement des stages, conventions, évaluations, validation par la scolarité (DEVE), exports et API. | [`mod/stage/INSTALL.md`](mod/stage/INSTALL.md) |
| [`mod/stagesynthesis`](mod/stagesynthesis) | **Suivi des stages** — module complémentaire : donne à chaque enseignant référent une vue unique de tous ses étudiants, toutes promotions confondues. | [`mod/stagesynthesis/INSTALL.md`](mod/stagesynthesis/INSTALL.md) |

## À quoi ça sert

Un stage suit un circuit qui met en jeu quatre personnes, dont une sans compte
Moodle :

```
Étudiant ──demande de convention──▶ Enseignant référent ──▶ DEVE ──▶ convention signée
    │                                (validation optionnelle)              │
    │                     Maître de stage (lien à jeton, sans compte) ◀────┤ dès le 1er jour du stage
    │                                                                      ▼
    └──auto-évaluation◀────────────────────────────────────────── ouverture des évaluations
                │
                └──▶ Enseignant référent ──▶ DEVE : durée retenue, validation finale
```

`mod_stage` porte tout ce circuit à l'échelle d'une promotion (une activité par
cours de promotion). `mod_stagesynthesis` ne fait que regrouper la vue : il ne
stocke aucune donnée de stage et n'accorde aucun droit supplémentaire — il relit
les attributions de référent déjà en place dans chaque activité liée et n'affiche
que celles où l'enseignant a toujours ses droits à la source.

La DEVE peut aussi déposer une demande de convention au nom d'un étudiant pour
un stage déjà enregistré, sauf si celui-ci est dispensé de convention. Cette
demande contourne l'éventuelle validation préalable de l'enseignant référent et
arrive directement dans la file de traitement DEVE.

La DEVE conserve la possibilité de valider directement un stage sans évaluation
de l'enseignant référent ni du maître de stage. Un stage annulé doit d'abord être
réinitialisé. Les réponses obligatoires sont contrôlées côté serveur lorsqu'un
étudiant, enseignant ou maître de stage soumet son questionnaire.

Dans le bilan des thématiques obligatoires, le bouton **Objectifs** de chaque
ligne ouvre une page réunissant les documents à télécharger et la check-list
des objectifs. Cette consultation est disponible même avant l'enregistrement
d'un stage ; la check-list est renseignée lors de la demande de convention.

Les transferts entre promotions remappent les réponses aux évaluations et aux
objectifs vers leur thématique cible. Si un objectif renseigné n'a pas de
correspondance unique dans la destination, le transfert est bloqué jusqu'à ce
que la DEVE complète les objectifs cibles. L'annulation d'un stage révoque le
lien d'évaluation du maître de stage ; une réinitialisation ne réactive pas
l'ancien lien.

Depuis la version technique `2026092000`, la création du lien d'évaluation et
l'envoi de l'invitation sont suivis séparément : consulter le lien ne bloque plus
le cron et un échec d'envoi reste éligible à une nouvelle tentative. La mise à jour
Moodle ajoute le champ nécessaire. Pour les anciens jetons, l'historique ne permet
pas de savoir si le courriel a été envoyé : ils sont considérés comme déjà envoyés
pour éviter une relance générale. La DEVE peut relancer manuellement ceux qui
n'ont pas été reçus.

L'invitation automatique du maître de stage ne dépend pas de l'auto-évaluation de
l'étudiant : une tâche planifiée quotidienne (6 h 45) l'envoie une seule fois, à
partir du premier jour du stage. Elle n'est envoyée que pour un stage engagé :
convention signée (ici ou sur SignVet) ou dispense, stage encore en attente de son
avis (ni évalué par l'enseignant, ni validé, ni annulé), évaluation non contournée
par la DEVE, évaluation par le maître de stage activée pour l'activité et pour la
thématique, et courriel du maître de stage renseigné dans la demande de convention. Une copie ou une restauration du cours
conserve l'état « déjà invité » : les maîtres de stage ne sont pas réinvités.

Un étudiant ne peut demander une convention, et donc modifier un stage, que tant
que celui-ci est simplement enregistré. La réinitialisation du cours propose de
supprimer les stages et les attributions de référents de l'année écoulée.

## Installation rapide

```bash
# Depuis la racine de votre installation Moodle
cp -r mod/stage           <moodle>/mod/stage
cp -r mod/stagesynthesis  <moodle>/mod/stagesynthesis   # facultatif
php admin/cli/upgrade.php
```

- **Prérequis** : Moodle 4.0+ (`$plugin->requires = 2022041900`), PHP 7.4+.
- `mod_stagesynthesis` dépend de `mod_stage` : installer les deux, ou seulement
  `mod_stage`.
- FPDI 2.6.8 (MIT) est inclus dans `mod/stage/thirdparty/vendor` pour l'assemblage
  des conventions PDF.

Les procédures détaillées — mise en place d'un cours, rôles à créer, imports,
configuration des conventions et des courriels — sont dans les deux `INSTALL.md`.

Guides avec captures d'écran, dans [`docs/guides/`](docs/guides) (source LaTeX
dans le même dossier, `latexmk -pdf <guide>.tex`) :

| Guide | Public | Contenu |
|---|---|---|
| [`guide-parametrage.pdf`](docs/guides/guide-parametrage.pdf) | DEVE, administrateurs | Mise en place d'une promotion, circuit des stages, fin d'année et diplomation, redoublement, rentrée, courriels |
| [`guide-etudiant.pdf`](docs/guides/guide-etudiant.pdf) | Étudiants | Suivi des stages, demande de convention, auto-évaluation |
| [`guide-enseignant-referent.pdf`](docs/guides/guide-enseignant-referent.pdf) | Enseignants référents | Suivi multi-promotions, validation des conventions, évaluation |

## Contrôle continu

Chaque poussée déclenche `.github/workflows/ci.yml`, en deux temps :

- **Style Moodle** — `phpcs` avec le standard `moodle` (`moodlehq/moodle-cs`), sur la
  configuration `phpcs.xml` du dépôt. Le contrôle échoue au premier écart, avertissements
  compris. Une seule règle est désactivée, et le fichier dit pourquoi : le sniff qui exige
  qu'un commentaire commence par `[A-Z0-9]` ne reconnaît pas les capitales accentuées, et
  s'y conformer imposerait d'écrire « Ecran » pour « Écran ».
- **Moodle** — `moodle-plugin-ci` installe un Moodle avec PostgreSQL et les modules du
  dépôt, puis enchaîne analyse syntaxique, validation de la structure du module, points de
  sauvegarde de la mise à jour, blocs de documentation (`phpdoc`), gabarits Mustache et tests
  PHPUnit. Tous ces contrôles sont bloquants. Le job tourne sur deux combinaisons : Moodle 4.0
  avec PHP 7.4, la borne basse annoncée par `version.php` et par ce README, et Moodle 4.5 avec
  PHP 8.2. Sur cette dernière, la couverture de code est mesurée avec pcov : le résumé figure
  dans le journal de l'étape PHPUnit et les rapports Clover sont publiés en artefact du job. Le
  workflow peut aussi être lancé à la main depuis l'onglet *Actions* (`workflow_dispatch`), par
  exemple pour vérifier une branche avant d'ouvrir une demande de fusion.

Pour rejouer le contrôle de style en local :

```bash
mkdir -p /tmp/cs && composer --working-dir=/tmp/cs require moodlehq/moodle-cs
/tmp/cs/vendor/bin/phpcs --config-set installed_paths \
  /tmp/cs/vendor/moodlehq/moodle-cs/moodle,/tmp/cs/vendor/phpcsstandards/phpcsextra/Universal,\
/tmp/cs/vendor/phpcsstandards/phpcsextra/NormalizedArrays,/tmp/cs/vendor/phpcsstandards/phpcsextra/Modernize
/tmp/cs/vendor/bin/phpcs --standard=phpcs.xml -p
```

`phpcbf` (même chemin, mêmes options) corrige d'office la plus grande part des écarts.

## Points à connaître avant une mise en production

**Sauvegarde.** Les deux modules fournissent une implémentation
`backup/moodle2/` : une sauvegarde de cours Moodle emporte le paramétrage des
activités et, si les données utilisateur sont demandées, les stages, conventions
et évaluations, ainsi que les fichiers associés. Deux réserves : le jeton d'accès
du maître de stage n'est pas recopié (la copie en régénère un à la demande), et
les liens d'une synthèse vers une activité restée hors de la sauvegarde ne sont
conservés que lors d'une restauration sur le même site. La sauvegarde de cours
ne dispense pas pour autant d'une sauvegarde de la base (`mdl_stage*`) et du
`moodledata`, qui reste le filet de sécurité du site.

**Données personnelles.** `mod_stage` implémente le fournisseur de
confidentialité Moodle. La suppression d'un étudiant efface l'intégralité de ses
stages et évaluations, y compris les PDF de conventions signées ; la suppression
d'un membre du personnel, elle, laisse intacts les stages des étudiants dont il
n'est que le référent et se limite à dissocier ses références. Si vos conventions
signées sont soumises à une obligation d'archivage, extrayez-les avant de traiter
une demande de suppression. Détail en §11 de `mod/stage/INSTALL.md`.

**Journaux.** Chaque étape du circuit (enregistrement, auto-évaluation, évaluations de
l'enseignant référent et du maître de stage, validation ou non-validation, réinitialisation,
annulation, demande, validation, édition, signature ou refus de convention, dispense) déclenche
un événement Moodle rattaché au module, à la saisie et à l'étudiant. Les journaux standard
(*Rapports > Journaux*) permettent donc de répondre à « qui a validé ce stage, et quand » sans
lire la base. L'évaluation du maître de stage, faite sans compte, est tracée avec l'utilisateur 0.

**Rôles Moodle.** Le module s'appuie sur les archétypes standard : le personnel
DEVE utilise le rôle « Enseignant » (`editingteacher`) et les enseignants
référents le rôle « Enseignant non éditeur » (`teacher`). Aucun rôle Moodle
« DEVE » spécifique ne doit être créé (§2 de `mod/stage/INSTALL.md`).

## Développement

Suite PHPUnit au format standard Moodle, à exécuter depuis la racine de
l'installation Moodle après `php admin/tool/phpunit/cli/init.php` :

```bash
vendor/bin/phpunit --testsuite mod_stage_testsuite
vendor/bin/phpunit --testsuite mod_stagesynthesis_testsuite
```

`mod/stage/tests/generator/lib.php` fournit un générateur de données
(`mod_stage_generator`) pour créer thématiques, saisies et attributions de
référent dans les tests.

La couverture porte sur la logique métier la plus sensible aux régressions
silencieuses — dates dérivées des plages, bilans de validation, transfert
inter-cours, accès aux conventions, règles RGPD — et n'est pas exhaustive.
Étendre `tests/` au même format à mesure que de nouvelles règles sont ajoutées.

L'historique des versions des deux modules est tenu dans [`CHANGELOG.md`](CHANGELOG.md) :
y ajouter une entrée à chaque changement de `version.php`. Les conventions d'édition
(indentation, fins de ligne) sont décrites dans `.editorconfig`.

## Licence

GNU GPL v3 ou ultérieure, comme Moodle ; le texte complet est dans [`LICENSE`](LICENSE).
FPDI est distribué sous licence MIT (voir `mod/stage/thirdpartylibs.xml`).
