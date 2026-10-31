import sys, xml.etree.ElementTree as ET
t = ET.parse(sys.argv[1]).getroot()
out = []
for tab in t.iter('TABLE'):
    n = 'mdl_' + tab.get('NAME'); cols = []
    for f in tab.iter('FIELD'):
        ty = f.get('TYPE'); l = f.get('LENGTH')
        st = {'int': 'BIGINT' if l and int(l) > 9 else 'INTEGER', 'char': f'VARCHAR({l})', 'text': 'TEXT',
              'number': 'NUMERIC', 'float': 'DOUBLE PRECISION', 'binary': 'BYTEA'}[ty]
        if f.get('NAME') == 'id': st = 'BIGSERIAL PRIMARY KEY'
        else:
            if f.get('NOTNULL') == 'true': st += ' NOT NULL'
            d = f.get('DEFAULT')
            if d is not None: st += f" DEFAULT '{d}'"
        cols.append(f'"{f.get("NAME")}" {st}')
    out.append(f'CREATE TABLE {n} ({", ".join(cols)});')
    # Moodle crée un index pour chaque clé étrangère (non unique) ou unique.
    for k in tab.iter('KEY'):
        if k.get('TYPE') in ('foreign', 'foreign-unique', 'unique'):
            u = 'UNIQUE ' if k.get('TYPE') != 'foreign' else ''
            out.append(f'CREATE {u}INDEX ON {n} ({k.get("FIELDS")});')
    for i in tab.iter('INDEX'):
        u = 'UNIQUE ' if i.get('UNIQUE') == 'true' else ''
        out.append(f'CREATE {u}INDEX ON {n} ({i.get("FIELDS")});')
print('\n'.join(out))
