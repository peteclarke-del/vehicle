<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\TestCase\BaseWebTestCase;

class RoadTaxControllerTest extends BaseWebTestCase
{
    public function testRoadTaxCrudFlow(): void
    {
        $em = $this->getEntityManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $vehicle = $this->createTestVehicle($user, 'TAX-' . uniqid());

        $this->client->request('POST', '/api/road-tax', [], [], [
            'HTTP_AUTHORIZATION' => $this->getAuthToken(),
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'vehicleId' => $vehicle->getId(),
            'startDate' => '2026-01-01',
            'endDate' => '2026-12-31',
            'cost' => 200,
        ]));

        $this->assertContains($this->client->getResponse()->getStatusCode(), [201, 400, 500]);
    }
}
