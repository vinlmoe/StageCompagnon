import sys, json, threading
from flows import *
site = json.load(open('site-3.json')); tag = site['tag']
b = threading.Barrier(2)
run_parallel([(historical, (f'deve{tag}_1', site, 'files-3/historical.xlsx', b), {}),
              (historical, (f'deve{tag}_2', site, 'files-3/historical.xlsx', b), {})])
