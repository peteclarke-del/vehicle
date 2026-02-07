# API Reference

This document provides comprehensive documentation for all REST API endpoints in the Vehicle Management System.

> **Note:** This API is consumed by both the web frontend (`frontend/`) and the mobile app (`mobile/`). The mobile app uses the same endpoints with JWT authentication.

---

## Table of Contents

1. [Overview](#overview)
2. [Authentication](#authentication)
3. [Common Patterns](#common-patterns)
4. [Endpoints](#endpoints)
   - [Authentication](#authentication-endpoints)
   - [User Preferences](#user-preferences)
   - [Vehicles](#vehicles)
   - [Fuel Records](#fuel-records)
   - [Service Records](#service-records)
   - [MOT Records](#mot-records)
   - [Parts](#parts)
   - [Part Categories](#part-categories)
   - [Consumables](#consumables)
   - [Insurance](#insurance)
   - [Road Tax](#road-tax)
   - [Attachments](#attachments)
   - [Vehicle Images](#vehicle-images)
   - [Reports](#reports)
   - [Todos](#todos)
   - [Notifications](#notifications)
   - [External APIs](#external-apis)
   - [Import/Export](#importexport)
   - [System](#system)

---

## Overview

**Base URL:** `http://localhost:8081/api`

**Mobile App URL:** For Android emulator, use `http://10.0.2.2:8081/api` (maps to localhost)

All API endpoints return JSON responses and accept JSON request bodies (unless otherwise noted).

### Response Format

**Success Response:**
```json
{
  "id": 1,
  "field": "value"
}
```

**Error Response:**
```json
{
  "error": "Error message describing what went wrong"
}
```

### HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request (validation error) |
| 401 | Unauthorized (not authenticated) |
| 403 | Forbidden (not authorized) |
| 404 | Not Found |
| 500 | Server Error |

---

## Authentication

All endpoints except `/api/login`, `/api/register`, and `/health` require authentication.

### JWT Token

Include the JWT token in the Authorization header:

```
Authorization: Bearer <token>
```

Tokens are returned in the login response and should be stored securely. Tokens expire after a configurable period (default: 1 hour).

When a token expires, the API returns a 401 response. The frontend should redirect to the login page.

---

## Common Patterns

### Pagination

Pagination is handled client-side. All list endpoints return complete datasets.

### Filtering by Vehicle

Most record endpoints support filtering by vehicle:

```
GET /api/fuel-records?vehicleId=123
```

If `vehicleId` is omitted, records for all user's vehicles are returned.

### Sorting

Sorting is handled client-side. Data is typically returned in descending date order.

---

## Endpoints

### Authentication Endpoints

#### POST /api/login

Authenticate a user and receive a JWT token.

**Request Body:**
```json
{
  "username": "user@example.com",
  "password": "password123"
}
```

**Response (200):**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1...",
  "user": {
    "id": 1,
    "email": "user@example.com",
    "firstName": "John",
    "lastName": "Doe"
  }
}
```

**Errors:**
- 401: Invalid credentials

---

#### POST /api/register

Register a new user account.

**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "securePassword123",
  "firstName": "John",
  "lastName": "Doe",
  "preferredLanguage": "en"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| email | string | Yes | Valid email address |
| password | string | Yes | Minimum 8 characters |
| firstName | string | No | User's first name |
| lastName | string | No | User's last name |
| preferredLanguage | string | No | Language code (default: "en") |

**Response (201):**
```json
{
  "message": "User created successfully",
  "user": {
    "id": 1,
    "email": "user@example.com",
    "firstName": "John",
    "lastName": "Doe"
  }
}
```

**Errors:**
- 400: Email already exists
- 400: Invalid password format
- 400: Password too short

---

#### GET /api/me

Get current authenticated user information.

**Response (200):**
```json
{
  "id": 1,
  "email": "user@example.com",
  "firstName": "John",
  "lastName": "Doe",
  "roles": ["ROLE_USER"]
}
```

---

#### PUT /api/me

Update current user profile.

**Request Body:**
```json
{
  "firstName": "John",
  "lastName": "Smith"
}
```

**Response (200):**
```json
{
  "id": 1,
  "email": "user@example.com",
  "firstName": "John",
  "lastName": "Smith"
}
```

---

#### PUT /api/me/password

Change current user's password.

**Request Body:**
```json
{
  "currentPassword": "oldPassword123",
  "newPassword": "newSecurePassword456"
}
```

**Response (200):**
```json
{
  "message": "Password updated successfully"
}
```

**Errors:**
- 400: Current password incorrect
- 400: New password too weak

---

### User Preferences

#### GET /api/user/preferences

Get all user preferences.

**Response (200):**
```json
{
  "defaultVehicleId": 5,
  "defaultRowsPerPage": 25,
  "theme": "dark",
  "preferredLanguage": "en",
  "distanceUnit": "mi",
  "currency": "GBP"
}
```

---

#### POST /api/user/preferences

Update user preferences.

**Request Body:**
```json
{
  "defaultVehicleId": 5,
  "defaultRowsPerPage": 50,
  "theme": "light",
  "distanceUnit": "km"
}
```

All fields are optional; only provided fields are updated.

**Response (200):**
```json
{
  "message": "Preferences updated",
  "preferences": { ... }
}
```

---

### Vehicles

#### GET /api/vehicles

List all vehicles for the current user.

**Response (200):**
```json
[
  {
    "id": 1,
    "registration": "AB12 CDE",
    "name": "My Car",
    "make": "Ford",
    "model": "Focus",
    "year": 2019,
    "colour": "Blue",
    "vin": "WF0XXXGCDX1234567",
    "currentMileage": 45000,
    "fuelType": "Petrol",
    "vehicleType": {
      "id": 1,
      "name": "Car"
    },
    "purchaseDate": "2020-06-15",
    "purchaseCost": 15000.00,
    "primaryImage": {
      "id": 1,
      "url": "/uploads/vehicles/ab12-cde/image1.jpg"
    }
  }
]
```

---

#### GET /api/vehicles/{id}

Get a single vehicle by ID.

**Response (200):**
```json
{
  "id": 1,
  "registration": "AB12 CDE",
  "name": "My Car",
  "make": "Ford",
  "model": "Focus",
  "variant": "Zetec",
  "year": 2019,
  "colour": "Blue",
  "vin": "WF0XXXGCDX1234567",
  "engineSize": "1.0",
  "transmission": "Manual",
  "doors": 5,
  "bodyType": "Hatchback",
  "fuelType": "Petrol",
  "currentMileage": 45000,
  "purchaseDate": "2020-06-15",
  "purchaseCost": 15000.00,
  "purchaseMileage": 25000,
  "vehicleType": {
    "id": 1,
    "name": "Car"
  },
  "depreciationMethod": "automotive_standard",
  "depreciationYears": 10,
  "depreciationRate": 15,
  "sornStatus": false,
  "roadTaxExempt": false,
  "motExempt": false,
  "notes": "Optional notes about the vehicle"
}
```

---

#### POST /api/vehicles

Create a new vehicle.

**Request Body:**
```json
{
  "registration": "AB12 CDE",
  "name": "My Car",
  "make": "Ford",
  "model": "Focus",
  "year": 2019,
  "colour": "Blue",
  "fuelType": "Petrol",
  "vehicleTypeId": 1,
  "purchaseDate": "2020-06-15",
  "purchaseCost": 15000.00,
  "currentMileage": 45000
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| registration | string | Yes | Vehicle registration number |
| name | string | No | Nickname for the vehicle |
| make | string | No | Manufacturer |
| model | string | No | Model name |
| year | integer | No | Year of manufacture |
| colour | string | No | Vehicle colour |
| fuelType | string | No | Fuel type |
| vehicleTypeId | integer | No | Vehicle type ID |
| purchaseDate | string | No | Purchase date (YYYY-MM-DD) |
| purchaseCost | number | No | Purchase price |
| currentMileage | integer | No | Current odometer reading |

**Response (201):**
```json
{
  "id": 1,
  "registration": "AB12 CDE",
  ...
}
```

---

#### PUT /api/vehicles/{id}

Update a vehicle.

**Request Body:** Same fields as POST (all optional)

**Response (200):** Updated vehicle object

---

#### DELETE /api/vehicles/{id}

Delete a vehicle and all associated records.

**Response (200):**
```json
{
  "message": "Vehicle deleted"
}
```

---

#### GET /api/vehicles/{id}/depreciation

Get vehicle depreciation information.

**Response (200):**
```json
{
  "purchaseCost": 15000.00,
  "currentValue": 11250.00,
  "totalDepreciation": 3750.00,
  "method": "automotive_standard",
  "schedule": {
    "0": 15000.00,
    "1": 12750.00,
    "2": 11475.00,
    "3": 10615.00
  }
}
```

---

#### GET /api/vehicles/{id}/costs

Get vehicle cost breakdown.

**Response (200):**
```json
{
  "purchaseCost": 15000.00,
  "fuelCost": 2500.00,
  "serviceCost": 850.00,
  "partsCost": 350.00,
  "consumablesCost": 125.00,
  "insuranceCost": 600.00,
  "roadTaxCost": 165.00,
  "motCost": 54.85,
  "totalRunningCost": 4644.85,
  "totalCostToDate": 19644.85,
  "costPerMile": 0.98
}
```

---

#### GET /api/vehicles/{id}/stats

Get vehicle statistics.

**Response (200):**
```json
{
  "totalFuelRecords": 45,
  "totalServiceRecords": 8,
  "totalMotRecords": 3,
  "averageMpg": 42.5,
  "totalMilesDriven": 20000,
  "daysOwned": 1095,
  "milesPerDay": 18.26
}
```

---

#### GET /api/vehicles/totals

Get totals across all user's vehicles.

**Response (200):**
```json
{
  "totalVehicles": 3,
  "totalPurchaseCost": 45000.00,
  "totalCurrentValue": 32500.00,
  "totalRunningCost": 12500.00
}
```

---

### Fuel Records

#### GET /api/fuel-records

List fuel records.

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| vehicleId | integer | Filter by vehicle |

**Response (200):**
```json
[
  {
    "id": 1,
    "vehicleId": 1,
    "date": "2024-03-15",
    "mileage": 45000,
    "litres": 42.5,
    "cost": 67.50,
    "pricePerLitre": 1.59,
    "station": "Shell",
    "fuelType": "E5",
    "fullTank": true,
    "mpg": 45.2,
    "notes": "Long journey fuel up",
    "receiptAttachmentId": 12
  }
]
```

---

#### GET /api/fuel-records/{id}

Get a single fuel record.

---

#### POST /api/fuel-records

Create a fuel record.

**Request Body:**
```json
{
  "vehicleId": 1,
  "date": "2024-03-15",
  "mileage": 45000,
  "litres": 42.5,
  "cost": 67.50,
  "station": "Shell",
  "fuelType": "E5",
  "fullTank": true,
  "notes": "Long journey fuel up",
  "receiptAttachmentId": 12
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| vehicleId | integer | Yes | Vehicle ID |
| date | string | Yes | Date (YYYY-MM-DD) |
| mileage | integer | No | Odometer reading |
| litres | number | No | Fuel quantity |
| cost | number | No | Total cost |
| station | string | No | Station name |
| fuelType | string | No | Fuel type |
| fullTank | boolean | No | Whether tank was filled |
| notes | string | No | Additional notes |
| receiptAttachmentId | integer | No | Linked attachment |

**Response (201):** Created fuel record

---

#### PUT /api/fuel-records/{id}

Update a fuel record.

---

#### DELETE /api/fuel-records/{id}

Delete a fuel record.

---

#### GET /api/fuel-records/fuel-types

Get list of available fuel types.

**Response (200):**
```json
[
  "Biodiesel",
  "Diesel",
  "E5",
  "E10",
  "Electric",
  "Hybrid",
  "Hydrogen",
  "LPG",
  "Premium Diesel",
  "Super Unleaded"
]
```

---

### Service Records

#### GET /api/service-records

List service records.

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| vehicleId | integer | Filter by vehicle |
| unassociated | boolean | Only records not linked to MOT |

**Response (200):**
```json
[
  {
    "id": 1,
    "vehicleId": 1,
    "serviceDate": "2024-02-20",
    "serviceProvider": "Local Garage",
    "mileage": 44000,
    "cost": 285.00,
    "workPerformed": "Full service",
    "notes": "Oil and filter changed",
    "motRecordId": null,
    "items": [
      {
        "id": 1,
        "description": "Engine oil",
        "cost": 45.00,
        "quantity": 5
      }
    ],
    "parts": [...],
    "consumables": [...],
    "receiptAttachmentId": 15
  }
]
```

---

#### POST /api/service-records

Create a service record.

**Request Body:**
```json
{
  "vehicleId": 1,
  "serviceDate": "2024-02-20",
  "serviceProvider": "Local Garage",
  "mileage": 44000,
  "cost": 285.00,
  "workPerformed": "Full service",
  "notes": "Oil and filter changed",
  "items": [
    {
      "description": "Labour",
      "cost": 120.00
    }
  ],
  "partIds": [5, 6],
  "consumableIds": [10]
}
```

---

#### PUT /api/service-records/{id}

Update a service record.

---

#### DELETE /api/service-records/{id}

Delete a service record.

---

### MOT Records

#### GET /api/mot-records

List MOT records.

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| vehicleId | integer | Filter by vehicle |

**Response (200):**
```json
[
  {
    "id": 1,
    "vehicleId": 1,
    "testDate": "2024-01-15",
    "expiryDate": "2025-01-14",
    "result": "Pass",
    "mileage": 43500,
    "testNumber": "123456789012",
    "testCentre": "MOT Centre Ltd",
    "cost": 54.85,
    "advisories": [
      "Tyre worn close to legal limit"
    ],
    "failures": [],
    "parts": [...],
    "consumables": [...],
    "serviceRecord": null
  }
]
```

---

#### POST /api/mot-records

Create an MOT record.

**Request Body:**
```json
{
  "vehicleId": 1,
  "testDate": "2024-01-15",
  "expiryDate": "2025-01-14",
  "result": "Pass",
  "mileage": 43500,
  "testNumber": "123456789012",
  "testCentre": "MOT Centre Ltd",
  "cost": 54.85,
  "advisories": ["Tyre worn close to legal limit"],
  "failures": []
}
```

---

#### POST /api/mot-records/import-dvsa

Import MOT history from DVSA for a vehicle.

**Request Body:**
```json
{
  "vehicleId": 1
}
```

**Response (200):**
```json
{
  "imported": 5,
  "skipped": 2,
  "message": "Imported 5 MOT records"
}
```

---

### Parts

#### GET /api/parts

List parts.

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| vehicleId | integer | Filter by vehicle |
| unassociated | boolean | Only parts not linked to service/MOT |

**Response (200):**
```json
[
  {
    "id": 1,
    "vehicleId": 1,
    "description": "Brake pads - front",
    "partNumber": "BP-1234",
    "manufacturer": "Brembo",
    "supplier": "Euro Car Parts",
    "purchaseDate": "2024-02-01",
    "price": 45.00,
    "quantity": 1,
    "cost": 45.00,
    "installationDate": "2024-02-15",
    "mileageAtInstallation": 44000,
    "warranty": "2 years",
    "partCategory": {
      "id": 1,
      "name": "Brakes"
    },
    "serviceRecordId": 5,
    "motRecordId": null,
    "receiptAttachmentId": 20,
    "productUrl": "https://example.com/part",
    "includedInServiceCost": true
  }
]
```

---

#### POST /api/parts

Create a part.

**Request Body:**
```json
{
  "vehicleId": 1,
  "description": "Brake pads - front",
  "partNumber": "BP-1234",
  "manufacturer": "Brembo",
  "supplier": "Euro Car Parts",
  "purchaseDate": "2024-02-01",
  "price": 45.00,
  "quantity": 1,
  "partCategoryId": 1,
  "warranty": "2 years",
  "productUrl": "https://example.com/part",
  "receiptAttachmentId": 20
}
```

---

#### POST /api/parts/scrape-url

Scrape part information from a URL.

**Request Body:**
```json
{
  "url": "https://www.eurocarparts.com/p/..."
}
```

**Response (200):**
```json
{
  "description": "Brake Pads - Front",
  "partNumber": "BP-1234",
  "manufacturer": "Brembo",
  "price": 45.00
}
```

---

### Part Categories

#### GET /api/part-categories

List part categories.

**Response (200):**
```json
[
  {
    "id": 1,
    "name": "Brakes",
    "description": "Brake components"
  }
]
```

---

#### POST /api/part-categories

Create a part category.

**Request Body:**
```json
{
  "name": "Suspension",
  "description": "Suspension components"
}
```

---

### Consumables

#### GET /api/consumables

List consumables.

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| vehicleId | integer | Filter by vehicle |
| unassociated | boolean | Only unlinked consumables |

**Response (200):**
```json
[
  {
    "id": 1,
    "vehicleId": 1,
    "description": "Engine Oil 5W-30",
    "consumableType": {
      "id": 1,
      "name": "Engine Oil",
      "unit": "litres"
    },
    "brand": "Castrol",
    "partNumber": "15598D",
    "supplier": "Halfords",
    "cost": 35.00,
    "quantity": 5,
    "lastChanged": "2024-02-15",
    "mileageAtChange": 44000,
    "replacementIntervalMiles": 10000,
    "nextReplacementMileage": 54000,
    "receiptAttachmentId": 25
  }
]
```

---

### Insurance

#### GET /api/insurance/policies

List insurance policies.

**Response (200):**
```json
[
  {
    "id": 1,
    "provider": "Admiral",
    "policyNumber": "POL-123456",
    "startDate": "2024-01-01",
    "endDate": "2025-01-01",
    "premium": 450.00,
    "excess": 250.00,
    "coverType": "Comprehensive",
    "vehicles": [
      { "id": 1, "registration": "AB12 CDE" }
    ]
  }
]
```

---

#### POST /api/insurance/policies

Create an insurance policy.

**Request Body:**
```json
{
  "provider": "Admiral",
  "policyNumber": "POL-123456",
  "startDate": "2024-01-01",
  "endDate": "2025-01-01",
  "premium": 450.00,
  "excess": 250.00,
  "coverType": "Comprehensive",
  "vehicleIds": [1, 2]
}
```

---

#### POST /api/insurance/policies/{id}/vehicles

Add a vehicle to a policy.

**Request Body:**
```json
{
  "vehicleId": 3
}
```

---

#### DELETE /api/insurance/policies/{id}/vehicles/{vehicleId}

Remove a vehicle from a policy.

---

### Road Tax

#### GET /api/road-tax

List road tax records.

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| vehicleId | integer | Filter by vehicle |

**Response (200):**
```json
[
  {
    "id": 1,
    "vehicleId": 1,
    "startDate": "2024-01-01",
    "endDate": "2025-01-01",
    "cost": 165.00,
    "paymentMethod": "Direct Debit",
    "reference": "DVLA-123456"
  }
]
```

---

#### POST /api/road-tax

Create a road tax record.

**Request Body:**
```json
{
  "vehicleId": 1,
  "startDate": "2024-01-01",
  "endDate": "2025-01-01",
  "cost": 165.00,
  "paymentMethod": "Direct Debit"
}
```

---

### Attachments

#### GET /api/attachments

List attachments.

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| vehicleId | integer | Filter by vehicle |

**Response (200):**
```json
[
  {
    "id": 1,
    "filename": "receipt.pdf",
    "originalFilename": "fuel_receipt.pdf",
    "mimeType": "application/pdf",
    "size": 125000,
    "uploadedAt": "2024-03-15T10:30:00+00:00",
    "vehicleId": 1,
    "entityType": "FuelRecord",
    "entityId": 5,
    "url": "/uploads/vehicles/ab12-cde/attachments/receipt.pdf"
  }
]
```

---

#### POST /api/attachments

Upload an attachment.

**Request:** `multipart/form-data`

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| file | file | Yes | File to upload |
| vehicleId | integer | No | Associated vehicle |

**Response (201):**
```json
{
  "id": 1,
  "filename": "receipt.pdf",
  "url": "/uploads/attachments/..."
}
```

---

#### GET /api/attachments/{id}

Get attachment details.

---

#### DELETE /api/attachments/{id}

Delete an attachment.

---

#### GET /api/attachments/{id}/ocr

Run OCR on an attachment (for receipts).

**Response (200):**
```json
{
  "date": "2024-03-15",
  "cost": "45.50",
  "litres": "32.5",
  "station": "Shell",
  "fuelType": "E5"
}
```

---

### Vehicle Images

#### GET /api/vehicles/{id}/images

List images for a vehicle.

**Response (200):**
```json
[
  {
    "id": 1,
    "filename": "front.jpg",
    "url": "/uploads/vehicles/ab12-cde/images/front.jpg",
    "isPrimary": true,
    "caption": "Front view",
    "uploadedAt": "2024-01-15T10:00:00+00:00"
  }
]
```

---

#### POST /api/vehicles/{id}/images

Upload a vehicle image.

**Request:** `multipart/form-data`

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| image | file | Yes | Image file |
| caption | string | No | Image caption |
| isPrimary | boolean | No | Set as primary image |

---

#### PUT /api/vehicles/{vehicleId}/images/{imageId}/primary

Set an image as the primary image.

---

#### DELETE /api/vehicles/{vehicleId}/images/{imageId}

Delete a vehicle image.

---

### Reports

#### GET /api/reports

List generated reports.

**Response (200):**
```json
[
  {
    "id": 1,
    "name": "Monthly Fuel Report",
    "type": "fuel_summary",
    "vehicleId": 1,
    "createdAt": "2024-03-01T12:00:00+00:00",
    "fromDate": "2024-02-01",
    "toDate": "2024-02-29",
    "format": "xlsx"
  }
]
```

---

#### POST /api/reports

Generate a new report.

**Request Body:**
```json
{
  "vehicleId": 1,
  "type": "fuel_summary",
  "fromDate": "2024-01-01",
  "toDate": "2024-03-31",
  "format": "xlsx"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| vehicleId | integer | Yes | Vehicle ID |
| type | string | Yes | Report type |
| fromDate | string | No | Start date |
| toDate | string | No | End date |
| format | string | No | "xlsx" or "pdf" |

**Report Types:**
- `fuel_summary` - Fuel consumption summary
- `service_history` - Service history report
- `cost_analysis` - Cost breakdown analysis
- `vehicle_overview` - Complete vehicle overview

---

#### GET /api/reports/{id}/download

Download a generated report.

**Response:** Binary file with appropriate Content-Type header.

---

#### DELETE /api/reports/{id}

Delete a report.

---

### Todos

#### GET /api/todos

List todo items.

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| vehicleId | integer | Filter by vehicle |

**Response (200):**
```json
[
  {
    "id": 1,
    "vehicleId": 1,
    "title": "Change brake pads",
    "description": "Front brake pads wearing thin",
    "done": false,
    "dueDate": "2024-04-01",
    "completedBy": null,
    "parts": [{ "id": 5, "description": "Brake pads" }],
    "consumables": [],
    "createdAt": "2024-03-01T10:00:00+00:00"
  }
]
```

---

#### POST /api/todos

Create a todo item.

**Request Body:**
```json
{
  "vehicleId": 1,
  "title": "Change brake pads",
  "description": "Front brake pads wearing thin",
  "dueDate": "2024-04-01",
  "parts": [5],
  "consumables": []
}
```

---

#### PUT /api/todos/{id}

Update a todo item.

---

#### DELETE /api/todos/{id}

Delete a todo item.

---

### Notifications

#### GET /api/notifications

List notifications.

**Response (200):**
```json
[
  {
    "id": 1,
    "type": "mot_expiry",
    "message": "MOT expires in 30 days",
    "vehicleId": 1,
    "createdAt": "2024-03-01T08:00:00+00:00",
    "read": false,
    "snoozedUntil": null
  }
]
```

---

#### GET /api/notifications/stream

Server-Sent Events stream for real-time notifications.

**Response:** Event stream with notification updates.

---

### External APIs

#### GET /api/dvla/vehicle/{registration}

Look up vehicle information from DVLA.

**Response (200):**
```json
{
  "registrationNumber": "AB12 CDE",
  "make": "FORD",
  "colour": "BLUE",
  "fuelType": "PETROL",
  "taxStatus": "Taxed",
  "taxDueDate": "2024-12-01",
  "motStatus": "Valid",
  "motExpiryDate": "2024-06-15",
  "yearOfManufacture": 2019,
  "engineCapacity": 1500,
  "co2Emissions": 120
}
```

---

#### GET /api/dvsa/mot-history/{registration}

Get MOT history from DVSA.

**Response (200):**
```json
{
  "registration": "AB12 CDE",
  "make": "FORD",
  "model": "FOCUS",
  "motTests": [
    {
      "completedDate": "2024-01-15",
      "testResult": "PASSED",
      "expiryDate": "2025-01-14",
      "odometerValue": 43500,
      "odometerUnit": "mi",
      "motTestNumber": "123456789012",
      "rfrAndComments": [
        {
          "text": "Tyre worn close to legal limit",
          "type": "ADVISORY"
        }
      ]
    }
  ]
}
```

---

#### GET /api/vehicles/{id}/vin-decode

Decode the VIN for a vehicle.

**Response (200):**
```json
{
  "make": "Ford",
  "model": "Focus",
  "year": 2019,
  "country": "Germany",
  "plant": "Saarlouis",
  "engineType": "1.0L EcoBoost",
  "bodyStyle": "Hatchback"
}
```

---

#### GET /api/vehicles/{id}/specifications

Get vehicle specifications (may be scraped).

---

#### POST /api/vehicles/{id}/specifications/scrape

Scrape specifications from external sources.

---

### Import/Export

#### GET /api/vehicles/export

Export all vehicles to JSON.

**Response (200):** JSON array of complete vehicle data.

---

#### GET /api/vehicles/export-zip

Export all vehicles with attachments as ZIP.

**Response:** ZIP file download.

---

#### POST /api/vehicles/import

Import vehicles from JSON.

**Request Body:** JSON array of vehicle data.

**Response (200):**
```json
{
  "imported": 3,
  "skipped": 1,
  "errors": ["Vehicle AB12 CDE already exists"]
}
```

---

#### POST /api/vehicles/import-zip

Import vehicles from ZIP archive.

**Request:** `multipart/form-data` with ZIP file.

---

#### DELETE /api/vehicles/purge-all

Delete all vehicles for the current user.

**Response (200):**
```json
{
  "message": "All vehicles purged",
  "count": 5
}
```

---

### System

#### GET /health

Health check endpoint (no authentication required).

**Response (200):**
```json
{
  "status": "healthy"
}
```

---

#### GET /api/system-check

Detailed system status check.

**Response (200):**
```json
{
  "database": "ok",
  "redis": "ok",
  "uploads": "ok",
  "version": "1.0.0"
}
```

---

#### GET /api/vehicle-types

List available vehicle types.

**Response (200):**
```json
[
  { "id": 1, "name": "Car" },
  { "id": 2, "name": "Motorcycle" },
  { "id": 3, "name": "Van" },
  { "id": 4, "name": "Truck" }
]
```

---

#### GET /api/vehicle-makes

List vehicle manufacturers.

---

#### GET /api/vehicle-models

List vehicle models.

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| makeId | integer | Filter by manufacturer |
