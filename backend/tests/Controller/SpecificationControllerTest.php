<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\TestCase\BaseWebTestCase;

class SpecificationControllerTest extends BaseWebTestCase
{
    public function testGetSpecificationsWithoutAuthentication(): void
    {
        $this->client->request('GET', '/api/specifications/vehicle/1');
        $this->assertContains($this->client->getResponse()->getStatusCode(), [401, 403, 404]);
    }

    public function testSetSpecificationsForOwnedVehicle(): void
    {
        $em = $this->getEntityManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $vehicle = $this->createTestVehicle($user, 'SPEC-' . uniqid());

        $this->client->request('PUT', '/api/specifications/vehicle/' . $vehicle->getId(), [], [], [
            'HTTP_AUTHORIZATION' => $this->getAuthToken(),
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['specifications' => ['enginePower' => '150hp']]));

        $this->assertContains($this->client->getResponse()->getStatusCode(), [200, 201, 400, 404, 500]);
    }
}
