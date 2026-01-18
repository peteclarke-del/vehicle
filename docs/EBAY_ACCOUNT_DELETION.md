# eBay Marketplace Account Deletion Implementation

This document explains the implementation of eBay's Marketplace Account Deletion notification system as required by eBay's Developers Program.

## Overview

All active eBay Developers Program applications are **required** to either:
1. Subscribe to eBay marketplace account deletion/closure notifications, OR
2. Apply for exemption if they don't store any eBay user data

**Failure to comply will result in termination of API access.**

Reference: https://developer.ebay.com/develop/guides-v2/marketplace-user-account-deletion/marketplace-user-account-deletion

## Implementation Status

### ✅ Completed
- Webhook endpoint created at `/api/ebay/webhook/account-deletion`
- Challenge code verification (GET request handling)
- Account deletion notification handling (POST request handling)
- Deletion service skeleton with audit logging
- Environment configuration for verification token

### ⚠️ Pending
- **Signature verification** - Currently not implemented; notifications are accepted without verification
- **Actual data deletion logic** - Depends on whether your application stores eBay user data
- **eBay Developer Portal configuration** - Endpoint must be registered with eBay
- **Production testing** - Send test notification from eBay portal

## Files Created/Modified

### New Files
1. `backend/src/Controller/EbayWebhookController.php` - Webhook endpoint controller
2. `backend/src/Service/EbayAccountDeletionService.php` - Data deletion service
3. `docs/EBAY_ACCOUNT_DELETION.md` - This documentation

### Modified Files
1. `backend/.env` - Added `EBAY_VERIFICATION_TOKEN` configuration

## Setup Instructions

### 1. Generate Verification Token

Create a 32-80 character alphanumeric string with optional underscores and hyphens:

```bash
# Example using openssl
openssl rand -hex 40 | cut -c1-64
```

Add to your `.env` file:
```env
EBAY_VERIFICATION_TOKEN=your_generated_token_here
```

### 2. Configure Public Endpoint URL

Your webhook endpoint must be:
- Accessible via HTTPS (not HTTP)
- Not localhost or internal IP address
- Publicly routable

Example: `https://yourdomain.com/api/ebay/webhook/account-deletion`

### 3. Register Endpoint with eBay

1. Sign into your eBay Developer account: https://developer.ebay.com/signin
2. Go to Application Keys: https://developer.ebay.com/my/keys
3. Click **Notifications** next to your App ID
4. Select **Marketplace Account Deletion** radio button
5. Enter an alert email address and click **Save**
6. Enter your **Notification Endpoint URL** (the full HTTPS URL)
7. Enter your **Verification Token** (from step 1)
8. Click **Save**

eBay will immediately send a challenge code to verify your endpoint.

### 4. Test the Endpoint

#### Test Challenge Verification (GET)
```bash
# Simulate eBay's challenge code verification
curl "https://yourdomain.com/api/ebay/webhook/account-deletion?challenge_code=testchallenge123"
```

Expected response:
```json
{
  "challengeResponse": "a1b2c3d4e5f6..."
}
```

#### Send Test Notification from eBay
After successful registration, use the **Send Test Notification** button in the eBay Developer Portal.

### 5. Implement Data Deletion Logic

Update `EbayAccountDeletionService::deleteUserData()` to:

1. **Identify** what eBay user data your application stores
2. **Delete** all personal data associated with:
   - `username` - eBay username (may be null for US users after Sept 2025)
   - `userId` - Immutable eBay user ID
   - `eiasToken` - eBay EIAS token
3. **Ensure** deletion is irreversible (even with highest privilege)
4. **Log** deletions for audit purposes

Example implementation:
```php
public function deleteUserData(?string $username, string $userId, string $eiasToken): bool
{
    // Find and delete data associated with eBay user
    if ($username) {
        $this->deleteByEbayUsername($username);
    }
    $this->deleteByEbayUserId($userId);
    $this->deleteByEiasToken($eiasToken);
    
    // Create audit log
    $this->createAuditLog($username, $userId, $eiasToken);
    
    return true;
}
```

### 6. (Optional) Implement Signature Verification

For production, you should verify that notifications actually come from eBay:

#### Option A: Use eBay SDK (Recommended)
```bash
composer require ebay/event-notification-php-sdk
```

#### Option B: Manual Verification
1. Decode `x-ebay-signature` header (Base64)
2. Call eBay's `getPublicKey` API with decoded value
3. Verify signature against notification payload
4. Cache public key (recommended: 1 hour)

Reference: https://developer.ebay.com/api-docs/commerce/notification/overview.html#use

## Opting Out (If Not Storing eBay Data)

If your application does NOT store any eBay user personal data:

1. Go to the Marketplace Account Deletion page in Developer Portal
2. Toggle **"Not persisting eBay data"** to **On**
3. Select exemption reason and submit

**Important:** Providing false information may result in penalties or account termination.

## Current Data Storage Assessment

Based on the current implementation:

- ✅ **EbayAdapter.php** - Only fetches product information via Browse API
- ✅ **No eBay user data stored** - The adapter fetches item details but doesn't store eBay user accounts
- ⚠️ **Review your database** - Ensure no eBay user identifiers are stored anywhere

**Recommendation:** If you truly don't store eBay user data, apply for exemption. Otherwise, implement the deletion logic above.

## Notification Payload Format

```json
{
  "metadata": {
    "topic": "MARKETPLACE_ACCOUNT_DELETION",
    "schemaVersion": "1.0",
    "deprecated": false
  },
  "notification": {
    "notificationId": "********-****-****-****-****-****-****-******bd9a6d",
    "eventDate": "2025-09-19T20:43:59.462Z",
    "publishDate": "2025-09-19T20:43:59.679Z",
    "publishAttemptCount": 1,
    "data": {
      "username": "******ser",
      "userId": "********SJC",
      "eiasToken": "**************************************************+seQ=="
    }
  }
}
```

## Acceptable Response Codes

Your endpoint must immediately acknowledge notifications with:
- `200 OK`
- `201 Created`
- `202 Accepted`
- `204 No Content`

## Failure Handling

If your endpoint doesn't acknowledge notifications:
- eBay will retry sending the notification
- After 24 hours of failed attempts, you'll receive an alert email
- You have 30 days to fix the issue
- After 30 days, you'll be marked non-compliant

## Testing Checklist

- [ ] Verification token generated and added to `.env`
- [ ] Endpoint registered with eBay Developer Portal
- [ ] Challenge verification successful (GET request)
- [ ] Test notification received and acknowledged (POST request)
- [ ] Data deletion logic implemented (if storing eBay data)
- [ ] Signature verification implemented (recommended for production)
- [ ] Audit logging configured
- [ ] Monitoring/alerting set up for failed notifications

## Support

- eBay Developer Support: https://developer.ebay.com/my/support/tickets
- Developer Forums: https://community.ebay.com/t5/Developer-Groups/ct-p/developergroup
- API Documentation: https://developer.ebay.com/develop/apis

## Compliance

**Important:** Once you start receiving notifications, you MUST delete the user data. Retention is only allowed for specific legal requirements (tax, AML regulations, etc.). Deletion must be irreversible.
