# Awk script to extract INSERT events for vehicles from mysqlbinlog --verbose output
BEGIN {
  cols[1]="id";cols[2]="owner_id";cols[3]="vehicle_type_id";cols[4]="name";cols[5]="make";cols[6]="model";cols[7]="year";cols[8]="vin";cols[9]="registration_number";cols[10]="engine_number";cols[11]="v5_document_number";cols[12]="purchase_cost";cols[13]="purchase_date";cols[14]="current_mileage";cols[15]="last_service_date";cols[16]="mot_expiry_date";cols[17]="road_tax_expiry_date";cols[18]="insurance_expiry_date";cols[19]="security_features";cols[20]="vehicle_color";cols[21]="service_interval_months";cols[22]="service_interval_miles";cols[23]="depreciation_method";cols[24]="depreciation_years";cols[25]="depreciation_rate";cols[26]="created_at";cols[27]="updated_at";
  in=0; n=0;
}
{
  if ($0 ~ /### INSERT INTO `vehicle_management`.`vehicles`/) { in=1; n=0; next }
  if (in && $0 ~ /### SET/) { next }
  if (in && $0 ~ /###\s+@/) {
    # line like: ###   @1=1
    gsub(/^.*@/, "@", $0)
    split($0, a, "=")
    idx = a[1]; sub(/@/, "", idx)
    val = substr($0, index($0,"=")+1)
    values[idx]=val
    if (idx+0 > n) n=idx+0
    next
  }
  if (in && $0 ~ /^#/) {
    # end of block -> produce INSERT
    printf "INSERT INTO vehicles ("
    first=1
    for(i=1;i<=n;i++){
      if (!first) printf ", "
      printf "%s", cols[i]
      first=0
    }
    printf ") VALUES ("
    first=1
    for(i=1;i<=n;i++){
      if (!first) printf ", "
      v=values[i]
      if (v=="NULL" || v=="NULL") printf "%s", v; else printf "%s", v
      first=0
      delete values[i]
    }
    printf ");\n"
    in=0; n=0
  }
}
