# Vehicle Specification Adapter Pattern

## Overview

The vehicle specification scraping system uses an **adapter pattern** to support multiple data sources and vehicle types. This makes it easy to add new adapters for different APIs, web scraping sources, or vehicle types without modifying the core service.

## Architecture

```
VehicleSpecificationScraperService (Coordinator)
    ├── VehicleSpecAdapterInterface
    │   ├── ApiNinjasMotorcycleAdapter (Priority: 90)
    │   ├── ApiNinjasCarAdapter (Priority: 85)
    │   └── [Future adapters...]
```

### Components

#### 1. VehicleSpecAdapterInterface

Defines the contract for all specification adapters.

**Methods:**
- `supports(string $vehicleType, Vehicle $vehicle): bool` - Check if adapter can handle the vehicle type
- `fetchSpecifications(Vehicle $vehicle): ?Specification` - Fetch specifications for a vehicle
- `searchModels(string $make, ?string $model): array` - Search for available models
- `getPriority(): int` - Determine adapter priority (0-100, higher = checked first)

#### 2. VehicleSpecificationScraperService

Coordinates multiple adapters and attempts them in priority order.

**Features:**
- Registers adapters via dependency injection
- Sorts adapters by priority (highest first)
- Tries each adapter until one succeeds
- Logs all attempts for debugging
- Falls back to next adapter on failure

#### 3. Concrete Adapters

**ApiNinjasMotorcycleAdapter** (Priority: 90)
- API: https://api.api-ninjas.com/v1/motorcycles
- Supports: Motorcycle, Motorbike, Bike
- Data: 30+ fields including engine, transmission, chassis, brakes, dimensions, weight, performance
- Model Search: Yes (via /v1/motorcyclemodels endpoint)

**ApiNinjasCarAdapter** (Priority: 85)
- API: https://api.api-ninjas.com/v1/cars
- Supports: Car, Sedan, SUV, Coupe, Hatchback, Wagon, Convertible
- Data: Engine, transmission, fuel economy, drive type
- Model Search: No (API limitation)

## Configuration

### Environment Variables

```env
# API Ninjas API Key (free tier: 50,000 requests/month)
# Get yours at https://api-ninjas.com/register
API_NINJAS_KEY=your_api_key_here
```

### Service Registration

Adapters are automatically registered in `config/services.yaml`:

```yaml
# Vehicle Specification Adapters
App\Service\VehicleSpecAdapter\ApiNinjasMotorcycleAdapter:
    arguments:
        $apiNinjasKey: '%env(API_NINJAS_KEY)%'
    tags: ['app.vehicle_spec_adapter']

App\Service\VehicleSpecAdapter\ApiNinjasCarAdapter:
    arguments:
        $apiNinjasKey: '%env(API_NINJAS_KEY)%'
    tags: ['app.vehicle_spec_adapter']

# Auto-register adapters with the main service
App\Service\VehicleSpecificationScraperService:
    calls:
        - registerAdapter: ['@App\Service\VehicleSpecAdapter\ApiNinjasMotorcycleAdapter']
        - registerAdapter: ['@App\Service\VehicleSpecAdapter\ApiNinjasCarAdapter']
```

## Adding a New Adapter

### Example: Adding a Truck Adapter

1. **Create the Adapter Class**

```php
<?php

namespace App\Service\VehicleSpecAdapter;

use App\Entity\Specification;
use App\Entity\Vehicle;

class TruckDatabaseAdapter implements VehicleSpecAdapterInterface
{
    public function supports(string $vehicleType, Vehicle $vehicle): bool
    {
        return in_array(strtolower($vehicleType), ['truck', 'van', 'lorry']);
    }

    public function getPriority(): int
    {
        return 80; // Lower than cars and motorcycles
    }

    public function fetchSpecifications(Vehicle $vehicle): ?Specification
    {
        // Your implementation here
        // Could be API call, web scraping, database lookup, etc.
    }

    public function searchModels(string $make, ?string $model = null): array
    {
        // Your implementation here
    }
}
```

2. **Register in services.yaml**

```yaml
App\Service\VehicleSpecAdapter\TruckDatabaseAdapter:
    arguments:
        $apiKey: '%env(TRUCK_API_KEY)%'
    tags: ['app.vehicle_spec_adapter']

App\Service\VehicleSpecificationScraperService:
    calls:
        - registerAdapter: ['@App\Service\VehicleSpecAdapter\ApiNinjasMotorcycleAdapter']
        - registerAdapter: ['@App\Service\VehicleSpecAdapter\ApiNinjasCarAdapter']
        - registerAdapter: ['@App\Service\VehicleSpecAdapter\TruckDatabaseAdapter']
```

3. **Clear Cache**

```bash
docker-compose exec php php bin/console cache:clear
```

## Usage

### From Controller

```php
// Scraping is handled automatically by the SpecificationController
// POST /api/vehicles/{id}/specifications/scrape

$specification = $this->scraperService->scrapeSpecifications($vehicle);
```

### From Frontend

```javascript
// VehicleSpecifications component
const handleScrape = async () => {
  const response = await api.post(`/vehicles/${vehicle.id}/specifications/scrape`);
  // Specifications automatically fetched using best available adapter
};
```

## Adapter Priority

Adapters are tried in priority order (highest first):

| Priority | Adapter | Vehicle Types | Notes |
|----------|---------|---------------|-------|
| 90 | ApiNinjasMotorcycle | Motorcycle, Motorbike, Bike | Most comprehensive motorcycle data |
| 85 | ApiNinjasCar | Car, Sedan, SUV, Coupe, etc. | Good car data, limited fields |
| 80 | [Future adapters] | Trucks, Vans | To be implemented |

## Data Flow

```
User clicks "Scrape Online"
    ↓
SpecificationController::scrapeSpecifications()
    ↓
VehicleSpecificationScraperService::scrapeSpecifications()
    ↓
Try adapters in priority order:
    1. Check if adapter supports vehicle type
    2. Call adapter->fetchSpecifications()
    3. If successful, return result
    4. If failed, try next adapter
    ↓
Return Specification or null
```

## Error Handling

- Each adapter handles its own errors (API timeouts, parsing failures, etc.)
- Service logs warnings for failed adapters but continues to next one
- Only returns error if ALL adapters fail
- Frontend displays appropriate message to user

## Benefits

✅ **Extensibility**: Add new adapters without modifying existing code  
✅ **Flexibility**: Support multiple data sources for same vehicle type  
✅ **Reliability**: Automatic fallback if one source fails  
✅ **Maintainability**: Each adapter is independent and testable  
✅ **Scalability**: Easy to add support for new vehicle types  
✅ **Priority System**: Control which sources are tried first  

## Future Enhancements

Potential adapters to add:

1. **CarfolioCom Adapter** - Web scraping for detailed car specs
2. **NHTSASafetyAdapter** - NHTSA API for safety ratings and recalls
3. **ManufacturerApiAdapter** - Direct manufacturer APIs (Ford, Toyota, etc.)
4. **VehicleDatabaseAdapter** - Local database cache to reduce API calls
5. **WikipediaAdapter** - Fallback source for basic vehicle info
6. **VINDecoderAdapter** - Decode specifications from VIN number

## Testing

Each adapter should have unit tests:

```php
class ApiNinjasMotorcycleAdapterTest extends TestCase
{
    public function testSupportsMotorcycle(): void
    {
        $adapter = new ApiNinjasMotorcycleAdapter($httpClient, $logger, 'test-key');
        $vehicle = new Vehicle();
        
        $this->assertTrue($adapter->supports('Motorcycle', $vehicle));
        $this->assertFalse($adapter->supports('Car', $vehicle));
    }
    
    // More tests...
}
```

## Troubleshooting

### No adapters support my vehicle type

Check vehicle type name matches adapter's `supports()` method. Add logging:

```php
$this->logger->info('Vehicle type', ['type' => $vehicleType]);
```

### All adapters failing

1. Check API keys are configured: `echo $API_NINJAS_KEY`
2. Check network connectivity: `curl https://api.api-ninjas.com`
3. Check logs: `docker-compose logs php`
4. Verify vehicle has make/model set

### Wrong adapter being used

Adjust priority values in adapter's `getPriority()` method.
