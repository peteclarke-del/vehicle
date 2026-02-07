# Backend Libraries Reference

This document provides comprehensive documentation for the shared traits, services, and utilities used throughout the backend application. These modules are designed to be reusable and should be used whenever their functionality is needed.

---

## Table of Contents

1. [Controller Traits](#controller-traits)
   - [UserSecurityTrait](#usersecuritytrait)
   - [JsonValidationTrait](#jsonvalidationtrait)
   - [EntityHydrationTrait](#entityhydrationtrait)
   - [DateSerializationTrait](#dateserializationtrait)
   - [OwnershipVerificationTrait](#ownershipverificationtrait)
   - [ReceiptAttachmentTrait](#receiptattachmenttrait)
   - [AuthenticationRequiredTrait](#authenticationrequiredtrait)
2. [Services](#services)
   - [AttachmentLinkingService](#attachmentlinkingservice)
   - [EntitySerializerService](#entityserializerservice)
   - [CostCalculator](#costcalculator)
   - [DepreciationCalculator](#depreciationcalculator)
   - [ReportEngine](#reportengine)
   - [DvlaApiService](#dvlaapiservice)
   - [DvsaApiService](#dvsaapiservice)
   - [VehicleExportService](#vehicleexportservice)
   - [VehicleImportService](#vehicleimportservice)
   - [ReceiptOcrService](#receiptocrservice)
   - [UrlScraperService](#urlscraperservice)
   - [VinDecoderService](#vindecoderservice)
   - [RepairCostCalculator](#repaircostcalculator)
3. [Service Traits](#service-traits)
   - [UnitConversionTrait](#unitconversiontrait)
   - [EntityHydratorTrait](#entityhydratortrait)
4. [Entities](#entities)
5. [Best Practices](#best-practices)

---

## Controller Traits

All controller traits are located in `backend/src/Controller/Trait/`.

### UserSecurityTrait

**File:** `UserSecurityTrait.php`

Provides common user authentication and authorisation methods for controllers. Use this trait in any controller that needs to verify user authentication or check admin privileges.

#### Methods

**`getUserEntity(): ?User`**

Get the authenticated user entity. Returns null if no user is authenticated.

**Returns:** `User|null`

**Example:**
```php
$user = $this->getUserEntity();
if (!$user) {
    return new JsonResponse(['error' => 'Unauthorized'], 401);
}
```

**`isAdminForUser(?User $user): bool`**

Check if the given user has the ROLE_ADMIN role.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$user` | `User\|null` | Yes | User entity to check |

**Returns:** `bool`

**Example:**
```php
$user = $this->getUserEntity();
if (!$this->isAdminForUser($user)) {
    // Check ownership instead
    if ($vehicle->getOwner()->getId() !== $user->getId()) {
        return new JsonResponse(['error' => 'Forbidden'], 403);
    }
}
```

---

### JsonValidationTrait

**File:** `JsonValidationTrait.php`

Provides safe JSON decoding with validation. Use this trait in any controller that accepts JSON request bodies.

#### Methods

**`decodeJsonRequest(Request $request, bool $assoc = true): array|object|null`**

Safely decode JSON request content with validation.

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `$request` | `Request` | Yes | - | Symfony Request object |
| `$assoc` | `bool` | No | `true` | Return associative array |

**Returns:** `array|object|null` - Decoded data, or null if JSON is invalid

**`jsonError(string $message = 'Invalid JSON', int $statusCode = 400): JsonResponse`**

Create a JSON error response.

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `$message` | `string` | No | `'Invalid JSON'` | Error message |
| `$statusCode` | `int` | No | `400` | HTTP status code |

**Returns:** `JsonResponse`

**`validateJsonRequest(Request $request, bool $assoc = true): array`**

Validate and decode JSON request, returning an array with data and potential error response.

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `$request` | `Request` | Yes | - | Symfony Request object |
| `$assoc` | `bool` | No | `true` | Return associative array |

**Returns:** `array` - `['data' => mixed, 'error' => JsonResponse|null]`

**Example:**
```php
$validation = $this->validateJsonRequest($request);
if ($validation['error']) {
    return $validation['error'];
}
$data = $validation['data'];
```

---

### EntityHydrationTrait

**File:** `EntityHydrationTrait.php`

Provides common entity hydration methods for updating entities from request data. Use this trait when you need to update entity fields from JSON request data.

#### Methods

**`setDateFromData(object $entity, array $data, string $key, string $setter): void`**

Set a date field on an entity from request data.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$entity` | `object` | Yes | Entity to update |
| `$data` | `array` | Yes | Request data array |
| `$key` | `string` | Yes | Key in the data array |
| `$setter` | `string` | Yes | Setter method name |

**Example:**
```php
$this->setDateFromData($fuelRecord, $data, 'date', 'setDate');
```

**`setNullableDateFromData(object $entity, array $data, string $key, string $setter): void`**

Set a nullable date field. Handles empty/null values by setting null on the entity.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$entity` | `object` | Yes | Entity to update |
| `$data` | `array` | Yes | Request data array |
| `$key` | `string` | Yes | Key in the data array |
| `$setter` | `string` | Yes | Setter method name |

**`setStringFromData(object $entity, array $data, string $key, string $setter, ?string $default = null): void`**

Set a string field on an entity from request data.

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `$entity` | `object` | Yes | - | Entity to update |
| `$data` | `array` | Yes | - | Request data array |
| `$key` | `string` | Yes | - | Key in the data array |
| `$setter` | `string` | Yes | - | Setter method name |
| `$default` | `string\|null` | No | `null` | Default value if not set |

**`setNullableStringFromData(object $entity, array $data, string $key, string $setter): void`**

Set a nullable string field. Converts empty strings to null.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$entity` | `object` | Yes | Entity to update |
| `$data` | `array` | Yes | Request data array |
| `$key` | `string` | Yes | Key in the data array |
| `$setter` | `string` | Yes | Setter method name |

**`setNumericFromData(object $entity, array $data, string $key, string $setter, bool $nullable = true): void`**

Set a numeric field on an entity from request data.

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `$entity` | `object` | Yes | - | Entity to update |
| `$data` | `array` | Yes | - | Request data array |
| `$key` | `string` | Yes | - | Key in the data array |
| `$setter` | `string` | Yes | - | Setter method name |
| `$nullable` | `bool` | No | `true` | Allow null values |

**`setBooleanFromData(object $entity, array $data, string $key, string $setter, bool $default = false): void`**

Set a boolean field on an entity from request data.

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `$entity` | `object` | Yes | - | Entity to update |
| `$data` | `array` | Yes | - | Request data array |
| `$key` | `string` | Yes | - | Key in the data array |
| `$setter` | `string` | Yes | - | Setter method name |
| `$default` | `bool` | No | `false` | Default value if not set |

**`setIntegerFromData(object $entity, array $data, string $key, string $setter, bool $nullable = true): void`**

Set an integer field on an entity from request data.

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `$entity` | `object` | Yes | - | Entity to update |
| `$data` | `array` | Yes | - | Request data array |
| `$key` | `string` | Yes | - | Key in the data array |
| `$setter` | `string` | Yes | - | Setter method name |
| `$nullable` | `bool` | No | `true` | Allow null values |

**Example - Updating a fuel record:**
```php
private function updateRecordFromData(FuelRecord $record, array $data): void
{
    $this->setDateFromData($record, $data, 'date', 'setDate');
    $this->setNumericFromData($record, $data, 'mileage', 'setMileage');
    $this->setNumericFromData($record, $data, 'litres', 'setLitres');
    $this->setNumericFromData($record, $data, 'cost', 'setCost');
    $this->setNullableStringFromData($record, $data, 'station', 'setStation');
    $this->setNullableStringFromData($record, $data, 'fuelType', 'setFuelType');
    $this->setBooleanFromData($record, $data, 'fullTank', 'setFullTank', true);
}
```

---

### DateSerializationTrait

**File:** `DateSerializationTrait.php`

Provides consistent date formatting methods for API responses.

#### Methods

**`formatDate(?\DateTimeInterface $date): ?string`**

Format a date for API output (Y-m-d format).

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$date` | `\DateTimeInterface\|null` | Yes | Date to format |

**Returns:** `string|null` - Formatted date or null

**`formatDateTime(?\DateTimeInterface $date): ?string`**

Format a datetime for API output (ISO 8601 format).

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$date` | `\DateTimeInterface\|null` | Yes | DateTime to format |

**Returns:** `string|null` - Formatted datetime or null

**`formatDateTimeDisplay(?\DateTimeInterface $date): ?string`**

Format a datetime for display (d/m/Y H:i format).

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$date` | `\DateTimeInterface\|null` | Yes | DateTime to format |

**Returns:** `string|null` - Formatted datetime or null

---

### OwnershipVerificationTrait

**File:** `OwnershipVerificationTrait.php`

Provides methods to verify that the current user owns or has access to specific resources.

#### Methods

**`checkVehicleAccess(Vehicle $vehicle): ?JsonResponse`**

Verify that the current user can access the given vehicle.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$vehicle` | `Vehicle` | Yes | Vehicle to check |

**Returns:** `JsonResponse|null` - Error response if access denied, null if allowed

**`checkEntityVehicleAccess(object $entity): ?JsonResponse`**

Verify access to an entity's associated vehicle.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$entity` | `object` | Yes | Entity with `getVehicle()` method |

**Returns:** `JsonResponse|null` - Error response if access denied, null if allowed

**Example:**
```php
$vehicle = $this->entityManager->getRepository(Vehicle::class)->find($id);
if (!$vehicle) {
    return new JsonResponse(['error' => 'Vehicle not found'], 404);
}

$accessError = $this->checkVehicleAccess($vehicle);
if ($accessError) {
    return $accessError;
}
```

---

### ReceiptAttachmentTrait

**File:** `ReceiptAttachmentTrait.php`

Provides methods for handling receipt attachment relationships on entities.

#### Methods

**`handleReceiptAttachmentUpdate(object $entity, array $data, AttachmentLinkingService $linkingService): void`**

Handle updating the receipt attachment for an entity.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$entity` | `object` | Yes | Entity with receipt attachment |
| `$data` | `array` | Yes | Request data containing `receiptAttachmentId` |
| `$linkingService` | `AttachmentLinkingService` | Yes | Attachment linking service |

---

### AuthenticationRequiredTrait

**File:** `AuthenticationRequiredTrait.php`

Provides a standard authentication check method.

#### Methods

**`requireAuthenticatedUser(): User|JsonResponse`**

Require an authenticated user, returning an error response if not authenticated.

**Returns:** `User|JsonResponse` - User entity or 401 error response

**`isErrorResponse(mixed $result): bool`**

Check if a result is an error response.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$result` | `mixed` | Yes | Value to check |

**Returns:** `bool`

**Example:**
```php
$user = $this->requireAuthenticatedUser();
if ($this->isErrorResponse($user)) {
    return $user;
}
// $user is now definitely a User entity
```

---

## Services

All services are located in `backend/src/Service/`.

### AttachmentLinkingService

**File:** `AttachmentLinkingService.php`

Manages bidirectional relationships between attachments and entities. Handles file reorganisation when attachments are linked to vehicles.

#### Constructor

```php
public function __construct(
    EntityManagerInterface $entityManager,
    LoggerInterface $logger,
    string $projectDir
)
```

#### Methods

**`linkAttachmentToEntity(Attachment $attachment, object $entity): void`**

Link an attachment to an entity (sets the reverse relationship).

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$attachment` | `Attachment` | Yes | Attachment to link |
| `$entity` | `object` | Yes | Entity to link to |

**`unlinkAttachment(Attachment $attachment): void`**

Unlink an attachment from its entity.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$attachment` | `Attachment` | Yes | Attachment to unlink |

**`finalizeAttachmentLink(object $entity): void`**

Called after entity flush to finalize attachment linking (moves files to vehicle folder).

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$entity` | `object` | Yes | Entity with receipt attachment |

**Example:**
```php
// In a controller
$record = new FuelRecord();
$record->setVehicle($vehicle);
$this->updateRecordFromData($record, $data);

$this->entityManager->persist($record);
$this->entityManager->flush();

// Finalize attachment link after entity has ID
$this->attachmentLinkingService->finalizeAttachmentLink($record);
$this->entityManager->flush();
```

---

### EntitySerializerService

**File:** `EntitySerializerService.php`

Centralised service for serializing entities to arrays. Eliminates duplicate serialization methods across controllers.

#### Methods

**`serializePart(Part $part, bool $detailed = true): array`**

Serialize a Part entity to an array.

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `$part` | `Part` | Yes | - | Part entity |
| `$detailed` | `bool` | No | `true` | Include all fields |

**Returns:** `array` - Serialized part data

**`serializeConsumable(Consumable $consumable, bool $detailed = true): array`**

Serialize a Consumable entity to an array.

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `$consumable` | `Consumable` | Yes | - | Consumable entity |
| `$detailed` | `bool` | No | `true` | Include all fields |

**Returns:** `array` - Serialized consumable data

**`serializeMotRecord(MotRecord $mot, bool $detailed = false): array`**

Serialize a MotRecord entity to an array.

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `$mot` | `MotRecord` | Yes | - | MOT record entity |
| `$detailed` | `bool` | No | `false` | Include all fields |

**Returns:** `array` - Serialized MOT record data

---

### CostCalculator

**File:** `CostCalculator.php`

Calculates various costs associated with vehicles (fuel, parts, consumables, running costs, cost per mile).

#### Constructor

```php
public function __construct(
    EntityManagerInterface $entityManager,
    DepreciationCalculator $depreciationCalculator
)
```

#### Methods

**`calculateTotalFuelCost(Vehicle $vehicle): float`**

Calculate total fuel costs for a vehicle.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$vehicle` | `Vehicle` | Yes | Vehicle entity |

**Returns:** `float` - Total fuel cost

**`calculateTotalPartsCost(Vehicle $vehicle): float`**

Calculate total parts costs (excluding parts included in service costs).

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$vehicle` | `Vehicle` | Yes | Vehicle entity |

**Returns:** `float` - Total parts cost

**`calculateTotalConsumablesCost(Vehicle $vehicle): float`**

Calculate total consumables costs (excluding those included in service costs).

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$vehicle` | `Vehicle` | Yes | Vehicle entity |

**Returns:** `float` - Total consumables cost

**`calculateTotalServiceCost(Vehicle $vehicle): float`**

Calculate total service record costs.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$vehicle` | `Vehicle` | Yes | Vehicle entity |

**Returns:** `float` - Total service cost

**`calculateTotalRunningCost(Vehicle $vehicle): float`**

Calculate total running costs (fuel + parts + consumables + services).

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$vehicle` | `Vehicle` | Yes | Vehicle entity |

**Returns:** `float` - Total running cost

**`calculateTotalCostToDate(Vehicle $vehicle): float`**

Calculate total cost to date (purchase cost + running costs).

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$vehicle` | `Vehicle` | Yes | Vehicle entity |

**Returns:** `float` - Total cost to date

**`calculateCostPerMile(Vehicle $vehicle): ?float`**

Calculate cost per mile driven.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$vehicle` | `Vehicle` | Yes | Vehicle entity |

**Returns:** `float|null` - Cost per mile or null if cannot calculate

**`calculateAverageFuelConsumption(Vehicle $vehicle): ?float`**

Calculate average fuel consumption in MPG.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$vehicle` | `Vehicle` | Yes | Vehicle entity |

**Returns:** `float|null` - MPG or null if insufficient data

---

### DepreciationCalculator

**File:** `DepreciationCalculator.php`

Calculates vehicle depreciation using various methods.

#### Supported Methods

- `straight_line` - Linear depreciation over the specified years
- `declining_balance` - Percentage-based declining balance
- `double_declining` - Accelerated double declining balance
- `automotive_standard` - UK automotive industry standard rates

#### Methods

**`calculateCurrentValue(Vehicle $vehicle, bool $adjustForMileage = false): float`**

Calculate current vehicle value after depreciation.

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `$vehicle` | `Vehicle` | Yes | - | Vehicle entity |
| `$adjustForMileage` | `bool` | No | `false` | Apply mileage adjustment |

**Returns:** `float` - Current value

**`calculateTotalDepreciation(Vehicle $vehicle): float`**

Calculate total depreciation since purchase.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$vehicle` | `Vehicle` | Yes | Vehicle entity |

**Returns:** `float` - Total depreciation amount

**`getDepreciationSchedule(Vehicle $vehicle, ?int $years = null): array`**

Generate a year-by-year depreciation schedule.

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `$vehicle` | `Vehicle` | Yes | - | Vehicle entity |
| `$years` | `int\|null` | No | Vehicle setting | Number of years |

**Returns:** `array` - Year-indexed array of values

**`generateSchedule(Vehicle $vehicle, int $years, ?string $method = null, ?float $rate = null, float $minValue = 0.0): array`**

Generate depreciation schedule with custom parameters.

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `$vehicle` | `Vehicle` | Yes | - | Vehicle entity |
| `$years` | `int` | Yes | - | Number of years |
| `$method` | `string\|null` | No | Vehicle setting | Depreciation method |
| `$rate` | `float\|null` | No | Vehicle setting | Depreciation rate |
| `$minValue` | `float` | No | `0.0` | Minimum value floor |

**Returns:** `array` - Depreciation schedule

---

### ReportEngine

**File:** `ReportEngine.php`

Generic report engine that interprets JSON templates and generates reports in XLSX or PDF format.

#### Constructor

```php
public function __construct(EntityManagerInterface $em)
```

#### Methods

**`generate(array $template, array $params, string $format = 'xlsx'): array`**

Generate a report from a JSON template.

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `$template` | `array` | Yes | - | Parsed JSON template |
| `$params` | `array` | Yes | - | Parameters (vehicle_id, dates, etc.) |
| `$format` | `string` | No | `'xlsx'` | Output format (`'xlsx'` or `'pdf'`) |

**Returns:** `array` - `['content' => string, 'mimeType' => string, 'filename' => string]`

#### Template Format

Templates support the following structure:

```json
{
  "dataSources": {
    "fuelRecords": { "entity": "FuelRecord" },
    "allCosts": { "merge": ["fuelRecords", "serviceRecords"] }
  },
  "calculations": {
    "totalCost": { "source": "fuelRecords", "aggregate": "sum", "field": "cost" }
  },
  "layout": {
    "sections": [...]
  },
  "styles": {
    "header": { "font": { "bold": true } }
  }
}
```

---

### DvlaApiService

**File:** `DvlaApiService.php`

Integrates with the DVLA (Driver and Vehicle Licensing Agency) API to retrieve vehicle information from registration numbers.

#### Constructor

```php
public function __construct(
    HttpClientInterface $httpClient,
    LoggerInterface $logger,
    ?string $authUrl = null,
    ?string $clientId = null,
    ?string $clientSecret = null,
    ?string $dvlaApiKey = null
)
```

#### Environment Variables

- `DVLA_AUTH_URL` - OAuth token endpoint (if using client credentials)
- `DVLA_CLIENT_ID` - OAuth client ID
- `DVLA_CLIENT_SECRET` - OAuth client secret
- `DVLA_API_KEY` - Direct API key (alternative to OAuth)
- `DVLA_VEHICLE_URL` - Vehicle enquiry endpoint

#### Methods

**`getVehicleByRegistration(string $registration): ?array`**

Retrieve vehicle information from DVLA by registration number.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$registration` | `string` | Yes | Vehicle registration number |

**Returns:** `array|null` - Vehicle data or null if not found

**Response Data:**
```php
[
    'registrationNumber' => 'AB12 CDE',
    'make' => 'FORD',
    'colour' => 'BLUE',
    'fuelType' => 'PETROL',
    'taxStatus' => 'Taxed',
    'taxDueDate' => '2024-12-01',
    'motStatus' => 'Valid',
    'motExpiryDate' => '2024-06-15',
    'yearOfManufacture' => 2019,
    'engineCapacity' => 1500,
    'co2Emissions' => 120,
    // ... more fields
]
```

---

### DvsaApiService

**File:** `DvsaApiService.php`

Integrates with the DVSA (Driver and Vehicle Standards Agency) API to retrieve MOT history.

#### Methods

**`getMotHistory(string $registration): ?array`**

Retrieve MOT test history for a vehicle.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$registration` | `string` | Yes | Vehicle registration number |

**Returns:** `array|null` - MOT history or null if not found

**`getLatestMot(string $registration): ?array`**

Retrieve only the latest MOT test result.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$registration` | `string` | Yes | Vehicle registration number |

**Returns:** `array|null` - Latest MOT data or null

---

### VehicleExportService

**File:** `VehicleExportService.php`

Handles exporting vehicle data to JSON format, optionally with attachment files in a ZIP archive.

#### Methods

**`exportVehicles(User $user, bool $isAdmin = false, bool $includeAttachmentRefs = false, ?string $zipDir = null): ExportResult`**

Export all vehicles for a user.

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `$user` | `User` | Yes | - | User to export for |
| `$isAdmin` | `bool` | No | `false` | Export all vehicles |
| `$includeAttachmentRefs` | `bool` | No | `false` | Include attachment references |
| `$zipDir` | `string\|null` | No | `null` | Directory for ZIP export |

**Returns:** `ExportResult` - Result object with vehicles data

---

### VehicleImportService

**File:** `VehicleImportService.php`

Handles importing vehicle data from JSON format, including attachment file handling.

#### Methods

**`importVehicles(User $user, array $data, bool $isAdmin = false): ImportResult`**

Import vehicles from JSON data.

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `$user` | `User` | Yes | - | User to import for |
| `$data` | `array` | Yes | - | JSON data array |
| `$isAdmin` | `bool` | No | `false` | Skip ownership checks |

**Returns:** `ImportResult` - Result object with import statistics

---

### ReceiptOcrService

**File:** `ReceiptOcrService.php`

Extracts data from receipt images using Tesseract OCR.

#### Methods

**`extractReceiptData(string $filePath): array`**

Extract fuel receipt data from an image.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$filePath` | `string` | Yes | Path to receipt image |

**Returns:** `array` - Extracted data

**Response:**
```php
[
    'date' => '2024-03-15',      // Extracted date
    'cost' => '45.50',           // Total cost
    'litres' => '32.5',          // Fuel quantity
    'station' => 'Shell',        // Station name
    'fuelType' => 'E5',          // Fuel type
]
```

---

### UrlScraperService

**File:** `UrlScraperService.php`

Scrapes product information from URLs (e.g., parts suppliers, eBay listings).

#### Methods

**`scrapeUrl(string $url): array`**

Scrape product data from a URL.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$url` | `string` | Yes | URL to scrape |

**Returns:** `array` - Scraped product data

---

### VinDecoderService

**File:** `VinDecoderService.php`

Decodes Vehicle Identification Numbers (VINs) to extract vehicle specifications.

#### Methods

**`decode(string $vin): ?array`**

Decode a VIN to get vehicle information.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$vin` | `string` | Yes | 17-character VIN |

**Returns:** `array|null` - Decoded vehicle data or null if invalid

---

### RepairCostCalculator

**File:** `RepairCostCalculator.php`

Calculates repair costs including parts and labour.

#### Methods

**`calculateServiceRecordCost(ServiceRecord $record): float`**

Calculate total cost for a service record including parts and consumables.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$record` | `ServiceRecord` | Yes | Service record entity |

**Returns:** `float` - Total cost

---

## Service Traits

Service traits are located in `backend/src/Service/Trait/`.

### UnitConversionTrait

**File:** `UnitConversionTrait.php`

Provides distance unit conversion methods for services.

#### Methods

**`setDistanceUnit(string $unit): void`**

Set the current distance unit preference.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$unit` | `string` | Yes | `'miles'` or `'km'` |

**`getDistanceUnitPreference(): string`**

Get the current distance unit.

**Returns:** `string` - `'miles'` or `'km'`

**`convertDistanceFromKm(float $km, int $decimals = 2): float`**

Convert kilometres to the current unit.

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `$km` | `float` | Yes | - | Distance in kilometres |
| `$decimals` | `int` | No | `2` | Decimal places |

**Returns:** `float` - Converted distance

**`getDistanceLabel(): string`**

Get the label for the current unit.

**Returns:** `string` - `'miles'` or `'km'`

**`getFuelEconomyLabel(): string`**

Get the fuel economy label.

**Returns:** `string` - `'mpg'` or `'km/l'`

---

### EntityHydratorTrait

**File:** `EntityHydratorTrait.php`

Service-level entity hydration utilities, similar to the controller trait but for use in services.

---

## Entities

All entities are located in `backend/src/Entity/`. Key entities include:

| Entity | Description |
|--------|-------------|
| `User` | User accounts with authentication |
| `UserPreference` | User preferences (key-value pairs) |
| `Vehicle` | Vehicle records with specifications |
| `VehicleType` | Vehicle type categories |
| `VehicleMake` | Vehicle manufacturers |
| `VehicleModel` | Vehicle models |
| `VehicleImage` | Vehicle photos |
| `FuelRecord` | Fuel purchase records |
| `ServiceRecord` | Service history records |
| `ServiceItem` | Individual items in a service |
| `MotRecord` | MOT test records |
| `Part` | Vehicle parts inventory |
| `PartCategory` | Part categories |
| `Consumable` | Consumable items (fluids, filters) |
| `ConsumableType` | Consumable type definitions |
| `InsurancePolicy` | Insurance policies |
| `RoadTax` | Road tax records |
| `Attachment` | File attachments |
| `Specification` | Vehicle specifications |
| `Todo` | Maintenance tasks |
| `Report` | Generated reports |

---

## Best Practices

### When to Use These Libraries

1. **Always use `JsonValidationTrait`** in controllers that accept JSON request bodies.

2. **Always use `UserSecurityTrait`** when checking user authentication or admin status.

3. **Always use `EntityHydrationTrait`** when updating entities from request data to ensure consistent null handling.

4. **Always use `AttachmentLinkingService`** when managing receipt attachments on entities.

5. **Always use `EntitySerializerService`** for consistent entity serialization in API responses.

6. **Always use the cost calculators** rather than implementing cost logic inline.

### Standard Controller Pattern

```php
#[Route('/api/records')]
class RecordController extends AbstractController
{
    use UserSecurityTrait;
    use JsonValidationTrait;
    use EntityHydrationTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AttachmentLinkingService $attachmentLinkingService,
        private EntitySerializerService $serializer
    ) {}

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUserEntity();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $validation = $this->validateJsonRequest($request);
        if ($validation['error']) {
            return $validation['error'];
        }
        $data = $validation['data'];

        // Verify vehicle access
        $vehicle = $this->entityManager->getRepository(Vehicle::class)
            ->find($data['vehicleId']);
        if (!$vehicle || (!$this->isAdminForUser($user) && $vehicle->getOwner()->getId() !== $user->getId())) {
            return new JsonResponse(['error' => 'Vehicle not found'], 404);
        }

        $record = new Record();
        $record->setVehicle($vehicle);
        $this->updateRecordFromData($record, $data);

        $this->entityManager->persist($record);
        $this->entityManager->flush();

        // Handle attachment linking
        $this->attachmentLinkingService->finalizeAttachmentLink($record);
        $this->entityManager->flush();

        return new JsonResponse($this->serializeRecord($record), 201);
    }
}
```

### Ownership Verification Pattern

```php
// For single resource access
$record = $this->entityManager->getRepository(Record::class)->find($id);
if (!$record) {
    return new JsonResponse(['error' => 'Not found'], 404);
}

$vehicle = $record->getVehicle();
if (!$this->isAdminForUser($user) && $vehicle->getOwner()->getId() !== $user->getId()) {
    return new JsonResponse(['error' => 'Forbidden'], 403);
}

// For listing all resources
$vehicleRepo = $this->entityManager->getRepository(Vehicle::class);
$vehicles = $this->isAdminForUser($user) 
    ? $vehicleRepo->findAll() 
    : $vehicleRepo->findBy(['owner' => $user]);

$qb = $this->entityManager->createQueryBuilder()
    ->select('r')
    ->from(Record::class, 'r')
    ->where('r.vehicle IN (:vehicles)')
    ->setParameter('vehicles', $vehicles);
```
