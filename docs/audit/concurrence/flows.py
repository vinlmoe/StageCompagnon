import sys, json, re, time, threading, subprocess
from web import *

def theme_id(stageid, name):
    out = subprocess.run(['psql', '-h', 'localhost', '-U', 'moodle', '-d', 'moodle', '-tAc',
        f"SELECT id FROM mdl_stage_theme WHERE stageid={stageid} AND name='{name}'"],
        capture_output=True, text=True, env={'PGPASSWORD': 'moodle'}).stdout.strip()
    return int(out)

def page_post(s, url, data, files=None):
    data = dict(data); data['sesskey'] = s.sesskey
    return timed(s, 'POST', url, data=data, files=files)

def hidden_inputs(html, formmarker):
    # Champs du formulaire contenant formmarker (case forceimport incluses, cochées ou non).
    i = html.find(formmarker)
    if i < 0:
        return None
    start = html.rfind('<form', 0, i); end = html.find('</form>', i)
    form = html[start:end]
    fields = {}
    for m in re.finditer(r'<input[^>]+>', form):
        tag = m.group(0)
        n = re.search(r'name="([^"]+)"', tag); v = re.search(r'value="([^"]*)"', tag)
        t = re.search(r'type="([^"]+)"', tag)
        if n and (not t or t.group(1) in ('hidden', 'text')):
            fields[n.group(1)] = v.group(1) if v else ''
    return fields

def historical(user, site, path, barrier=None, log=print):
    s = login(user, 'Deve1!xx') if user != 'admin' else login('admin', 'Admin1!x')
    cm = site['cmid']; url = f'/mod/stage/import_historical.php?id={cm}'
    ep = theme_id(site['stageid'], 'NAC')
    with open(path, 'rb') as f:
        r, dt, errs = page_post(s, url, {'previewimport': 1, 'epthemeid': ep}, files={'xlsxfile': f})
    ok = 'confirmimport' in r.text
    log(json.dumps({'flow': 'historical-preview', 'user': user, 'sec': round(dt, 2), 'status': r.status_code, 'confirmform': ok, 'errs': errs}))
    if barrier: barrier.wait()
    r, dt, errs = page_post(s, url, {'confirmimport': 1})
    msg = re.search(r'(\d+) (?:internship|stage)[^<]{0,80}', r.text)
    alert = re.findall(r'class="alert[^"]*"[^>]*>(.*?)</div>', r.text, re.S)
    log(json.dumps({'flow': 'historical-confirm', 'user': user, 'sec': round(dt, 2), 'status': r.status_code, 'errs': errs,
                    'alert': [re.sub(r'<[^>]+>|\s+', ' ', a).strip()[:200] for a in alert][:3]}, ensure_ascii=False))

def globalimport(user, site, path, barrier=None, log=print, force=False):
    s = login(user, 'Deve1!xx') if user != 'admin' else login('admin', 'Admin1!x')
    cm = site['cmid']; url = f'/mod/stage/import_global.php?id={cm}'
    with open(path, 'rb') as f:
        r, dt, errs = page_post(s, url, {'previewimport': 1}, files={'xlsxfile': f})
    fields = hidden_inputs(r.text, 'name="confirmimport"')
    log(json.dumps({'flow': 'global-preview', 'user': user, 'sec': round(dt, 2), 'status': r.status_code,
                    'confirmform': fields is not None, 'kb': len(r.content)//1024, 'errs': errs}))
    if barrier: barrier.wait()
    data = {'confirmimport': 1}
    if force:
        for m in re.finditer(r'name="(forceimport\[\d+\])"', r.text):
            data[m.group(1)] = 1
    t = time.time()
    r, dt, errs = page_post(s, url, data)
    alert = re.findall(r'class="alert[^"]*"[^>]*>(.*?)</div>', r.text, re.S)
    log(json.dumps({'flow': 'global-confirm', 'user': user, 'sec': round(dt, 2), 'status': r.status_code, 'errs': errs,
                    'alert': [re.sub(r'<[^>]+>|\s+', ' ', a).strip()[:200] for a in alert][:3]}, ensure_ascii=False))
    return s

def run_parallel(targets):
    th = [threading.Thread(target=f, args=a, kwargs=k) for f, a, k in targets]
    [t.start() for t in th]; [t.join() for t in th]
