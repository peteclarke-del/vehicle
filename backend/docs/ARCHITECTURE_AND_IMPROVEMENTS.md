# Vehicle Management System - Architecture & Recent Improvements

## Overview
A comprehensive vehicle management system built with Symfony 6 (PHP 8.4) backend and React 18 frontend.

## Recent Improvements (January 2026)

### Code Quality & Performance

#### 1. UserSecurityTrait (Commit 2b3bb74)
**Problem:** Duplicate `getUserEntity()` and `isAdminForUser()` methods across 10+ controllers.

**Solution:** Created `App\Controller\Trait\UserSecurityTrait`
- Centralized user authentication and authorization logic
- Removed ~350 lines of duplicate code
- Improved maintainability

**Usage:**
```php
class VehicleController extends AbstractController
{
    use UserSecurityTrait;

    public function listVehicles(): JsonResponse
    {
        $user = $this->getUserEntity();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }
        // ...
    }
}
```

#### 2. EntitySerializerService (Commit 818c791)
**Problem:** Duplicate serialization code for Parts, Consumables, and MotRecords across multiple controllers.

**Solution:** Created `App\Service\EntitySerializerService`
- Centralized `serializePart()`, `serializeConsumable()`, `serializeMotRecord()` methods
- Removed ~200 lines of duplicate code
- Consistent serialization format across endpoints

**Usage:**
```php
class PartController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EntitySerializerService $serializer
    ) {}

    public function getPart(int $id): JsonResponse
    {
        $part = $this->entityManager->find(Part::class, $id);
        return $this->json($this->serializer->serializePart($part));
    }
}
```

#### 3. Cascade Delete for Attachments (Commit 4a420c9)
**Problem:** Deleting Parts, Consumables, or Records left orphaned attachment records and files.

**Solution:** Added `cascade: ['remove']` to entity relationships
- Affected entities: Part, Consumable, MotRecord, ServiceRecord, FuelRecord
- Automatic cleanup prevents data integrity issues

**Implementation:**
```php
#[ORM\ManyToOne(targetEntity: Attachment::class, cascade: ['remove'])]
#[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
private ?Attachment $receiptAttachment = null;
```

#### 4. N+1 Query Optimization (Commit d8e34bd)
**Problem:** InsuranceController::listPolicies() had N+1 query issue with lazy loading.

**Solution:** Eager loading with QueryBuilder
```php
// Before: 1 + N queries
$policies = $this->entityManager->getRepository(InsurancePolicy::class)->findAll();

// After: 1 query
$policies = $this->entityManager->getRepository(InsurancePolicy::class)
    ->createQueryBuilder('p')
    ->leftJoin('p.vehicles', 'v')
    ->addSelect('v')
    ->leftJoin('v.owner', 'o')
    ->addSelect('o')
    ->getQuery()
    ->getResult();
```

#### 5. PSR-12 Compliance (Commit d8e34bd)
**Improvement:** Auto-fixed 8 PSR-12 errors using PHP_CodeSniffer
- Controllers: AttachmentController, FuelRecordController, InsuranceController, NotificationController, RoadTaxController, TodoController, VehicleImageController, VehicleImportExportController
- Improved code consistency and readability

#### 6. ErrorBoundary Component (Commit 72d3c11)
**Problem:** React errors could crash the entire frontend application.

**Solution:** Implemented ErrorBoundary component
- Catches React component errors gracefully
- Displays user-friendly error UI
- Provides "Try Again" functionality
- Shows stack traces in development mode

**Implementation:**
```javascript
// App.js
<ErrorBoundary>
  <Routes>
    {/* All routes */}
  </Routes>
</ErrorBoundary>
```

### Code Statistics

**Backend:**
- Controllers: 29 files
- Entities: ~20 entities
- Services: ~15 services
- PSR-12 Errors: 0
- PSR-12 Warnings: ~200 (mostly line length)
- Entity Tests: 153 passing

**Frontend:**
- Components: 32+ components
- Pages: 15+ pages
- React Optimizations: useCallback, useMemo, React.memo where appropriate
- Error Handling: ErrorBoundary implemented

### Architecture Patterns

#### Backend Patterns
1. **Controller Layer:**
   - Uses traits for cross-cutting concerns (UserSecurityTrait)
   - Thin controllers, business logic in services
   - Consistent JSON responses

2. **Service Layer:**
   - EntitySerializerService for consistent data formatting
   - Adapter pattern for external APIs (DVLA, DVSA, eBay)
   - Dedicated calculators (CostCalculator, DepreciationCalculator)

3. **Entity Layer:**
   - Doctrine ORM with proper relationships
   - Cascade operations for data integrity
   - Type-safe properties

#### Frontend Patterns
1. **Context API:**
   - AuthContext for authentication
   - ThemeContext for theming
   - UserPreferencesContext
   - VehiclesContext

2. **Error Handling:**
   - ErrorBoundary for component errors
   - SessionTimeoutWarning
   - Axios interceptors for API errors

3. **Performance:**
   - React.memo for expensive components
   - useCallback for event handlers
   - useMemo for expensive computations

### Known Issues

See [TEST_INFRASTRUCTURE_ISSUES.md](TEST_INFRASTRUCTURE_ISSUES.md) for:
- WebTestCase kernel booting issues (163 test errors)
- Missing test classes (3 warnings)
- Test failures (74 failures)

### Future Improvements

**Deferred:**
- Rate limiting on /api/login and /api/register (requires symfony/rate-limiter package)

**Recommended:**
- Fix WebTestCase setUp() pattern in 11 controller tests
- Add more React component tests
- Implement API response caching
- Add OpenAPI/Swagger documentation
- Implement audit logging for sensitive operations

## Development Guidelines

### Backend
- Follow PSR-12 coding standard
- Use type hints for all parameters and return types
- Write unit tests for new services
- Use QueryBuilder for complex queries to avoid N+1
- Leverage EntitySerializerService for entity serialization

### Frontend
- Use functional components with hooks
- Wrap expensive components in React.memo
- Use useCallback for event handlers passed as props
- Use useMemo for expensive computations
- All components should be wrapped by ErrorBoundary

### Testing
- Write tests for new features
- Ensure WebTestCase tests don't call createClient() in setUp()
- Entity tests should cover all getters/setters
- Service tests should mock dependencies

## Deployment

**Backend:**
- PHP 8.4 required
- Symfony 6 framework
- Doctrine ORM
- JWT authentication (lexik/jwt-authentication-bundle)

**Frontend:**
- React 18.2
- Material-UI 5.15
- React Router 6
- i18next for internationalization

**Infrastructure:**
- Docker Compose for local development
- Nginx reverse proxy
- MySQL database
- Redis for caching

## Contact & Contributions

For questions or contributions, refer to the git commit history and follow the established patterns documented here.
