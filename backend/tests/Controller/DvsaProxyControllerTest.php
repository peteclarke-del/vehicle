<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\TestCase\BaseWebTestCase;

class DvsaProxyControllerTest extends BaseWebTestCase
{
    public function testCheckEndpointRespondsWithServiceStatus(): void
    {
        $this->client->request('GET', '/api/dvsa/check');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('configured', $data);
        $this->assertArrayHasKey('message', $data);
    }

    public function testVehicleLookupEndpointExists(): void
    {
        $this->client->request('GET', '/api/dvsa/vehicle/AB12CDE');

        $status = $this->client->getResponse()->getStatusCode();
        $this->assertContains($status, [200, 404]);
    }
}
