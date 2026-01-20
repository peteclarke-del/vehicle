#!/usr/bin/env python3
import sys, re
if len(sys.argv) < 2:
    print('Usage: parse_binlog_table.py <table_name>')
    sys.exit(2)
table = sys.argv[1]
# expects mysqlbinlog --verbose output on stdin
collect = False
values = {}
maxidx = 0
for line in sys.stdin:
    line=line.rstrip('\n')
    if f"### INSERT INTO `vehicle_management`.`{table}`" in line or f"### UPDATE `{table}`" in line or f"### DELETE FROM `vehicle_management`.`{table}`" in line:
        collect = True
        values = {}
        maxidx = 0
        mode = 'INSERT' if 'INSERT INTO' in line else ('DELETE' if 'DELETE FROM' in line else 'UPDATE')
        continue
    if collect and '@' in line and '=' in line:
        atpos = line.find('@')
        part = line[atpos+1:]
        if '=' not in part:
            continue
        at, val = part.split('=',1)
        try:
            idx = int(at)
        except:
            continue
        values[idx] = val
        if idx > maxidx: maxidx = idx
        continue
    if collect and line.startswith('#'):
        # end of block -> emit SQL
        if maxidx>0 and mode == 'INSERT':
            cols = []
            for i in range(1, maxidx+1):
                cols.append(f"c{i}")
            # We don't know exact column names for arbitrary tables; try to emit values-only INSERT
            vals = [values.get(i,'NULL') for i in range(1,maxidx+1)]
            sys.stdout.write('INSERT INTO ' + table + ' VALUES (' + ', '.join(vals) + ');\n')
        elif maxidx>0 and mode == 'DELETE':
            # best-effort: ignore deletes for now
            pass
        collect=False
        values={}
        maxidx=0
        continue
# end
