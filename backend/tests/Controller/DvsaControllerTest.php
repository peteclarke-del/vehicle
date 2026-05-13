<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\TestCase\BaseWebTestCase;

class DvsaControllerTest extends BaseWebTestCase
{
    public function testCheckEndpointResponds(): void
    {
        $this->client->request('GET', '/api/dvsa/check', [], [], [
            'HTTP_AUTHORIZATION' => $this->getAuthToken(),
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('configured', $data);
    }

    public function testVehicleAndMotHistoryEndpointsExist(): void
    {
        $this->client->request('GET', '/api/dvsa/vehicle/AB12CDE', [], [], [
            'HTTP_AUTHORIZATION' => $this->getAuthToken(),
        ]);
        $this->assertContains($this->client->getResponse()->getStatusCode(), [200, 404]);

        $this->client->request('GET', '/api/dvsa/mot-history/AB12CDE', [], [], [
            'HTTP_AUTHORIZATION' => $this->getAuthToken(),
        ]);
        $this->assertContains($this->client->getResponse()->getStatusCode(), [200, 404]);
    }
}
