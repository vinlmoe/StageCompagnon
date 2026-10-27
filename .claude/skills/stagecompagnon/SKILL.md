---
name: stagecompagnon
description: Conventions et liste de contrôle pour faire évoluer StageCompagnon (modules Moodle mod_stage et mod_stagesynthesis) — nouvelle fonctionnalité, correctif, changement de schéma, chaîne de langue, événement, test, guide LaTeX, commit. À charger avant toute modification du code, des guides ou de la CI de ce dépôt, et avant de pousser.
---

# Faire évoluer StageCompagnon

Deux modules d'activité Moodle (4.3+, PHP 8.0+) pour les stages en école vétérinaire :

- `mod/stage` : le module principal (thématiques, saisies de stage, conventions, évaluations,
  validation DEVE, imports/exports, services web).
- `mod/stagesynthesis` : vue transversale de l'enseignant référent ; ne stocke aucune donnée de
  stage, relit les attributions de `mod_stage`. Dépend de `mod_stage` (`$plugin->dependencies`).

Vocabulaire métier : *DEVE* = scolarité (rôle `editingteacher`), *enseignant référent* (rôle
`teacher`), *maître de stage* (sans compte, lien à jeton), *saisie* = `stage_entry`,
*thématique* = `stage_theme`, *promotion* = un cours. Le circuit est décrit en tête du
`README.md` ; le lire avant de toucher aux statuts.

## Où se trouve quoi

| Besoin | Emplacement |
|---|---|
| Logique métier | `mod/stage/locallib.php` (fonctions `stage_*`), `classes/local/` (imports, cache du pilotage) |
| Pages | un script PHP par page à la racine du module (`register.php`, `deve.php`, …) |
| Formulaires | `classes/form/*_form.php` (moodleform) |
| Rendu | gabarits `templates/*.mustache` + `classes/output/` ; les pages anciennes utilisent encore `html_writer` — migrer au fil de l'eau |
| Schéma | `db/install.xml` + `db/upgrade.php` |
| Droits, tâches, services | `db/access.php`, `db/tasks.php`, `db/services.php` + `classes/external/` |
| Journaux | `classes/event/` (les événements de saisie héritent de `entry_event_base`) |
| RGPD | `classes/privacy/provider.php` |
| Sauvegarde | `backup/moodle2/` |
| Tests | `tests/*_test.php`, générateur `tests/generator/lib.php`, Behat `tests/behat/` |
| Documentation | `README.md`, `mod/*/INSTALL.md`, `CHANGELOG.md`, guides `docs/guides/*.tex` |

`mod/stage/thirdparty/` (FPDI) est redistribué tel quel : ne jamais le modifier ni le reformater.

## Conventions de code

- Code, commentaires, PHPDoc et noms de tests rédigés **en français** ; capitales accentuées
  autorisées en début de commentaire (seule règle désactivée dans `phpcs.xml`).
- Standard Moodle complet, avertissements compris : en-tête GPL, bloc `@package`,
  `@copyright 2026 Sébastien Lefebvre`, `@license`, PHPDoc sur chaque fonction, classe et
  propriété (`phpdoc --max-warnings=0` en CI), lignes ≤ 132 caractères, appels et `foreach`
  longs coupés en multi-lignes.
- `.editorconfig` : 4 espaces, LF, UTF-8 ; 2 espaces pour YAML/JSON/TeX.
- Paramètres de requête lus avec leur type exact (`PARAM_INT`, `PARAM_ALPHA`, …), jamais
  `PARAM_RAW` ; filtres de statut via `stage_optional_status_param`.
- Toute action d'écriture : `require_sesskey()` et `require_capability()` sur le contexte du module.
- Après une action, revenir à la page d'origine (`returnurl`, cf. `entry_actions_returnurl_test`).
- Les commentaires expliquent le *pourquoi* (règle métier, cas limite) comme dans le code existant.

## Liste de contrôle selon le changement

**Toute chaîne affichée** → ajouter la clé dans `lang/en/<module>.php` **et** `lang/fr/<module>.php`,
dans la section thématique correspondante (mêmes clés des deux côtés ; le français est la langue
de référence, l'anglais une traduction fidèle). Pas de texte en dur dans le PHP ni les gabarits.

**Changement de schéma** (table, champ, index) :
1. `db/install.xml` (commentaires de table/champ en français, `VERSION` = date du dernier point de sauvegarde) ;
2. bloc `if ($oldversion < AAAAMMJJXX)` dans `db/upgrade.php`, idempotent (`table_exists`,
   `field_exists`), terminé par `upgrade_mod_savepoint(true, AAAAMMJJXX, '<module>')` ;
3. `version.php` : `$plugin->version` = ce même numéro, `release` incrémentée ;
4. noms de champs courts : les noms de plus de 30 caractères ne passent qu'à partir de Moodle 4.3,
   borne basse du projet à ne pas abaisser sans les raccourcir ;
5. pour une table de données utilisateur ou rattachée à l'activité, propager **partout** :
   `stage_delete_instance` et `stage_reset_userdata` (`lib.php`), sauvegarde et restauration
   (`backup_stage_stepslib.php`, `restore_stage_stepslib.php`), `privacy/provider.php`
   (métadonnées + chaînes `privacy:metadata:*`, contextes, utilisateurs, export, suppressions),
   `classes/local/progress_cache.php` si elle influe sur le pilotage, transfert entre promotions
   si elle suit l'étudiant. Modèle complet : commit `103e31f` (table `stage_study_mobility`).

**Nouvelle étape ou action du circuit** → un événement dans `classes/event/` (CRUD, niveau
éducatif, description, URL), déclenché au bon moment (avant suppression pour un `d`), et
couvert dans `tests/events_test.php` ou le test de la fonctionnalité (`redirectEvents()`).

**Nouvelle capacité** → `db/access.php`, chaîne `<module>:<capacité>`, montée de version.

**Toute montée de `version.php`** → entrée dans `CHANGELOG.md`, section « Non publié »,
sous `### mod_stage`, `### mod_stagesynthesis`, `### Les deux modules` ou `### Dépôt`,
en phrases complètes décrivant l'effet pour l'utilisateur. Si `mod_stagesynthesis` dépend
d'un nouveau comportement de `mod_stage`, relever `$plugin->dependencies` avec un commentaire.

**Tests** → toute règle métier nouvelle ou corrigée a son test PHPUnit :
`final class <sujet>_test extends \advanced_testcase`, `namespace mod_stage;`, balises
`@covers` exactes, `resetAfterTest()`, données via `get_plugin_generator('mod_stage')`
(`create_theme`, `create_entry`, …), cas tabulaires en `dataProvider`. Étendre le générateur
plutôt que d'insérer en base à la main. Un parcours d'interface important → scénario
`tests/behat/` (sans JavaScript).

**Documentation** :
- comportement visible ou procédure de la DEVE → `mod/stage/INSTALL.md` et, s'il y est décrit,
  le guide concerné : `guide-parametrage.tex` (DEVE), `guide-etudiant.tex`,
  `guide-enseignant-referent.tex` ;
- dans le `.tex` modifié : incrémenter `\versionguide`, mettre `\dateguide` à jour,
  `\versionstage`/`\versionsynthese` si les versions ont changé, et ajouter une ligne à
  l'historique en tête ;
- recompiler et committer le PDF avec sa source :
  `cd docs/guides && latexmk -pdf <guide>.tex` (ou deux passes `pdflatex`) ; ne pas committer
  les fichiers auxiliaires ;
- écran modifié → captures à refaire selon `docs/guides/captures/README.md`
  (`demo_data.php` puis `node shot.js plan.json ../images`), ou le signaler si ce n'est pas
  faisable dans la session ;
- tout document rédigé (rapport, guide, note) est en **LaTeX**, sauf les courriels.

## Vérifier avant de pousser

```bash
.claude/skills/stagecompagnon/scripts/verifier.sh            # base origin/main par défaut
```

Le script contrôle la syntaxe PHP des fichiers modifiés, la parité des clés de langue en/fr,
la cohérence `version.php` / `upgrade.php` / `install.xml` / `CHANGELOG.md`, puis lance
`phpcs` sur les fichiers modifiés si le standard Moodle est installé (procédure dans la
section « Contrôle continu » du `README.md`, à l'emplacement `/tmp/cs`). `phpcbf` corrige la
plupart des écarts de style.

PHPUnit, Behat, `validate`, `savepoints`, `phpdoc` et `mustache` exigent une installation
Moodle : ils tournent dans la CI (`.github/workflows/ci.yml`, Moodle 4.3/PHP 8.0 et
Moodle 4.5/PHP 8.2). Si une installation locale existe, lancer au moins la suite touchée :
`vendor/bin/phpunit --testsuite mod_stage_testsuite`. Sinon, relire soigneusement les tests
écrits et le dire dans le compte rendu.

## Commits et branches

- Auteur : `git -c user.name="Sébastien Lefebvre" -c user.email="sebastien.lefebvre@vetbrain.fr" commit`.
- Message en français, première ligne « Sujet : description » courte (ex. `Mobilité d'études :
  import Excel par la DEVE`, `Tests : …`, `Style : …`, `CI : …`, `Guide de paramétrage : …`),
  corps qui explique le quoi et le pourquoi, et liste ce qui a été mis à jour (tests, guide,
  CHANGELOG, INSTALL).
- **Aucune** ligne d'attribution (`Co-Authored-By`, `Claude-Session`, « Generated with Claude
  Code ») ni dans les commits ni dans les descriptions de PR.
- Un commit par changement cohérent ; les corrections de style séparées des changements de fond.
