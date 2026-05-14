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

        $payload = [
            'vehicleId' => $vehicle->getId(),
            'serviceDate' => '2026-01-01',
            'serviceType' => 'Full Service',
            'laborCost' => '250.00',
            'workPerformed' => 'Annual service',
        ];

        $this->client->request('POST', '/api/service-records', [], [], [
            'HTTP_AUTHORIZATION' => $this->getAuthToken(),
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $this->assertSame(201, $this->client->getResponse()->getStatusCode());
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('id', $data);
        $this->assertSame('Annual service', $data['workPerformed']);
    }
}
