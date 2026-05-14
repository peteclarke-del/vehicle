<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\TestCase\BaseWebTestCase;

class DvsaControllerTest extends BaseWebTestCase
{
    public function testCheckEndpointResponds(): void
    {
        $this->client->request(
            'GET',
            '/api/dvsa/check',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
            ]
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('configured', $data);
    }

    public function testVehicleAndMotHistoryEndpointsExist(): void
    {
        $registration = 'ZZ99ZZZ';

        $this->client->request(
            'GET',
            '/api/dvsa/vehicle/' . $registration,
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
            ]
        );
        $this->assertSame(404, $this->client->getResponse()->getStatusCode());
        $vehicleData = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true
        );
        $this->assertIsArray($vehicleData);
        $this->assertArrayHasKey('error', $vehicleData);

        $this->client->request(
            'GET',
            '/api/dvsa/mot-history/' . $registration,
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
            ]
        );
        $this->assertSame(404, $this->client->getResponse()->getStatusCode());
        $motData = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true
        );
        $this->assertIsArray($motData);
        $this->assertArrayHasKey('error', $motData);
    }
}
