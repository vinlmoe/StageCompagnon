"""Compare deux classeurs de l'export général, cellule par cellule : noms et ordre des feuilles,
dimensions, valeurs (avec leur type : texte, nombre, date), format de nombre et gras.

Usage : python3 export_compare.py avant.xlsx après.xlsx
Code de sortie 0 si les valeurs sont identiques ; les écarts de format sont listés à part.
"""
import sys
from collections import Counter
from itertools import zip_longest
import openpyxl


def norm(value):
    # Cellule vide ou texte vide : même chose pour le lecteur du tableur. Un classeur xlsx n'a
    # qu'un type numérique : openpyxl lit « 1 » en entier et « 1.0 » en flottant, même valeur.
    if value == '':
        return None
    if isinstance(value, int) and not isinstance(value, bool):
        return float(value)
    return value


a, b = (openpyxl.load_workbook(p, read_only=True) for p in sys.argv[1:3])
diffs, formats, cells = [], Counter(), 0
if a.sheetnames != b.sheetnames:
    diffs.append(f'feuilles : {a.sheetnames} != {b.sheetnames}')
for name in a.sheetnames:
    if name not in b.sheetnames:
        continue
    rows = zip_longest(a[name].iter_rows(), b[name].iter_rows(), fillvalue=())
    for rownum, (ra, rb) in enumerate(rows, 1):
        for col, (ca, cb) in enumerate(zip_longest(ra, rb, fillvalue=None), 1):
            cells += 1
            va, vb = norm(getattr(ca, 'value', None)), norm(getattr(cb, 'value', None))
            if va != vb or type(va) is not type(vb):
                diffs.append(f'{name} L{rownum}C{col} : {va!r} ({type(va).__name__}) != {vb!r} ({type(vb).__name__})')
            if va is not None and vb is not None and (ca.number_format != cb.number_format or ca.font.b != cb.font.b):
                formats[(name, col, ca.number_format, bool(ca.font.b), cb.number_format, bool(cb.font.b))] += 1
print(f'{cells} cellules comparées, feuilles {b.sheetnames}')
for (name, col, fa, ba, fb, bb), n in sorted(formats.items()):
    print(f'format {name} colonne {col} : {fa!r}{" gras" if ba else ""} -> {fb!r}{" gras" if bb else ""} ({n} cellules)')
for d in diffs[:50]:
    print(d)
print(f'{len(diffs)} écart(s) de valeur')
sys.exit(1 if diffs else 0)
