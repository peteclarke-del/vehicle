<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class DvsaApiService
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private ?string $apiKey;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        ?string $dvsaApiKey = null
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->apiKey = $dvsaApiKey;
    }

    /**
     * Get MOT history for a vehicle by registration number
     */
    public function getMotHistory(string $registration): ?array
    {
        if (!$this->apiKey) {
            $this->logger->warning('DVSA API key not configured');
            return null;
        }

        try {
            $apiUrl = 'https://beta.check-mot.service.gov.uk/trade/vehicles/mot-tests';
            $response = $this->httpClient->request('GET', $apiUrl, [
                'headers' => [
                    'x-api-key' => $this->apiKey,
                    'Accept' => 'application/json+v6',
                ],
                'query' => [
                    'registration' => strtoupper(str_replace(' ', '', $registration)),
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                return $data;
            }

            return null;
        } catch (\Exception $e) {
            $this->logger->error('DVSA API error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get vehicle details from MOT history data
     */
    public function getVehicleDetails(string $registration): ?array
    {
        $motData = $this->getMotHistory($registration);

        if (!$motData || !isset($motData[0])) {
            return null;
        }

        $vehicle = $motData[0];
        $details = [
            'registration' => $vehicle['registration'] ?? null,
            'make' => $vehicle['make'] ?? null,
            'model' => $vehicle['model'] ?? null,
            'firstUsedDate' => $vehicle['firstUsedDate'] ?? null,
            'fuelType' => $vehicle['fuelType'] ?? null,
            'primaryColour' => $vehicle['primaryColour'] ?? null,
            'vehicleId' => $vehicle['vehicleId'] ?? null,
            'registrationDate' => $vehicle['registrationDate'] ?? null,
            'manufactureDate' => $vehicle['manufactureDate'] ?? null,
            'engineSize' => $vehicle['engineSize'] ?? null,
            'yearOfManufacture' => null,
        ];

        // Extract year from dates
        if (isset($vehicle['manufactureDate'])) {
            $details['yearOfManufacture'] = (int) substr($vehicle['manufactureDate'], 0, 4);
        } elseif (isset($vehicle['firstUsedDate'])) {
            $details['yearOfManufacture'] = (int) substr($vehicle['firstUsedDate'], 0, 4);
        }

        return $details;
    }

    /**
     * Get latest MOT test details
     */
    public function getLatestMotTest(string $registration): ?array
    {
        $motData = $this->getMotHistory($registration);

        if (!$motData || !isset($motData[0]['motTests']) || empty($motData[0]['motTests'])) {
            return null;
        }

        // MOT tests are ordered by date descending
        $latestTest = $motData[0]['motTests'][0];

        return [
            'completedDate' => $latestTest['completedDate'] ?? null,
            'testResult' => $latestTest['testResult'] ?? null,
            'expiryDate' => $latestTest['expiryDate'] ?? null,
            'odometerValue' => $latestTest['odometerValue'] ?? null,
            'odometerUnit' => $latestTest['odometerUnit'] ?? null,
            'motTestNumber' => $latestTest['motTestNumber'] ?? null,
            'rfrAndComments' => $latestTest['rfrAndComments'] ?? [],
        ];
    }

    /**
     * Parse MOT history into a simpler format
     */
    public function parseMotHistory(string $registration): array
    {
        $motData = $this->getMotHistory($registration);

        if (!$motData || !isset($motData[0])) {
            return [];
        }

        $vehicle = $motData[0];
        $motTests = $vehicle['motTests'] ?? [];

        $parsed = [
            'vehicle' => $this->getVehicleDetails($registration),
            'motTests' => [],
        ];

        foreach ($motTests as $test) {
            $parsed['motTests'][] = [
                'completedDate' => $test['completedDate'] ?? null,
                'testResult' => $test['testResult'] ?? null,
                'expiryDate' => $test['expiryDate'] ?? null,
                'odometerValue' => $test['odometerValue'] ?? null,
                'odometerUnit' => $test['odometerUnit'] ?? null,
                'testNumber' => $test['motTestNumber'] ?? null,
                'defects' => count($test['rfrAndComments'] ?? []),
            ];
        }

        return $parsed;
    }
}
