"""Montée en charge des questionnaires à huit types de question, par HTTP.

Usage : python3 load.py site-<tag>.json <threads> <premier stage> <nombre de stages> [graine]

Pour chaque stage de la tranche, un client enchaîne : auto-évaluation de l'étudiant (doublée,
en parallèle, pour un stage sur dix : double clic), avis de l'enseignant référent, évaluation du
maître de stage par son lien, puis demande de convention de l'étudiant sur la thématique R
(questionnaire pré-stage). Pendant toute la vague, deux membres de la DEVE téléchargent en boucle
l'export général et l'export pré-stage (Excel et CSV). Temps par action dans logs/load-<threads>.json.
"""
import json, random, statistics, sys, threading, time
from collections import defaultdict
from concurrent.futures import ThreadPoolExecutor
from lib import *
from scenarios_lib import valid_answers, request_form, fill_request, request_answers

site = json.load(open(sys.argv[1]))
THREADS = int(sys.argv[2])
FIRST, COUNT = int(sys.argv[3]), int(sys.argv[4])
random.seed(int(sys.argv[5]) if len(sys.argv) > 5 else 1)
CM, TAG = site['cmid'], site['tag']
E = site['entries'][FIRST:FIRST + COUNT]
TIMES = defaultdict(list)
ERRORS = defaultdict(list)
LOCK = threading.Lock()


def record(action, r, dt, errs, expect_redirect=True):
    with LOCK:
        TIMES[action].append(dt)
        bad = errs or r.status_code >= 500
        if bad:
            ERRORS[action].append((r.status_code, errs[:2]))


def student(e, seed):
    s = session(e['user'], 'Etud1!xx')
    good, _ = valid_answers(site, 'student', seed=seed)
    r, dt, errs = timed(s, 'POST', f"/mod/stage/entry.php?id={CM}&entryid={e['id']}", data=good + [('sesskey', s.sesskey)])
    # Un double clic peut arriver après la première soumission : la page dit alors que le stage a
    # changé, ce qui n'est pas une erreur.
    errs = [x for x in errs if 'errorentrychanged' not in x and 'a été modifié' not in x]
    record('étudiant', r, dt, errs)


def chain(e):
    seed = e['id']
    if e['id'] % 10 == 0:
        with ThreadPoolExecutor(2) as pool:
            list(pool.map(lambda _: student(e, seed), range(2)))
    else:
        student(e, seed)
    t = session(e['teacher'], 'Ens1!xxx')
    good, _ = valid_answers(site, 'teacher', seed=seed)
    r, dt, errs = timed(t, 'POST', f"/mod/stage/teacher.php?id={CM}&entryid={e['id']}", data=good + [('sesskey', t.sesskey)])
    record('enseignant', r, dt, errs)
    g = requests.Session()
    r, dt, errs = timed(g, 'GET', f"/mod/stage/tutor_eval.php?token={e['token']}")
    record('maître de stage (page)', r, dt, errs)
    sk = re.search(r'name="sesskey" value="([^"]+)"', r.text)
    good, _ = valid_answers(site, 'tutor', e['lang'], seed=seed)
    r, dt, errs = timed(g, 'POST', f"/mod/stage/tutor_eval.php?token={e['token']}", data=good + [('sesskey', sk.group(1) if sk else '')])
    record('maître de stage (envoi)', r, dt, errs)
    t1 = time.time()
    sess, u, r, f, errs = request_form(site, e['user'], e['rid'])
    record('demande (page)', r, time.time() - t1, errs)
    if f is not None:
        r, dt, errs = timed(sess, 'POST', u, data=flat(fill_request(site, f, e, request_answers(site, seed), r.text)))
        record('demande (envoi)', r, dt, errs)


STOP = threading.Event()


def exporter(user, urls):
    d = session(user, 'Deve1!xx')
    while not STOP.is_set():
        for action, url in urls:
            r, dt, errs = timed(d, 'GET', url)
            ok = 'attachment' in r.headers.get('Content-Disposition', '')
            record(action, r, dt, errs if ok else errs + [f'pas de fichier ({r.status_code})'])
            if STOP.is_set():
                break


GENERAL = [('export général', f'/mod/stage/export.php?id={CM}')]
PRESTAGE = [('export pré-stage Excel', f"/mod/stage/request_answers_export.php?id={CM}&themeid={site['themer']}&dataformat=excel"),
            ('export pré-stage CSV', f"/mod/stage/request_answers_export.php?id={CM}&themeid={site['themer']}&dataformat=csv")]


# Connexions avant la vague : elles ne sont pas mesurées.
with ThreadPoolExecutor(16) as pool:
    list(pool.map(lambda e: (session(e['user'], 'Etud1!xx'), session(e['teacher'], 'Ens1!xxx')), E))
exporters = [threading.Thread(target=exporter, args=(f'deve{TAG}_2', GENERAL)),
             threading.Thread(target=exporter, args=(f'deve{TAG}_3', PRESTAGE))]
for x in exporters:
    x.start()
t0 = time.time()
with ThreadPoolExecutor(THREADS) as pool:
    list(pool.map(chain, E))
wall = time.time() - t0
STOP.set()
for x in exporters:
    x.join()

summary = {'threads': THREADS, 'entries': len(E), 'wall_s': round(wall, 1), 'actions': {}}
for action, times in TIMES.items():
    times = sorted(t for t in times if t > 0) or [0]
    summary['actions'][action] = {
        'n': len(TIMES[action]), 'p50': round(statistics.median(times), 3),
        'p95': round(times[int(0.95 * (len(times) - 1))], 3), 'max': round(times[-1], 3),
        'errors': len(ERRORS[action]), 'examples': ERRORS[action][:2]}
ids = ','.join(str(e['id']) for e in E)
rids = ','.join(str(e['rid']) for e in E)
summary['final'] = {
    'statuts': psql(f'SELECT status, count(*) FROM mdl_stage_entry WHERE id IN ({ids}) GROUP BY 1 ORDER BY 1'),
    'maître de stage évalué': psql(f'SELECT count(*) FROM mdl_stage_entry WHERE id IN ({ids}) AND tutortime > 0'),
    'demandes': psql(f'SELECT conventionstatus, count(*) FROM mdl_stage_entry WHERE id IN ({rids}) GROUP BY 1'),
    'réponses': psql(f'SELECT count(*) FROM mdl_stage_answer WHERE entryid IN ({ids},{rids})'),
}
os.makedirs('logs', exist_ok=True)
json.dump(summary, open(f'logs/load-{THREADS}.json', 'w'), ensure_ascii=False, indent=1)
print(json.dumps(summary, ensure_ascii=False, indent=1))
