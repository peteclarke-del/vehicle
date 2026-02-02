<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\TestCase\BaseWebTestCase;

class VehicleImageControllerTest extends BaseWebTestCase
{
    public function testListImagesWithoutAuthentication(): void
    {
        $this->client->request('GET', '/api/vehicles/1/images');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testListImagesForNonExistentVehicle(): void
    {
        $token = $this->getAuthToken();

        $this->client->request('GET', '/api/vehicles/999999/images', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testListImagesForVehicle(): void
    {
        $token = $this->getAuthToken();
        $em = $this->getEntityManager();

        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $vehicle = $this->createTestVehicle($user, 'IMG-TEST-' . time());

        $this->client->request('GET', '/api/vehicles/' . $vehicle->getId() . '/images', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('images', $responseData);
        $this->assertIsArray($responseData['images']);
    }

    public function testDeleteImageWithoutAuthentication(): void
    {
        $this->client->request('DELETE', '/api/vehicle-images/1');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testDeleteNonExistentImage(): void
    {
        $token = $this->getAuthToken();

        $this->client->request('DELETE', '/api/vehicle-images/999999', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(404);
    }
}
