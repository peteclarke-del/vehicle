# Test Infrastructure Issues and Recommendations

## Summary
As of 31 January 2026, the test suite has significant infrastructure issues that need addressing:

**Test Results:**
- Total Tests: 473
- Passing: 236
- Errors: 163
- Failures: 74
- Risky: 1

## Critical Issues

### 1. WebTestCase Kernel Booting Error (163 errors)
**Issue:** Tests fail with `LogicException: Booting the kernel before calling "Symfony\Bundle\FrameworkBundle\Test\WebTestCase::createClient()" is not supported, the kernel should only be booted once.`

**Affected Files:**
- `tests/Controller/DvsaControllerTest.php`
- `tests/Controller/FuelRecordControllerTest.php`
- `tests/Controller/ServiceRecordControllerTest.php`
- `tests/Controller/VehicleMakeControllerTest.php`
- `tests/Controller/MotRecordControllerTest.php`
- `tests/Controller/VehicleControllerTest.php`
- `tests/Controller/VehicleImportExportControllerTest.php`
- `tests/Controller/InsuranceControllerTest.php`
- `tests/Controller/AttachmentControllerTest.php`
- `tests/Controller/ConsumableControllerTest.php`
- `tests/Controller/RoadTaxControllerTest.php`

**Root Cause:**
Tests call `static::createClient()` in `setUp()` method AND in individual test methods, causing kernel to boot twice.

**Example Problem Code:**
```php
class DvsaControllerTest extends WebTestCase
{
    private string $token;

    protected function setUp(): void
    {
        $client = static::createClient(); // Boots kernel here
        $this->token = $this->getAuthToken($client);
    }

    public function testGetMotHistoryRequiresAuthentication(): void
    {
        $client = static::createClient(); // Tries to boot kernel again - ERROR!
        $client->request('GET', '/api/dvsa/mot-history/AB12CDE');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
```

**Recommended Fix:**
Remove `setUp()` method and create fresh client in each test for better isolation:

```php
class DvsaControllerTest extends WebTestCase
{
    private function getAuthToken(): string
    {
        $client = static::createClient();
        $client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'test@example.com',
            'password' => 'password123'
        ]));

        $response = json_decode($client->getResponse()->getContent(), true);
        return $response['token'] ?? '';
    }

    public function testGetMotHistoryRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/dvsa/mot-history/AB12CDE');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testGetMotHistoryForRegistration(): void
    {
        $token = $this->getAuthToken();
        $client = static::createClient();
        $client->request('GET', '/api/dvsa/mot-history/AB12CDE', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);
        $this->assertResponseIsSuccessful();
    }
}
```

### 2. Missing Test Classes (3 warnings)
**Issue:** Test files exist but classes cannot be found.

**Affected Files:**
- `tests/Controller/AuthControllerTest.php`
- `tests/Controller/ConsumableControllerTest.php`
- `tests/Entity/ConsumableTest.php`

**Recommended Action:** Check if files are empty or have wrong class names.

### 3. Test Failures (74 failures)
**Issue:** Various test assertions failing, likely due to:
- Test data not properly set up (e.g., "Vehicle not found")
- Outdated assertions
- Changes to application logic not reflected in tests

**Recommended Action:** Review each failure after fixing the kernel booting issues.

### 4. Risky Test (1)
**Issue:** `VehicleImportExportControllerTest::testExportIncludesRelatedData` performs no assertions.

**Recommended Action:** Add proper assertions or mark test as incomplete.

## Priority Order

1. **HIGH PRIORITY:** Fix WebTestCase kernel booting issues (163 errors)
   - Impact: Blocks 11 test files from running
   - Effort: Medium (requires updating setUp() in 11 files)
   - Benefit: Will reduce error count significantly

2. **MEDIUM PRIORITY:** Investigate missing test classes (3 warnings)
   - Impact: 3 test files not executing
   - Effort: Low (likely just missing namespace declaration)

3. **MEDIUM PRIORITY:** Fix test failures (74 failures)
   - Impact: Tests running but not passing
   - Effort: Variable (depends on failure type)
   - Benefit: Ensures application behavior is correct

4. **LOW PRIORITY:** Fix risky test (1)
   - Impact: Minimal
   - Effort: Low (add assertions or mark incomplete)

## Next Steps

1. Update all WebTestCase-based tests to remove `setUp()` method
2. Verify missing test class files
3. Run full test suite again
4. Fix remaining failures one by one
5. Add missing test coverage where needed

## Notes

- All Entity tests are passing (153/153) ✓
- Service layer tests are passing
- Main issue is Controller integration tests
- Code quality is good (PSR-12 compliant, no console.log statements)
