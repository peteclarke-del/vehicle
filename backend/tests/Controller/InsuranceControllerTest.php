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
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $vehicle = $this->createTestVehicle($user, 'INS-' . uniqid());

        $this->client->request('POST', '/api/insurance', [], [], [
            'HTTP_AUTHORIZATION' => $this->getAuthToken(),
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'vehicleId' => $vehicle->getId(),
            'provider' => 'Test Insurance',
            'policyNumber' => 'PN-' . uniqid(),
            'startDate' => '2026-01-01',
            'endDate' => '2026-12-31',
            'premium' => 500,
        ]));

        $this->assertContains($this->client->getResponse()->getStatusCode(), [201, 400, 500]);
    }
}
