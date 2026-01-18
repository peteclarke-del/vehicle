#!/bin/bash

# Extract the token from .env
TOKEN=$(grep "^EBAY_ACCESS_TOKEN=" backend/.env | cut -d '=' -f2)
MARKETPLACE=$(grep "^EBAY_MARKETPLACE=" backend/.env | cut -d '=' -f2)
ITEM_ID="326304900639"

if [ -z "$TOKEN" ]; then
    echo "❌ EBAY_ACCESS_TOKEN not found in backend/.env"
    exit 1
fi

echo "🔍 Testing eBay Browse API"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Item ID: ${ITEM_ID}"
echo "Marketplace: ${MARKETPLACE}"
echo "Token: ${TOKEN:0:20}..."
echo ""

curl -s -w "\n📡 HTTP Status: %{http_code}\n" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "X-EBAY-C-MARKETPLACE-ID: ${MARKETPLACE}" \
  -H "Content-Type: application/json" \
  "https://api.ebay.com/buy/browse/v1/item/v1|${ITEM_ID}|0" | tee ebay_response.json | python3 -c "
import sys, json
try:
    data = json.load(sys.stdin)
    if 'errors' in data:
        print('\n❌ eBay API Error:')
        for err in data['errors']:
            print(f\"  • {err.get('message', 'Unknown error')}\")
            if 'longMessage' in err:
                print(f\"    {err['longMessage']}\")
    else:
        print('\n✅ Success! Product Data:')
        print('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━')
        print(f\"Title: {data.get('title', 'N/A')}\")
        price = data.get('price', {})
        print(f\"Price: {price.get('value', 'N/A')} {price.get('currency', '')}\")
        print(f\"Condition: {data.get('condition', 'N/A')}\")
        print(f\"Brand: {data.get('brand', 'N/A')}\")
        print(f\"MPN: {data.get('mpn', 'N/A')}\")
        print(f\"Category: {data.get('categoryPath', 'N/A')}\")
        print('\n📦 Full response saved to ebay_response.json')
except:
    pass
"

echo ""
