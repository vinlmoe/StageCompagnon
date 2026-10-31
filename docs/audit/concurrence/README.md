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
