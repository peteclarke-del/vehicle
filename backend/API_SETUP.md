# API Setup Guide

## eBay Browse API Setup

### 1. Create Developer Account
- Go to https://developer.ebay.com
- Sign in with your eBay account
- Accept developer agreement

### 2. Get API Credentials
1. Go to "My Account" → "Application Keys"
2. Create a **Production** keyset (for live data)
3. Copy your credentials:
   - **App ID (Client ID)**
   - **Cert ID (Client Secret)**
   - **Dev ID**

### 3. Configure Application
Update `.env` or `.env.local`:
```env
EBAY_CLIENT_ID=YourAppID-YourComp-PRD-1234567890-abcdef12
EBAY_CLIENT_SECRET=PRD-1234567890abcdef-1234-5678-9012-abcd
EBAY_MARKETPLACE=EBAY_GB
```

**Available Marketplaces:**
- `EBAY_GB` - United Kingdom
- `EBAY_US` - United States
- `EBAY_DE` - Germany
- `EBAY_FR` - France

### 4. Rate Limits
- **Free tier**: ~5,000 calls/day
- **No affiliate requirement**
- Production keys required for live data

---

## Amazon Product Advertising API (PA-API) Setup

### 1. Join Amazon Associates
- Go to https://affiliate-program.amazon.com (or .co.uk for UK)
- Apply for Amazon Associates account
- **Must be approved first**

### 2. Generate API Credentials
1. Once approved, go to https://affiliate-program.amazon.com/assoc_credentials/home
2. Scroll to "Product Advertising API"
3. Click "Add Credentials" or "Manage Your Credentials"
4. Copy:
   - **Access Key**
   - **Secret Key**
   - **Associate Tag** (tracking ID)

### 3. Configure Application
Update `.env` or `.env.local`:
```env
AMAZON_ACCESS_KEY=AKIAIOSFODNN7EXAMPLE
AMAZON_SECRET_KEY=wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
AMAZON_ASSOCIATE_TAG=yoursite-21
AMAZON_PARTNER_REGION=eu-west-1
```

**Available Regions:**
- `us-east-1` - United States (amazon.com)
- `eu-west-1` - United Kingdom (amazon.co.uk)
- `us-west-2` - United States West
- `ap-northeast-1` - Japan (amazon.co.jp)

### 4. Important Requirements
⚠️ **Must maintain qualifying sales:**
- Generate at least 3 qualifying sales within first 180 days
- Maintain ongoing sales to keep API access
- API access revoked if inactive

### 5. Rate Limits
- Starts at **1 request/second**
- Increases with revenue (up to 10 req/sec for high earners)
- 8,640 requests per day at base rate

---

## Testing

### Test eBay (once credentials configured):
```bash
curl -X POST http://localhost:8081/api/parts/scrape-url \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{"url":"https://www.ebay.co.uk/itm/326304900639"}'
```

### Test Amazon (once credentials configured):
```bash
curl -X POST http://localhost:8081/api/parts/scrape-url \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{"url":"https://www.amazon.co.uk/dp/B0FC6C72CQ"}'
```

---

## How It Works

### Flow with APIs Configured:
1. **eBay URL** detected → Try eBay Browse API
2. **Amazon URL** detected → Try Amazon PA-API
3. If API succeeds → Return product data ✅
4. If API fails/not configured → Fall back to scraping
5. If scraping blocked → Show error message

### Flow without APIs (current):
1. Try scraping directly
2. If bot detection → Show error ❌

### Fallback Strategy:
- APIs tried first (fast, reliable, no CAPTCHA)
- Scraping used for:
  - Other sites (Shopify, generic e-commerce)
  - Fallback when API unavailable
  - Sites without official APIs

---

## Benefits of Using APIs

### eBay Browse API:
✅ No bot detection/CAPTCHA  
✅ Structured JSON response  
✅ Consistent data format  
✅ 5,000+ requests/day free  
✅ No affiliate requirement  

### Amazon PA-API:
✅ No bot detection/CAPTCHA  
✅ Official Amazon data  
✅ Product details, pricing, images  
⚠️ Requires Amazon Associates approval  
⚠️ Must maintain sales activity  

### Scraping (fallback):
✅ Works for any site  
❌ Bot detection issues  
❌ CAPTCHA challenges  
❌ Unreliable for major sites  
