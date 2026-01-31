<?php

declare(strict_types=1);

namespace App\Service\VehicleSpecAdapter;

use App\Entity\Specification;
use App\Entity\Vehicle;

/**
 * Interface for vehicle specification adapters
 *
 * Each adapter handles fetching specifications from a specific data source
 * (API, web scraping, database, etc.)
 */
interface VehicleSpecAdapterInterface
{
    /**
     * Check if this adapter can handle the given vehicle type
     *
     * @param string $vehicleType The vehicle type (e.g., 'Motorcycle', 'Car', 'Truck')
     * @param Vehicle $vehicle The vehicle entity
     *
     * @return bool True if this adapter supports the vehicle type
     */
    public function supports(string $vehicleType, Vehicle $vehicle): bool;

    /**
     * Fetch specifications for a vehicle
     *
     * @param Vehicle $vehicle The vehicle to fetch specifications for
     *
     * @return Specification|null The specification object or null if not found
     */
    public function fetchSpecifications(Vehicle $vehicle): ?Specification;

    /**
     * Search for available models for a given make
     *
     * @param string $make The vehicle manufacturer/brand
     * @param string|null $model Optional model filter
     *
     * @return array<string> List of available model names
     */
    public function searchModels(string $make, ?string $model = null): array;

    /**
     * Get the priority of this adapter (higher = checked first)
     *
     * @return int Priority value (0-100)
     */
    public function getPriority(): int;
}
