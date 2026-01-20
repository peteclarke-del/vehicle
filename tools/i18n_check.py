#!/usr/bin/env python3
import os, re, json, sys
root='frontend/src'
translations='frontend/public/locales/en/translation.json'
pattern=re.compile(r"t\((?:\"|\')([^\"\')]+)(?:\"|\')\)")
used=set()
for dirpath,_,files in os.walk(root):
    for f in files:
        if f.endswith(('.js','.jsx','.ts','.tsx')):
            path=os.path.join(dirpath,f)
            try:
                txt=open(path,encoding='utf-8').read()
            except Exception:
                continue
            for m in pattern.finditer(txt):
                used.add(m.group(1))

try:
    with open(translations,'r',encoding='utf-8') as fh:
        data=json.load(fh)
except Exception as e:
    print(f"ERROR: cannot load {translations}: {e}",file=sys.stderr)
    sys.exit(2)

available=set()
def walk(d,prefix=None):
    if isinstance(d,dict):
        for k,v in d.items():
            new = k if not prefix else prefix + '.' + k
            walk(v,new)
    else:
        available.add(prefix)
walk(data)

missing=sorted(k for k in used if k not in available)
print(f"Used keys: {len(used)}")
print(f"Available keys: {len(available)}")
print('\nMissing keys:')
for k in missing:
    print(k)

# exit code non-zero if missing
sys.exit(1 if missing else 0)
