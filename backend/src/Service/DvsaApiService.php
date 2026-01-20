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
    private ?string $accessToken = null;
    private int $accessTokenExpiresAt = 0;
    private ?string $tokenUrl = null;
    private ?string $clientId = null;
    private ?string $clientSecret = null;
    private ?string $scope = null;
    private string $apiUrl = 'https://tapi.dvsa.gov.uk/trade/vehicles/mot-tests';

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        ?string $dvsaApiKey = null,
        ?string $dvsaTokenUrl = null,
        ?string $dvsaClientId = null,
        ?string $dvsaClientSecret = null,
        ?string $dvsaScope = null,
        ?string $dvsaApiUrl = null
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->apiKey = $dvsaApiKey;
        $this->tokenUrl = $dvsaTokenUrl;
        $this->clientId = $dvsaClientId;
        $this->clientSecret = $dvsaClientSecret;
        $this->scope = $dvsaScope;
        if (!empty($dvsaApiUrl)) {
            $this->apiUrl = $dvsaApiUrl;
        }
    }

    /**
     * Get MOT history for a vehicle by registration number
     */
    public function getMotHistory(string $registration): ?array
    {
        // Prefer OAuth2 token flow when configured (per DVSA auth docs). If not configured, fall back to x-api-key.
        if (!$this->apiKey && !$this->tokenUrl) {
            $this->logger->warning('DVSA API key and token URL not configured');
            return null;
        }

        try {
            // Build request URL by appending the registration to configured API URL.
            $normalized = strtoupper(str_replace(' ', '', $registration));
            $apiUrl = rtrim($this->apiUrl, '/') . '/' . rawurlencode($normalized);

            // Build headers. Attempt to ensure an access token exists if token endpoint configured.
            $headers = ['Accept' => 'application/json'];

            $token = $this->getAccessToken();
            if ($token) {
                $headers['Authorization'] = 'Bearer ' . $token;
            }

            if ($this->apiKey) {
                $headers['x-api-key'] = $this->apiKey;
            }

            // Log headers and URL we're about to call for diagnostics
            $this->logger->info('DVSA request url: ' . $apiUrl);
            $this->logger->info('DVSA request headers: ' . json_encode($headers));

            $response = $this->httpClient->request('GET', $apiUrl, [
                'headers' => $headers,
            ]);

            // Log response headers for diagnostics
            try {
                $respHeaders = $response->getHeaders(false);
                $this->logger->info('DVSA response headers: ' . json_encode($respHeaders));
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to get DVSA response headers: ' . $e->getMessage());
            }

            $status = $response->getStatusCode();
            $content = $response->getContent(false); // don't throw on non-200

            if ($status === 200) {
                $data = $response->toArray();

                // The history API can return a single vehicle object or an array.
                // Normalize to an array of vehicle objects so callers can always use index 0.
                if (is_array($data) && array_keys($data) !== range(0, count($data) - 1)) {
                    // associative array => single object
                    return [$data];
                }

                return $data;
            }

            $this->logger->warning(sprintf('DVSA API returned status %d for %s: %s', $status, $registration, $content));
            return null;
        } catch (\Exception $e) {
            $this->logger->error('DVSA API exception for ' . $registration . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Ensure we have a valid access token (cached until expiry).
     */
    private function getAccessToken(): ?string
    {
        // Return cached token if still valid
        if ($this->accessToken && time() < $this->accessTokenExpiresAt) {
            return $this->accessToken;
        }

        // Attempt to fetch a token if token endpoint is configured
        $tokenUrl = $this->tokenUrl;
        $clientId = $this->clientId;
        $clientSecret = $this->clientSecret;

        if (empty($tokenUrl) || empty($clientId) || empty($clientSecret)) {
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', $tokenUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'body' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'scope' => $this->scope ?? '',
                ],
            ]);

            $status = $response->getStatusCode();
            $content = $response->getContent(false);

            if ($status !== 200) {
                $this->logger->warning(sprintf('DVSA token endpoint returned %d: %s', $status, $content));
                return null;
            }

            $data = $response->toArray();
            if (empty($data['access_token'])) {
                $this->logger->warning('DVSA token endpoint did not return access_token: ' . $content);
                return null;
            }

            $this->accessToken = $data['access_token'];
            $expiresIn = isset($data['expires_in']) ? (int)$data['expires_in'] : 300;
            // expire a little earlier to be safe
            $this->accessTokenExpiresAt = time() + max(60, $expiresIn - 30);

            $this->logger->info('DVSA access token obtained, expires in ' . $expiresIn . 's');

            return $this->accessToken;
        } catch (\Exception $e) {
            $this->logger->error('DVSA token fetch exception: ' . $e->getMessage());
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
