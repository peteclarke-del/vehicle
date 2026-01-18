<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Specification;
use App\Entity\Vehicle;
use App\Service\VehicleSpecAdapter\VehicleSpecAdapterInterface;
use Psr\Log\LoggerInterface;

/**
 * Vehicle Specification Scraper Service
 * 
 * Coordinates multiple adapters to fetch vehicle specifications from various sources.
 * Uses adapter pattern to support different vehicle types and data sources.
 * 
 * Supported vehicle types:
 * - Motorcycles: API Ninjas Motorcycles API
 * - Cars: API Ninjas Cars API
 * - Trucks/Vans: To be implemented
 */
class VehicleSpecificationScraperService
{
    /** @var array<VehicleSpecAdapterInterface> */
    private array $adapters = [];

    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    /**
     * Register an adapter for vehicle specification fetching
     */
    public function registerAdapter(VehicleSpecAdapterInterface $adapter): void
    {
        $this->adapters[] = $adapter;
        
        // Sort by priority (highest first)
        usort($this->adapters, function ($a, $b) {
            return $b->getPriority() <=> $a->getPriority();
        });
    }

    /**
     * Scrape specifications for a vehicle using registered adapters
     */
    public function scrapeSpecifications(Vehicle $vehicle): ?Specification
    {
        $vehicleType = $vehicle->getVehicleType()?->getName() ?? 'Unknown';
        
        $this->logger->info('Attempting to scrape specifications', [
            'vehicle_id' => $vehicle->getId(),
            'vehicle_type' => $vehicleType,
            'make' => $vehicle->getMake(),
            'model' => $vehicle->getModel(),
            'year' => $vehicle->getYear(),
            'adapter_count' => count($this->adapters)
        ]);

        if (empty($this->adapters)) {
            $this->logger->error('No adapters registered!');
            return null;
        }

        // Try each adapter in priority order
        foreach ($this->adapters as $adapter) {
            $adapterClass = get_class($adapter);
            $supports = $adapter->supports($vehicleType, $vehicle);
            
            $this->logger->info('Checking adapter', [
                'adapter' => $adapterClass,
                'priority' => $adapter->getPriority(),
                'supports' => $supports,
                'vehicle_type' => $vehicleType
            ]);
            
            if (!$supports) {
                continue;
            }

            $this->logger->info('Using adapter', [
                'adapter' => $adapterClass,
                'priority' => $adapter->getPriority()
            ]);

            try {
                $spec = $adapter->fetchSpecifications($vehicle);
                
                if ($spec !== null) {
                    $this->logger->info('Successfully fetched specifications', [
                        'adapter' => $adapterClass,
                        'vehicle_id' => $vehicle->getId()
                    ]);
                    return $spec;
                }
                
                $this->logger->warning('Adapter returned null', [
                    'adapter' => $adapterClass
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Adapter failed with exception', [
                    'adapter' => $adapterClass,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                // Continue to next adapter
            }
        }

        $this->logger->warning('No adapter could fetch specifications', [
            'vehicle_id' => $vehicle->getId(),
            'vehicle_type' => $vehicleType
        ]);

        return null;
    }

    /**
     * Search for available models using registered adapters
     */
    public function searchAvailableModels(Vehicle $vehicle, string $make, ?string $model = null): array
    {
        $vehicleType = $vehicle->getVehicleType()?->getName() ?? 'Unknown';

        foreach ($this->adapters as $adapter) {
            if (!$adapter->supports($vehicleType, $vehicle)) {
                continue;
            }

            try {
                $models = $adapter->searchModels($make, $model);
                
                if (!empty($models)) {
                    $this->logger->info('Found models', [
                        'adapter' => get_class($adapter),
                        'make' => $make,
                        'count' => count($models)
                    ]);
                    return $models;
                }
            } catch (\Exception $e) {
                $this->logger->warning('Model search failed', [
                    'adapter' => get_class($adapter),
                    'error' => $e->getMessage()
                ]);
                // Continue to next adapter
            }
        }

        return [];
    }
}
