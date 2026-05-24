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
        $user = $em->getRepository(User::class)
            ->findOneBy(['email' => 'test@example.com']);
        $vehicle = $this->createTestVehicle($user, 'TAX-' . uniqid());

        $payloadData = [
            'vehicleId' => $vehicle->getId(),
            'startDate' => '2026-01-01',
            'expiryDate' => '2026-12-31',
            'amount' => 200,
            'notes' => 'Annual road tax',
        ];
        $payload = json_encode($payloadData);

        $this->client->request(
            'POST',
            '/api/road-tax',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );

        $this->assertSame(201, $this->client->getResponse()->getStatusCode());
        $data = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true
        );
        $this->assertIsArray($data);
        $this->assertArrayHasKey('id', $data);
        $this->assertSame('Annual road tax', $data['notes']);
    }
}
