"""Test multi-acteurs : étudiants, enseignants référents, maîtres de stage et DEVE agissent en même
temps, par HTTP, chacun dans sa propre session, sur les mêmes stages.

Usage : python3 actors.py actors-<tag>.json <tours> [threads] [graine]
Chaque tour lance, pour chaque stage, toutes les actions de tous les acteurs dans un ordre
aléatoire, sur un pool de threads. Le journal des réponses est écrit dans logs/actors.jsonl.
"""
import json, random, re, subprocess, sys, threading, time
from collections import Counter, defaultdict
from concurrent.futures import ThreadPoolExecutor
from html.parser import HTMLParser
from web import BASE, timed
from actorslib import *
import requests

site = json.load(open(sys.argv[1]))
ROUNDS = int(sys.argv[2]) if len(sys.argv) > 2 else 2
THREADS = int(sys.argv[3]) if len(sys.argv) > 3 else 32
random.seed(int(sys.argv[4]) if len(sys.argv) > 4 else 1)
CM = site['cmid']


QUESTIONS = defaultdict(list)  # (thème, type) -> ids
for line in psql(f"""SELECT t.name, l.evaltype, q.questionid FROM mdl_stage_theme t
    JOIN mdl_stage_evallist l ON l.id IN (t.studentlistid, t.teacherlistid, t.tutorlistid)
    JOIN mdl_stage_evallist_question q ON q.listid = l.id WHERE t.stageid = {site['stageid']}""").splitlines():
    name, evaltype, qid = line.split('|')
    QUESTIONS[(name, evaltype)].append(int(qid))


def deve(e):
    return session(f"deve{site['tag']}_{1 + e['id'] % 6}", 'Deve1!xx')


def answers(e, evaltype):
    return {f'q_{q}': f'Réponse {evaltype} {e["id"]}' for q in QUESTIONS[(e['theme'], evaltype)]}


def a_student_eval(e):
    s = session(e['user'], 'Etud1!xx'); url = f'/mod/stage/entry.php?id={CM}&entryid={e["id"]}'
    if QUESTIONS[(e['theme'], 'student')]:
        return post(s, url, answers(e, 'student'))
    r, _, _ = timed(s, 'GET', url)
    f = mform(r.text, 'studentselfeval')
    if f is None:
        return r, 0, ['(formulaire absent : stage non modifiable)']
    f['studentselfeval[text]'] = f'Auto-évaluation {e["id"]}'
    return timed(s, 'POST', url, data=f)


def a_teacher_eval(e):
    s = session(e['teacher'], 'Ens1!xxx')
    data = answers(e, 'teacher') or {'teachereval': f'Avis enseignant {e["id"]}'}
    return post(s, f'/mod/stage/teacher.php?id={CM}&entryid={e["id"]}', data)


def a_teacher_reject(e):
    s = session(e['teacher'], 'Ens1!xxx')
    return post(s, f'/mod/stage/teacher.php?id={CM}&entryid={e["id"]}', {'rejectstage': 'Refuser', 'rejectcomment': 'Non'})


def a_tutor_eval(e):
    s = requests.Session(); url = f'/mod/stage/tutor_eval.php?token={e["token"]}'
    r, _, _ = timed(s, 'GET', url)
    m = re.search(r'name="sesskey" value="([^"]+)"', r.text)
    if not m:
        return r, 0, ['(formulaire maître de stage absent)']
    data = answers(e, 'tutor') or {'tutoreval': f'Avis maître de stage {e["id"]}'}
    data['sesskey'] = m.group(1); data['token'] = e['token']
    return timed(s, 'POST', url, data=data)


def a_deve_validate(e):
    return post(deve(e), f'/mod/stage/deve.php?id={CM}&entryid={e["id"]}', {'retainedduration': 8, 'devecomment': 'Validé'})


def a_deve_reject(e):
    return post(deve(e), f'/mod/stage/deve.php?id={CM}&entryid={e["id"]}', {'rejectstage': 'Refuser', 'devecomment': 'Refusé'})


def a_deve_cancel(e):
    s = deve(e); url = f'/mod/stage/cancel_entry.php?id={CM}&entryid={e["id"]}'
    r, _, _ = timed(s, 'GET', url)
    f = mform(r.text, 'cancelcomment')
    if f is None:
        return r, 0, ['(déjà annulé)']
    f['cancelcomment'] = 'Annulé par la DEVE'
    return timed(s, 'POST', url, data=f)


def a_deve_reset(e):
    return post(deve(e), f'/mod/stage/deve.php?id={CM}&entryid={e["id"]}', {'resetentry': 1})


def a_deve_bypass(e):
    return post(deve(e), f'/mod/stage/deve.php?id={CM}&entryid={e["id"]}', {'bypasstutor': 1})


def workdays(page, s, e, days):
    return post(s, f'/mod/stage/{page}?id={CM}&entryid={e["id"]}', {'saveworkdays': 1, 'workdays[]': days})


def a_workdays_student(e):
    return workdays('entry.php', session(e['user'], 'Etud1!xx'), e, site['workdays'][:2])


def a_workdays_teacher(e):
    return workdays('teacher.php', session(e['teacher'], 'Ens1!xxx'), e, site['workdays'][1:])


def a_workdays_deve(e):
    return workdays('deve.php', deve(e), e, site['workdays'])


ACTIONS = [a_student_eval, a_teacher_eval, a_tutor_eval, a_deve_validate, a_workdays_student, a_workdays_teacher,
           a_workdays_deve]
OPTIONAL = [a_deve_cancel, a_deve_reset, a_deve_reject, a_deve_bypass, a_teacher_reject]

out = open('logs/actors.jsonl', 'a')
olock = threading.Lock()
stats = Counter()
times = defaultdict(list)


def run(action, e, rnd):
    time.sleep(random.random() * 0.05)
    try:
        r, dt, errs = action(e)
        code = r.status_code
    except Exception as ex:  # noqa
        code, dt, errs = 'EXC', 0, [repr(ex)[:200]]
    real = [x for x in errs if not x.startswith('(')]
    notes = re.findall(r'alert-(?:danger|warning)[^>]*>(.*?)</div>', r.text, re.S) if code != 'EXC' else []
    rec = {'round': rnd, 'action': action.__name__, 'entry': e['id'], 'code': code, 'sec': round(dt, 3),
           'errs': real, 'info': [x for x in errs if x.startswith('(')],
           'alerts': [re.sub(r'<[^>]+>|\s+', ' ', n).strip()[:160] for n in notes][:2]}
    with olock:
        out.write(json.dumps(rec, ensure_ascii=False) + '\n')
        stats[(action.__name__, 'erreur' if real or code not in (200, 303) else 'ok')] += 1
        times[action.__name__].append(dt)


# Connexions d'abord : elles ne doivent pas compter dans la concurrence mesurée.
with ThreadPoolExecutor(16) as pool:
    users = {(e['user'], 'Etud1!xx') for e in site['entries']} | {(e['teacher'], 'Ens1!xxx') for e in site['entries']}
    users |= {(f"deve{site['tag']}_{i}", 'Deve1!xx') for i in range(1, 7)}
    list(pool.map(lambda u: session(*u), users))
print(f'{len(SESSIONS)} sessions ouvertes', flush=True)

for rnd in range(1, ROUNDS + 1):
    jobs = []
    for e in site['entries']:
        acts = ACTIONS + random.sample(OPTIONAL, 2)
        random.shuffle(acts)
        jobs += [(a, e) for a in acts]
    t0 = time.time()
    with ThreadPoolExecutor(THREADS) as pool:
        for a, e in jobs:
            pool.submit(run, a, e, rnd)
    print(f'tour {rnd} : {len(jobs)} requêtes en {time.time() - t0:.1f} s', flush=True)

for name in sorted(times):
    t = sorted(times[name])
    print(f'{name:22s} n={len(t):4d} médiane={t[len(t) // 2]:.2f}s p95={t[int(len(t) * .95)]:.2f}s max={t[-1]:.2f}s '
          f'erreurs={stats[(name, "erreur")]}')
