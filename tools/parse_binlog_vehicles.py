#!/usr/bin/env python3
import sys
cols = [
 'id','owner_id','vehicle_type_id','name','make','model','year','vin','registration_number','engine_number','v5_document_number','purchase_cost','purchase_date','current_mileage','last_service_date','mot_expiry_date','road_tax_expiry_date','insurance_expiry_date','security_features','vehicle_color','service_interval_months','service_interval_miles','depreciation_method','depreciation_years','depreciation_rate','created_at','updated_at'
]
collect = False
values = {}
maxidx = 0
for line in sys.stdin:
    line=line.rstrip('\n')
    if '### INSERT INTO `vehicle_management`.`vehicles`' in line:
        collect = True
        values = {}
        maxidx = 0
        continue
    if collect and '=@' not in line and '@' in line and '=' in line:
        # parse lines like "###   @1=1" or "###   @4='FXLR Softail'"
        try:
            atpos = line.find('@')
            part = line[atpos+1:]
            at, val = part.split('=',1)
            idx = int(at)
            values[idx] = val
            if idx > maxidx: maxidx = idx
            continue
        except Exception:
            pass
    if collect and line.startswith('#'):
        # end of block
        if maxidx>0:
            cols_to_use = cols[:maxidx]
            vals = []
            for i in range(1, maxidx+1):
                v = values.get(i, 'NULL')
                vals.append(v)
            sys.stdout.write('INSERT INTO vehicles (' + ', '.join(cols_to_use) + ') VALUES (' + ', '.join(vals) + ');\n')
        collect = False
        values = {}
        maxidx = 0
        continue
# end
