#!/bin/bash

ITEM_ID="326304900639"
TOKEN=$(grep "^EBAY_ACCESS_TOKEN=" backend/.env | cut -d '=' -f2)
MARKETPLACE=$(grep "^EBAY_MARKETPLACE=" backend/.env | cut -d '=' -f2)

echo "Testing eBay API - Checking supplier field"
echo "=========================================="

curl -s \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "X-EBAY-C-MARKETPLACE-ID: ${MARKETPLACE}" \
  "https://api.ebay.com/buy/browse/v1/item/v1|${ITEM_ID}|0" | python3 << 'PYTHON'
import sys, json

data = json.load(sys.stdin)

print("\nFields extracted by adapter:")
print("- title:", data.get('title', 'NOT FOUND')[:50] + "...")
print("- brand:", data.get('brand', 'NOT FOUND'))
print("- mpn:", data.get('mpn', 'NOT FOUND'))
print("- legacyItemId:", data.get('legacyItemId', 'NOT FOUND'))

seller = data.get('seller', {})
print("\nSeller object:")
print("- username:", seller.get('username', 'NOT FOUND'))
print("- feedbackScore:", seller.get('feedbackScore', 'NOT FOUND'))

print("\n✓ Supplier should be:", seller.get('username', 'NOT FOUND'))
print("✓ PartNumber should be:", data.get('mpn') or data.get('legacyItemId', 'NOT FOUND'))
PYTHON
