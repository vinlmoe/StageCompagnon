"""Réponses valides et formulaire de demande, partagés par load.py."""
import random, re
from lib import session, timed, mform


def opts(q, lang='fr'):
    raw = q['optionsen'] if lang == 'en' and q.get('optionsen') else q['options']
    return [x.strip() for x in (raw or '').split('\n') if x.strip()]


def valid_answers(site, evaltype, lang='fr', seed=0):
    r = random.Random(seed)
    form, stored = [], {}
    for q in site['questions'][evaltype]:
        name, t = f"q_{q['id']}", q['qtype']
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
            form.append((name, 'Saint-Étienne')); stored[q['id']] = 'Saint-Étienne'
        else:
            form.append((name, f'Commentaire {seed}')); stored[q['id']] = f'Commentaire {seed}'
    return form, stored


def request_form(site, user, entryid):
    sess = session(user, 'Etud1!xx')
    u = f"/mod/stage/convention_request.php?id={site['cmid']}&entryid={entryid}"
    r, dt, errs = timed(sess, 'GET', u)
    return sess, u, r, mform(r.text, 'rq_'), errs


def first_option(html, name):
    m = re.search(r'<select[^>]*name="' + re.escape(name) + r'"[^>]*>(.*?)</select>', html, re.S)
    return [v for v in re.findall(r'value="([^"]*)"', m.group(1)) if v][0]


def request_answers(site, seed):
    r = random.Random(seed)
    out = {}
    for q in site['questions']['request']:
        f, t = f"rq_{site['themer']}_{q['id']}", q['qtype']
        if t == 'choice':
            out[f] = r.choice(opts(q))
        elif t == 'checkbox':
            out[f + '[]'] = r.sample(opts(q), 2)
        elif t == 'yesno':
            out[f] = r.choice(['yes', 'no'])
        elif t == 'rating':
            out[f] = str(r.randint(1, 5))
        elif t == 'number':
            out[f] = f'{r.randint(0, 99)},25'
        elif t == 'date':
            out.update({f'{f}[enabled]': '1', f'{f}[day]': str(r.randint(1, 28)), f'{f}[month]': '1', f'{f}[year]': '2027'})
        elif t == 'shorttext':
            out[f] = 'Lyon'
    return out


def fill_request(site, f, entry, answers, html):
    f = dict(f)
    f.update({'conventiontemplateid': str(site['tplfr']), 'referentteacherid': str(entry['teacherid']),
              'yearsituation': first_option(html, 'yearsituation'), 'stagetype': first_option(html, 'stagetype'),
              'studentaddress': '1 rue A', 'studentphone': '0600000000', 'hostaddress': '2 rue B',
              'hostrepresentative': 'Dr X', 'hostrepresentativetitle': 'Gérant', 'hostservice': 'Clinique',
              'hostphone': '0100000000', 'hostemail': 'org@example.test', 'tutorname': 'MS',
              'tutorfunction': 'Vétérinaire', 'tutorphone': '0611111111', 'tutoremail': 'ms@example.test',
              'gratificationamount': '0', 'siret': ''})
    for k in list(f):
        if k.startswith('studentbirthdate['):
            f[k] = {'studentbirthdate[day]': '4', 'studentbirthdate[month]': '5', 'studentbirthdate[year]': '2002'}.get(k, f[k])
        if k.startswith('checklistcomment'):
            f[k] = 'Rendez-vous pris'
    f.pop('cancel', None)
    f.update(answers)
    return f
