# AI Context Document

This document provides a comprehensive overview of the Vehicle Management System application for AI assistants. It contains all the information needed to understand, troubleshoot, and extend the codebase.

---

## Application Overview

The Vehicle Management System is a full-stack web application for tracking personal or fleet vehicles. It manages vehicle records, fuel purchases, service history, MOT tests, insurance policies, road tax, parts inventory, consumables, and generates reports.

**Technology Stack:**
- Backend: Symfony 6.4 (PHP 8.3+), Doctrine ORM, MySQL 8.0
- Frontend: React 18, Material-UI 5, React Router 6, Axios
- Authentication: JWT via LexikJWTAuthenticationBundle
- Infrastructure: Docker, Nginx, Redis (caching)

---

## Project Structure

```
/
├── backend/                    # Symfony PHP backend
│   ├── bin/console             # Symfony CLI
│   ├── config/                 # Configuration files
│   │   ├── packages/           # Bundle configurations
│   │   └── routes/             # Route configurations
│   ├── migrations/             # Database migrations
│   ├── public/index.php        # Entry point
│   ├── src/
│   │   ├── Command/            # Console commands
│   │   ├── Controller/         # API controllers
│   │   │   └── Trait/          # Reusable controller traits
│   │   ├── DataFixtures/       # Test data fixtures
│   │   ├── Entity/             # Doctrine entities
│   │   ├── EventSubscriber/    # Event subscribers
│   │   └── Service/            # Business logic services
│   │       └── Trait/          # Reusable service traits
│   ├── templates/              # Twig templates (minimal)
│   ├── tests/                  # PHPUnit tests
│   └── var/                    # Cache, logs, temp files
├── frontend/                   # React frontend
│   ├── public/                 # Static assets
│   │   └── locales/            # i18n translation files
│   └── src/
│       ├── components/         # Reusable React components
│       ├── contexts/           # React context providers
│       ├── hooks/              # Custom React hooks
│       ├── pages/              # Page components
│       ├── reports/            # Report templates (JSON)
│       └── utils/              # Utility functions
├── docker/                     # Docker build contexts
│   ├── nginx/                  # Nginx configuration
│   ├── node/                   # Node.js for frontend build
│   └── php/                    # PHP-FPM configuration
├── docs/                       # Documentation
├── uploads/                    # User file uploads
└── Makefile                    # Development commands
```

---

## Key Concepts

### Multi-tenancy

Each user owns their own vehicles and associated records. Users can only access their own data unless they have the `ROLE_ADMIN` role. Admin users can access all data.

**Ownership checking pattern:**
```php
// Standard ownership check
if (!$this->isAdminForUser($user) && $vehicle->getOwner()->getId() !== $user->getId()) {
    return new JsonResponse(['error' => 'Forbidden'], 403);
}

// For listing records
$vehicles = $this->isAdminForUser($user) 
    ? $vehicleRepo->findAll() 
    : $vehicleRepo->findBy(['owner' => $user]);
```

### Entity Relationships

```
User
├── UserPreference[] (one-to-many)
└── Vehicle[] (one-to-many)
    ├── VehicleType (many-to-one)
    ├── VehicleImage[] (one-to-many)
    ├── Specification (one-to-one)
    ├── FuelRecord[] (one-to-many)
    │   └── Attachment (receipt, many-to-one)
    ├── ServiceRecord[] (one-to-many)
    │   ├── ServiceItem[] (one-to-many)
    │   ├── Part[] (one-to-many)
    │   ├── Consumable[] (one-to-many)
    │   ├── MotRecord (many-to-one, optional)
    │   └── Attachment (receipt, many-to-one)
    ├── MotRecord[] (one-to-many)
    │   ├── Part[] (one-to-many)
    │   ├── Consumable[] (one-to-many)
    │   └── ServiceRecord (one-to-one, optional)
    ├── Part[] (one-to-many)
    │   ├── PartCategory (many-to-one)
    │   └── Attachment (receipt, many-to-one)
    ├── Consumable[] (one-to-many)
    │   ├── ConsumableType (many-to-one)
    │   └── Attachment (receipt, many-to-one)
    ├── InsurancePolicy[] (many-to-many)
    ├── RoadTax[] (one-to-many)
    ├── Todo[] (one-to-many)
    └── Attachment[] (one-to-many)
```

### Distance Units

The application supports both metric (kilometres) and imperial (miles) measurements.

**Database storage:** All distances are stored in kilometres.

**Conversion points:**
- Backend: `UnitConversionTrait` in services
- Frontend: `useDistance` hook and `distanceUtils`
- Reports: `ReportEngine` applies user preference

### Attachment System

Attachments are files (receipts, documents) linked to entities.

**Storage structure:**
```
uploads/
├── attachments/           # Unassigned attachments
│   └── misc/              # Temporary storage
└── vehicles/
    └── {registration}/    # Per-vehicle folder
        ├── images/        # Vehicle photos
        └── attachments/   # Receipt files
```

**Linking flow:**
1. File uploaded via `/api/attachments` (stored in `misc/`)
2. Entity created with `receiptAttachmentId`
3. `AttachmentLinkingService::finalizeAttachmentLink()` moves file to vehicle folder

### Cost Calculations

Parts and consumables can be linked to service records or MOT records. The `includedInServiceCost` flag determines whether their cost is counted separately or as part of the service.

**Cost aggregation:**
- `CostCalculator::calculateTotalPartsCost()` - Standalone parts only
- `CostCalculator::calculateTotalServiceCost()` - Service records (includes linked parts)
- `CostCalculator::calculateTotalRunningCost()` - Fuel + parts + consumables + services

---

## Frontend Architecture

### State Management

**Contexts:**
- `AuthContext` - Authentication state, JWT token, API instance
- `VehiclesContext` - Shared vehicle list with 30-second cache
- `UserPreferencesContext` - User preferences (theme, language, defaults)
- `ThemeContext` - Light/dark mode

**Pattern for data pages:**
```javascript
function RecordsPage() {
  const { vehicles } = useVehicles();
  const { selectedVehicleId, setSelectedVehicleId } = useVehicleSelection(vehicles);
  const { orderBy, order, handleRequestSort } = usePersistedSort('records_sort');
  const { data, loading } = useApiData(() => api.get('/records', { 
    params: { vehicleId: selectedVehicleId !== 'all' ? selectedVehicleId : undefined }
  }));
  
  const sortedData = [...data].sort(createSortComparator(order, orderBy, commonFieldConfigs));
  // ... render
}
```

### Common Hooks

| Hook | Purpose |
|------|---------|
| `useApiData` | Fetch API data with array validation |
| `usePersistedSort` | Table sorting with localStorage persistence |
| `useVehicleSelection` | Vehicle filtering with default vehicle support |
| `useTablePagination` | Pagination with user preference sync |
| `useDistance` | Distance conversion based on preferences |
| `useNotifications` | Real-time notifications via SSE |
| `useDragDrop` | File drag-and-drop handling |

### Utility Functions

| Utility | Purpose |
|---------|---------|
| `SafeStorage` | Safe localStorage wrapper |
| `distanceUtils` | Distance conversions |
| `splitLabel` | Label formatting for tables |
| `formatCurrency` | Currency formatting |
| `formatDate` | Date formatting |
| `logger` | Production-safe logging |
| `sortUtils` | Generic sort comparators |

---

## Backend Architecture

### Controller Structure

Controllers use traits for common functionality:

```php
class RecordController extends AbstractController
{
    use UserSecurityTrait;      // getUserEntity(), isAdminForUser()
    use JsonValidationTrait;    // validateJsonRequest()
    use EntityHydrationTrait;   // setDateFromData(), setNumericFromData(), etc.
    
    // ... methods
}
```

### Standard CRUD Pattern

```php
#[Route('/api/records')]
class RecordController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUserEntity();
        if (!$user) return new JsonResponse(['error' => 'Unauthorized'], 401);
        
        $vehicleId = $request->query->get('vehicleId');
        
        if ($vehicleId) {
            $vehicle = $this->em->getRepository(Vehicle::class)->find($vehicleId);
            if (!$vehicle || (!$this->isAdminForUser($user) && 
                $vehicle->getOwner()->getId() !== $user->getId())) {
                return new JsonResponse(['error' => 'Vehicle not found'], 404);
            }
            $records = $this->em->getRepository(Record::class)
                ->findBy(['vehicle' => $vehicle]);
        } else {
            $vehicles = $this->isAdminForUser($user) 
                ? $this->em->getRepository(Vehicle::class)->findAll()
                : $this->em->getRepository(Vehicle::class)->findBy(['owner' => $user]);
            
            $records = $this->em->createQueryBuilder()
                ->select('r')->from(Record::class, 'r')
                ->where('r.vehicle IN (:vehicles)')
                ->setParameter('vehicles', $vehicles)
                ->getQuery()->getResult();
        }
        
        return new JsonResponse(array_map(fn($r) => $this->serialize($r), $records));
    }
    
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUserEntity();
        if (!$user) return new JsonResponse(['error' => 'Unauthorized'], 401);
        
        $validation = $this->validateJsonRequest($request);
        if ($validation['error']) return $validation['error'];
        $data = $validation['data'];
        
        $vehicle = $this->em->getRepository(Vehicle::class)->find($data['vehicleId']);
        if (!$vehicle || (!$this->isAdminForUser($user) && 
            $vehicle->getOwner()->getId() !== $user->getId())) {
            return new JsonResponse(['error' => 'Vehicle not found'], 404);
        }
        
        $record = new Record();
        $record->setVehicle($vehicle);
        $this->updateFromData($record, $data);
        
        $this->em->persist($record);
        $this->em->flush();
        
        // Handle attachment linking
        $this->attachmentLinkingService->finalizeAttachmentLink($record);
        $this->em->flush();
        
        return new JsonResponse($this->serialize($record), 201);
    }
}
```

### Services

| Service | Purpose |
|---------|---------|
| `AttachmentLinkingService` | Manage attachment-entity relationships |
| `EntitySerializerService` | Consistent entity serialization |
| `CostCalculator` | Vehicle cost calculations |
| `DepreciationCalculator` | Vehicle depreciation |
| `ReportEngine` | Generate reports from JSON templates |
| `DvlaApiService` | DVLA vehicle lookup |
| `DvsaApiService` | DVSA MOT history |
| `VehicleExportService` | Export to JSON/ZIP |
| `VehicleImportService` | Import from JSON/ZIP |
| `ReceiptOcrService` | Extract data from receipt images |
| `UrlScraperService` | Scrape product information |
| `VinDecoderService` | Decode VINs |

---

## Database Schema

### Core Tables

**users** - User accounts
```sql
id, email, password, first_name, last_name, roles (JSON), created_at
```

**user_preferences** - Key-value preferences
```sql
id, user_id, name, value
```

**vehicles** - Vehicle records
```sql
id, owner_id, registration, name, make, model, variant, year, colour, vin,
engine_size, transmission, doors, body_type, fuel_type, current_mileage,
purchase_date, purchase_cost, purchase_mileage, vehicle_type_id,
depreciation_method, depreciation_years, depreciation_rate,
sorn_status, road_tax_exempt, mot_exempt, notes, created_at
```

**fuel_records**
```sql
id, vehicle_id, date, mileage, litres, cost, station, fuel_type,
full_tank, notes, receipt_attachment_id, created_at
```

**service_records**
```sql
id, vehicle_id, service_date, service_provider, mileage, cost,
work_performed, notes, mot_record_id, receipt_attachment_id, created_at
```

**mot_records**
```sql
id, vehicle_id, test_date, expiry_date, result, mileage, test_number,
test_centre, cost, advisories (JSON), failures (JSON), created_at
```

**parts**
```sql
id, vehicle_id, description, part_number, manufacturer, supplier,
purchase_date, price, quantity, installation_date, mileage_at_installation,
warranty, part_category_id, service_record_id, mot_record_id,
receipt_attachment_id, product_url, included_in_service_cost, created_at
```

**consumables**
```sql
id, vehicle_id, description, consumable_type_id, brand, part_number,
supplier, cost, quantity, last_changed, mileage_at_change,
replacement_interval_miles, next_replacement_mileage, service_record_id,
mot_record_id, receipt_attachment_id, product_url, included_in_service_cost,
created_at
```

---

## Common Issues and Solutions

### Authentication Issues

**Issue:** 401 Unauthorized on API requests
**Causes:**
1. JWT token expired
2. Token not included in Authorization header
3. Token malformed

**Solution:** Check token expiry, ensure `api` from AuthContext is used for requests.

### Ownership Errors

**Issue:** 403 Forbidden or 404 Not Found when accessing valid resources
**Cause:** Resource belongs to another user

**Solution:** Verify the user owns the vehicle. Check `isAdminForUser()` logic.

### Attachment Linking Issues

**Issue:** Attachment not appearing on record
**Cause:** `finalizeAttachmentLink()` not called after entity flush

**Solution:** Always call `$attachmentLinkingService->finalizeAttachmentLink($entity)` after the initial `flush()`.

### Sorting Not Persisted

**Issue:** Table sorting resets on page reload
**Cause:** Not using `usePersistedSort` hook

**Solution:** Use `usePersistedSort('unique_storage_key')` for all sortable tables.

### Vehicle Selection Issues

**Issue:** Default vehicle not selected on page load
**Cause:** Not using `useVehicleSelection` hook

**Solution:** Use `useVehicleSelection(vehicles)` instead of manual state management.

---

## Adding New Features

### Adding a New Record Type

1. **Create Entity** (`backend/src/Entity/NewRecord.php`):
   - Add Doctrine annotations
   - Add relationship to Vehicle
   - Add relationship to Attachment (if needed)

2. **Create Migration**:
   ```bash
   make backend-shell
   bin/console make:migration
   bin/console doctrine:migrations:migrate
   ```

3. **Create Controller** (`backend/src/Controller/NewRecordController.php`):
   - Use standard traits
   - Implement list, get, create, update, delete methods
   - Handle attachment linking

4. **Create Frontend Page** (`frontend/src/pages/NewRecords.js`):
   - Use standard hooks pattern
   - Implement table with sorting
   - Implement CRUD dialogs

5. **Add Route** (`frontend/src/App.js`):
   - Add protected route

6. **Add Navigation** (`frontend/src/components/Navigation.js`):
   - Add menu item

### Adding a New API Endpoint

1. **Add Route** in controller:
   ```php
   #[Route('/new-endpoint', methods: ['POST'])]
   public function newEndpoint(Request $request): JsonResponse
   {
       // Implementation
   }
   ```

2. **Document** in `docs/API_REFERENCE.md`

### Adding a New Hook

1. **Create File** (`frontend/src/hooks/useNewHook.js`)

2. **Export from hooks** (if using index.js)

3. **Document** in `docs/FRONTEND_LIBRARIES.md`

### Adding a New Service

1. **Create File** (`backend/src/Service/NewService.php`)

2. **Register** (automatic with autowiring)

3. **Inject** in controllers/services that need it

4. **Document** in `docs/BACKEND_LIBRARIES.md`

---

## Testing

### Backend Tests

```bash
make test                    # Run all tests
make test-coverage           # Run with coverage
docker-compose exec php bin/phpunit tests/Service/  # Run specific tests
```

### Frontend Tests

```bash
make test-frontend           # Run all tests
cd frontend && npm test -- --coverage  # Run with coverage
```

---

## Environment Configuration

### Required Environment Variables

```env
# Database
DATABASE_URL=mysql://user:pass@mysql:3306/vehicle

# JWT
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your_passphrase

# DVLA API (optional)
DVLA_API_KEY=your_api_key

# DVSA API (optional)
DVSA_API_KEY=your_api_key

# Frontend
REACT_APP_API_URL=http://localhost:8081/api
REACT_APP_PASSWORD_POLICY=^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$
```

---

## Deployment Considerations

### Production Build

```bash
make build-prod              # Build production containers
make deploy-prod             # Full deployment
```

### Security Checklist

- [ ] Change default admin password
- [ ] Configure JWT keys
- [ ] Set secure cookie options
- [ ] Enable HTTPS
- [ ] Configure CORS origins
- [ ] Review file upload limits
- [ ] Configure rate limiting

### Performance

- Redis is used for session storage and caching
- Vehicle list is cached for 30 seconds in VehiclesContext
- Doctrine query caching enabled
- Consider adding Nginx caching for static assets
