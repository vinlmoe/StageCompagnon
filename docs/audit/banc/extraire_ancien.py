"""Extrait d'une version antérieure de locallib.php les fonctions comparées par equiv.php et
perf.php, renommées old_*. Usage : python3 extraire_ancien.py <commit> > old.php"""
import re, subprocess, sys

src = subprocess.run(['git', 'show', sys.argv[1] + ':mod/stage/locallib.php'],
                     capture_output=True, text=True, check=True).stdout
names = ['stage_get_filtered_entries', 'stage_get_convention_entries', 'stage_get_entries_needing_tutor_request',
         'stage_get_entry_creation_days', 'stage_load_entries_by_id', 'stage_find_probable_duplicate_entries',
         'stage_delete_entries', 'stage_get_entry_stagetypes']
out = ['<?php']
for n in names:
    m = re.search(r'^function ' + n + r'\(.*?^}\n', src, re.S | re.M)
    out.append(m.group(0).replace('function ' + n + '(', 'function old_' + n + '(', 1))
print('\n'.join(out))
