<?php

declare(strict_types=1);

namespace App\Service\VehicleSpecAdapter;

use App\Entity\Specification;
use App\Entity\Vehicle;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * class OpenApiVehicleAdapter
 *
 * OpenAPI adapter for car specifications
 * Fetches detailed car specifications from Open Vehicles API
 * https://api.openvehicles.com/
 */
class OpenApiVehicleAdapter implements VehicleSpecAdapterInterface
{
    /**
     * @var string
     */
    private string $apiKey;

    /**
     * @var string
     */
    private string $baseUrl;

    /**
     * function __construct
     *
     * @param HttpClientInterface $httpClient
     * @param LoggerInterface $logger
     * @param string $openApiKey
     * @param string $baseUrl
     *
     * @return void
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        string $openApiKey = '',
        string $baseUrl = 'https://api.openvehicles.com/v1'
    ) {
        $this->apiKey = $openApiKey;
        $this->baseUrl = $baseUrl;
    }

    /**
     * Map of vehicle types to API endpoints
     */
    private const API_ENDPOINTS = [
        'car' => 'cars',
        'motorcycle' => 'motorcycles',
        'truck' => 'trucks',
        'van' => 'vans',
        'ev' => 'cars', // EVs handled via cars endpoint
    ];

    /**
     * function supports
     *
     * @param string $vehicleType
     * @param Vehicle $vehicle
     *
     * @return bool
     */
    public function supports(string $vehicleType, Vehicle $vehicle): bool
    {
        // OpenAPI covers all main vehicle types
        return isset(self::API_ENDPOINTS[strtolower($vehicleType)]);
    }

    /**
     * function getPriority
     *
     * @return int
     */
    public function getPriority(): int
    {
        // Higher priority than API Ninjas - OpenAPI often has more comprehensive data
        return 90;
    }

    /**
     * function fetchSpecifications
     *
     * @param Vehicle $vehicle
     *
     * @return Specification
     */
    public function fetchSpecifications(Vehicle $vehicle): ?Specification
    {
        $make = $vehicle->getMake();
        $model = $vehicle->getModel();
        $year = $vehicle->getYear();
        $vehicleType = $vehicle->getVehicleType()?->getName() ?? 'car';

        if (!$make || !$model) {
            return null;
        }

        if (empty($this->apiKey)) {
            $this->logger->error('OpenAPI key not configured');
            return null;
        }

        // Get the API endpoint for this vehicle type
        $endpoint = self::API_ENDPOINTS[strtolower($vehicleType)] ?? 'cars';

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

                $apiUrl = $this->baseUrl . '/' . $endpoint . '?' . http_build_query($params);

                $this->logger->info('Fetching specs from OpenAPI', [
                    'endpoint' => $endpoint,
                    'make' => $make,
                    'model' => $modelVariant,
                    'year' => $year,
                ]);

                $response = $this->httpClient->request(
                    'GET',
                    $apiUrl,
                    [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $this->apiKey,
                            'Accept' => 'application/json',
                        ],
                        'timeout' => 10,
                    ]
                );

                $statusCode = $response->getStatusCode();
                if ($statusCode >= 400) {
                    $this->logger->error('OpenAPI returned error', [
                        'status' => $statusCode,
                        'endpoint' => $endpoint,
                        'make' => $make,
                        'model' => $model,
                    ]);
                    continue;
                }

                $data = $response->toArray();

                if (!is_array($data)) {
                    $this->logger->warning('OpenAPI returned non-array response', [
                        'response_type' => gettype($data),
                        'endpoint' => $endpoint,
                        'make' => $make,
                        'model' => $model,
                    ]);
                    continue;
                }

                if (!empty($data) && is_array($data)) {
                    // Handle both single result and array of results
                    $vehicleData = isset($data[0]) && is_array($data[0]) ? $data[0] : $data;

                    $spec = $this->parseApiData($vehicleData, $vehicleType);

                    if ($spec) {
                        $spec->setScrapedAt(new \DateTime());
                        $spec->setSourceUrl($apiUrl);
                        return $spec;
                    }
                }
            }

            // No data found for any variation
            $this->logger->warning('No data found from OpenAPI', [
                'endpoint' => $endpoint,
                'make' => $make,
                'model' => $model,
                'year' => $year,
                'variations_tried' => $modelVariations,
            ]);
            return null;
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch specs from OpenAPI', [
                'error' => $e->getMessage(),
                'endpoint' => $endpoint,
                'make' => $make,
                'model' => $model,
            ]);
            return null;
        }
    }

    /**
     * function generateModelVariations
     *
     * @param string $model
     *
     * @return array
     */
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

        // Remove trim/edition suffixes (Estate, Sport, Touring, Hatchback, Sedan)
        $suffixesToRemove = ['Estate', 'Saloon', 'Sport', 'Touring', 'Hatchback', 'Sedan'];
        foreach ($suffixesToRemove as $suffix) {
            if (stripos($model, $suffix) !== false) {
                $variations[] = trim(str_ireplace($suffix, '', $model));
            }
        }

        return array_unique($variations);
    }

    /**
     * function searchModels
     *
     * Search for available models for a given make.
     * Note: OpenAPI (Open Vehicles API) does not provide a dedicated models
     * search endpoint. This method returns an empty array.
     *
     * @param string $make
     * @param string $model
     *
     * @return array
     */
    public function searchModels(string $make, ?string $model = null): array
    {
        // OpenAPI doesn't have a dedicated models search endpoint
        // Would need to implement differently or return empty array
        return [];
    }

    /**
     * function parseApiData
     *
     * @param array $data
     * @param string $vehicleType
     *
     * @return Specification
     */
    private function parseApiData(array $data, string $vehicleType = 'car'): Specification
    {
        $spec = new Specification();

        // Initialize additional info as empty array
        $additionalInfo = [
            'vehicle_type' => $vehicleType,
        ];

        // Engine specifications
        if (isset($data['engine']['cylinders'])) {
            $spec->setEngineType($data['engine']['cylinders'] . ' cylinders');
        }
        if (isset($data['engine']['displacement'])) {
            $displacement = $data['engine']['displacement'];
            // Handle both numeric and string values
            if (is_numeric($displacement)) {
                $displacement = $displacement . ' L';
            }
            $spec->setDisplacement($displacement);
        }
        if (isset($data['engine']['fuel_type'])) {
            $spec->setFuelSystem($data['engine']['fuel_type']);
        }
        if (isset($data['engine']['power'])) {
            $spec->setPower($data['engine']['power']);
        }
        if (isset($data['engine']['torque'])) {
            $spec->setTorque($data['engine']['torque']);
        }

        // Transmission
        if (isset($data['transmission']['type'])) {
            $spec->setTransmission($data['transmission']['type']);
        }
        if (isset($data['transmission']['gears'])) {
            $spec->setGearbox($data['transmission']['gears'] . ' speed');
        }

        // Drive
        if (isset($data['drivetrain'])) {
            $info = json_decode($spec->getAdditionalInfo() ?? '{}', true);
            $info['drive'] = $data['drivetrain'];
            $spec->setAdditionalInfo(json_encode($info));
        }

        // Performance
        if (isset($data['performance']['top_speed'])) {
            $spec->setTopSpeed($data['performance']['top_speed']);
        }
        if (isset($data['performance']['acceleration'])) {
            $info = json_decode($spec->getAdditionalInfo() ?? '{}', true);
            $info['acceleration_0_60'] = $data['performance']['acceleration'];
            $spec->setAdditionalInfo(json_encode($info));
        }

        // Fuel economy
        if (isset($data['fuel_economy'])) {
            $info = json_decode($spec->getAdditionalInfo() ?? '{}', true);
            $info['fuel_economy'] = $data['fuel_economy'];
            $spec->setAdditionalInfo(json_encode($info));
        }

        // Dimensions
        if (isset($data['dimensions']['wheelbase'])) {
            $spec->setWheelbase($data['dimensions']['wheelbase']);
        }
        if (isset($data['dimensions']['length'])) {
            $info = json_decode($spec->getAdditionalInfo() ?? '{}', true);
            $info['length'] = $data['dimensions']['length'];
            $spec->setAdditionalInfo(json_encode($info));
        }
        if (isset($data['dimensions']['width'])) {
            $info = json_decode($spec->getAdditionalInfo() ?? '{}', true);
            $info['width'] = $data['dimensions']['width'];
            $spec->setAdditionalInfo(json_encode($info));
        }
        if (isset($data['dimensions']['height'])) {
            $info = json_decode($spec->getAdditionalInfo() ?? '{}', true);
            $info['height'] = $data['dimensions']['height'];
            $spec->setAdditionalInfo(json_encode($info));
        }

        // Weight
        if (isset($data['weight']['curb_weight'])) {
            $spec->setWetWeight($data['weight']['curb_weight']);
        }

        // Fuel capacity
        if (isset($data['fuel']['capacity'])) {
            $spec->setFuelCapacity($data['fuel']['capacity']);
        }

        // Store all data in additional info for reference
        $additionalInfo = json_decode($spec->getAdditionalInfo() ?? '{}', true);
        $additionalInfo = array_merge(
            $additionalInfo,
            [
                'make' => $data['make'] ?? null,
                'model' => $data['model'] ?? null,
                'year' => $data['year'] ?? null,
                'class' => $data['class'] ?? null,
                'generation' => $data['generation'] ?? null,
            ]
        );
        $spec->setAdditionalInfo(json_encode(array_filter($additionalInfo)));

        return $spec;
    }
}
