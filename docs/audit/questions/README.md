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

## Montée en charge

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
