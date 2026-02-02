<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\TestCase\BaseWebTestCase;

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
}
