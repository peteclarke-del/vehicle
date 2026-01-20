#!/usr/bin/env python3
import json, os, sys
EN='frontend/public/locales/en/translation.json'
BAK=EN+'.pre-insurance-group.bak'

with open(EN,'r',encoding='utf-8') as f:
    data=json.load(f)

if not os.path.exists(BAK):
    with open(BAK,'w',encoding='utf-8') as f:
        json.dump(data,f,ensure_ascii=False,indent=2)

# ensure nav.insurance
nav = data.get('nav')
if nav is None:
    data['nav']={'insurance':'Insurance'}
else:
    nav.setdefault('insurance','Insurance')

# prepare insurance grouping
insurance = data.get('insurance',{})
insurance.setdefault('title','Insurance')

# common fields
common_defaults = {
    'provider': data.get('policies',{}).get('provider','Provider'),
    'policyNumber': data.get('policies',{}).get('policyNumber','Policy Number'),
    'startDate': data.get('policies',{}).get('startDate','Start Date'),
    'expiryDate': data.get('policies',{}).get('expiryDate','Expiry Date'),
    'annualCost': data.get('policies',{}).get('annualCost','Annual Cost'),
    'coverageType': data.get('policies',{}).get('coverageType','Coverage Type'),
    'vehicles': data.get('policies',{}).get('vehicles','Vehicles'),
    'notes': data.get('policies',{}).get('notes','Notes')
}
insurance.setdefault('commonFields',{})
insurance['commonFields'].update(common_defaults)

# policies subsection: copy existing top-level policies entries where present
pol_top = data.get('policies',{})
pol_sub = insurance.get('policies',{})
# copy a selected set
for k in ['title','addPolicy','editPolicy','provider','policyNumber','ncdYears','ncdPercentage','expiryDate','startDate','annualCost','vehicles','selectVehicles','deleteUndo','deleteConfirm']:
    if k in pol_top:
        pol_sub[k]=pol_top[k]

insurance['policies']=pol_sub

# write back
with open(EN,'w',encoding='utf-8') as f:
    json.dump(data,f,ensure_ascii=False,indent=2)

print('Updated',EN,'(backup at',BAK,')')
