<?php

declare(strict_types=1);

namespace App\Service\VehicleSpecAdapter;

use App\Entity\Specification;
use App\Entity\Vehicle;
use App\Service\DvlaApiService;
use Psr\Log\LoggerInterface;

/**
 * Adapter that sources specifications from DVLA vehicle-enquiry data.
 */
class DvlaAdapter implements VehicleSpecAdapterInterface
{
    /**
     * @param DvlaApiService $dvlaService
     * @param LoggerInterface $logger
     */
    public function __construct(
        private DvlaApiService $dvlaService,
        private LoggerInterface $logger
    ) {
    }

    public function supports(string $vehicleType, Vehicle $vehicle): bool
    {
        // Support scraping when we have a registration (we can query DVLA)
        return (bool) $vehicle->getRegistrationNumber();
    }

    public function getPriority(): int
    {
        // Highest priority so DVLA is preferred over other adapters
        return 100;
    }

    /**
     * Search for models - DVLA doesn't expose a models endpoint, so return empty.
     *
     * @param string $make
     * @param string|null $model
     * @return array<string>
     */
    public function searchModels(string $make, ?string $model = null): array
    {
        return [];
    }

    public function fetchSpecifications(Vehicle $vehicle): ?Specification
    {
        $reg = $vehicle->getRegistrationNumber();
        if (!$reg) {
            return null;
        }

        $this->logger->info(
            'DvlaAdapter: fetching DVLA data for registration',
            ['registration' => $reg]
        );

        $data = $this->dvlaService->getVehicleByRegistration($reg);
        if (!$data) {
            $this->logger->warning(
                'DvlaAdapter: no data returned from DVLA',
                ['registration' => $reg]
            );
            return null;
        }

        $spec = new Specification();

        // Populate spec fields where sensible
        if (!empty($data['engineCapacity'])) {
            // DVLA returns cc - store as L where appropriate
            $spec->setDisplacement((string) $data['engineCapacity'] . ' cc');
        }

        if (!empty($data['enginePower'])) {
            $spec->setPower((string) $data['enginePower']);
        }

        if (!empty($data['fuelType'])) {
            $spec->setFuelSystem((string) $data['fuelType']);
        }

        if (!empty($data['transmission'])) {
            $spec->setTransmission((string) $data['transmission']);
        }

        // Add raw DVLA payload into additionalInfo for reference
        try {
            $additional = json_encode($data, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $additional = var_export($data, true);
        }
        $spec->setAdditionalInfo($additional);
        $spec->setScrapedAt(new \DateTime());
        $spec->setSourceUrl('dvla');

        return $spec;
    }
}
