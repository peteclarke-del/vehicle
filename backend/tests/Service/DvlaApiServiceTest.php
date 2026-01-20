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
}
