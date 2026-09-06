# Suivi des stages (mod_stagesynthesis)

Module d'activité complémentaire à `mod_stage` : donne à un enseignant
référent une vue unique de tous les étudiants qui lui sont attribués comme
référent, tous cours/promotions `mod_stage` confondus, sans avoir à naviguer
d'un cours à l'autre.

- **Prérequis** : `mod_stage` installé (dépendance déclarée dans
  `version.php`).

## Installation

Copier le dossier `mod/stagesynthesis` dans `<moodle>/mod/stagesynthesis`,
puis lancer la mise à jour de la base :

```bash
php admin/cli/upgrade.php
```

## Mise en place

1. Créer un cours dédié (par exemple « Suivi des référents de stage »),
   distinct des cours de promotion qui portent chacun leur propre activité
   « Gestion des stages ».
2. Y inscrire les enseignants référents concernés (rôle Enseignant non
   éditeur suffit).
3. Ajouter une activité **Suivi des stages**.
4. En tant qu'enseignant éditeur ou manager du cours (voir « Rôles et
   capacités » ci-dessous), cliquer depuis la page de l'activité sur
   **Gérer les liens**, puis cocher les activités « Gestion des stages » (une
   par promotion) dont les étudiants doivent y apparaître. Décocher une
   activité (promotion sortie, pas encore concernée...) la retire de la
   synthèse sans rien modifier dans l'activité stage d'origine ni dans les
   attributions de référents qu'elle contient.

   Seules sont proposées comme source les activités où vous avez vous-même
   `mod/stage:manageteachers`, afin de ne jamais exposer le nom d'un cours ou
   d'une promotion auquel vous n'avez par ailleurs aucun accès.

## Rôles et capacités

| Capacité | Enseignant non éditeur | Enseignant éditeur | Manager |
|---|:-:|:-:|:-:|
| `mod/stagesynthesis:view` — consulter la synthèse de ses étudiants | ✅ | ✅ | ✅ |
| `mod/stagesynthesis:addinstance` — ajouter l'activité à un cours | — | ✅ | ✅ |
| `mod/stagesynthesis:managelinks` — choisir les activités liées | — | ✅ | ✅ |

Lier une activité revient à désigner des cours et des promotions extérieurs à
celui-ci : c'est un acte d'administration de l'activité. L'enseignant non
éditeur en est donc écarté — il ne voit pas même le lien **Gérer les liens** —
tout en gardant l'accès complet à la synthèse de ses propres étudiants, qui est
l'usage attendu du module.

## Les deux écrans

- **Pilotage** (page d'accueil) : une ligne par étudiant attribué, toutes
  promotions confondues, avec son année d'étude courante, ses années validées,
  l'avancement de ses thématiques obligatoires et le total des jours retenus.
  Chaque ligne ouvre le détail de l'étudiant.
- **Stages** : la liste combinée des saisies, avec les mêmes filtres que dans
  `mod_stage` (nom d'étudiant, thématique, statut) et un tri global sur toutes
  les activités liées. La page présente aussi, en tête, les **demandes de
  convention en attente de validation** par l'enseignant référent, agrégées de
  la même façon.

Une thématique n'existant que dans son activité d'origine, le filtre de
thématique est une valeur combinée (activité + thématique) étiquetée avec le
nom du cours : deux promotions peuvent avoir des thématiques homonymes sans
rapport entre elles.

## Fonctionnement

L'activité ne stocke ni droit ni donnée de stage : à l'affichage, elle relit,
pour l'utilisateur connecté, les attributions de référent (`stage_entry_teacher`)
déjà existantes dans chaque activité « Gestion des stages » liée, et ne montre
que celles où l'utilisateur a toujours la capacité `mod/stage:evaluateteacher`
sur l'instance d'origine. Retirer un enseignant d'un cours, ou révoquer son
rôle référent dans une activité, le retire donc automatiquement de la
synthèse — aucune synchronisation à faire.

Chaque ligne renvoie vers la page d'évaluation habituelle
(`mod/stage/teacher.php`) de l'activité d'origine : l'évaluation elle-même
continue de se faire dans le cours de la promotion concernée.

## Données personnelles (RGPD)

Le module ne stocke aucune donnée personnelle propre : sa seule table,
`stagesynthesis_link`, n'enregistre que l'identifiant des activités
« Gestion des stages » liées. Il déclare donc un fournisseur de confidentialité
« null » (`classes/privacy/provider.php`). Tout ce qui est affiché est relu à la
volée dans `mod_stage`, qui porte son propre fournisseur et les règles d'export
et de suppression correspondantes.

## Sauvegarde et restauration

Comme `mod_stage`, le module ne fournit pas d'implémentation `backup/moodle2/`
et déclare `FEATURE_BACKUP_MOODLE2` à `false` : une sauvegarde de cours
n'emporte pas la liste des activités liées. Après restauration d'un cours
contenant une synthèse, refaire le choix depuis **Gérer les liens** — l'opération
prend quelques secondes et ne dépend d'aucune donnée perdue par ailleurs.

## Tests unitaires

Même environnement que `mod_stage` (voir son `INSTALL.md`, section « Tests
unitaires ») :

```bash
vendor/bin/phpunit --testsuite mod_stagesynthesis_testsuite
```

| Fichier | Couvre |
|---|---|
| `managelinks_access_test.php` | Qui peut gérer les liens et qui voit le lien : l'enseignant non éditeur n'a ni l'un ni l'autre mais garde l'accès à la synthèse ; l'enseignant éditeur et le manager ont les deux. |

## Licence

GNU GPL v3 ou ultérieure, comme Moodle.
