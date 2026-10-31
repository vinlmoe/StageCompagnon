import sys, json, os, glob
from web import *
site = json.load(open(sys.argv[1])); cm = site['cmid']
s = login('admin', 'Admin1!x')
pages = sorted(os.path.basename(p) for p in glob.glob(os.path.join(os.path.dirname(os.path.abspath(__file__)), '../../../mod/stage/*.php'))
               if os.path.basename(p) not in ('lib.php', 'locallib.php', 'mod_form.php', 'version.php'))
for p in pages:
    url = f'/mod/stage/{p}?id={cm}' if p != 'index.php' else f'/mod/stage/index.php?id={site["courseid"]}'
    r, dt, errs = timed(s, 'GET', url)
    errs = [e for e in errs if 'missingparam' not in e and 'A required parameter' not in e]
    print(f'{dt:7.2f}s {r.status_code} {len(r.content)//1024:6d}KB {p} {errs if errs else ""}', flush=True)
