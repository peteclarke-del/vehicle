<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\TestCase\BaseWebTestCase;

class SpecificationControllerTest extends BaseWebTestCase
{
    public function testGetSpecificationsWithoutAuthentication(): void
    {
        $this->client->request('GET', '/api/vehicles/1/specifications');
        $this->assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testSetSpecificationsForOwnedVehicle(): void
    {
        $em = $this->getEntityManager();
        $user = $em->getRepository(User::class)
            ->findOneBy(['email' => 'test@example.com']);
        $vehicle = $this->createTestVehicle($user, 'SPEC-' . uniqid());

        $payload = ['specifications' => ['enginePower' => '150hp']];

        $this->client->request(
            'PUT',
            '/api/vehicles/' . $vehicle->getId() . '/specifications',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($payload)
        );

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $data = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true
        );
        $this->assertIsArray($data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('specification', $data);
    }
}
