"""Test de charge des thématiques à organismes proposés, par HTTP, avec des utilisateurs simulés.

Usage : python3 load.py site-<tag>.json <threads> [graine]

Chaque étudiant simulé a sa propre session Moodle. La vague enchaîne cinq phases :

0. Import Excel. Un compte DEVE télécharge le modèle (les organismes déjà proposés) et le
   réimporte tel quel : rien ne doit être ajouté. Il importe ensuite un classeur qui ajoute des
   organismes et en met d'autres à jour. Deux comptes DEVE importent au même instant le même
   classeur d'organismes nouveaux : chacun ne doit être créé qu'une fois. Un classeur de 2 000
   lignes est importé dans une autre thématique, et un fichier qui n'est pas un classeur est refusé.
   Pendant la phase 1, un compte DEVE réimporte en boucle le classeur des organismes (mises à jour
   seules) et un classeur de 40 lignes toutes en erreur (places illisibles ou SIRET invalide).

1. Vœux. Chaque étudiant ouvre « Mes stages », la page des organismes, puis enregistre ses vœux.
   Les organismes sont choisis selon une popularité très inégale (les premiers sont demandés par
   presque tout le monde). Un étudiant sur trois modifie ensuite ses vœux, un sur dix envoie deux
   fois le formulaire en même temps (double clic), un sur vingt retire ses vœux puis les refait,
   et un sur vingt tente un envoi invalide (doublon, rang vide, organisme d'une autre thématique).
   Pendant toute la vague, un quatrième compte DEVE recharge la liste des organismes, la page des
   vœux et l'export.
2. Clôture. Trois comptes DEVE lancent l'appariement au même instant, pendant que des étudiants
   renvoient encore leurs vœux : un seul appariement doit affecter chaque étudiant, et les vœux
   arrivés après sont refusés.
3. Dernière place. Pour plusieurs organismes complets, une place est ajoutée et deux comptes DEVE
   y affectent au même instant deux étudiants différents : un seul doit l'obtenir.
4. Demandes de convention. Chaque étudiant affecté ouvre sa demande préremplie et l'envoie (deux
   fois en même temps pour un sur six). Les étudiants sans affectation tentent une demande hors
   liste : refusée tant que la thématique ne l'autorise pas, puis, une fois autorisée, refusée
   sans SIRET en France, acceptée avec un SIRET ou pour un stage à l'étranger.

Temps par action et décompte des issues dans logs/load-<threads>.json. Les invariants se vérifient
ensuite avec check.php.
"""
import io, json, os, random, re, statistics, sys, threading, time
from collections import Counter, defaultdict
from concurrent.futures import ThreadPoolExecutor

sys.path.insert(0, os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'questions'))
from lib import BASE, errors, flat, mform, psql, session, timed  # noqa: E402
import openpyxl  # noqa: E402

site = json.load(open(sys.argv[1]))
THREADS = int(sys.argv[2])
RNG = random.Random(int(sys.argv[3]) if len(sys.argv) > 3 else 1)
CM, TAG, THEME, CHOICES = site['cmid'], site['tag'], site['theme'], site['choices']
OFFERS = [o['id'] for o in site['offers']]
STUDENTS = site['students']
SIRET = '73282932000074'
TIMES = defaultdict(list)
ERRORS = defaultdict(list)
OUTCOMES = Counter()
LOCK = threading.Lock()
# Popularité des organismes : loi de Zipf, les premiers concentrent les vœux.
WEIGHTS = [1 / (rank + 1) for rank in range(len(OFFERS))]

# Refus attendus : ce sont des réponses normales de l'application, pas des erreurs.
EXPECTED = {
    'vœux clos': 'Les vœux sont clos',
    'vœux en doublon': "ne peut figurer qu'une fois",
    'vœux incomplets': 'sans laisser de rang vide',
    'organisme inconnu': "n'est pas proposé pour cette thématique",
    'déjà affecté': 'déjà affecté(e) à un organisme',
    'organisme complet': "n'a plus de place libre",
    'affectation déjà utilisée': "pas d'affectation à utiliser",
    'hors liste refusé': 'passe par les organismes proposés',
    'SIRET exigé': 'SIRET de l\'organisme est obligatoire',
    'doublon de stage': 'déjà enregistré',
    'fichier illisible': 'ne peut pas être lu comme un classeur',
    'colonne du nom absente': 'Colonne « Organisme » introuvable',
}


def outcome(text):
    text = text.replace('&#039;', "'")
    for label, marker in EXPECTED.items():
        if marker in text:
            return label
    return None


def record(action, r, dt, errs, result=None):
    refused = outcome(r.text) if 'text' in r.headers.get('Content-Type', '') else None
    with LOCK:
        TIMES[action].append(dt)
        OUTCOMES[f"{action} : {result or refused or 'ok'}"] += 1
        if r.status_code >= 500 or (errs and not refused):
            ERRORS[action].append((r.status_code, errs[:2]))
    return refused


def pick_wishes(rng):
    chosen = []
    while len(chosen) < min(CHOICES, len(OFFERS)):
        offer = rng.choices(OFFERS, WEIGHTS)[0]
        if offer not in chosen:
            chosen.append(offer)
    return chosen


def post_wishes(s, wishes, action='vœux (envoi)', extra=None):
    data = [('sesskey', s.sesskey)] + [(f'wish[{rank + 1}]', str(offer)) for rank, offer in enumerate(wishes)]
    r, dt, errs = timed(s, 'POST', f'/mod/stage/placement.php?id={CM}&themeid={THEME}', data=data + (extra or []))
    return record(action, r, dt, errs)


def wishes_phase(student):
    rng = random.Random(student['id'])
    s = session(student['user'], 'Etud1!xx')
    r, dt, errs = timed(s, 'GET', f'/mod/stage/view.php?id={CM}')
    record('Mes stages (page)', r, dt, errs)
    r, dt, errs = timed(s, 'GET', f'/mod/stage/placement.php?id={CM}&themeid={THEME}')
    record('organismes et vœux (page)', r, dt, errs)
    draw = rng.random()
    if draw < 0.05:
        # Envois invalides : chacun doit être refusé sans rien enregistrer.
        good = pick_wishes(rng)
        post_wishes(s, [good[0]] * len(good), 'vœux invalides (envoi)')
        post_wishes(s, good[:-1], 'vœux invalides (envoi)')
        post_wishes(s, good[:-1] + [999999999], 'vœux invalides (envoi)')
    if draw < 0.10:
        wishes = pick_wishes(rng)
        with ThreadPoolExecutor(2) as pool:
            list(pool.map(lambda _: post_wishes(s, wishes, 'vœux (double clic)'), range(2)))
    else:
        post_wishes(s, pick_wishes(rng))
    if 0.10 <= draw < 0.15:
        post_wishes(s, [], 'vœux (retrait)', [('withdraw', '1')])
        post_wishes(s, pick_wishes(rng))
    if draw > 0.67:
        post_wishes(s, pick_wishes(rng), 'vœux (modification)')


IMPORT_URL = f'/mod/stage/placement_import.php?id={CM}&themeid='
HEADERS = ['Organisme', 'Places', 'Précisions', 'Adresse', 'SIRET', 'Tuteur', 'Courriel du tuteur']


def workbook(rows, headers=HEADERS):
    """Classeur XLSX en mémoire : une ligne d'intitulés puis les lignes données."""
    book = openpyxl.Workbook()
    sheet = book.active
    sheet.append(headers)
    for row in rows:
        sheet.append(row)
    buffer = io.BytesIO()
    book.save(buffer)
    return buffer.getvalue()


def new_rows(prefix, count):
    return [[f'{prefix} {i:04d}', 1 + i % 3, f'Import {prefix}', f'{i} rue du Classeur, 01000 Bourg', '',
             f'Dr {prefix} {i}', f'tuteur{i}@example.test'] for i in range(1, count + 1)]


def import_file(n, content, action, themeid=None, expect=None, name='organismes.xlsx'):
    """Téléverse un classeur ; renvoie (ajoutés, mis à jour, lignes en erreur)."""
    d = session(f'odeve{TAG}_{n}', 'Deve1!xx')
    files = {'xlsxfile': (name, content, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')}
    r, dt, errs = timed(d, 'POST', IMPORT_URL + str(themeid or THEME), data={'sesskey': d.sesskey}, files=files)
    m = re.search(r'(\d+) organisme\(s\) ajouté\(s\), (\d+) mis à jour', r.text)
    result = (int(m.group(1)), int(m.group(2)), len(re.findall(r'Ligne \d+ ', r.text))) if m else None
    refused = outcome(r.text)
    wrong = [] if (result or refused) else ['ni compte rendu ni refus']
    if expect is not None and result is not None and not expect(result):
        wrong.append(f'compte rendu inattendu : {result}')
    record(action, r, dt, errs + wrong,
           f'{result[0]} ajoutés, {result[1]} mis à jour, {result[2]} lignes en erreur' if result else refused)
    return result


def import_phase():
    """Phase 0 : imports avant l'ouverture des vœux. Renvoie le classeur de référence de la thématique."""
    d = session(f'odeve{TAG}_3', 'Deve1!xx')
    count = len(OFFERS)
    # Modèle téléchargé puis réimporté tel quel : le classeur écrit par Moodle doit se relire.
    r, dt, errs = timed(d, 'GET', IMPORT_URL + f'{THEME}&template=1')
    ok = 'spreadsheetml' in r.headers.get('Content-Type', '') or 'attachment' in r.headers.get('Content-Disposition', '')
    record('modèle Excel (téléchargement)', r, dt, [] if ok else ['pas de classeur'])
    import_file(3, r.content, 'import du modèle inchangé', expect=lambda x: x == (0, count, 0))
    # Ajouts et mises à jour dans le même classeur : 20 nouveaux, 10 existants dont les places changent.
    sheet = openpyxl.load_workbook(io.BytesIO(r.content)).active
    existing = [row[0] for row in sheet.iter_rows(min_row=2, values_only=True)][:10]
    rows = new_rows('Nouvel organisme', 20) + [[name, 2, 'Places revues à l\'import', '', '', '', ''] for name in existing]
    import_file(3, workbook(rows), 'import mixte (ajouts et mises à jour)', expect=lambda x: x == (20, 10, 0))
    # Deux comptes DEVE importent au même instant les mêmes 15 nouveaux organismes.
    content = workbook(new_rows('Organisme simultané', 15))
    with ThreadPoolExecutor(2) as pool:
        both = list(pool.map(lambda n: import_file(n, content, 'import simultané (deux DEVE)'), (2, 3)))
    if sorted(x[0] for x in both if x) != [0, 15]:
        with LOCK:
            ERRORS['import simultané (deux DEVE)'].append((200, [f'ajouts {both} au lieu de 15 et 0']))
    # Gros classeur, dans une thématique à part pour ne pas noyer la liste proposée aux étudiants.
    big = workbook(new_rows('Organisme en volume', 2000))
    import_file(3, big, 'import de 2 000 lignes', site['themefree'], lambda x: x == (2000, 0, 0))
    import_file(3, big, 'réimport de 2 000 lignes', site['themefree'], lambda x: x == (0, 2000, 0))
    # Refus : fichier qui n'est pas un classeur, classeur sans colonne du nom.
    import_file(3, b'ceci n est pas un classeur', 'import refusé (fichier illisible)')
    import_file(3, workbook([[1, 'x']], ['Places', 'Adresse']), 'import refusé (colonne manquante)')
    # Classeur de référence : tous les organismes de la thématique, places telles qu'en base.
    rows = psql(f'SELECT id, name, places FROM mdl_stage_placement_offer WHERE themeid = {THEME} ORDER BY id')
    offers = [row.split('|') for row in rows.splitlines() if row]
    return offers, workbook([[name, int(places), 'Réimport pendant les vœux', '', '', '', ''] for _, name, places in offers])


def deve_importer(reference, total):
    """Pendant les vœux : réimports (mises à jour seules) et classeur de 40 lignes toutes en erreur."""
    bad = [[f'Organisme refusé {i}', 'deux' if i % 2 else 1, '', '', '12345678901234' if not i % 2 else '', '', '']
           for i in range(40)]
    faulty = workbook(bad)
    while not STOP_IMPORT.is_set():
        import_file(2, reference, 'réimport pendant les vœux', expect=lambda x: x == (0, total, 0))
        import_file(2, faulty, 'import de lignes en erreur', expect=lambda x: x == (0, 0, 40))


STOP = threading.Event()
STOP_IMPORT = threading.Event()


def deve_reader():
    d = session(f'odeve{TAG}_4', 'Deve1!xx')
    pages = [('organismes (DEVE)', f'/mod/stage/placement_offers.php?id={CM}&themeid={THEME}'),
             ('vœux et appariement (DEVE)', f'/mod/stage/placement_matching.php?id={CM}&themeid={THEME}'),
             ('export des affectations (DEVE)', f'/mod/stage/placement_matching.php?id={CM}&themeid={THEME}&dataformat=csv')]
    while not STOP.is_set():
        for action, url in pages:
            r, dt, errs = timed(d, 'GET', url)
            record(action, r, dt, errs)
            if STOP.is_set():
                break


def run_matching(n):
    d = session(f'odeve{TAG}_{n}', 'Deve1!xx')
    r, dt, errs = timed(d, 'POST', f'/mod/stage/placement_matching.php?id={CM}&themeid={THEME}&action=match',
                        data={'sesskey': d.sesskey, 'notify': '1'})
    m = re.search(r'Appariement fait : (\d+) étudiant\(s\) affecté\(s\), (\d+) sans affectation', r.text)
    record('appariement (envoi)', r, dt, errs if m else errs + ['pas de compte rendu'],
           f"{m.group(1)} affectés, {m.group(2)} sans affectation" if m else 'sans compte rendu')


def assign(n, userid, offerid):
    d = session(f'odeve{TAG}_{n}', 'Deve1!xx')
    r, dt, errs = timed(d, 'POST', f'/mod/stage/placement_matching.php?id={CM}&themeid={THEME}&action=assign',
                        data={'sesskey': d.sesskey, f'offer[{userid}]': str(offerid)})
    record('affectation manuelle (envoi)', r, dt, errs)


def fill_request(fields, student, html, extra):
    f = dict(fields)

    def first_option(name):
        m = re.search(r'<select[^>]*name="' + re.escape(name) + r'"[^>]*>(.*?)</select>', html, re.S)
        return [v for v in re.findall(r'value="([^"]*)"', m.group(1)) if v][0]

    f.update({'conventionlang': 'fr', 'conventiontemplateid': str(site['tplfr']),
              'referentteacherid': str(student['teacherid']), 'studyyear': '4',
              'yearsituation': first_option('yearsituation'), 'stagetype': first_option('stagetype'),
              'declaredduration': '10', 'studentaddress': '1 rue A, Lyon', 'studentphone': '0600000000',
              'gratificationamount': '0'})
    dates = {'perioddatestart[0]': ('1', '3', '2027'), 'perioddateend[0]': ('12', '3', '2027'),
             'studentbirthdate': ('4', '5', '2002')}
    for name, (day, month, year) in dates.items():
        f.update({f'{name}[day]': day, f'{name}[month]': month, f'{name}[year]': year})
    f.update(extra)
    f.pop('cancel', None)
    return f


HOST = {'structure': 'Ferme hors liste', 'hostaddress': '3 chemin du Haut, Ambert', 'hostrepresentative': 'M. Haut',
        'hostrepresentativetitle': 'Exploitant', 'hostservice': 'Élevage', 'hostphone': '0473000000',
        'hostemail': 'ferme@example.test', 'tutorname': 'Dr Haut', 'tutorfunction': 'Vétérinaire',
        'tutorphone': '0473000001', 'tutoremail': 'haut@example.test'}


def send_request(s, url, fields, action, double=False):
    r, dt, errs = timed(s, 'POST', url, data=flat(fields))
    # Le formulaire réaffiché avec une erreur de champ n'est pas une erreur de l'application.
    refused = outcome(r.text)
    saved = 'Le stage est enregistré' in r.text or 'a été enregistré' in r.text
    if double and not refused and not saved and not errs and r.status_code == 200:
        # Les deux envois d'un double clic partagent la session, donc la file des messages de
        # Moodle : la page servie en premier affiche les deux messages, l'autre aucun. Ce qui
        # compte est qu'un seul stage soit enregistré, ce que check.php vérifie.
        record(action, r, dt, [], "sans message (affiché sur l'autre réponse)")
        return False
    record(action, r, dt, [] if (refused or saved) else errs + ['ni enregistré ni refusé'],
           'enregistrée' if saved else refused)
    return saved


def assigned_request(student):
    s = session(student['user'], 'Etud1!xx')
    url = f'/mod/stage/student_register.php?id={CM}&placement={THEME}'
    r, dt, errs = timed(s, 'GET', url)
    fields = mform(r.text, 'structure')
    prefilled = fields is not None and fields.get('structure', '') in NAMES
    record('demande préremplie (page)', r, dt, errs if prefilled else errs + ['formulaire non prérempli'])
    if not prefilled:
        return
    # Un organisme importé avec son seul nom laisse des champs vides : l'étudiant les complète.
    missing = {k: v for k, v in HOST.items() if k != 'structure' and not fields.get(k)}
    fields = fill_request(fields, student, r.text, missing)
    if student['id'] % 6 == 0:
        with ThreadPoolExecutor(2) as pool:
            list(pool.map(lambda _: send_request(s, url, fields, 'demande préremplie (double clic)', True), range(2)))
    else:
        send_request(s, url, fields, 'demande préremplie (envoi)')


def offlist_request(student, allowed):
    s = session(student['user'], 'Etud1!xx')
    url = f'/mod/stage/student_register.php?id={CM}'
    r, dt, errs = timed(s, 'GET', url)
    fields = mform(r.text, 'structure')
    record('demande hors liste (page)', r, dt, errs if fields else errs + ['pas de formulaire'])
    if not fields:
        return
    base = fill_request(fields, student, r.text, dict(HOST, themeid=str(THEME), structure=f"Ferme {student['id']}"))
    if not allowed:
        send_request(s, url, dict(base, siret=SIRET), 'hors liste non autorisé (envoi)')
        return
    send_request(s, url, dict(base, siret=''), 'hors liste sans SIRET (envoi)')
    if student['id'] % 4 == 0:
        send_request(s, url, dict(base, siret='', abroad='1', country='Belgique'), "hors liste à l'étranger (envoi)")
    else:
        send_request(s, url, dict(base, siret=SIRET), 'hors liste avec SIRET (envoi)')


def assignments():
    rows = psql(f'SELECT userid, offerid FROM mdl_stage_placement_assign WHERE themeid = {THEME}')
    return {int(u): int(o) for u, o in (row.split('|') for row in rows.splitlines() if row)}


# Connexions avant la vague : elles ne sont pas mesurées.
t0 = time.time()
with ThreadPoolExecutor(16) as pool:
    list(pool.map(lambda st: session(st['user'], 'Etud1!xx'), STUDENTS))
for n in (1, 2, 3, 4):
    session(f'odeve{TAG}_{n}', 'Deve1!xx')
login_s = time.time() - t0
phases = {}

# Phase 0 : imports Excel. Les organismes ajoutés entrent dans la liste proposée aux étudiants.
t0 = time.time()
ALL_OFFERS, REFERENCE = import_phase()
phases['0 import Excel'] = round(time.time() - t0, 1)
PLACES = {int(offerid): int(places) for offerid, _, places in ALL_OFFERS}
NAMES = {name for _, name, _ in ALL_OFFERS}
OFFERS[:] = list(PLACES)
WEIGHTS[:] = [1 / (rank + 1) for rank in range(len(OFFERS))]

# Phase 1 : vœux, pendant qu'un compte DEVE réimporte des classeurs.
reader = threading.Thread(target=deve_reader)
importer = threading.Thread(target=deve_importer, args=(REFERENCE, len(OFFERS)))
reader.start()
importer.start()
t0 = time.time()
with ThreadPoolExecutor(THREADS) as pool:
    list(pool.map(wishes_phase, STUDENTS))
phases['1 vœux'] = round(time.time() - t0, 1)
STOP_IMPORT.set()
importer.join()
wishers = int(psql(f'SELECT count(DISTINCT userid) FROM mdl_stage_placement_wish WHERE themeid = {THEME}'))

# Phase 2 : trois appariements simultanés, pendant que des étudiants renvoient leurs vœux.
late = RNG.sample(STUDENTS, min(len(STUDENTS), 3 * THREADS))
t0 = time.time()
with ThreadPoolExecutor(THREADS + 3) as pool:
    jobs = [pool.submit(lambda st=st: post_wishes(session(st['user'], 'Etud1!xx'), pick_wishes(random.Random(-st['id'])),
                                                  'vœux pendant la clôture (envoi)')) for st in late[:len(late) // 2]]
    jobs += [pool.submit(run_matching, n) for n in (1, 2, 3)]
    jobs += [pool.submit(lambda st=st: post_wishes(session(st['user'], 'Etud1!xx'), pick_wishes(random.Random(-st['id'])),
                                                   'vœux pendant la clôture (envoi)')) for st in late[len(late) // 2:]]
    for job in jobs:
        job.result()
phases['2 clôture'] = round(time.time() - t0, 1)
assigned = assignments()

# Phase 3 : dernière place d'un organisme disputée par deux comptes DEVE.
unassigned = [st for st in STUDENTS if st['id'] not in assigned]
full = [o for o in OFFERS if list(assigned.values()).count(o) >= PLACES[o]]
races = min(10, len(full), len(unassigned) // 2)
t0 = time.time()
for i in range(races):
    offer, a, b = full[i], unassigned[2 * i], unassigned[2 * i + 1]
    psql(f'UPDATE mdl_stage_placement_offer SET places = places + 1 WHERE id = {offer}')
    with ThreadPoolExecutor(2) as pool:
        first = pool.submit(assign, 2, a['id'], offer)
        second = pool.submit(assign, 3, b['id'], offer)
        first.result(); second.result()
phases['3 dernière place'] = round(time.time() - t0, 1)
assigned = assignments()
unassigned = [st for st in STUDENTS if st['id'] not in assigned]

# Phase 4 : demandes de convention. Hors liste interdit, puis autorisé (réglage de la thématique).
t0 = time.time()
with ThreadPoolExecutor(THREADS) as pool:
    jobs = [pool.submit(assigned_request, st) for st in STUDENTS if st['id'] in assigned]
    jobs += [pool.submit(offlist_request, st, False) for st in unassigned]
    for job in jobs:
        job.result()
psql(f'UPDATE mdl_stage_theme SET placementofflist = 1 WHERE id = {THEME}')
with ThreadPoolExecutor(THREADS) as pool:
    list(pool.map(lambda st: offlist_request(st, True), unassigned))
phases['4 demandes'] = round(time.time() - t0, 1)
STOP.set()
reader.join()

summary = {'threads': THREADS, 'étudiants': len(STUDENTS), 'organismes': len(OFFERS),
           'places': sum(PLACES.values()), 'connexions_s': round(login_s, 1),
           'phases_s': phases, 'requêtes': sum(len(t) for t in TIMES.values()),
           'erreurs': sum(len(e) for e in ERRORS.values()), 'actions': {}}
summary['requêtes_par_s'] = round(summary['requêtes'] / sum(phases.values()), 1)
for action in sorted(TIMES):
    times = sorted(TIMES[action])
    summary['actions'][action] = {
        'n': len(times), 'p50': round(statistics.median(times), 3),
        'p95': round(times[int(0.95 * (len(times) - 1))], 3), 'max': round(times[-1], 3),
        'erreurs': len(ERRORS[action]), 'exemples': ERRORS[action][:2]}
summary['issues'] = dict(sorted(OUTCOMES.items()))
summary['final'] = {
    'étudiants avec vœux avant la clôture': wishers,
    'affectations': len(assigned),
    'courses à la dernière place': races,
    'affectations manuelles': int(psql(f'SELECT count(*) FROM mdl_stage_placement_assign WHERE themeid = {THEME} AND assignedby IS NOT NULL')),
    'stages déposés': int(psql(f"SELECT count(*) FROM mdl_stage_entry WHERE stageid = {site['stageid']} AND themeid = {THEME}")),
    'stages rattachés à une affectation': int(psql(f'SELECT count(*) FROM mdl_stage_placement_assign WHERE themeid = {THEME} AND entryid IS NOT NULL')),
    'organismes importés en volume': int(psql(f"SELECT count(*) FROM mdl_stage_placement_offer WHERE themeid = {site['themefree']}")),
}
os.makedirs('logs', exist_ok=True)
json.dump(summary, open(f'logs/load-{THREADS}.json', 'w'), ensure_ascii=False, indent=1)
print(json.dumps(summary, ensure_ascii=False, indent=1))
sys.exit(1 if summary['erreurs'] else 0)
