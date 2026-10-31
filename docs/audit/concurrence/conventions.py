"""Test multi-acteurs des conventions : deux enseignants référents (validation, refus), deux
membres de la DEVE (revue : validation, refus) et une annulation du stage, en même temps.

Usage : python3 conventions.py conv-<tag>.json [threads] [graine] [nombre de stages]
"""
import json, random, re, sys, threading, time
from collections import Counter, defaultdict
from concurrent.futures import ThreadPoolExecutor
from actorslib import *

site = json.load(open(sys.argv[1]))
THREADS = int(sys.argv[2]) if len(sys.argv) > 2 else 32
random.seed(int(sys.argv[3]) if len(sys.argv) > 3 else 1)
ENTRIES = site['entries'][:int(sys.argv[4])] if len(sys.argv) > 4 else site['entries']
CM = site['cmid']


def review(s, page, e, button, comment=''):
    url = f'/mod/stage/{page}?id={CM}&entryid={e["id"]}'
    r, _, _ = timed(s, 'GET', url)
    f = mform(r.text, 'rejectcomment')
    if f is None:
        return r, 0, ['(formulaire absent)']
    f[button] = 'x'
    if comment:
        f['rejectcomment'] = comment
    return timed(s, 'POST', url, data=f)


def deve(e, k):
    return session(f"deve{site['tag']}_{1 + (e['id'] + k) % 6}", 'Deve1!xx')


def t1_validate(e):
    return review(session(e['t1'], 'Ens1!xxx'), 'convention_teacher_validate.php', e, 'validateconvention')


def t2_reject(e):
    return review(session(e['t2'], 'Ens1!xxx'), 'convention_teacher_validate.php', e, 'rejectconvention', 'Refus référent 2')


def deve_validate(e):
    return review(deve(e, 0), 'convention_review.php', e, 'validateconvention')


def deve_reject(e):
    return review(deve(e, 3), 'convention_review.php', e, 'rejectconvention', 'Refus DEVE')


def deve_cancel(e):
    s = deve(e, 1); url = f'/mod/stage/cancel_entry.php?id={CM}&entryid={e["id"]}'
    r, _, _ = timed(s, 'GET', url)
    f = mform(r.text, 'cancelcomment')
    if f is None:
        return r, 0, ['(déjà annulé)']
    f['cancelcomment'] = 'Annulé'
    return timed(s, 'POST', url, data=f)


out = open('logs/conventions.jsonl', 'a'); lock = threading.Lock(); stats = Counter()


def run(action, e):
    time.sleep(random.random() * 0.03)
    try:
        r, dt, errs = action(e); code = r.status_code
        formerr = re.findall(r'id="id_error_([a-z_\[\]0-9]+)"[^>]*>\s*([^<]+)', r.text)
    except Exception as ex:  # noqa
        code, dt, errs, formerr = 'EXC', 0, [repr(ex)[:200]], []
    real = [x for x in errs if not x.startswith('(')]
    with lock:
        out.write(json.dumps({'action': action.__name__, 'entry': e['id'], 'code': code, 'sec': round(dt, 3), 'errs': real,
                              'info': [x for x in errs if x.startswith('(')], 'formerr': formerr[:3]},
                             ensure_ascii=False) + '\n')
        stats[(action.__name__, 'erreur' if real or formerr else 'ok')] += 1


users = {(e[k], 'Ens1!xxx') for e in ENTRIES for k in ('t1', 't2')} | {(f"deve{site['tag']}_{i}", 'Deve1!xx') for i in range(1, 7)}
with ThreadPoolExecutor(16) as pool:
    list(pool.map(lambda u: session(*u), users))
jobs = []
for e in ENTRIES:
    # Le référent 1 valide pendant que le référent 2 refuse ; la DEVE revoit la demande (validation
    # ou refus) et, une fois sur trois, annule le stage.
    acts = [t1_validate, t2_reject, deve_validate, deve_reject] + ([deve_cancel] if e['id'] % 3 == 0 else [])
    random.shuffle(acts)
    jobs += [(a, e) for a in acts]
t0 = time.time()
with ThreadPoolExecutor(THREADS) as pool:
    for a, e in jobs:
        pool.submit(run, a, e)
print(f'{len(jobs)} requêtes en {time.time() - t0:.1f} s ; {dict(stats)}')
