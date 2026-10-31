# Banc de charge de mod_stage

Scripts du test de charge décrit dans `../test-de-charge.tex`.

## Base de stress

- `xmldb2pg.py` : traduit `mod/stage/db/install.xml` en schéma PostgreSQL (index des clés
  étrangères compris, comme le fait Moodle).
- `data.sql` : 20 promotions, 5 000 étudiants, 1 200 thématiques, 200 000 stages,
  2 000 000 de réponses. Le rapport décrit les compléments (fichiers, plages, responsables,
  durées requises) ajoutés pour la seconde campagne.
- `queries.sql`, `queries_after.sql` : `EXPLAIN ANALYZE` des requêtes du module, avant et après
  correction (le second porte un horodatage « maintenant » figé).

```bash
python3 xmldb2pg.py ../../../mod/stage/db/install.xml > schema.sql
createdb stress && psql -d stress < schema.sql && psql -d stress < data.sql
psql -d stress < queries.sql
```

## Code du module contre la base de stress

- `boot.php` : amorce sans Moodle. Un `$DB` minimal (PDO sur la base `stress`) compte les
  requêtes, et les quelques API Moodle appelées sont remplacées par des bouchons.
- `extraire_ancien.py <commit>` : extrait d'une version antérieure les fonctions comparées,
  renommées `old_*` (à écrire dans `old.php`).
- `equiv.php` : vérifie que les fonctions corrigées renvoient les mêmes résultats que les
  anciennes (pagination SQL, chargements groupés, paquets, doublons probables…).
- `perf.php` : requêtes, durée et mémoire, ancien code contre nouveau.
- `race.php` : validation ou rejet DEVE d'un stage annulé entre-temps.
- `bench1.php`, `mem.php` : première campagne (fonctions de calcul, mémoire des listes).

```bash
python3 extraire_ancien.py <commit avant correction> > old.php
php -d memory_limit=4G equiv.php && php -d memory_limit=4G perf.php && php race.php
```
