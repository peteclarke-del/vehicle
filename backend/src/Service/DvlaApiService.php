<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Service\DvlaBusyException;

class DvlaApiService
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private ?string $authUrl;
    private ?string $clientId;
    private ?string $clientSecret;
    private ?string $dvlaApiKey;

    private ?string $accessToken = null;
    private ?int $tokenExpiry = null;
    private string $vehicleBaseUrl;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        ?string $authUrl = null,
        ?string $clientId = null,
        ?string $clientSecret = null,
        ?string $dvlaApiKey = null
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->authUrl = $authUrl;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->dvlaApiKey = $dvlaApiKey;

        if (empty($this->authUrl)) {
            $this->authUrl = $_ENV['DVLA_AUTH_URL'] ?? getenv('DVLA_AUTH_URL') ?: null;
        }
        if (empty($this->clientId)) {
            $this->clientId = $_ENV['DVLA_CLIENT_ID'] ?? getenv('DVLA_CLIENT_ID') ?: null;
        }
        if (empty($this->clientSecret)) {
            $this->clientSecret = $_ENV['DVLA_CLIENT_SECRET'] ?? getenv('DVLA_CLIENT_SECRET') ?: null;
        }
        if (empty($this->dvlaApiKey)) {
            $this->dvlaApiKey = $_ENV['DVLA_API_KEY'] ?? getenv('DVLA_API_KEY') ?: null;
        }

        $this->vehicleBaseUrl = $_ENV['DVLA_VEHICLE_URL'] ?? getenv('DVLA_VEHICLE_URL') ?:
            'https://driver-vehicle-licensing.api.gov.uk/vehicle-enquiry/v1/vehicles';
    }

    private function fetchAccessToken(): ?string
    {
        if ($this->accessToken && $this->tokenExpiry && time() < $this->tokenExpiry) {
            return $this->accessToken;
        }

        if (!$this->authUrl || !$this->clientId || !$this->clientSecret) {
            $this->logger->warning('DVLA client credentials not configured; skipping token fetch');
            return null;
        }

        try {
            $this->logger->debug('DVLA auth request', ['url' => $this->authUrl]);
            $resp = $this->httpClient->request('POST', $this->authUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ]),
                'timeout' => 10,
            ]);

            if ($resp->getStatusCode() !== 200) {
                try {
                    $body = $resp->getContent(false);
                } catch (\Throwable $e) {
                    $body = 'unable to read response body: ' . $e->getMessage();
                }
                $this->logger->error('DVLA auth failed', ['status' => $resp->getStatusCode(), 'body' => $body]);
                return null;
            }

            $data = $resp->toArray(false);
            $this->accessToken = $data['access_token'] ?? null;
            $expiresIn = isset($data['expires_in']) ? (int) $data['expires_in'] : 300;
            $this->tokenExpiry = time() + max(60, $expiresIn - 30);

            return $this->accessToken;
        } catch (\Exception $e) {
            $this->logger->error('DVLA auth exception: ' . $e->getMessage());
            return null;
        }
    }

    public function getVehicleByRegistration(string $registration): ?array
    {
        $token = null;
        $useApiKey = false;

        if (!empty($this->dvlaApiKey)) {
            $useApiKey = true;
            $this->logger->info('Using DVLA API key flow for vehicle lookup');
        } elseif (!empty($this->authUrl) && !empty($this->clientId) && !empty($this->clientSecret)) {
            $this->logger->info('Using DVLA token flow for vehicle lookup');
            $token = $this->fetchAccessToken();
            if (!$token) {
                $this->logger->error('Unable to obtain DVLA access token');
                return null;
            }
        } else {
            $this->logger->error('No DVLA authentication configured (no API key, no client credentials)');
            throw new \RuntimeException('DVLA authentication not configured');
        }

        $normalized = strtoupper(str_replace(' ', '', $registration));
        // Respect whatever form `DVLA_VEHICLE_URL` is set to. If it already contains
        // the trailing `/vehicles` path, don't append another segment.
        $url = rtrim($this->vehicleBaseUrl, '/');

        try {
            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ];
            if ($useApiKey) {
                $headers['x-api-key'] = $this->dvlaApiKey;
            } else {
                $headers['Authorization'] = 'Bearer ' . $token;
            }

            $this->logger->debug(
                'DVLA outgoing request',
                [
                    'method' => 'POST',
                    'url' => $url,
                    'headers' => $headers,
                    'body' => ['registrationNumber' => $normalized],
                ]
            );

            $maxRetries = 3; // number of retries on 429
            $attempt = 0;
            while (true) {
                $attempt++;
                $resp = $this->httpClient->request('POST', $url, [
                    'headers' => $headers,
                    'body' => json_encode(['registrationNumber' => $normalized]),
                    'timeout' => 10,
                ]);

                $status = $resp->getStatusCode();

                try {
                    $rawBody = $resp->getContent(false);
                } catch (\Throwable $e) {
                    $rawBody = 'unable to read response body: ' . $e->getMessage();
                }

                $this->logger->debug('DVLA vehicle lookup response', ['status' => $status, 'body' => $rawBody, 'attempt' => $attempt]);

                if ($status === 200) {
                    return $resp->toArray(false);
                }

                // If rate limited (429) or gateway timeout (504), retry with backoff
                if ($status === 429 || $status === 504) {
                    if ($attempt > $maxRetries) {
                        $this->logger->warning(
                            'DVLA rate limit/504 exceeded after retries',
                            ['registration' => $normalized]
                        );
                        throw new DvlaBusyException('DVLA rate limit or gateway timeout exceeded');
                    }

                    $backoff = (int) pow(2, $attempt - 1);
                    $this->logger->info(
                        'DVLA rate limited; backing off',
                        ['seconds' => $backoff, 'attemp' => $attempt]
                    );
                    sleep($backoff);
                    continue;
                }

                // Handle API key 403 -> try token flow (existing behaviour)
                if ($useApiKey && $status === 403 && $this->authUrl && $this->clientId && $this->clientSecret) {
                    $this->logger->info('DVLA returned 403; attempting token flow and retry');
                    $token = $this->fetchAccessToken();
                    if ($token) {
                        $headers = $headers; // keep same headers variable
                        $headers['Authorization'] = 'Bearer ' . $token;
                        // retry once with token
                        try {
                            $retryResp = $this->httpClient->request('POST', $url, [
                                'headers' => $headers,
                                'body' => json_encode(['registrationNumber' => $normalized]),
                                'timeout' => 10,
                            ]);

                            if ($retryResp->getStatusCode() === 200) {
                                return $retryResp->toArray(false);
                            }

                            try {
                                $retryRaw = $retryResp->getContent(false);
                            } catch (\Throwable $e) {
                                $retryRaw = 'unable to read response body: ' . $e->getMessage();
                            }

                            $this->logger->warning('DVLA vehicle lookup retry failed', ['status' => $retryResp->getStatusCode(), 'body' => $retryRaw]);
                        } catch (\Exception $e) {
                            $this->logger->error('DVLA vehicle retry exception: ' . $e->getMessage());
                        }
                    }
                }

                // Other non-200 responses - don't retry
                $this->logger->warning('DVLA vehicle lookup returned non-200', ['status' => $status, 'body' => $rawBody]);
                return null;
            }
        } catch (DvlaBusyException $e) {
            // Bubble up to caller to handle user-facing message
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('DVLA vehicle lookup failed: ' . $e->getMessage());
            return null;
        }
    }
}
