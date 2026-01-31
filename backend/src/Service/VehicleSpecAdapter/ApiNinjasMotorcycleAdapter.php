<?php

declare(strict_types=1);

namespace App\Service\VehicleSpecAdapter;

use App\Entity\Specification;
use App\Entity\Vehicle;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * API Ninjas adapter for motorcycle specifications
 *
 * Fetches detailed motorcycle specifications from API Ninjas API
 * https://api-ninjas.com/api/motorcycles
 */
class ApiNinjasMotorcycleAdapter implements VehicleSpecAdapterInterface
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
        // Only supports motorcycles
        return in_array(strtolower($vehicleType), ['motorcycle', 'motorbike', 'bike']);
    }

    public function getPriority(): int
    {
        // High priority - API is reliable and comprehensive
        return 90;
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

        // Try multiple variations of make/model names to improve match rate
        $makeVariations = $this->generateMakeVariations($make);
        $modelVariations = $this->generateModelVariations($model);

        try {
            // Try each make variation
            foreach ($makeVariations as $makeVariant) {
                // Try with full model first
                foreach ($modelVariations as $modelVariant) {
                    $spec = $this->tryFetchWithParams($makeVariant, $modelVariant, $year);
                    if ($spec) {
                        return $spec;
                    }
                }

                // If no match with model variations, try without model (get all for make/year)
                if ($year) {
                    $allResults = $this->fetchAllForMakeYear($makeVariant, $year);
                    if (!empty($allResults)) {
                        // Find best match by comparing model names
                        $bestMatch = $this->findBestModelMatch($model, $allResults, $make);
                        if ($bestMatch) {
                            $spec = $this->parseApiData($bestMatch);
                            if ($spec) {
                                $params = ['make' => $makeVariant, 'year' => (string) $year];
                                $spec->setScrapedAt(new \DateTime());
                                $spec->setSourceUrl('https://api.api-ninjas.com/v1/motorcycles?' . http_build_query($params));

                                $this->logger->info('Found best match using fuzzy matching', [
                                    'original_model' => $model,
                                    'matched_model' => $bestMatch['model'] ?? 'unknown',
                                    'similarity_score' => $this->calculateSimilarity($model, $bestMatch['model'] ?? '')
                                ]);

                                return $spec;
                            }
                        }
                    }

                    // Try nearby years (+/- 2 years) if exact year didn't work
                    $nearbyYears = [$year + 1, $year - 1, $year + 2, $year - 2];
                    $this->logger->info('Trying nearby years', [
                        'original_year' => $year,
                        'nearby_years' => $nearbyYears
                    ]);

                    foreach ($nearbyYears as $nearYear) {
                        $allResults = $this->fetchAllForMakeYear($makeVariant, $nearYear);
                        if (!empty($allResults)) {
                            $bestMatch = $this->findBestModelMatch($model, $allResults, $make);
                            if ($bestMatch) {
                                $spec = $this->parseApiData($bestMatch);
                                if ($spec) {
                                    $params = ['make' => $makeVariant, 'year' => (string) $nearYear];
                                    $spec->setScrapedAt(new \DateTime());
                                    $spec->setSourceUrl('https://api.api-ninjas.com/v1/motorcycles?' . http_build_query($params));

                                    $this->logger->info('Found match using nearby year', [
                                        'original_year' => $year,
                                        'matched_year' => $nearYear,
                                        'original_model' => $model,
                                        'matched_model' => $bestMatch['model'] ?? 'unknown'
                                    ]);

                                    return $spec;
                                }
                            }
                        }
                    }
                }
            }

            $this->logger->warning('No motorcycle data found after trying all variations', [
                'original_make' => $make,
                'original_model' => $model,
                'year' => $year,
                'make_variations_tried' => count($makeVariations),
                'model_variations_tried' => count($modelVariations)
            ]);

            return null;
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch motorcycle specs from API', [
                'error' => $e->getMessage(),
                'make' => $make,
                'model' => $model
            ]);
            return null;
        }
    }

    private function generateMakeVariations(string $make): array
    {
        $variations = [$make]; // Original

        // Add hyphenated version (Harley Davidson -> Harley-Davidson)
        if (strpos($make, ' ') !== false) {
            $variations[] = str_replace(' ', '-', $make);
        }

        // Add space version (Harley-Davidson -> Harley Davidson)
        if (strpos($make, '-') !== false) {
            $variations[] = str_replace('-', ' ', $make);
        }

        // Try lowercase versions
        foreach (array_unique($variations) as $variation) {
            $variations[] = strtolower($variation);
        }

        return array_unique($variations);
    }

    private function generateModelVariations(string $model): array
    {
        $variations = [$model]; // Original

        // Try without model code prefix (e.g., "FXLR Low Rider" -> "Low Rider")
        if (preg_match('/^[A-Z0-9]{3,5}\s+(.+)$/', $model, $matches)) {
            $variations[] = $matches[1];
        }

        // Try just the first significant word(s)
        $words = explode(' ', $model);
        if (count($words) > 1) {
            // Try first word only (e.g., "Z1000 JHF R" -> "Z1000")
            $variations[] = $words[0];

            if (count($words) > 2) {
                // Try first two words (e.g., "Z1000 JHF R" -> "Z1000 JHF")
                $variations[] = implode(' ', array_slice($words, 0, 2));
                // Try last two words
                $variations[] = implode(' ', array_slice($words, -2));
            }
        }

        return array_unique($variations);
    }

    private function tryFetchWithParams(?string $make, ?string $model, ?int $year): ?Specification
    {
        if (!$make) {
            return null;
        }

        $params = ['make' => $make];

        if ($model) {
            $params['model'] = $model;
        }

        if ($year) {
            $params['year'] = (string) $year;
        }

        $apiUrl = 'https://api.api-ninjas.com/v1/motorcycles?' . http_build_query($params);

        $this->logger->info('Trying API Ninjas with variation', [
            'make' => $make,
            'model' => $model,
            'year' => $year,
            'url' => $apiUrl
        ]);

        $response = $this->httpClient->request('GET', $apiUrl, [
            'headers' => [
                'X-Api-Key' => $this->apiKey
            ],
            'timeout' => 10
        ]);

        $data = $response->toArray();

        if (empty($data)) {
            return null;
        }

        $this->logger->info('API Ninjas found match', [
            'make' => $make,
            'model' => $model,
            'year' => $year,
            'response_count' => count($data),
            'matched_model' => $data[0]['model'] ?? 'unknown'
        ]);

        // Use the first result (most relevant)
        $motorcycleData = $data[0];

        $spec = $this->parseApiData($motorcycleData);

        if ($spec) {
            $spec->setScrapedAt(new \DateTime());
            $spec->setSourceUrl($apiUrl);
        }

        return $spec;
    }

    private function fetchAllForMakeYear(string $make, int $year): array
    {
        try {
            $params = ['make' => $make, 'year' => (string) $year];
            $apiUrl = 'https://api.api-ninjas.com/v1/motorcycles?' . http_build_query($params);

            $response = $this->httpClient->request('GET', $apiUrl, [
                'headers' => ['X-Api-Key' => $this->apiKey],
                'timeout' => 10
            ]);

            return $response->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function findBestModelMatch(string $targetModel, array $results, string $expectedMake): ?array
    {
        if (empty($results)) {
            return null;
        }

        $bestMatch = null;
        $bestScore = 0;

        foreach ($results as $result) {
            $apiModel = $result['model'] ?? '';
            $apiMake = $result['make'] ?? '';

            // Skip if make doesn't match (filters out bad API data like "620 Duke" listed under Kawasaki)
            if (stripos(strtolower($apiMake), strtolower($expectedMake)) === false) {
                $this->logger->debug('Skipping result due to make mismatch', [
                    'api_make' => $apiMake,
                    'expected_make' => $expectedMake,
                    'model' => $apiModel
                ]);
                continue;
            }

            $similarity = $this->calculateSimilarity($targetModel, $apiModel);

            $this->logger->debug('Comparing models', [
                'target' => $targetModel,
                'api_model' => $apiModel,
                'similarity' => $similarity
            ]);

            if ($similarity > $bestScore) {
                $bestScore = $similarity;
                $bestMatch = $result;
            }
        }

        $this->logger->info('Best match result: score=' . $bestScore . ', found=' . ($bestScore >= 40 ? 'yes' : 'no') . ', model=' . ($bestMatch['model'] ?? 'none'));

        // Only return if similarity is above threshold (40% - lowered for better matches)
        if ($bestScore >= 40) {
            return $bestMatch;
        }

        return null;
    }

    private function calculateSimilarity(string $str1, string $str2): float
    {
        // Normalize strings
        $str1Original = $str1;
        $str2Original = $str2;
        $str1 = strtolower(trim($str1));
        $str2 = strtolower(trim($str2));

        // Extract numbers from both strings for comparison
        preg_match_all('/\d+/', $str1, $matches1);
        preg_match_all('/\d+/', $str2, $matches2);
        $numbers1 = $matches1[0] ?? [];
        $numbers2 = $matches2[0] ?? [];

        // If both have numbers, check if they match
        // Heavily penalize number mismatches (e.g., ZX6R vs ZX9R)
        if (!empty($numbers1) && !empty($numbers2)) {
            $commonNumbers = array_intersect($numbers1, $numbers2);
            if (empty($commonNumbers)) {
                // No common numbers - this is likely wrong (ZX6 vs ZX9)
                return 0.0;
            }
        }

        // Remove common separators and spaces
        $str1 = preg_replace('/[\s\-]+/', '', $str1);
        $str2 = preg_replace('/[\s\-]+/', '', $str2);

        // Check for exact match after normalization
        if ($str1 === $str2) {
            return 100.0;
        }

        // Check if one contains the other
        if (strpos($str1, $str2) !== false || strpos($str2, $str1) !== false) {
            return 90.0;
        }

        // Calculate Levenshtein distance
        $maxLen = max(strlen($str1), strlen($str2));
        if ($maxLen === 0) {
            return 0.0;
        }

        $distance = levenshtein($str1, $str2);
        $similarity = (1 - ($distance / $maxLen)) * 100;

        return max(0.0, $similarity);
    }

    public function searchModels(string $make, ?string $model = null): array
    {
        try {
            if (empty($this->apiKey)) {
                $this->logger->error('API Ninjas API key not configured');
                return [];
            }

            $apiUrl = 'https://api.api-ninjas.com/v1/motorcyclemodels?make=' . urlencode($make);

            $response = $this->httpClient->request('GET', $apiUrl, [
                'headers' => [
                    'X-Api-Key' => $this->apiKey
                ],
                'timeout' => 10
            ]);

            $models = $response->toArray();

            // Filter by model name if provided
            if ($model) {
                $models = array_filter($models, function ($modelName) use ($model) {
                    return stripos($modelName, $model) !== false;
                });
            }

            return array_values($models);
        } catch (\Exception $e) {
            $this->logger->error('Failed to search models', [
                'error' => $e->getMessage(),
                'make' => $make
            ]);
            return [];
        }
    }

    private function parseApiData(array $data): Specification
    {
        $spec = new Specification();

        // Extract engine specifications
        if (isset($data['engine'])) {
            $spec->setEngineType($data['engine']);
        }
        if (isset($data['displacement'])) {
            $spec->setDisplacement($data['displacement']);
        }
        if (isset($data['power'])) {
            $spec->setPower($data['power']);
        }
        if (isset($data['torque'])) {
            $spec->setTorque($data['torque']);
        }
        if (isset($data['compression'])) {
            $spec->setCompression($data['compression']);
        }
        if (isset($data['bore_stroke'])) {
            $spec->setBore($data['bore_stroke']);
        }
        if (isset($data['fuel_system'])) {
            $spec->setFuelSystem($data['fuel_system']);
        }
        if (isset($data['cooling'])) {
            $spec->setCooling($data['cooling']);
        }

        // Transmission
        if (isset($data['gearbox'])) {
            $spec->setGearbox($data['gearbox']);
        }
        if (isset($data['transmission'])) {
            $spec->setTransmission($data['transmission']);
        }
        if (isset($data['clutch'])) {
            $spec->setClutch($data['clutch']);
        }

        // Chassis
        if (isset($data['frame'])) {
            $spec->setFrame($data['frame']);
        }
        if (isset($data['front_suspension'])) {
            $spec->setFrontSuspension($data['front_suspension']);
        }
        if (isset($data['rear_suspension'])) {
            $spec->setRearSuspension($data['rear_suspension']);
        }

        // Brakes
        if (isset($data['front_brakes'])) {
            $spec->setFrontBrakes($data['front_brakes']);
        }
        if (isset($data['rear_brakes'])) {
            $spec->setRearBrakes($data['rear_brakes']);
        }

        // Wheels and Tires
        if (isset($data['front_tire'])) {
            $spec->setFrontTyre($data['front_tire']);
        }
        if (isset($data['rear_tire'])) {
            $spec->setRearTyre($data['rear_tire']);
        }
        if (isset($data['front_wheel_travel'])) {
            $spec->setFrontWheelTravel($data['front_wheel_travel']);
        }
        if (isset($data['rear_wheel_travel'])) {
            $spec->setRearWheelTravel($data['rear_wheel_travel']);
        }

        // Dimensions
        if (isset($data['wheelbase'])) {
            $spec->setWheelbase($data['wheelbase']);
        }
        if (isset($data['seat_height'])) {
            $spec->setSeatHeight($data['seat_height']);
        }
        if (isset($data['ground_clearance'])) {
            $spec->setGroundClearance($data['ground_clearance']);
        }

        // Weight
        if (isset($data['dry_weight'])) {
            $spec->setDryWeight($data['dry_weight']);
        }
        if (isset($data['wet_weight'])) {
            $spec->setWetWeight($data['wet_weight']);
        }
        if (isset($data['fuel_capacity'])) {
            $spec->setFuelCapacity($data['fuel_capacity']);
        }

        // Performance
        if (isset($data['top_speed'])) {
            $spec->setTopSpeed($data['top_speed']);
        }

        // Store additional info (only type/category, not make/model/year)
        $additionalInfo = [];
        if (isset($data['type'])) {
            $additionalInfo['category'] = $data['type'];
        }
        // Store the matched API model name for reference
        if (isset($data['model'])) {
            $additionalInfo['api_model_name'] = $data['model'];
        }
        if (!empty($additionalInfo)) {
            $spec->setAdditionalInfo(json_encode($additionalInfo));
        }

        return $spec;
    }
}
