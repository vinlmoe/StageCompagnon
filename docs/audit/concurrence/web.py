import re, sys, time, json, requests
BASE = 'http://localhost'


def login(user, pw):
    s = requests.Session()
    r = s.get(BASE + '/login/index.php')
    tok = re.search(r'name="logintoken" value="([^"]+)"', r.text).group(1)
    r = s.post(BASE + '/login/index.php', data={'username': user, 'password': pw, 'logintoken': tok})
    m = re.search(r'"sesskey":"([^"]+)"', r.text)
    if not m:
        r = s.get(BASE + '/my/')
        m = re.search(r'"sesskey":"([^"]+)"', r.text)
    s.sesskey = m.group(1)
    return s


PATTERNS = [r'Fatal error[^<]*', r'Maximum execution time[^<]*', r'class="errormessage"[^>]*>[^<]*',
            r'Debug info:[^<]*', r'Error code: [a-z_]+', r'Exception - [^<]*', r'<b>Warning</b>:[^<]*',
            r'<b>Notice</b>:[^<]*', r'<b>Deprecated</b>:[^<]*', r'Coding error detected[^<]*']


def timed(s, method, url, **kw):
    t = time.time()
    r = s.request(method, BASE + url, timeout=900, **kw)
    dt = time.time() - t
    errs = []
    for pat in PATTERNS:
        m = re.search(pat, r.text)
        if m:
            errs.append(re.sub(r'\s+', ' ', m.group(0))[:300])
    return r, dt, errs
