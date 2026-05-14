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

        $payload = [
            'vehicleId' => $vehicle->getId(),
            'date' => '2026-01-15',
            'mileage' => 10000,
            'litres' => 45,
            'cost' => 70,
        ];

        $this->client->request('POST', '/api/fuel-records', [], [], [
            'HTTP_AUTHORIZATION' => $this->getAuthToken(),
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $this->assertSame(201, $this->client->getResponse()->getStatusCode());
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('id', $data);
        $this->assertSame('2026-01-15', $data['date']);
    }
}
