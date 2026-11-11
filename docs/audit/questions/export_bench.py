"""Mesure de l'export général (export.php) : durée côté client et côté serveur, pic de mémoire
PHP (consigné par export_prepend.php), taille du classeur ; enregistre le dernier classeur reçu.

Usage : python3 export_bench.py site-<tag>.json <page> <répétitions> <classeur.xlsx>
<page> : export.php, ou une copie de l'ancienne version placée dans mod/stage (export_old.php).
"""
import json, os, sys, time
from lib import session, timed

site = json.load(open(sys.argv[1]))
page, runs, out = sys.argv[2], int(sys.argv[3]), sys.argv[4]
log = os.environ['EXPORT_BENCH_LOG']
s = session(f"deve{site['tag']}_1", 'Deve1!xx')
results = []
for i in range(runs):
    before = os.path.getsize(log) if os.path.exists(log) else 0
    r, dt, errs = timed(s, 'GET', f"/mod/stage/{page}?id={site['cmid']}")
    if r.status_code != 200 or errs or not r.content.startswith(b'PK'):
        sys.exit(f'échec : {r.status_code} {errs} {r.content[:300]!r}')
    time.sleep(0.2)
    with open(log) as f:
        f.seek(before)
        server = [json.loads(line) for line in f if page in line]
    results.append({'client_s': round(dt, 2), **(server[-1] if server else {}), 'bytes': len(r.content)})
    print(json.dumps(results[-1]))
open(out, 'wb').write(r.content)
best = min(results, key=lambda x: x['client_s'])
print(f"{page} : meilleur {best['client_s']} s, médiane {sorted(x['client_s'] for x in results)[runs // 2]} s, "
      f"pic {max(x.get('peakmb', 0) for x in results)} Mo, {best['bytes']} octets")
