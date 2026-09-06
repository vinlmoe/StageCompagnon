# Moodle-stage

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
    │                                                                      ▼
    └──auto-évaluation◀────────────────────────────────────────── ouverture des évaluations
                │
                ├──▶ Maître de stage (lien à jeton, sans compte Moodle)
                └──▶ Enseignant référent ──▶ DEVE : durée retenue, validation finale
```

`mod_stage` porte tout ce circuit à l'échelle d'une promotion (une activité par
cours de promotion). `mod_stagesynthesis` ne fait que regrouper la vue : il ne
stocke aucune donnée de stage et n'accorde aucun droit supplémentaire — il relit
les attributions de référent déjà en place dans chaque activité liée et n'affiche
que celles où l'enseignant a toujours ses droits à la source.

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

## Points à connaître avant une mise en production

**Sauvegarde.** Aucun des deux modules ne fournit d'implémentation
`backup/moodle2/`. Une sauvegarde de cours Moodle s'exécute normalement mais
n'emporte pas les données des activités. La conservation passe par une
sauvegarde de la base (`mdl_stage*`) et du `moodledata`.

**Données personnelles.** `mod_stage` implémente le fournisseur de
confidentialité Moodle. La suppression d'un étudiant efface l'intégralité de ses
stages et évaluations, y compris les PDF de conventions signées ; la suppression
d'un membre du personnel, elle, laisse intacts les stages des étudiants dont il
n'est que le référent et se limite à dissocier ses références. Si vos conventions
signées sont soumises à une obligation d'archivage, extrayez-les avant de traiter
une demande de suppression. Détail en §11 de `mod/stage/INSTALL.md`.

**Rôle DEVE.** Le module s'appuie sur les archétypes Moodle standard. Pour
distinguer la scolarité des enseignants éditeurs de contenu, dupliquer le rôle
« Enseignant » et n'y cocher que les capacités `mod/stage:*` (§2 de
`mod/stage/INSTALL.md`).

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

## Licence

GNU GPL v3 ou ultérieure, comme Moodle. FPDI est distribué sous licence MIT
(voir `mod/stage/thirdpartylibs.xml`).
