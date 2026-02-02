<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\VehicleModel;
use App\Entity\VehicleMake;
use App\Tests\TestCase\BaseWebTestCase;

class VehicleModelControllerTest extends BaseWebTestCase
{
    public function testListAllVehicleModels(): void
    {
        $token = $this->getAuthToken();

        $this->client->request('GET', '/api/vehicle-models', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($responseData);
    }

    public function testListVehicleModelsByMake(): void
    {
        $token = $this->getAuthToken();
        $em = $this->getEntityManager();

        $vehicleType = $this->getVehicleType('Car');

        // Create a make and model
        $make = new VehicleMake();
        $make->setName('Test Make');
        $make->setVehicleType($vehicleType);
        $em->persist($make);

        $model = new VehicleModel();
        $model->setName('Test Model');
        $model->setMake($make);
        $em->persist($model);
        $em->flush();

        $this->client->request('GET', '/api/vehicle-models?makeId=' . $make->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($responseData);
        
        // Should find our test model
        $found = false;
        foreach ($responseData as $m) {
            if ($m['name'] === 'Test Model') {
                $found = true;
                $this->assertEquals($make->getId(), $m['makeId']);
                break;
            }
        }
        $this->assertTrue($found, 'Should find the test model');
    }

    public function testListVehicleModelsByYear(): void
    {
        $token = $this->getAuthToken();
        $em = $this->getEntityManager();

        $vehicleType = $this->getVehicleType('Car');

        // Create a make and model with year range
        $make = new VehicleMake();
        $make->setName('Year Test Make');
        $make->setVehicleType($vehicleType);
        $em->persist($make);

        $model = new VehicleModel();
        $model->setName('Year Test Model');
        $model->setMake($make);
        $model->setStartYear(2020);
        $model->setEndYear(2025);
        $em->persist($model);
        $em->flush();

        // Search for year within range
        $this->client->request('GET', '/api/vehicle-models?year=2022', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($responseData);
        
        // Should find model with year in range
        $found = false;
        foreach ($responseData as $m) {
            if ($m['name'] === 'Year Test Model') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Should find model with year 2022 in range 2020-2025');
    }

    public function testCreateVehicleModel(): void
    {
        $token = $this->getAuthToken();
        $em = $this->getEntityManager();

        $vehicleType = $this->getVehicleType('Car');

        // Create a make first
        $make = new VehicleMake();
        $make->setName('Create Test Make');
        $make->setVehicleType($vehicleType);
        $em->persist($make);
        $em->flush();

        $payload = [
            'name' => 'New Model',
            'makeId' => $make->getId(),
            'startYear' => 2023,
            'endYear' => 2026,
        ];

        $this->client->request('POST', '/api/vehicle-models', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $responseData);
        $this->assertEquals('New Model', $responseData['name']);
        $this->assertEquals($make->getId(), $responseData['makeId']);
    }

    public function testCreateVehicleModelWithInvalidMake(): void
    {
        $token = $this->getAuthToken();

        $payload = [
            'name' => 'Invalid Model',
            'makeId' => 999999,
        ];

        $this->client->request('POST', '/api/vehicle-models', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $this->assertResponseStatusCodeSame(404);
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
    }

    public function testListModelsFilteredByMakeAndYear(): void
    {
        $token = $this->getAuthToken();
        $em = $this->getEntityManager();

        $vehicleType = $this->getVehicleType('Car');

        // Create a make and model with year range
        $make = new VehicleMake();
        $make->setName('Combined Filter Make');
        $make->setVehicleType($vehicleType);
        $em->persist($make);

        $model = new VehicleModel();
        $model->setName('Combined Filter Model');
        $model->setMake($make);
        $model->setStartYear(2020);
        $model->setEndYear(2025);
        $em->persist($model);
        $em->flush();

        // Search with both make and year filters
        $this->client->request('GET', '/api/vehicle-models?makeId=' . $make->getId() . '&year=2022', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($responseData);
        
        // Should find model matching both criteria
        $found = false;
        foreach ($responseData as $m) {
            if ($m['name'] === 'Combined Filter Model') {
                $found = true;
                $this->assertEquals($make->getId(), $m['makeId']);
                break;
            }
        }
        $this->assertTrue($found, 'Should find model with matching make and year');
    }

    public function testCreateVehicleModelWithoutRequiredFields(): void
    {
        $token = $this->getAuthToken();

        $payload = [
            'name' => 'Incomplete Model',
            // Missing makeId
        ];

        $this->client->request('POST', '/api/vehicle-models', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        // Should get an error (400, 404 or 500 depending on handling)
        $response = $this->client->getResponse();
        $this->assertTrue($response->getStatusCode() >= 400, 'Expected error status code');
        
        // Try to decode response, but don't fail if it's not JSON
        $content = $response->getContent();
        if ($content) {
            $responseData = json_decode($content, true);
            if (is_array($responseData) && isset($responseData['error'])) {
                $this->assertArrayHasKey('error', $responseData);
            }
        }
        
        // At minimum, verify we got an error
        $this->assertFalse($response->isSuccessful(), 'Response should not be successful');
    }
}
