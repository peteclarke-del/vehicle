# Testing Documentation

## Overview
This document describes the comprehensive test suite for the Vehicle Management Platform, covering both frontend (React) and backend (Symfony/PHP) components with a target of 95% code coverage.

## Test Infrastructure

### Backend (PHP/Symfony)
- **Framework**: PHPUnit 10.5
- **Test Types**: Unit tests, Integration tests
- **Location**: `backend/tests/`
- **Configuration**: `backend/phpunit.xml.dist`
- **Coverage Target**: 95%

### Frontend (React)
- **Framework**: Jest + React Testing Library
- **Test Types**: Component tests, Integration tests
- **Location**: `frontend/src/__tests__/`
- **Configuration**: Built into react-scripts
- **Coverage Target**: 95%

## Directory Structure

```
backend/tests/
├── bootstrap.php                    # Test environment initialization
├── Controller/                      # Controller integration tests
│   ├── InsuranceControllerTest.php
│   ├── VehicleControllerTest.php
│   └── ... (13 more controller tests)
├── Service/                         # Service unit tests
│   ├── CostCalculatorTest.php
│   ├── DepreciationCalculatorTest.php
│   ├── ShopifyAdapterTest.php
│   └── ... (7 more service tests)
└── Entity/                          # Entity tests
    └── ... (13 entity tests)

frontend/src/__tests__/
├── Insurance.test.js                # Insurance page component tests
├── InsuranceDialog.test.js          # Insurance dialog component tests
├── Dashboard.test.js                # Dashboard page component tests
├── Vehicles.test.js                 # Vehicles page component tests
└── ... (10 more component tests)
```

## Running Tests

### Backend Tests

**Run all tests:**
```bash
cd backend
docker exec vehicle_php vendor/bin/phpunit
```

**Run specific test suite:**
```bash
# Controller tests only
docker exec vehicle_php vendor/bin/phpunit --testsuite=Controller

# Service tests only
docker exec vehicle_php vendor/bin/phpunit --testsuite=Service

# Entity tests only
docker exec vehicle_php vendor/bin/phpunit --testsuite=Entity
```

**Run with coverage:**
```bash
# HTML coverage report
docker exec vehicle_php vendor/bin/phpunit --coverage-html var/coverage/html

# Clover XML for CI
docker exec vehicle_php vendor/bin/phpunit --coverage-clover var/coverage/clover.xml

# Text summary
docker exec vehicle_php vendor/bin/phpunit --coverage-text
```

**View coverage report:**
```bash
open backend/var/coverage/html/index.html
```

### Frontend Tests

**Run all tests:**
```bash
cd frontend
npm test
```

**Run with coverage:**
```bash
npm test -- --coverage --watchAll=false
```

**Run specific test file:**
```bash
npm test -- Insurance.test.js
```

**Update snapshots:**
```bash
npm test -- -u
```

## Test Coverage Status

### Backend Coverage (Target: 95%)

| Component | Files | Coverage | Status |
|-----------|-------|----------|--------|
| Controllers | 14 | TBD | ⚠️ In Progress |
| Services | 10 | TBD | ⚠️ In Progress |
| Entities | 13 | TBD | ⚠️ In Progress |
| **Total** | **37** | **TBD** | **⚠️** |

### Frontend Coverage (Target: 95%)

| Component | Files | Coverage | Status |
|-----------|-------|----------|--------|
| Pages | 8 | TBD | ⚠️ In Progress |
| Components | 15 | TBD | ⚠️ In Progress |
| Hooks | 3 | TBD | ⚠️ In Progress |
| **Total** | **26** | **TBD** | **⚠️** |

## Test Patterns

### Backend Integration Tests (Controllers)

**Pattern: WebTestCase with authentication**

```php
<?php
declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class ExampleControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    
    protected function setUp(): void
    {
        $this->client = static::createClient();
    }
    
    private function getAuthToken(): string
    {
        return 'Bearer mock-jwt-token';
    }
    
    public function testEndpointRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/resource');
        $this->assertResponseStatusCodeSame(401);
    }
    
    public function testSuccessfulRequest(): void
    {
        $this->client->request(
            'GET',
            '/api/resource',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
    }
}
```

### Backend Unit Tests (Services)

**Pattern: TestCase with mocks**

```php
<?php
declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ExampleService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ExampleServiceTest extends TestCase
{
    private ExampleService $service;
    
    protected function setUp(): void
    {
        $mockClient = new MockHttpClient([
            new MockResponse('{"data": "test"}')
        ]);
        
        $this->service = new ExampleService($mockClient);
    }
    
    public function testServiceMethod(): void
    {
        $result = $this->service->doSomething();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
    }
}
```

### Frontend Component Tests

**Pattern: React Testing Library with mocks**

```javascript
import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import ExampleComponent from '../components/ExampleComponent';
import { AuthContext } from '../context/AuthContext';

jest.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key) => key }),
}));

jest.mock('../hooks/useApiData', () => ({
  useApiData: jest.fn(),
}));

const { useApiData } = require('../hooks/useApiData');

describe('ExampleComponent', () => {
  const mockAuthContext = {
    user: { id: 1, email: 'test@example.com' },
  };
  
  const renderWithProviders = (component) => {
    return render(
      <BrowserRouter>
        <AuthContext.Provider value={mockAuthContext}>
          {component}
        </AuthContext.Provider>
      </BrowserRouter>
    );
  };
  
  test('renders component', () => {
    useApiData.mockReturnValue({ data: [], loading: false, error: null });
    
    renderWithProviders(<ExampleComponent />);
    
    expect(screen.getByText('example.title')).toBeInTheDocument();
  });
  
  test('handles user interaction', async () => {
    useApiData.mockReturnValue({ data: [], loading: false, error: null });
    
    renderWithProviders(<ExampleComponent />);
    
    const button = screen.getByText('example.button');
    fireEvent.click(button);
    
    await waitFor(() => {
      expect(screen.getByText('example.result')).toBeInTheDocument();
    });
  });
});
```

## Test Files Created

### Backend Tests (6 files)

1. **InsuranceControllerTest.php** - 11 test methods
   - Authentication testing
   - CRUD operations
   - Authorization boundaries
   - Validation

2. **VehicleControllerTest.php** - 15 test methods
   - Full CRUD coverage
   - User isolation
   - Depreciation calculations
   - Cost summaries

3. **ShopifyAdapterTest.php** - 10 test methods
   - Pattern detection
   - JSON parsing
   - Fallback mechanisms
   - Edge cases

4. **CostCalculatorTest.php** - 18 test methods
   - Cost aggregation
   - Category breakdowns
   - Per-mile calculations
   - Monthly/annual projections

5. **DepreciationCalculatorTest.php** - 18 test methods
   - Multiple depreciation methods
   - Schedule generation
   - Value calculations
   - Mileage adjustments

### Frontend Tests (5 files)

1. **Insurance.test.js** - 9 test methods
   - Data display
   - User interactions
   - Calculations
   - Conditional rendering

2. **InsuranceDialog.test.js** - 11 test methods
   - Form rendering
   - Validation
   - Create/edit modes
   - Error handling

3. **Dashboard.test.js** - 19 test methods
   - Statistics display
   - Charts rendering
   - Reminders
   - Data filtering

4. **Vehicles.test.js** - 20 test methods
   - List display
   - Search/filter
   - Sorting/grouping
   - CRUD operations

## Remaining Work

### Backend Tests Needed

**Controllers (8 remaining):**
- PartController
- ConsumableController
- ServiceRecordController
- MotRecordController
- FuelRecordController
- AttachmentController
- AuthController
- DvsaController
- VehicleMakeController
- VehicleModelController
- VehicleTypeController
- VehicleImportExportController

**Services (5 remaining):**
- DvsaApiService
- ReceiptOcrService
- UrlScraperService
- GenericDomAdapter
- AmazonAdapter
- EbayAdapter

**Entities (13 files):**
- Vehicle
- Part
- Consumable
- ServiceRecord
- MotRecord
- FuelRecord
- Insurance
- Attachment
- User
- VehicleMake
- VehicleModel
- VehicleType
- ConsumableType

### Frontend Tests Needed

**Pages (3 remaining):**
- ServiceRecords.js
- Parts.js
- Consumables.js
- FuelRecords.js
- MotRecords.js
- VehicleDetails.js

**Components (10 remaining):**
- VehicleCard.js
- ServiceRecordDialog.js
- PartDialog.js
- FuelRecordDialog.js
- MotRecordDialog.js
- ConsumableDialog.js
- AttachmentUpload.js
- CostChart.js
- DepreciationChart.js
- FuelEconomyChart.js

## CI/CD Integration

### GitHub Actions Example

```yaml
name: Tests

on: [push, pull_request]

jobs:
  backend:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Run Backend Tests
        run: |
          cd backend
          docker-compose up -d
          docker exec vehicle_php composer install
          docker exec vehicle_php vendor/bin/phpunit --coverage-clover coverage.xml
      - name: Upload Coverage
        uses: codecov/codecov-action@v2
        with:
          file: ./backend/coverage.xml

  frontend:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup Node
        uses: actions/setup-node@v2
        with:
          node-version: '18'
      - name: Run Frontend Tests
        run: |
          cd frontend
          npm ci
          npm test -- --coverage --watchAll=false
      - name: Upload Coverage
        uses: codecov/codecov-action@v2
        with:
          file: ./frontend/coverage/clover.xml
```

## Best Practices

### General
- ✅ **Arrange-Act-Assert**: Structure tests clearly
- ✅ **Isolation**: Each test should be independent
- ✅ **Fast**: Keep tests quick (< 1 second each)
- ✅ **Descriptive**: Test names should explain what's tested
- ✅ **DRY**: Use helper methods for common setups

### Backend
- ✅ Use `setUp()` and `tearDown()` for test initialization
- ✅ Mock external dependencies (HTTP clients, APIs)
- ✅ Test both success and failure scenarios
- ✅ Verify HTTP status codes and response structures
- ✅ Test authorization boundaries

### Frontend
- ✅ Mock API calls and external dependencies
- ✅ Test user interactions, not implementation details
- ✅ Use `waitFor()` for async operations
- ✅ Test accessibility (screen reader support)
- ✅ Clean up mocks in `beforeEach()`

## Troubleshooting

### Backend

**Issue: Tests not found**
```bash
# Ensure autoloader is updated
docker exec vehicle_php composer dump-autoload
```

**Issue: Database errors**
```bash
# Reset test database
docker exec vehicle_php bin/console doctrine:database:drop --force --env=test
docker exec vehicle_php bin/console doctrine:database:create --env=test
docker exec vehicle_php bin/console doctrine:migrations:migrate --no-interaction --env=test
```

**Issue: Permission errors**
```bash
# Fix cache permissions
docker exec vehicle_php chmod -R 777 var/cache var/log
```

### Frontend

**Issue: Tests hang**
```bash
# Run with --forceExit
npm test -- --forceExit --detectOpenHandles
```

**Issue: Module not found**
```bash
# Clear cache and reinstall
rm -rf node_modules package-lock.json
npm install
```

**Issue: Snapshot failures**
```bash
# Update snapshots
npm test -- -u
```

## Coverage Reports

After running tests with coverage, reports are generated in:

- **Backend HTML**: `backend/var/coverage/html/index.html`
- **Backend Clover**: `backend/var/coverage/clover.xml`
- **Frontend HTML**: `frontend/coverage/lcov-report/index.html`
- **Frontend Clover**: `frontend/coverage/clover.xml`

## Continuous Improvement

- 📊 Monitor coverage trends over time
- 🎯 Add tests for new features before implementation (TDD)
- 🔍 Review and refactor tests regularly
- 📝 Update documentation when patterns change
- 🚀 Optimize slow tests
- ✅ Maintain 95%+ coverage on all PRs

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Symfony Testing Guide](https://symfony.com/doc/current/testing.html)
- [Jest Documentation](https://jestjs.io/docs/getting-started)
- [React Testing Library](https://testing-library.com/docs/react-testing-library/intro/)
- [Testing Best Practices](https://testingjavascript.com/)
