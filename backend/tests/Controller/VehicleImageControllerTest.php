<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\TestCase\BaseWebTestCase;

class VehicleImageControllerTest extends BaseWebTestCase
{
    public function testListImagesForVehicle(): void
    {
        $em = $this->getEntityManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $vehicle = $this->createTestVehicle($user, 'IMG-TEST-' . time());

        $this->client->request('GET', '/api/vehicles/' . $vehicle->getId() . '/images', [], [], [
            'HTTP_AUTHORIZATION' => $this->getAuthToken(),
        ]);

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('images', $responseData);
    }

    public function testListImagesForMissingVehicle(): void
    {
        $this->client->request('GET', '/api/vehicles/999999/images', [], [], [
            'HTTP_AUTHORIZATION' => $this->getAuthToken(),
        ]);

        $this->assertResponseStatusCodeSame(404);
    }
}
