import json
from flows import *
site = json.load(open('site-2.json'))
s = login('deve2_2', 'Deve1!xx')
url = '/mod/stage/import_global.php?id=1'
with open('files-2/export_big.xlsx', 'rb') as f:
    r, dt, errs = page_post(s, url, {'previewimport': 1}, files={'xlsxfile': f})
print('preview', round(dt, 1), 's', errs)
r, dt, errs = page_post(s, url, {'confirmimport': 1})
alert = re.findall(r'class="alert[^"]*"[^>]*>(.*?)</div>', r.text, re.S)
print('confirm', round(dt, 1), 's', r.status_code, errs, [re.sub(r'<[^>]+>|\s+', ' ', a).strip()[:120] for a in alert][:2])
