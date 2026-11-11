# Banc des types de question et des exports

Scripts de l'audit décrit dans `../audit-types-de-question.tex`. Ils tournent contre un vrai
Moodle 4.5 (PostgreSQL 16) où `mod/stage` est **copié** (pas de lien symbolique).
`MOODLE_CONFIG` donne le chemin de `config.php`, `MOODLE_URL` l'adresse du serveur
(par défaut `http://localhost:8080`). `config.php` doit contenir `$CFG->noemailever = true;`
et `$CFG->noreplyaddress`.

```bash
PHP_CLI_SERVER_WORKERS=16 php -d max_input_vars=5000 -d memory_limit=256M -S localhost:8080 -t moodle
```

## Données

- `setup.php <étudiants>` : thématique Q dont les quatre listes (étudiant, enseignant, maître de
  stage, demande) contiennent les huit types de question, avec des options à virgule, guillemets
  et balise ; thématique R (pré-stage) avec questionnaire de demande, check-list des prérequis et
  un enseignant responsable ; un stage signé sur Q (gabarit français ou anglais, lien du maître de
  stage) et un stage sans convention sur R par étudiant. Écrit `site-<tag>.json`.
- `volume.php site.json <n>` : `n` stages de plus sur R, chacun avec ses réponses au
  questionnaire, puis durée, requêtes et mémoire de `stage_get_request_answers_export()`.

## Scénarios

- `scenarios.py site.json` : 68 contrôles par HTTP (saisie et refus par type, falsifications,
  enseignant, maître de stage en français et en anglais, demande de convention, reprise d'une
  demande refusée, édition des listes et changements de type, export général, export pré-stage
  dans cinq formats, filtres, droits, courriel, injection de formule). Résultat : `logs/scenarios.txt`.
- `transfer.php` : transfert entre promotions d'une question de même nom et de type différent.
- `check.php site.json` : invariants (forme de chaque réponse selon son type, doublons, réponses
  obligatoires vides).

## Export général

Comparaison de l'ancienne version de `export.php` (MoodleExcelWorkbook) et de la version en flux
(OpenSpout). L'ancienne version est copiée dans `mod/stage/export_old.php` du Moodle de test ; le
serveur est lancé avec `-d auto_prepend_file=export_prepend.php` et `EXPORT_BENCH_LOG` désignant
le fichier où chaque requête consigne sa durée et son pic de mémoire. Résultats : `logs/export.txt`.

- `export_data.php site.json` : complète les stages de `setup.php` pour que chaque colonne des
  trois feuilles porte une valeur (réponses des huit types, détail de convention, étapes datées,
  plages multiples, rapports déposés, évaluations StageVet notées, textes commençant par = + - @,
  date de naissance avant 1970).
- `export_bench.py site.json <page> <n> <classeur.xlsx>` : télécharge `n` fois l'export ; durée,
  mémoire, taille ; garde le dernier classeur.
- `export_compare.py avant.xlsx après.xlsx` : compare feuilles, valeurs et types cellule par
  cellule, puis liste les écarts de format.

```bash
EXPORT_BENCH_LOG=$PWD/export.log php -d max_input_vars=5000 -d auto_prepend_file=$PWD/export_prepend.php -S localhost:8080 -t moodle
php setup.php 1500 && php export_data.php site-<tag>.json
python3 export_bench.py site-<tag>.json export_old.php 3 avant.xlsx
python3 export_bench.py site-<tag>.json export.php 3 apres.xlsx
python3 export_compare.py avant.xlsx apres.xlsx
```

## Montée en charge

À jouer sur un site neuf, pas sur celui des scénarios : `scenarios.py` réenregistre une liste
d'évaluation, ce qui retire la balise de l'option `<b>Urgences</b>` alors que `site.json` garde le
texte d'origine (les envois de l'étudiant sont alors refusés). Résultats de l'export en flux :
`logs/load-flux-*.json`, `logs/scenarios-flux.txt`, `logs/check-flux.txt`.

- `load.py site.json <threads> <premier> <nombre>` : pour chaque stage, auto-évaluation (doublée
  pour un stage sur dix), avis de l'enseignant, évaluation du maître de stage, demande de
  convention ; deux comptes DEVE téléchargent en boucle l'export général et l'export pré-stage.
  Résultats : `logs/load-<threads>.json`.

```bash
php setup.php 700
python3 load.py site-<tag>.json 16 100 200
python3 load.py site-<tag>.json 32 300 200
python3 load.py site-<tag>.json 64 500 200
php check.php site-<tag>.json
php -d memory_limit=2G volume.php site-<tag>.json 20000
```
