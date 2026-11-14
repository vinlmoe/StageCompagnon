"""Scénarios fonctionnels des types de question et de leurs exports, par HTTP, dans un Moodle réel.

Usage : python3 scenarios.py site-<tag>.json
Chaque scénario affiche « ok » ou « ECHEC » avec le détail ; le code de sortie est le nombre
d'échecs. Les stages utilisés sont pris dans le jeu de données de setup.php (les premiers).
"""
import csv, io, json, random, re, sys, zipfile
import openpyxl
from lib import *

site = json.load(open(sys.argv[1]))
CM, TAG = site['cmid'], site['tag']
Q = site['questions']
E = site['entries']
FAILS = []
random.seed(7)


def check(label, cond, detail=''):
    print(('ok    ' if cond else 'ECHEC ') + label + ('' if cond else f' : {detail}'))
    if not cond:
        FAILS.append(label)


def opts(q, lang='fr'):
    raw = q['optionsen'] if lang == 'en' and q.get('optionsen') else q['options']
    return [x.strip() for x in (raw or '').split('\n') if x.strip()]


def valid_answers(evaltype, lang='fr', seed=0):
    """Réponses valides, une par question : valeurs de formulaire et forme enregistrée attendue."""
    r = random.Random(seed)
    form, stored = [], {}
    for q in Q[evaltype]:
        name = f"q_{q['id']}"
        t = q['qtype']
        if t == 'choice':
            v = r.choice(opts(q, lang)); form.append((name, v)); stored[q['id']] = v
        elif t == 'checkbox':
            vs = r.sample(opts(q, lang), 2); form += [(name + '[]', v) for v in vs]; stored[q['id']] = '\n'.join(vs)
        elif t == 'yesno':
            v = r.choice(['yes', 'no']); form.append((name, v)); stored[q['id']] = v
        elif t == 'rating':
            v = str(r.randint(1, 5)); form.append((name, v)); stored[q['id']] = v
        elif t == 'number':
            n = r.randint(0, 400); form.append((name, f'{n},5')); stored[q['id']] = f'{n}.5'
        elif t == 'date':
            v = f'2026-09-0{r.randint(1, 9)}'; form.append((name, v)); stored[q['id']] = v
        elif t == 'shorttext':
            v = 'Saint-Étienne'; form.append((name, v)); stored[q['id']] = v
        else:
            v = 'Ligne 1\nLigne 2 <script>alert(1)</script> 1 < 2'; form.append((name, v)); stored[q['id']] = 'Ligne 1\nLigne 2 alert(1) 1 < 2'
    return form, stored


def stored_answers(entryid, evaltype):
    rows = psql(f"""SELECT a.questionid, a.answertext FROM mdl_stage_answer a JOIN mdl_stage_question q
        ON q.id = a.questionid WHERE a.entryid = {entryid} AND q.evaltype = '{evaltype}'""")
    out = {}
    # Les réponses peuvent contenir des retours à la ligne : relues une par une.
    for qid in [int(x.split('|')[0]) for x in rows.splitlines() if re.match(r'^\d+\|', x)]:
        out[qid] = psql(f"SELECT answertext FROM mdl_stage_answer WHERE entryid = {entryid} AND questionid = {qid}")
    return out


def status(entryid):
    return int(psql(f'SELECT status FROM mdl_stage_entry WHERE id = {entryid}'))


# ---------------------------------------------------------------------------------------------
# 1. Auto-évaluation de l'étudiant : réponses invalides refusées sans écriture, puis valides.
def bad_values():
    """Une réponse invalide par type, et des falsifications de formulaire."""
    by = {q['qtype']: q for q in Q['student']}
    return [
        ('choix inconnu', by['choice'], [(f"q_{by['choice']['id']}", 'Toujours')]),
        ('case inconnue', by['checkbox'], [(f"q_{by['checkbox']['id']}[]", 'Chirurgie'), (f"q_{by['checkbox']['id']}[]", 'Dentisterie')]),
        ('cases requises vides', by['checkbox'], []),
        ('oui/non inconnu', by['yesno'], [(f"q_{by['yesno']['id']}", 'peut-être')]),
        ('oui/non en tableau', by['yesno'], [(f"q_{by['yesno']['id']}[]", 'yes')]),
        ('note 6', by['rating'], [(f"q_{by['rating']['id']}", '6')]),
        ('nombre en lettres', by['number'], [(f"q_{by['number']['id']}", 'douze')]),
        ('date inexistante', by['date'], [(f"q_{by['date']['id']}", '2026-02-30')]),
        ('date au format français', by['date'], [(f"q_{by['date']['id']}", '01/09/2026')]),
        ('texte court de 300 car.', by['shorttext'], [(f"q_{by['shorttext']['id']}", 'x' * 300)]),
    ]


e = E[0]
s = session(e['user'], 'Etud1!xx')
url = f"/mod/stage/entry.php?id={CM}&entryid={e['id']}"
r, dt, errs = timed(s, 'GET', url)
check('formulaire étudiant affiché sans erreur', not errs and 'type="checkbox"' in r.text and 'type="date"' in r.text, errs)
check('options échappées dans le formulaire', '&lt;b&gt;Urgences&lt;/b&gt;' in r.text and '<b>Urgences</b>' not in r.text)
good, expected = valid_answers('student', seed=1)
for label, q, values in bad_values():
    data = [x for x in good if not x[0].startswith(f"q_{q['id']}")] + values + [('sesskey', s.sesskey)]
    r, dt, errs = timed(s, 'POST', url, data=data)
    check(f'étudiant, {label} : refusé', status(e['id']) == 0 and not stored_answers(e['id'], 'student'),
          f'statut {status(e["id"])}, {len(stored_answers(e["id"], "student"))} réponses, {errs}')
r, dt, errs = timed(s, 'POST', url, data=good + [('sesskey', s.sesskey)])
got = stored_answers(e['id'], 'student')
check('étudiant, réponses valides enregistrées sous leur forme normalisée', got == expected and status(e['id']) == 1,
      f'{got} != {expected} (statut {status(e["id"])})')

# Lecture seule par la DEVE : cases en liste, oui/non et date en clair, texte échappé.
d = session(f'deve{TAG}_1', 'Deve1!xx')
r, dt, errs = timed(d, 'GET', f"/mod/stage/entrydetail.php?id={CM}&entryid={e['id']}")
check('détail DEVE sans erreur', not errs, errs)
check('détail : texte libre échappé', '<script>alert(1)' not in r.text and '1 &lt; 2' in r.text)
yes = expected[[q['id'] for q in Q['student'] if q['qtype'] == 'yesno'][0]]
check('détail : oui/non en clair', ('Oui' if yes == 'yes' else 'Non') in r.text or ('Yes' if yes == 'yes' else 'No') in r.text)
check('détail : cases cochées en liste', re.search(r'<ul[^>]*mb-0[^>]*>\s*<li>', r.text) is not None)

# ---------------------------------------------------------------------------------------------
# 2. Enseignant référent, puis maître de stage par lien à jeton (convention FR et EN).
t = session(e['teacher'], 'Ens1!xxx')
good, expected = valid_answers('teacher', seed=2)
r, dt, errs = timed(t, 'POST', f"/mod/stage/teacher.php?id={CM}&entryid={e['id']}", data=good + [('sesskey', t.sesskey)])
check('enseignant, réponses enregistrées', stored_answers(e['id'], 'teacher') == expected and status(e['id']) == 2, errs)

for ent in E[2:4]:
    g = requests.Session()
    r = g.get(BASE + f"/mod/stage/tutor_eval.php?token={ent['token']}")
    sk = re.search(r'name="sesskey" value="([^"]+)"', r.text)
    lang = ent['lang']
    shown = opts([q for q in Q['tutor'] if q['qtype'] == 'checkbox'][0], lang)[0]
    check(f'maître de stage ({lang}) : options dans sa langue', shown.replace('"', '&quot;') in r.text, shown)
    good, expected = valid_answers('tutor', lang, seed=ent['id'])
    r2 = g.post(BASE + f"/mod/stage/tutor_eval.php?token={ent['token']}", data=good + [('sesskey', sk.group(1) if sk else '')])
    got = stored_answers(ent['id'], 'tutor')
    check(f'maître de stage ({lang}) : réponses enregistrées', got == expected, f'{got} != {expected} {errors(r2.text)}')

# ---------------------------------------------------------------------------------------------
# 3. Demande de convention sur la thématique R (questionnaire pré-stage, check-list des prérequis).
def request_form(user, entryid):
    sess = session(user, 'Etud1!xx')
    u = f"/mod/stage/convention_request.php?id={CM}&entryid={entryid}"
    r, dt, errs = timed(sess, 'GET', u)
    f = mform(r.text, 'rq_')
    return sess, u, r, f, errs


def first_option(html, name):
    m = re.search(r'<select[^>]*name="' + re.escape(name) + r'"[^>]*>(.*?)</select>', html, re.S)
    return [v for v in re.findall(r'value="([^"]*)"', m.group(1)) if v][0]


def fill_request(f, entry, answers, html):
    f = dict(f)
    f.update({'conventiontemplateid': str(site['tplfr']), 'referentteacherid': str(entry['teacherid']),
              'yearsituation': first_option(html, 'yearsituation'), 'stagetype': first_option(html, 'stagetype'),
              'studentaddress': '1 rue A', 'studentphone': '0600000000',
              'hostaddress': '2 rue B', 'hostrepresentative': 'Dr X', 'hostrepresentativetitle': 'Gérant',
              'hostservice': 'Clinique', 'hostphone': '0100000000', 'hostemail': 'org@example.test',
              'tutorname': 'MS', 'tutorfunction': 'Vétérinaire', 'tutorphone': '0611111111',
              'tutoremail': 'ms@example.test', 'gratificationamount': '0', 'siret': ''})
    for k in list(f):
        if k.startswith('studentbirthdate['):
            f[k] = {'studentbirthdate[day]': '4', 'studentbirthdate[month]': '5', 'studentbirthdate[year]': '2002'}.get(k, f[k])
        if k.startswith('checklistcomment'):
            f[k] = 'Rendez-vous pris'
    f.pop('cancel', None)
    f.update(answers)
    return f


def rq(qtype):
    q = [x for x in Q['request'] if x['qtype'] == qtype][0]
    return f"rq_{site['themer']}_{q['id']}", q


ent = E[1]
sess, u, r, f, errs = request_form(ent['user'], ent['rid'])
check('formulaire de demande affiché sans erreur', f is not None and not errs, errs)
check('demande : cases à cocher en liste multiple', re.search(r'<select[^>]*multiple[^>]*name="rq_\d+_\d+\[\]"|<select[^>]*name="rq_\d+_\d+\[\]"[^>]*multiple', r.text) is not None)
check('demande : sélecteur de date', f'{rq("date")[0]}[enabled]' in r.text)
fc, qc = rq('checkbox'); fd, _ = rq('date'); fn, _ = rq('number'); fy, _ = rq('yesno'); fch, qch = rq('choice')
fr_, _ = rq('rating'); fs, _ = rq('shorttext')
answers = {fc + '[]': [opts(qc)[1], opts(qc)[3]], f'{fd}[enabled]': '1', f'{fd}[day]': '7', f'{fd}[month]': '1',
           f'{fd}[year]': '2027', fn: '2,5', fy: 'no', fch: opts(qch)[0], fr_: '3', fs: 'Lyon'}
data = fill_request(f, ent, answers, r.text)
r, dt, errs = timed(sess, 'POST', u, data=flat(data))
got = stored_answers(ent['rid'], 'request')
want = {qc['id']: f'{opts(qc)[1]}\n{opts(qc)[3]}', rq('date')[1]['id']: '2027-01-07', rq('number')[1]['id']: '2.5',
        rq('yesno')[1]['id']: 'no', qch['id']: opts(qch)[0], rq('rating')[1]['id']: '3', rq('shorttext')[1]['id']: 'Lyon'}
check('demande : réponses enregistrées sous leur forme normalisée',
      all(got.get(k) == v for k, v in want.items()), f'{got} {errs}')
cl = psql(f"SELECT count(*) FROM mdl_stage_entry_checklist WHERE entryid = {ent['rid']}")
check('demande : check-list des prérequis enregistrée', cl == '2', cl)

# Demande invalide : nombre en lettres, case inconnue, date requise manquante.
ent2 = E[2]
sess, u, r, f, errs = request_form(ent2['user'], ent2['rid'])
html2 = r.text
bad = dict(answers); bad[fn] = 'beaucoup'; bad[fc + '[]'] = ['Dentisterie']
r, dt, errs = timed(sess, 'POST', u, data=flat(fill_request(f, ent2, bad, r.text)))
check('demande invalide : refusée, rien d\'enregistré', not stored_answers(ent2['rid'], 'request')
      and psql(f"SELECT conventionstatus FROM mdl_stage_entry WHERE id = {ent2['rid']}") == '0')
check('demande invalide : erreurs affichées sur les champs', r.text.count('invalid-feedback') >= 1 and 'beaucoup' in r.text)
# Pour les scénarios suivants, la demande de ent2 est déposée correctement.
r, dt, errs = timed(sess, 'POST', u, data=flat(fill_request(f, ent2, answers, html2)))

# Demande dont le texte court commence par « = » : jamais une formule dans les exports.
ent3 = E[3]
sess, u, r, f, errs = request_form(ent3['user'], ent3['rid'])
inj = dict(answers); inj[fs] = '=HYPERLINK("http://example.invalid","x")'
r, dt, errs = timed(sess, 'POST', u, data=flat(fill_request(f, ent3, inj, r.text)))
check('demande avec « = » en tête : enregistrée', stored_answers(ent3['rid'], 'request').get(rq('shorttext')[1]['id'], '').startswith('=HYPERLINK'))

# Correction d'une demande refusée : réponses reprises dans le formulaire.
before = psql(f"SELECT conventionstatus FROM mdl_stage_entry WHERE id = {ent['rid']}")
psql(f"UPDATE mdl_stage_entry SET conventionstatus = -1 WHERE id = {ent['rid']}")  # Refusée.
sess, u, r, f, errs = request_form(ent['user'], ent['rid'])
check('demande refusée : cases reprises', f is not None and sorted(f.get(fc + '[]', [])) == sorted(answers[fc + '[]']), f and f.get(fc + '[]'))
check('demande refusée : date reprise', f is not None and f.get(f'{fd}[day]') == '7' and f.get(f'{fd}[year]') == '2027',
      f and {k: v for k, v in f.items() if k.startswith(fd)})
psql(f"UPDATE mdl_stage_entry SET conventionstatus = {before} WHERE id = {ent['rid']}")

# ---------------------------------------------------------------------------------------------
# 4. Édition d'une liste par la DEVE : cases à cocher sans option refusées.
r, dt, errs = timed(d, 'GET', f'/mod/stage/evallist_edit.php?id={CM}')
f = mform(r.text, 'questionqtype')
check('nouvelle liste : formulaire sans erreur, huit types proposés', f is not None and not errs
      and all(f'value="{t}"' in r.text for t in ['choice', 'checkbox', 'yesno', 'rating', 'number', 'date', 'shorttext', 'text']), errs)
f = dict(f); f.pop('cancel', None)
f.update({'name': f'Liste audit {TAG}', 'evaltype': 'student', 'questionqtype[0]': 'checkbox', 'questionname[0]': f'Cases {TAG}',
          'questionoptions[0]': '', 'questionqtype[1]': 'date', 'questionname[1]': f'Date {TAG}', 'questionoptions[1]': 'résidu'})
r, dt, errs = timed(d, 'POST', f'/mod/stage/evallist_edit.php?id={CM}', data=flat(f))
check('liste : cases à cocher sans option refusées', psql(f"SELECT count(*) FROM mdl_stage_evallist WHERE name = 'Liste audit {TAG}'") == '0')
f['questionoptions[0]'] = 'A\nB'
r, dt, errs = timed(d, 'POST', f'/mod/stage/evallist_edit.php?id={CM}', data=flat(f))
rows = psql(f"SELECT q.qtype, coalesce(q.options, '<null>') FROM mdl_stage_question q WHERE q.name IN ('Cases {TAG}', 'Date {TAG}') ORDER BY q.name")
check('liste : options gardées pour les cases, retirées pour la date', rows == 'checkbox|A\nB\ndate|<null>', rows)

# ---------------------------------------------------------------------------------------------
# 5. Exports.
def xlsx(content):
    return openpyxl.load_workbook(io.BytesIO(content), read_only=True)


r, dt, errs = timed(d, 'GET', f'/mod/stage/export.php?id={CM}')
wb = xlsx(r.content)
ws = wb[wb.sheetnames[2]]
rows = list(ws.iter_rows(values_only=True))
head = rows[0]
check('export général : 11 colonnes, dont valeur numérique et type', len(head) == 11 and head[-1] in ('Type de question', 'Question type'), head)
mine = [x for x in rows[1:] if x[0] == e['id']]
bytype = {x[10]: x for x in mine if x[5] in ('Auto-évaluation', 'Student self-assessment') or True}
check('export général : réponses du premier stage présentes', len(mine) >= 16, len(mine))
labels = {x[6]: x for x in mine}
num = [x for x in mine if x[10] in ('Nombre', 'Number')]
check('export général : nombre en colonne numérique', num and isinstance(num[0][9], (int, float)), num[:1])
yn = [x for x in mine if x[10] in ('Oui / Non', 'Yes / No')]
check('export général : oui/non en clair et 1/0', yn and yn[0][7] in ('Oui', 'Non', 'Yes', 'No') and yn[0][9] in (0, 1), yn[:1])
cb = [x for x in mine if x[10] and x[10].startswith(('Cases', 'Checkboxes'))]
check('export général : cases séparées par « ; »', cb and '; ' in cb[0][7], cb[:1])
rt = [x for x in mine if x[10] and x[10].startswith(('Note', 'Rating'))]
check('export général : note en colonne de note', rt and isinstance(rt[0][8], (int, float)) and rt[0][9] is None, rt[:1])

base = f"/mod/stage/request_answers_export.php?id={CM}&themeid={site['themer']}"
for fmt in ['excel', 'csv', 'ods', 'json', 'html']:
    r, dt, errs = timed(d, 'GET', base + f'&dataformat={fmt}')
    check(f'export pré-stage ({fmt}) : téléchargé ({dt:.2f} s)', r.status_code == 200 and len(r.content) > 100
          and 'attachment' in r.headers.get('Content-Disposition', ''), (r.status_code, r.headers.get('Content-Disposition'), errs))
    if fmt == 'excel':
        rows = list(xlsx(r.content).active.iter_rows(values_only=True))
        check('export pré-stage : une ligne par demande déposée', len(rows) == 4, len(rows))
        injrow = [x for x in rows[1:] if x[0] == ent3['rid']]
        z = zipfile.ZipFile(io.BytesIO(r.content))
        sheetxml = z.read([n for n in z.namelist() if n.startswith('xl/worksheets/')][0]).decode()
        check('export pré-stage Excel : « = » écrit en texte, pas en formule', injrow and '<f>' not in sheetxml, injrow)
        line = [x for x in rows[1:] if x[0] == ent['rid']][0]
        h = list(rows[0])
        check('export pré-stage : nombre et note numériques', isinstance(line[h.index(next(c for c in h if c.startswith('Nombre')))], float)
              and isinstance(line[h.index(next(c for c in h if c.startswith('Accueil')))], (int, float)), line)
        check('export pré-stage : date mise en forme', any(isinstance(v, str) and '2027' in v for v in line), line)
    if fmt == 'csv':
        rows = list(csv.reader(io.StringIO(r.content.decode('utf-8-sig'))))
        injcsv = [x for x in rows if x and x[0] == str(ent3['rid'])]
        check('export pré-stage CSV : formule neutralisée', injcsv and any(c.startswith("'=") for c in injcsv[0]), injcsv)
        check('export pré-stage CSV : cases cochées dans une seule cellule', any(f'{opts(qc)[1]}; {opts(qc)[3]}' in c for c in rows[1] + rows[-1]), rows[1:])
    if fmt == 'ods':
        z = zipfile.ZipFile(io.BytesIO(r.content))
        check('export pré-stage ODS : contenu lisible', 'Lyon' in z.read('content.xml').decode())

# Filtres de la liste : recherche sur un étudiant.
r, dt, errs = timed(d, 'GET', base + f"&dataformat=csv&search=NOM2")
rows = list(csv.reader(io.StringIO(r.content.decode('utf-8-sig'))))
check('export pré-stage : filtre de recherche appliqué', len(rows) >= 2 and all('NOM2' in x[1] for x in rows[1:]), len(rows))
r, dt, errs = timed(d, 'GET', base + '&dataformat=inconnu')
check('export pré-stage : format inconnu refusé proprement', 'attachment' not in r.headers.get('Content-Disposition', '') and errs, errs)

# Droits : responsable de la thématique oui, autre enseignant et étudiant non.
for user, pw, allowed in [(f't{TAG}_1', 'Ens1!xxx', True), (f't{TAG}_2', 'Ens1!xxx', False), (E[5]['user'], 'Etud1!xx', False)]:
    sx = session(user, pw)
    r, dt, errs = timed(sx, 'GET', base + '&dataformat=csv')
    ok = 'attachment' in r.headers.get('Content-Disposition', '')
    check(f'export pré-stage : accès {user} {"autorisé" if allowed else "refusé"}', ok == allowed, (r.status_code, errs))
r, dt, errs = timed(d, 'GET', f"/mod/stage/theme_stages.php?id={CM}&themeid={site['themer']}")
check('bouton d\'export sur la liste des stages de la thématique', 'request_answers_export.php' in r.text and not errs, errs)
r, dt, errs = timed(d, 'GET', f"/mod/stage/theme_stages.php?id={CM}&themeid={site['themeq']}")
check('liste de la thématique Q sans erreur', not errs, errs)

# ---------------------------------------------------------------------------------------------
# 6. Corrections de l'audit.
# Changement de type d'une question déjà répondue : refusé (texte -> oui/non), permis s'il est
# compatible (choix -> cases à cocher).
listid = psql(f"SELECT studentlistid FROM mdl_stage_theme WHERE id = {site['themeq']}")
r, dt, errs = timed(d, 'GET', f'/mod/stage/evallist_edit.php?id={CM}&listid={listid}')
f = mform(r.text, 'questionqtype'); f = dict(f); f.pop('cancel', None)
idx = {f[k]: k[len('questionid['):-1] for k in f if k.startswith('questionid[') and f[k] not in ('', '0')}
qtext = [q for q in Q['student'] if q['qtype'] == 'text'][0]['id']
qchoice = [q for q in Q['student'] if q['qtype'] == 'choice'][0]['id']
f2 = dict(f); f2[f'questionqtype[{idx[str(qtext)]}]'] = 'yesno'
r, dt, errs = timed(d, 'POST', f'/mod/stage/evallist_edit.php?id={CM}&listid={listid}', data=flat(f2))
check('changement de type incompatible refusé', psql(f'SELECT qtype FROM mdl_stage_question WHERE id = {qtext}') == 'text'
      and ('illisibles' in r.text or 'unreadable' in r.text), errs)
f2 = dict(f); f2[f'questionqtype[{idx[str(qchoice)]}]'] = 'checkbox'
r, dt, errs = timed(d, 'POST', f'/mod/stage/evallist_edit.php?id={CM}&listid={listid}', data=flat(f2))
check('changement de type compatible permis (choix -> cases)', psql(f'SELECT qtype FROM mdl_stage_question WHERE id = {qchoice}') == 'checkbox', errs)
psql(f"UPDATE mdl_stage_question SET qtype = 'choice' WHERE id = {qchoice}")

# Nombre infini refusé (enseignant d'un autre stage).
e5 = E[5]
s5 = session(e5['user'], 'Etud1!xx')
good, _ = valid_answers('student', seed=5)
# La liste vient d'être réenregistrée par son formulaire, qui retire les balises des options :
# « <b>Urgences</b> » y est devenu « Urgences ». Une seule case, sans balise.
qcb = [q for q in Q['student'] if q['qtype'] == 'checkbox'][0]['id']
good = [x for x in good if x[0] != f'q_{qcb}[]'] + [(f'q_{qcb}[]', 'Chirurgie')]
qnum = [q for q in Q['student'] if q['qtype'] == 'number'][0]['id']
bad = [x for x in good if x[0] != f'q_{qnum}'] + [(f'q_{qnum}', '1e999'), ('sesskey', s5.sesskey)]
r, dt, errs = timed(s5, 'POST', f"/mod/stage/entry.php?id={CM}&entryid={e5['id']}", data=bad)
check('nombre 1e999 refusé', status(e5['id']) == 0 and not stored_answers(e5['id'], 'student')
      and 'Nombre de consultations' in ' '.join(errs), errs)
# Tableau falsifié : message d'erreur, pas d'exception de programmation.
bad = [x for x in good if x[0] != f'q_{qnum}'] + [(f'q_{qnum}[]', '3'), ('sesskey', s5.sesskey)]
r, dt, errs = timed(s5, 'POST', f"/mod/stage/entry.php?id={CM}&entryid={e5['id']}", data=bad)
check('tableau falsifié : réponse invalide signalée', status(e5['id']) == 0 and 'Nombre de consultations' in ' '.join(errs)
      and 'coding' not in ' '.join(errs).lower(), errs)

# Courriel absent pour le responsable de thématique, présent pour la DEVE.
for user, pw, want in [(f't{TAG}_1', 'Ens1!xxx', False), (f'deve{TAG}_1', 'Deve1!xx', True)]:
    r, dt, errs = timed(session(user, pw), 'GET', base + '&dataformat=csv')
    header = next(csv.reader(io.StringIO(r.content.decode('utf-8-sig'))))
    check(f'export pré-stage : courriel {"présent" if want else "absent"} pour {user}',
          any('mail' in h.lower() for h in header) == want, header)
# Format HTML : réponses échappées.
r, dt, errs = timed(d, 'GET', base + '&dataformat=html')
check('export pré-stage HTML : options échappées', '&lt;b&gt;Urgences&lt;/b&gt;' in r.text and '<b>Urgences</b>' not in r.text)
r, dt, errs = timed(d, 'GET', base + '&dataformat=csv')
rows = list(csv.reader(io.StringIO(r.content.decode('utf-8-sig'))))
check('export pré-stage : dates au format AAAA-MM-JJ', any('2027-01-07' in rows[i] for i in range(1, len(rows))), rows[1:2])

print(f'\n{len(FAILS)} échec(s)')
for x in FAILS:
    print(' -', x)
sys.exit(len(FAILS))
