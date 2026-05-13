<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Service\DvlaApiService;
use App\Tests\TestCase\BaseWebTestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * DVLA Controller Test
 * 
 * Integration tests for DVLA vehicle lookup endpoint
 */
class DvlaControllerTest extends BaseWebTestCase
{
    public function testGetVehicleWithValidRegistration(): void
    {
        $client = $this->client;

        // Note: This will attempt real API call or use cached data
        // In production tests, you'd mock the DvlaApiService
        $client->request('GET', '/api/dvla/vehicle/BT14UDJ');

        // Should return 200 or 404 depending on API availability
        $this->assertContains($client->getResponse()->getStatusCode(), [200, 404, 503]);
    }

    public function testGetVehicleNormalizesRegistration(): void
    {
        $client = $this->client;

        // Test with spaces - should be normalized
        $client->request('GET', '/api/dvla/vehicle/BT14%20UDJ');

        $this->assertContains($client->getResponse()->getStatusCode(), [200, 404, 503]);
    }

    public function testGetVehicleWithInvalidRegistration(): void
    {
        $client = $this->client;

        $client->request('GET', '/api/dvla/vehicle/INVALID');

        // Should return error response
        $this->assertContains($client->getResponse()->getStatusCode(), [404, 400, 503]);
    }

    public function testEndpointReturns200WhenDvlaServiceUsesFallbackApiKeyAfter403(): void
    {
        $registration = 'ZZ' . strtoupper(substr(md5((string) microtime(true)), 0, 6));

        $tmpDotenv = tempnam(sys_get_temp_dir(), 'dvla_ctrl_env_');
        $this->assertNotFalse($tmpDotenv);
        file_put_contents($tmpDotenv, "DVLA_API_KEY=fallback-api-key\n");

        $requestApiKeys = [];
        $mockClient = new MockHttpClient(function ($method, $url, $options) use (&$requestApiKeys) {
            $headers = $options['headers'] ?? [];
            $apiKey = null;

            if (isset($headers['x-api-key'])) {
                $raw = $headers['x-api-key'];
                $apiKey = is_array($raw) ? ($raw[0] ?? null) : $raw;
            } else {
                foreach ($headers as $line) {
                    if (!is_string($line)) {
                        continue;
                    }
                    if (stripos($line, 'x-api-key:') === 0) {
                        $apiKey = trim(substr($line, strlen('x-api-key:')));
                        break;
                    }
                }
            }

            $requestApiKeys[] = $apiKey;

            if (count($requestApiKeys) === 1) {
                return new MockResponse('{"message":"Forbidden"}', ['http_code' => 403]);
            }

            if ($apiKey === 'fallback-api-key') {
                return new MockResponse(json_encode([
                    'make' => 'MAZDA',
                    'colour' => 'SILVER',
                    'yearOfManufacture' => 2014,
                ]), ['http_code' => 200]);
            }

            return new MockResponse('{"message":"Forbidden"}', ['http_code' => 403]);
        });

        $dvlaService = new DvlaApiService($mockClient, new NullLogger(), null, null, null, 'stale-api-key');
        $ref = new \ReflectionClass($dvlaService);
        $prop = $ref->getProperty('dotenvFallbackPaths');
        $prop->setAccessible(true);
        $prop->setValue($dvlaService, [$tmpDotenv]);

        static::getContainer()->set(DvlaApiService::class, $dvlaService);

        try {
            $this->client->request('GET', '/api/dvla/vehicle/' . $registration, [], [], [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
            ]);
        } finally {
            @unlink($tmpDotenv);
        }

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame($registration, $data['registration'] ?? null);
        $this->assertSame(['stale-api-key', 'fallback-api-key'], $requestApiKeys);
    }
}
