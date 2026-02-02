<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\PartCategory;
use App\Entity\User;
use App\Entity\VehicleType;
use App\Tests\TestCase\BaseWebTestCase;

class PartCategoryControllerTest extends BaseWebTestCase
{
    public function testListPartCategoriesWithoutAuthentication(): void
    {
        $this->client->request('GET', '/api/part-categories');

        $this->assertResponseStatusCodeSame(401);
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
    }

    public function testListAllPartCategories(): void
    {
        $token = $this->getAuthToken();

        $this->client->request('GET', '/api/part-categories', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($responseData);
    }

    public function testListPartCategoriesByVehicleType(): void
    {
        $token = $this->getAuthToken();
        $em = $this->getEntityManager();

        $vehicleType = $this->getVehicleType('Car');

        // Create a part category for this vehicle type
        $category = new PartCategory();
        $category->setName('Test Engine Parts');
        $category->setVehicleType($vehicleType);
        $em->persist($category);
        $em->flush();

        $this->client->request('GET', '/api/part-categories?vehicleTypeId=' . $vehicleType->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($responseData);
        
        // Should include categories for this type or generic categories
        $found = false;
        foreach ($responseData as $cat) {
            if ($cat['name'] === 'Test Engine Parts') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Should find the test category');
    }

    public function testCreatePartCategory(): void
    {
        $token = $this->getAuthToken();

        $payload = [
            'name' => 'New Brake Parts',
            'description' => 'Parts related to braking system',
        ];

        $this->client->request('POST', '/api/part-categories', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $responseData);
        $this->assertEquals('New Brake Parts', $responseData['name']);
    }

    public function testCreatePartCategoryWithVehicleType(): void
    {
        $token = $this->getAuthToken();

        $vehicleType = $this->getVehicleType('Car');

        $payload = [
            'name' => 'Transmission Parts',
            'description' => 'Parts for transmission system',
            'vehicleTypeId' => $vehicleType->getId(),
        ];

        $this->client->request('POST', '/api/part-categories', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $responseData);
        $this->assertEquals('Transmission Parts', $responseData['name']);
        $this->assertEquals($vehicleType->getId(), $responseData['vehicleType']);
    }

    public function testCreatePartCategoryWithoutName(): void
    {
        $token = $this->getAuthToken();

        $payload = [
            'description' => 'No name provided',
        ];

        $this->client->request('POST', '/api/part-categories', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $this->assertResponseStatusCodeSame(400);
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
    }
}
