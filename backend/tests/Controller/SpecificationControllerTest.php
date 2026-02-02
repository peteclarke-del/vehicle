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

        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetSpecificationsForNonExistentVehicle(): void
    {
        $token = $this->getAuthToken();

        $this->client->request('GET', '/api/vehicles/999999/specifications', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetSpecificationsForVehicle(): void
    {
        $token = $this->getAuthToken();
        $em = $this->getEntityManager();

        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $vehicle = $this->createTestVehicle($user, 'SPEC-TEST-' . time());

        $this->client->request('GET', '/api/vehicles/' . $vehicle->getId() . '/specifications', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        // Should return null if no specifications exist
        $this->assertResponseIsSuccessful();
    }

    public function testScrapeSpecificationsWithoutAuthentication(): void
    {
        $this->client->request('POST', '/api/vehicles/1/specifications/scrape');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testScrapeSpecificationsForNonExistentVehicle(): void
    {
        $token = $this->getAuthToken();

        $this->client->request('POST', '/api/vehicles/999999/specifications/scrape', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testUpdateSpecifications(): void
    {
        $token = $this->getAuthToken();
        $em = $this->getEntityManager();

        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $vehicle = $this->createTestVehicle($user, 'SPEC-UPDATE-' . time());

        $specData = [
            'engineSize' => 2000,
            'cylinders' => 4,
            'fuelType' => 'Petrol',
            'transmission' => 'Manual',
        ];

        $this->client->request('POST', '/api/vehicles/' . $vehicle->getId() . '/specifications', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($specData));

        $this->assertResponseIsSuccessful();
    }
}
