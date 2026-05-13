<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\DvlaApiService;
use App\Service\DvlaBusyException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DvlaApiServiceTest extends TestCase
{
    public function testSuccessfulLookupWithApiKey(): void
    {
        $body = json_encode([
            'make' => 'MAZDA',
            'primaryColour' => 'SILVER',
            'vin' => 'JMZBK12L123456789'
        ]);

        $mock = new MockHttpClient([
            new MockResponse($body, ['http_code' => 200])
        ]);

        $svc = new DvlaApiService($mock, new NullLogger(), null, null, null, 'dummy-api-key');

        $res = $svc->getVehicleByRegistration('BT14UDJ');

        $this->assertIsArray($res);
        $this->assertEquals('MAZDA', $res['make']);
        $this->assertEquals('SILVER', $res['primaryColour']);
        $this->assertEquals('JMZBK12L123456789', $res['vin']);
    }

    public function testRetriesAndThrowsOnRepeated429(): void
    {
        $responses = [
            new MockResponse('', ['http_code' => 429]),
            new MockResponse('', ['http_code' => 429]),
            new MockResponse('', ['http_code' => 429]),
            new MockResponse('', ['http_code' => 429]),
        ];

        $mock = new MockHttpClient($responses);
        $svc = new DvlaApiService($mock, new NullLogger(), null, null, null, 'dummy-api-key');

        $this->expectException(DvlaBusyException::class);
        $svc->getVehicleByRegistration('BT14UDJ');
    }

    public function testRetriesOn504BehavesSameAs429(): void
    {
        $responses = [
            new MockResponse('', ['http_code' => 504]),
            new MockResponse('', ['http_code' => 504]),
            new MockResponse('', ['http_code' => 504]),
            new MockResponse('', ['http_code' => 504]),
        ];

        $mock = new MockHttpClient($responses);
        $svc = new DvlaApiService($mock, new NullLogger(), null, null, null, 'dummy-api-key');

        $this->expectException(DvlaBusyException::class);
        $svc->getVehicleByRegistration('BT14UDJ');
    }

    public function testApiKey403ThenTokenFlowRetriesAndSucceeds(): void
    {
        $vehicleUrl = 'https://fake.dvla/vehicle-enquiry/v1/vehicles';
        $authUrl = 'https://fake.dvla/auth/token';

        $callCount = 0;

        $mock = new MockHttpClient(function ($method, $url, $options) use (&$callCount, $authUrl, $vehicleUrl) {
            $callCount++;
            // First call to vehicle returns 403
            if ($callCount === 1 && strpos($url, 'vehicle-enquiry') !== false) {
                return new MockResponse('', ['http_code' => 403]);
            }

            // Auth request returns token
            if (strpos($url, 'auth') !== false) {
                return new MockResponse(json_encode([
                    'access_token' => 'the-token',
                    'expires_in' => 300
                ]), ['http_code' => 200]);
            }

            // Subsequent vehicle call returns 200
            if (strpos($url, 'vehicle-enquiry') !== false) {
                return new MockResponse(json_encode(['make' => 'TEST', 'primaryColour' => 'Green']), ['http_code' => 200]);
            }

            return new MockResponse('', ['http_code' => 500]);
        });

        $svc = new DvlaApiService($mock, new NullLogger(), $authUrl, 'client', 'secret', 'dummy-api-key');

        $res = $svc->getVehicleByRegistration('BT14UDJ');

        $this->assertIsArray($res);
        $this->assertEquals('TEST', $res['make']);
        $this->assertEquals('Green', $res['primaryColour']);
    }

    public function testApiKey403ThenFallbackDotenvKeyRetriesAndSucceeds(): void
    {
        $tmpDotenv = tempnam(sys_get_temp_dir(), 'dvla_test_env_');
        $this->assertNotFalse($tmpDotenv);

        file_put_contents($tmpDotenv, "DVLA_API_KEY=fallback-api-key\n");

        $requestApiKeys = [];
        $mock = new MockHttpClient(function ($method, $url, $options) use (&$requestApiKeys) {
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
                    'primaryColour' => 'SILVER',
                ]), ['http_code' => 200]);
            }

            return new MockResponse('{"message":"Forbidden"}', ['http_code' => 403]);
        });

        $svc = new DvlaApiService($mock, new NullLogger(), null, null, null, 'stale-api-key');

        $ref = new \ReflectionClass($svc);
        $prop = $ref->getProperty('dotenvFallbackPaths');
        $prop->setAccessible(true);
        $prop->setValue($svc, [$tmpDotenv]);

        try {
            $res = $svc->getVehicleByRegistration('BT14UDJ');
        } finally {
            @unlink($tmpDotenv);
        }

        $this->assertIsArray($res);
        $this->assertEquals('MAZDA', $res['make']);
        $this->assertEquals('SILVER', $res['primaryColour']);
        $this->assertSame(['stale-api-key', 'fallback-api-key'], $requestApiKeys);
    }
}
