#!/usr/bin/env python3
import os, re, json, sys

SRC_DIR='frontend/src'
EN_FILE='frontend/public/locales/en/translation.json'

pattern=re.compile(r"t\((?:\"|\')([^\"\')]+)(?:\"|\')\)")

# humanize a key segment to a readable label
def humanize(s):
    s = s.replace('_',' ').replace('-',' ')
    # split camelCase: insert space before capitals
    s = re.sub('([a-z0-9])([A-Z])', r"\1 \2", s)
    # remove non-alnum except spaces
    s = re.sub('[^0-9A-Za-z ]+', ' ', s)
    parts = [p for p in s.split() if p]
    if not parts:
        return s
    # Title case
    return ' '.join(p.capitalize() for p in parts)

# collect used keys
used=set()
for dirpath,_,files in os.walk(SRC_DIR):
    for f in files:
        if f.endswith(('.js','.jsx','.ts','.tsx')):
            path=os.path.join(dirpath,f)
            try:
                txt=open(path,encoding='utf-8').read()
            except Exception:
                continue
            for m in pattern.finditer(txt):
                used.add(m.group(1))

# load en file
try:
    with open(EN_FILE,'r',encoding='utf-8') as fh:
        en=json.load(fh)
except Exception as e:
    print(f"ERROR: cannot load {EN_FILE}: {e}",file=sys.stderr)
    sys.exit(2)

available=set()
def walk(d,prefix=None):
    if isinstance(d,dict):
        for k,v in d.items():
            new = k if not prefix else prefix + '.' + k
            walk(v,new)
    else:
        available.add(prefix)
walk(en)

missing=sorted(k for k in used if k not in available)
if not missing:
    print("No missing keys found.")
    sys.exit(0)

# backup
bak=EN_FILE+'.bak'
if not os.path.exists(bak):
    open(bak,'w',encoding='utf-8').write(json.dumps(en,ensure_ascii=False,indent=2))
    print(f"Backup written to {bak}")

# insert missing keys into en dict
for key in missing:
    parts=key.split('.')
    node=en
    for i,p in enumerate(parts):
        if i==len(parts)-1:
            # set leaf value humanized
            if p not in node or isinstance(node[p],dict):
                node[p]=humanize(p)
        else:
            if p not in node or not isinstance(node[p],dict):
                node[p]={}
            node=node[p]

# write file
open(EN_FILE,'w',encoding='utf-8').write(json.dumps(en,ensure_ascii=False,indent=2,sort_keys=True))
print(f"Added {len(missing)} keys to {EN_FILE} (backup at {bak})")
print("Sample added keys:")
for k in missing[:200]:
    print(k)

sys.exit(0)
