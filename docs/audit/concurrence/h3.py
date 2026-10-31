import json, os, glob, time
from flows import *
site = json.load(open('site-2.json'))
s = login('deve2_1', 'Deve1!xx')
r, dt, errs = timed(s, 'GET', '/mod/stage/view.php?id=1'); print('view avant', round(dt, 2), errs)
url = '/mod/stage/import_global.php?id=1'
with open('files-2/export_big.xlsx', 'rb') as f:
    r, dt, errs = page_post(s, url, {'previewimport': 1}, files={'xlsxfile': f})
print('preview', round(dt, 2), 's', r.status_code, len(r.content) // 1024, 'KB', errs, 'confirm' if 'confirmimport' in r.text else 'NO CONFIRM FORM')
sid = s.cookies.get('MoodleSession')
p = os.environ.get('MOODLE_DATAROOT', '/home/user/moodle-stress/moodledata') + f'/sessions/sess_{sid}'
print('session', os.path.getsize(p) // 1024 if os.path.exists(p) else None, 'KB')
for i in range(3):
    r, dt, errs = timed(s, 'GET', '/mod/stage/view.php?id=1'); print('view après', round(dt, 2), errs)
