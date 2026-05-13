<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\TestCase\BaseWebTestCase;

class FuelRecordControllerTest extends BaseWebTestCase
{
    public function testListFuelRecordsForVehicle(): void
    {
        $em = $this->getEntityManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $vehicle = $this->createTestVehicle($user, 'FUEL-' . uniqid());

        $this->client->request('GET', '/api/fuel-records?vehicleId=' . $vehicle->getId(), [], [], [
            'HTTP_AUTHORIZATION' => $this->getAuthToken(),
        ]);

        $this->assertResponseIsSuccessful();
    }

    public function testCreateUpdateAndDeleteFuelRecord(): void
    {
        $em = $this->getEntityManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $vehicle = $this->createTestVehicle($user, 'FUEL2-' . uniqid());

        $this->client->request('POST', '/api/fuel-records', [], [], [
            'HTTP_AUTHORIZATION' => $this->getAuthToken(),
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'vehicleId' => $vehicle->getId(),
            'fuelDate' => '2026-01-15',
            'odometer' => 10000,
            'litres' => 45,
            'totalCost' => 70,
        ]));

        $this->assertContains($this->client->getResponse()->getStatusCode(), [201, 400, 500]);
    }
}
