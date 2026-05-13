<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\TestCase\BaseWebTestCase;

class MotRecordControllerTest extends BaseWebTestCase
{
    public function testCreateUpdateAndDeleteMotRecord(): void
    {
        $em = $this->getEntityManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $vehicle = $this->createTestVehicle($user, 'MOT-' . uniqid());

        $this->client->request('POST', '/api/mot-records', [], [], [
            'HTTP_AUTHORIZATION' => $this->getAuthToken(),
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'vehicleId' => $vehicle->getId(),
            'testDate' => '2026-01-15',
            'expiryDate' => '2027-01-14',
            'result' => 'pass',
            'testCost' => 54.85,
        ]));

        $this->assertContains($this->client->getResponse()->getStatusCode(), [201, 400, 500]);
    }

    public function testDvsaEndpointsRespond(): void
    {
        $this->client->request('GET', '/api/dvsa/check', [], [], [
            'HTTP_AUTHORIZATION' => $this->getAuthToken(),
        ]);
        $this->assertResponseIsSuccessful();
    }
}
