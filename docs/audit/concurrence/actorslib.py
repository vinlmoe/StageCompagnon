"""Outils communs aux tests multi-acteurs : formulaires, sessions, psql."""
import re, subprocess, threading
from html.parser import HTMLParser
import requests
from web import BASE, timed


def psql(sql):
    return subprocess.run(['psql', '-h', 'localhost', '-U', 'moodle', '-d', 'moodle', '-tAF', '|', '-c', sql],
                          capture_output=True, text=True, env={'PGPASSWORD': 'moodle'}).stdout.strip()


class FormParser(HTMLParser):
    """Champs de chaque formulaire de la page (valeurs par défaut comprises)."""
    def __init__(self):
        super().__init__(); self.forms = []; self.cur = None; self.textarea = None

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == 'form':
            self.cur = {'action': a.get('action', ''), 'fields': {}, 'cls': a.get('class', '')}; self.forms.append(self.cur)
        elif self.cur is not None and tag == 'input' and a.get('name'):
            if a.get('type') in ('checkbox', 'radio') and 'checked' not in a:
                return
            if a.get('type') == 'submit' and a.get('name') != 'submitbutton':
                return
            self.cur['fields'][a['name']] = a.get('value', '')
        elif self.cur is not None and tag == 'textarea':
            self.textarea = a.get('name'); self.cur['fields'][self.textarea] = ''
        elif self.cur is not None and tag == 'option' and getattr(self, 'select', None):
            # Comme un navigateur : l'option sélectionnée, à défaut la première de la liste.
            if 'selected' in a or self.select not in self.cur['fields']:
                self.cur['fields'][self.select] = a.get('value', '')
        if tag == 'select':
            self.select = a.get('name')

    def handle_endtag(self, tag):
        if tag == 'form':
            self.cur = None
        if tag == 'textarea':
            self.textarea = None

    def handle_data(self, data):
        if self.textarea and self.cur is not None:
            self.cur['fields'][self.textarea] += data


def mform(html, marker):
    p = FormParser(); p.feed(html)
    for f in p.forms:
        if any(marker in k for k in f['fields']):
            return f['fields']
    return None


SESSIONS = {}
SLOCK = threading.Lock()


def session(user, pw):
    with SLOCK:
        if user in SESSIONS:
            return SESSIONS[user]
    s = requests.Session()
    r = s.get(BASE + '/login/index.php')
    tok = re.search(r'name="logintoken" value="([^"]+)"', r.text).group(1)
    r = s.post(BASE + '/login/index.php', data={'username': user, 'password': pw, 'logintoken': tok})
    s.sesskey = re.search(r'"sesskey":"([^"]+)"', s.get(BASE + '/my/').text).group(1)
    with SLOCK:
        SESSIONS[user] = s
    return s



def post(s, url, data, **kw):
    data = dict(data); data['sesskey'] = s.sesskey
    return timed(s, 'POST', url, data=data, **kw)
