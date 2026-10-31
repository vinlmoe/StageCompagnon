# Banc de charge de mod_stage

Scripts du test de charge décrit dans `../test-de-charge.tex`.

- `xmldb2pg.py` : traduit `mod/stage/db/install.xml` en schéma PostgreSQL (index des clés
  étrangères compris, comme le fait Moodle).
- `data.sql` : 20 promotions, 5 000 étudiants, 1 200 thématiques, 200 000 stages,
  2 000 000 de réponses.
- `queries.sql` : `EXPLAIN ANALYZE` des requêtes du module.
- `bench1.php` : fonctions de calcul pur de `locallib.php` sous charge. Il lit
  `funcs.php`, extrait de `locallib.php` avec des bouchons Moodle (voir le rapport).
- `mem.php` : mémoire PHP consommée par une liste de stages chargée en entier.

```bash
python3 xmldb2pg.py ../../../mod/stage/db/install.xml > schema.sql
createdb stress && psql -d stress < schema.sql && psql -d stress < data.sql
psql -d stress < queries.sql
```
