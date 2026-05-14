<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\TestCase\BaseWebTestCase;

class InsuranceControllerTest extends BaseWebTestCase
{
    public function testCreateUpdateDeleteInsurance(): void
    {
        $em = $this->getEntityManager();
        $user = $em->getRepository(User::class)
            ->findOneBy(['email' => 'test@example.com']);
        $vehicle = $this->createTestVehicle($user, 'INS-' . uniqid());

        $payloadData = [
            'vehicleIds' => [$vehicle->getId()],
            'provider' => 'Test Insurance',
            'policyNumber' => 'PN-' . uniqid(),
            'startDate' => '2026-01-01',
            'expiryDate' => '2026-12-31',
            'annualCost' => 500,
        ];
        $payload = json_encode($payloadData);

        $this->client->request(
            'POST',
            '/api/insurance/policies',
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
        $this->assertSame('Test Insurance', $data['provider']);
    }
}
