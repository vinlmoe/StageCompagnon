"""Outils HTTP des scénarios « types de question » : sessions, formulaires, erreurs, psql."""
import json, os, re, subprocess, sys, threading, time
from html.parser import HTMLParser
import requests

BASE = os.environ.get('MOODLE_URL', 'http://localhost:8080')
PATTERNS = [r'Fatal error[^<]*', r'Maximum execution time[^<]*', r'class="errormessage"[^>]*>[^<]*',
            r'Debug info:[^<]*', r'Error code: [a-z_]+', r'Exception - [^<]*', r'<b>Warning</b>:[^<]*',
            r'<b>Notice</b>:[^<]*', r'<b>Deprecated</b>:[^<]*', r'Coding error detected[^<]*',
            r'class="[^"]*alert-danger[^"]*"[^>]*>(?:\s|<[^>]+>)*[^<]{3,200}']


def psql(sql):
    return subprocess.run(['psql', '-h', 'localhost', '-U', 'moodle', '-d', 'moodle', '-tAF', '|', '-c', sql],
                          capture_output=True, text=True, env={'PGPASSWORD': 'moodle', 'PATH': os.environ['PATH']}).stdout.strip()


def errors(text):
    found = []
    for pat in PATTERNS:
        m = re.search(pat, text)
        if m:
            found.append(re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', ' ', m.group(0)))[:300])
    return found


def timed(s, method, url, **kw):
    t = time.time()
    r = s.request(method, BASE + url, timeout=900, **kw)
    return r, time.time() - t, errors(r.text) if 'text' in r.headers.get('Content-Type', '') else []


SESSIONS = {}
SLOCK = threading.Lock()


def session(user, pw):
    with SLOCK:
        if user in SESSIONS:
            return SESSIONS[user]
    s = requests.Session()
    r = s.get(BASE + '/login/index.php')
    tok = re.search(r'name="logintoken" value="([^"]+)"', r.text).group(1)
    s.post(BASE + '/login/index.php', data={'username': user, 'password': pw, 'logintoken': tok})
    m = re.search(r'"sesskey":"([^"]+)"', s.get(BASE + '/my/').text)
    if not m:
        raise RuntimeError(f'connexion impossible : {user}')
    s.sesskey = m.group(1)
    with SLOCK:
        SESSIONS[user] = s
    return s


class FormParser(HTMLParser):
    """Formulaires de la page, champs et valeurs par défaut comme les enverrait un navigateur."""
    def __init__(self):
        super().__init__(); self.forms = []; self.cur = None; self.textarea = None; self.select = None
        self.multiple = False

    def add(self, name, value):
        f = self.cur['fields']
        if name.endswith('[]') or (self.select == name and self.multiple):
            f.setdefault(name, []).append(value)
        else:
            f[name] = value

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == 'form':
            self.cur = {'action': a.get('action', ''), 'fields': {}, 'id': a.get('id', '')}; self.forms.append(self.cur)
        elif self.cur is None:
            return
        elif tag == 'input' and a.get('name'):
            if a.get('type') in ('checkbox', 'radio') and 'checked' not in a:
                return
            if a.get('type') == 'submit' and a.get('name') != 'submitbutton':
                return
            self.add(a['name'], a.get('value', ''))
        elif tag == 'textarea':
            self.textarea = a.get('name'); self.cur['fields'][self.textarea] = ''
        elif tag == 'select':
            self.select = a.get('name'); self.multiple = 'multiple' in a; self.first = True
        elif tag == 'option' and self.select:
            if self.multiple:
                if 'selected' in a:
                    self.add(self.select, a.get('value', ''))
            elif 'selected' in a or self.first:
                self.cur['fields'][self.select] = a.get('value', '')
            self.first = False

    def handle_endtag(self, tag):
        if tag == 'form':
            self.cur = None
        elif tag == 'textarea':
            self.textarea = None
        elif tag == 'select':
            self.select = None

    def handle_data(self, data):
        if self.textarea and self.cur is not None:
            self.cur['fields'][self.textarea] += data


def forms(html):
    p = FormParser(); p.feed(html)
    return p.forms


def mform(html, marker):
    for f in forms(html):
        if any(marker in k for k in f['fields']):
            return f['fields']
    return None


def flat(fields):
    """Champs -> liste de paires pour requests (champs à valeurs multiples compris)."""
    out = []
    for k, v in fields.items():
        if isinstance(v, list):
            out += [(k, x) for x in v]
        else:
            out.append((k, v))
    return out
