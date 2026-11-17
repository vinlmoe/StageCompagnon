# Banc des thématiques à organismes proposés

Scripts du test de charge décrit dans `../test-de-charge-organismes.tex`. Ils tournent contre un
vrai Moodle 4.5 (PostgreSQL 16) où `mod/stage` est **copié** (pas de lien symbolique).
`MOODLE_CONFIG` donne le chemin de `config.php`, `MOODLE_URL` l'adresse du serveur (par défaut
`http://localhost:8080`). `config.php` doit contenir `$CFG->noemailever = true;` et
`$CFG->noreplyaddress`. Les outils HTTP (sessions, lecture des formulaires, détection des erreurs)
sont ceux de `../questions/lib.py`. Les classeurs importés sont écrits par `openpyxl`
(`pip install openpyxl requests`).

```bash
PHP_CLI_SERVER_WORKERS=16 php -d max_input_vars=5000 -d memory_limit=256M -S localhost:8080 -t moodle
```

## Données

- `setup.php <étudiants> [organismes] [vœux]` : une promotion, une thématique à organismes
  proposés (vœux ouverts, hors liste interdit), des organismes d'une à quelques places pour 85 % de
  l'effectif, dix enseignants référents, quatre comptes DEVE, un gabarit de convention. Écrit
  `site-<tag>.json`. Une vague consomme son site : en créer un par vague.

## Vague

- `load.py site.json <threads> [graine]` : chaque étudiant a sa session et enchaîne ce que ferait
  une promotion le jour de l'ouverture puis de la clôture des vœux.
  0. **Import Excel** : modèle téléchargé puis réimporté tel quel ; classeur qui ajoute et met à
     jour ; même classeur importé au même instant par deux comptes DEVE ; classeur de 2 000 lignes
     (dans la thématique libre) puis son réimport ; fichier illisible et classeur sans colonne du
     nom, refusés. Les organismes ajoutés entrent dans la liste proposée aux étudiants.
  1. **Vœux** : page « Mes stages », page des organismes, enregistrement des vœux selon une
     popularité très inégale des organismes ; modifications, doubles clics, retraits, envois
     invalides. Pendant ce temps, un compte DEVE réimporte en boucle le classeur des organismes
     (mises à jour seules) et un classeur de 40 lignes toutes en erreur. Un autre recharge en
     boucle ses trois pages pendant toute la vague.
  2. **Clôture** : trois comptes DEVE lancent l'appariement au même instant pendant que des
     étudiants renvoient leurs vœux.
  3. **Dernière place** : deux comptes DEVE affectent au même instant deux étudiants différents
     sur la place qui vient d'être ajoutée à un organisme complet.
  4. **Demandes de convention** : demande préremplie des étudiants affectés (double clic pour un
     sur six) ; demandes hors liste des autres, interdites puis autorisées, sans puis avec SIRET,
     ou à l'étranger.

  Résultat : `logs/load-<threads>.json` (durées par action, décompte des issues, refus attendus
  distingués des erreurs). Code de sortie 1 si une réponse est une erreur.
- `check.php site.json` : neuf invariants relus en base (places jamais dépassées, une affectation
  par étudiant, affectation sur un vœu de l'étudiant, vœux cohérents, stage rattaché au bon
  étudiant, pas de stage en double, SIRET des stages hors liste, demande complète, aucun
  organisme en double après les imports). Code de
  sortie 1 si l'un est violé. Résultats : `logs/check-<threads>.json`.

Sur MariaDB : `MOODLE_DB=mariadb`, avec `MOODLE_URL` et `MOODLE_CONFIG` pointant sur un Moodle
branché sur cette base (identifiants `moodle`/`moodle`, base `moodle`, sur `127.0.0.1`). Le journal
s'appelle alors `logs/load-mariadb-<threads>.json`. Le site doit être en français : le script
reconnaît les refus prévus à leur message.

```bash
for threads in 16 32 64; do
  site=$(php setup.php 400 80 5 | cut -d' ' -f1)
  python3 load.py $site $threads | tail -40
  php check.php $site | tee logs/check-$threads.json
done
```
