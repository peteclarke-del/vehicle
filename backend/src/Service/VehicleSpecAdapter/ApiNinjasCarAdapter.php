<?php

declare(strict_types=1);

namespace App\Service\VehicleSpecAdapter;

use App\Entity\Specification;
use App\Entity\Vehicle;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * API Ninjas adapter for car specifications
 *
 * Fetches detailed car specifications from API Ninjas API
 * https://api-ninjas.com/api/cars
 */
class ApiNinjasCarAdapter implements VehicleSpecAdapterInterface
{
    private string $apiKey;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        string $apiNinjasKey = ''
    ) {
        $this->apiKey = $apiNinjasKey ?: ($_ENV['API_NINJAS_KEY'] ?? '');
    }

    public function supports(string $vehicleType, Vehicle $vehicle): bool
    {
        // Supports most vehicle types except motorcycles/bikes
        $type = strtolower($vehicleType);
        $motorcycleTypes = ['motorcycle', 'motorbike', 'bike'];

        // Support all vehicle types except motorcycles
        return !in_array($type, $motorcycleTypes);
    }

    public function getPriority(): int
    {
        // High priority - API is reliable
        return 85;
    }

    public function fetchSpecifications(Vehicle $vehicle): ?Specification
    {
        $make = $vehicle->getMake();
        $model = $vehicle->getModel();
        $year = $vehicle->getYear();

        if (!$make || !$model) {
            return null;
        }

        if (empty($this->apiKey)) {
            $this->logger->error('API Ninjas API key not configured');
            return null;
        }

        // Generate model variations for better matching
        $modelVariations = $this->generateModelVariations($model);

        try {
            // Try each model variation
            foreach ($modelVariations as $modelVariant) {
                $params = [
                    'make' => $make,
                    'model' => $modelVariant,
                ];

                if ($year) {
                    $params['year'] = (string) $year;
                }

                $apiUrl = 'https://api.api-ninjas.com/v1/cars?' . http_build_query($params);

                $this->logger->info('Fetching car specs from API Ninjas', [
                    'make' => $make,
                    'model' => $modelVariant,
                    'year' => $year
                ]);

                $response = $this->httpClient->request('GET', $apiUrl, [
                    'headers' => [
                        'X-Api-Key' => $this->apiKey
                    ],
                    'timeout' => 10
                ]);

                $data = $response->toArray();

                if (!empty($data)) {
                    // Use the first result
                    $carData = $data[0];

                    $spec = $this->parseApiData($carData);

                    if ($spec) {
                        $spec->setScrapedAt(new \DateTime());
                        $spec->setSourceUrl($apiUrl);
                        return $spec;
                    }
                }
            }

            // No data found for any variation
            $this->logger->warning('No car data found', [
                'make' => $make,
                'model' => $model,
                'year' => $year,
                'variations_tried' => $modelVariations
            ]);
            return null;
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch car specs from API', [
                'error' => $e->getMessage(),
                'make' => $make,
                'model' => $model
            ]);
            return null;
        }
    }

    private function generateModelVariations(string $model): array
    {
        $variations = [$model]; // Original

        // Try just the first word (e.g., "6 SkyActiv Estate" -> "6")
        $words = explode(' ', $model);
        if (count($words) > 1) {
            $variations[] = $words[0];

            // Try first two words (e.g., "6 SkyActiv Estate" -> "6 SkyActiv")
            if (count($words) > 2) {
                $variations[] = implode(' ', array_slice($words, 0, 2));
            }
        }

        // Remove trim/edition suffixes (Estate, Sport, etc.)
        $suffixesToRemove = ['Estate', 'Saloon', 'Sport', 'Touring', 'Hatchback', 'Sedan'];
        foreach ($suffixesToRemove as $suffix) {
            if (stripos($model, $suffix) !== false) {
                $variations[] = trim(str_ireplace($suffix, '', $model));
            }
        }

        return array_unique($variations);
    }

    public function searchModels(string $make, ?string $model = null): array
    {
        // API Ninjas Cars API doesn't have a separate models endpoint
        // Would need to implement differently or return empty array
        return [];
    }

    private function parseApiData(array $data): Specification
    {
        $spec = new Specification();

        // Engine specifications
        if (isset($data['cylinders'])) {
            $spec->setEngineType($data['cylinders'] . ' cylinders');
        }
        if (isset($data['displacement'])) {
            $spec->setDisplacement($data['displacement'] . ' L');
        }
        if (isset($data['fuel_type'])) {
            $spec->setFuelSystem($data['fuel_type']);
        }

        // Transmission
        if (isset($data['transmission'])) {
            $spec->setTransmission($data['transmission']);
        }
        if (isset($data['drive'])) {
            $spec->setAdditionalInfo(json_encode(['drive' => $data['drive']]));
        }

        // Performance
        if (isset($data['combination_mpg'])) {
            $info = json_decode($spec->getAdditionalInfo() ?? '{}', true);
            $info['fuel_economy_combined'] = $data['combination_mpg'] . ' MPG';
            if (isset($data['city_mpg'])) {
                $info['fuel_economy_city'] = $data['city_mpg'] . ' MPG';
            }
            if (isset($data['highway_mpg'])) {
                $info['fuel_economy_highway'] = $data['highway_mpg'] . ' MPG';
            }
            $spec->setAdditionalInfo(json_encode($info));
        }

        // Store all data in additional info for reference
        $additionalInfo = json_decode($spec->getAdditionalInfo() ?? '{}', true);
        $additionalInfo = array_merge($additionalInfo, [
            'make' => $data['make'] ?? null,
            'model' => $data['model'] ?? null,
            'year' => $data['year'] ?? null,
            'class' => $data['class'] ?? null,
        ]);
        $spec->setAdditionalInfo(json_encode(array_filter($additionalInfo)));

        return $spec;
    }
}
