# Import/Export Refactoring Notes

## Progress

### Completed
- ✅ Created ImportExportConfig with configuration centralization
- ✅ Created custom exceptions (ImportException, ExportException)
- ✅ Created EntityHydratorTrait with common utilities
- ✅ Created DTOs (ImportResult, ExportResult)
- ✅ Created VehicleExportService (fully implemented - 850+ lines)
- ✅ Created VehicleImportService (helper methods complete, needs full implementation)
- ✅ Registered services in services.yaml
- ✅ Injected services into controller constructor

### In Progress
- ⏳ Refactor controller to delegate to services

### Pending
- ❌ Complete VehicleImportService implementation (needs ~2000+ lines of import logic)
- ❌ Refactor export() method to delegate
- ❌ Refactor import() method to delegate
- ❌ Refactor exportZip() method to delegate
- ❌ Refactor importZip() method to delegate
- ❌ Add /api/vehicles/import/validate endpoint
- ❌ Add /api/vehicles/import/template/{format} endpoint
- ❌ Update/create unit tests
- ❌ Test with user's export file
- ❌ Remove old code from controller

## Controller Refactoring Template

### Before (Current - 700+ lines of export logic):
```php
public function export(Request $request, EntityManagerInterface $entityManager, LoggerInterface $logger, ?string $zipDir = null): Response
{
    try {
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);
        $user = $this->getUserEntity();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }
        
        $includeAttachmentRefs = $request->query->getBoolean('includeAttachmentRefs', false);
        
        // ... 700+ lines of business logic ...
        
        return new JsonResponse(['vehicles' => $data]);
    } catch (\Exception $e) {
        $logger->error('Export failed', ['exception' => $e->getMessage()]);
        return new JsonResponse(['error' => 'Export failed: ' . $e->getMessage()], 500);
    }
}
```

### After (Refactored - ~20 lines):
```php
public function export(Request $request, ?string $zipDir = null): Response
{
    try {
        $user = $this->getUserEntity();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }
        
        $includeAttachmentRefs = $request->query->getBoolean('includeAttachmentRefs', false);
        $isAdmin = $this->isAdminForUser($user);
        
        $result = $this->exportService->exportVehicles(
            $user,
            $isAdmin,
            $includeAttachmentRefs,
            $zipDir
        );
        
        if (!$result->isSuccess()) {
            return new JsonResponse(['error' => $result->getMessage()], 500);
        }
        
        return new JsonResponse(['vehicles' => $result->getData()]);
    } catch (\Exception $e) {
        $this->logger->error('Export failed', ['exception' => $e->getMessage()]);
        return new JsonResponse(['error' => 'Export failed: ' . $e->getMessage()], 500);
    }
}
```

## Import Method Refactoring Template

### Before (Current - 2300+ lines):
```php
public function import(Request $request, EntityManagerInterface $entityManager, LoggerInterface $logger, TagAwareCacheInterface $cache, ?string $zipExtractDir = null): JsonResponse
{
    // ... 2300+ lines of business logic ...
}
```

### After (Refactored - ~30 lines):
```php
public function import(Request $request, ?string $zipExtractDir = null): JsonResponse
{
    try {
        $user = $this->getUserEntity();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }
        
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON data'], 400);
        }
        
        $dryRun = $request->query->getBoolean('dryRun', false);
        
        $result = $this->importService->importVehicles(
            $data,
            $user,
            $zipExtractDir,
            $dryRun
        );
        
        if (!$result->isSuccess()) {
            return new JsonResponse([
                'success' => false,
                'errors' => $result->getErrors(),
                'message' => $result->getMessage()
            ], 400);
        }
        
        return new JsonResponse($result->toArray());
    } catch (\Exception $e) {
        $this->logger->error('Import failed', ['exception' => $e->getMessage()]);
        return new JsonResponse(['error' => 'Import failed: ' . $e->getMessage()], 500);
    }
}
```

## Next Steps

1. **Complete VehicleImportService** - Extract remaining ~2000 lines from controller
   - Vehicle creation/update logic
   - Part import (4 contexts: standalone, service items, MOT items, MOT service items)
   - Consumable import (4 contexts)
   - Service record import
   - MOT record import
   - Fuel/insurance/tax import
   - Specification/todo/attachment import

2. **Apply Controller Refactoring**
   - Replace export() method body (line 308-1016)
   - Replace import() method body (line 1440-3685)
   - Replace exportZip() method (line 1028-1181)
   - Replace importZip() method (line 1193-1425)
   - Remove helper methods that are now in services

3. **Add New Endpoints**
   ```php
   #[Route('/import/validate', name: 'vehicles_import_validate', methods: ['POST'])]
   public function validateImport(Request $request): JsonResponse
   {
       $user = $this->getUserEntity();
       if (!$user) {
           return new JsonResponse(['error' => 'Unauthorized'], 401);
       }
       
       $data = json_decode($request->getContent(), true);
       
       $result = $this->importService->importVehicles($data, $user, null, true);
       
       return new JsonResponse($result->toArray());
   }
   
   #[Route('/import/template/{format}', name: 'vehicles_import_template', methods: ['GET'])]
   public function downloadTemplate(string $format): Response
   {
       // Generate sample template
       $template = [
           [
               'name' => 'Example Vehicle',
               'registrationNumber' => 'ABC123',
               'make' => 'Toyota',
               'model' => 'Corolla',
               'year' => 2020,
               'fuelType' => 'Petrol',
               // ... more fields ...
           ]
       ];
       
       return new JsonResponse($template);
   }
   ```

4. **Testing**
   - Create VehicleExportServiceTest
   - Create VehicleImportServiceTest
   - Update controller tests to mock services
   - Test with user's export file

5. **Final Cleanup**
   - Remove commented code
   - Update documentation
   - Final commit and merge

## Benefits of Refactoring

- **Testability**: Services can be unit tested independently
- **Maintainability**: ~3700 lines → ~300 lines in controller
- **Reusability**: Services can be used in CLI commands, async jobs, etc.
- **Single Responsibility**: Controller handles HTTP, services handle business logic
- **Performance**: Memory cleanup and batch processing centralized
- **Configuration**: Externalized to ImportExportConfig
- **Error Handling**: Custom exceptions with proper context
- **Code Duplication**: Reduced via EntityHydratorTrait
