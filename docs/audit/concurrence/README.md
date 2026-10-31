# Banc de concurrence de mod_stage

Scripts du test décrit dans `../test-de-concurrence.tex`. Ils tournent contre un vrai Moodle
4.5 (PostgreSQL 16) où `mod/stage` et `mod/stagesynthesis` sont **copiés** : avec des liens
symboliques, le `require('../../config.php')` des pages ne trouve pas `config.php`.

Variables d'environnement : `MOODLE_CONFIG` (chemin de `config.php`, par défaut
`/home/user/moodle-stress/moodle/config.php`) et `MOODLE_DATAROOT` (pour `h3.py`).
`config.php` doit contenir `$CFG->noemailever = true;`.

## Données

- `setup.php <étudiants> <enseignants>` : cours, activité, 10 thématiques, étudiants,
  enseignants et 6 comptes DEVE (`deve<tag>_1..6`, mot de passe `Deve1!xx`). Écrit `site-<tag>.json`.
- `genfiles.php site-<tag>.json` : CSV d'entrées (deux fichiers à moitié recouvrants), CSV de
  référents (deux fichiers contradictoires), CSV StageVet, classeurs de mobilités.
- `genhist.php site-<tag>.json` : classeur de suivi historique (3 blocs de thématiques).
- `mktarget.php <stageid source>` : activité vide, cible d'un import depuis un autre cours.
- `reset.php site.json` : vide les stages, référents et mobilités de l'activité.

## Processus concurrents (CLI)

- `worker.php site.json <scénario> [args]` : une procédure dans son propre processus, une
  ligne JSON (durée, requêtes, mémoire, exception). Scénarios : `entries`, `teachers`,
  `stagevet`, `mobility`, `ws <n> [décalage]`, `bulk <n>`, `bulkdelete [délai ms]`,
  `adminimport <source> <cible>`, `task <classe>`, `adhoc`.
- `wave.sh site.json <nom> "scénario args" ...` : lance les workers ensemble (départ
  synchronisé via `STARTAT`), puis `check.php`.
- `check.php site.json` : invariants (doublons de stages, stages sans plage, orphelins,
  attributions, mobilités, verrous consultatifs restants, transactions ouvertes).

```bash
F=files-2
./wave.sh site-2.json mobilites "mobility $F/mobility_A.xlsx" "mobility $F/mobility_B.xlsx"
./wave.sh site-2.json melange "entries $F/entries_A.csv" "stagevet $F/stagevet.csv" \
  "teachers $F/teachers_A.csv" "ws 500" "bulk 800" "mobility $F/mobility_A.xlsx"
```

## Parcours HTTP

Serveur intégré de PHP, plusieurs workers :

```bash
PHP_CLI_SERVER_WORKERS=10 php -d max_input_vars=5000 -d memory_limit=256M \
  -d post_max_size=64M -d upload_max_filesize=64M -S localhost:80 -t moodle
```

- `web.py`, `flows.py` : connexion, envoi de formulaires, repérage des erreurs dans la page.
- `h1.py` : deux DEVE confirment le même import historique.
- `h2.py` : deux DEVE confirment la même restauration d'export global.
- `h3.py` : prévisualisation d'une restauration d'un gros export (mémoire).
- `pages.py site.json` : chronométrage de toutes les pages de l'activité.
- `memread.php <classe> <fichier>` : mémoire de lecture d'un classeur par un importeur.

## Circuit du stage entre acteurs (HTTP)

Le journal standard doit écrire chaque événement aussitôt, pour que l'audit en connaisse l'ordre :

```bash
php admin/cli/cfg.php --component=logstore_standard --name=buffersize --set=1
```

- `setup4.php <étudiants>` : stages signés et commencés (thème A en commentaire libre, thème B
  avec questionnaires), un référent par étudiant, lien du maître de stage. Écrit `actors-<tag>.json`.
- `actors.py actors-<tag>.json <tours> <threads> <graine>` : étudiants (auto-évaluation, jours
  effectifs), enseignants (avis, refus, jours effectifs), maîtres de stage (lien à jeton) et DEVE
  (validation, refus, annulation, réinitialisation, contournement, jours effectifs) agissent
  ensemble, chacun dans sa session. Réponses dans `logs/actors.jsonl`, temps par action.
- `audit_actors.php actors-<tag>.json <id de journal de départ>` : rejoue les événements de
  chaque stage sur le circuit ; transitions interdites, mises à jour perdues, cohérence des champs.
- `setup7.php <étudiants>` : demandes de convention en attente de l'enseignant, deux référents
  par étudiant. Écrit `conv-<tag>.json`.
- `conventions.py conv-<tag>.json <threads> <graine>` : les deux référents (validation, refus),
  deux DEVE (revue : validation, refus) et une annulation du stage, en même temps.
- `audit_conv.php conv-<tag>.json <id de journal de départ>` : suites d'événements, décisions
  contraires, actions sur stage annulé, statuts finaux.
- `historique_stage.php <id de journal> <stage>...` : événements et champs d'un stage.
- `actorslib.py` : formulaires (valeurs par défaut comme un navigateur), sessions, `psql`.

```bash
mkdir -p logs
L0=$(psql -tAc "SELECT MAX(id) FROM mdl_logstore_standard_log")
python3 actors.py actors-6.json 2 48 7 && php audit_actors.php actors-6.json $L0
```
