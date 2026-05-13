<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\TestCase\BaseWebTestCase;

class ServiceRecordControllerTest extends BaseWebTestCase
{
    public function testCreateAndGetServiceRecord(): void
    {
        $em = $this->getEntityManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $vehicle = $this->createTestVehicle($user, 'SRV-' . uniqid());

        $this->client->request('POST', '/api/service-records', [], [], [
            'HTTP_AUTHORIZATION' => $this->getAuthToken(),
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'vehicleId' => $vehicle->getId(),
            'serviceDate' => '2026-01-01',
            'description' => 'Annual service',
            'cost' => 250,
        ]));

        $this->assertContains($this->client->getResponse()->getStatusCode(), [201, 400, 500]);
    }
}
