<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Consumable Controller Test
 * 
 * Integration tests for Consumable CRUD operations and replacement tracking
 */
class ConsumableControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    private function getAuthToken(): string
    {
        return 'Bearer mock-jwt-token-12345';
    }

    public function testListConsumablesRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/consumables');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testListConsumablesForVehicle(): void
    {
        $this->client->request(
            'GET',
            '/api/consumables?vehicleId=1',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
    }

    public function testCreateConsumable(): void
    {
        $consumableData = [
            'vehicleId' => 1,
            'type' => 'Engine Oil',
            'brand' => 'Castrol',
            'quantity' => 5.0,
            'unit' => 'litres',
            'cost' => 35.00,
            'purchaseDate' => '2026-01-15',
            'replacementMileage' => 50000,
            'nextReplacementMileage' => 60000,
        ];

        $this->client->request(
            'POST',
            '/api/consumables',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($consumableData)
        );

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('id', $data);
        $this->assertSame('Engine Oil', $data['type']);
        $this->assertSame(35.00, $data['cost']);
    }

    public function testUpdateConsumable(): void
    {
        $createData = [
            'vehicleId' => 1,
            'type' => 'Brake Fluid',
            'brand' => 'ATE',
            'quantity' => 1.0,
            'unit' => 'litres',
            'cost' => 12.00,
            'purchaseDate' => '2026-01-10',
            'replacementMileage' => 48000,
        ];

        $this->client->request(
            'POST',
            '/api/consumables',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($createData)
        );

        $createResponse = json_decode($this->client->getResponse()->getContent(), true);
        $consumableId = $createResponse['id'];

        $updateData = [
            'cost' => 15.00,
            'nextReplacementMileage' => 58000,
        ];

        $this->client->request(
            'PUT',
            '/api/consumables/' . $consumableId,
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($updateData)
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertSame(15.00, $data['cost']);
        $this->assertSame(58000, $data['nextReplacementMileage']);
    }

    public function testDeleteConsumable(): void
    {
        $createData = [
            'vehicleId' => 1,
            'type' => 'Coolant',
            'brand' => 'Prestone',
            'quantity' => 2.0,
            'unit' => 'litres',
            'cost' => 18.00,
            'purchaseDate' => '2026-01-10',
            'replacementMileage' => 45000,
        ];

        $this->client->request(
            'POST',
            '/api/consumables',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($createData)
        );

        $createResponse = json_decode($this->client->getResponse()->getContent(), true);
        $consumableId = $createResponse['id'];

        $this->client->request(
            'DELETE',
            '/api/consumables/' . $consumableId,
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseStatusCodeSame(204);
    }

    public function testGetConsumablesDueForReplacement(): void
    {
        $this->client->request(
            'GET',
            '/api/consumables/due?vehicleId=1&currentMileage=55000',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertIsArray($data);
    }

    public function testGetReplacementHistory(): void
    {
        $this->client->request(
            'GET',
            '/api/consumables/history?vehicleId=1',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('records', $data);
        $this->assertArrayHasKey('totalCost', $data);
    }

    public function testCalculateTotalConsumableCost(): void
    {
        $this->client->request(
            'GET',
            '/api/consumables/total-cost?vehicleId=1',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('totalCost', $data);
        $this->assertArrayHasKey('recordCount', $data);
    }

    public function testFilterConsumablesByType(): void
    {
        $this->client->request(
            'GET',
            '/api/consumables?vehicleId=1&type=Engine Oil',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
    }

    public function testGetReplacementSchedule(): void
    {
        $this->client->request(
            'GET',
            '/api/consumables/schedule?vehicleId=1',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertIsArray($data);
    }

    public function testTrackReplacementInterval(): void
    {
        $createData1 = [
            'vehicleId' => 1,
            'type' => 'Engine Oil',
            'brand' => 'Castrol',
            'quantity' => 5.0,
            'unit' => 'litres',
            'cost' => 35.00,
            'purchaseDate' => '2025-07-01',
            'replacementMileage' => 40000,
        ];

        $this->client->request(
            'POST',
            '/api/consumables',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($createData1)
        );

        $createData2 = [
            'vehicleId' => 1,
            'type' => 'Engine Oil',
            'brand' => 'Castrol',
            'quantity' => 5.0,
            'unit' => 'litres',
            'cost' => 35.00,
            'purchaseDate' => '2026-01-15',
            'replacementMileage' => 50000,
        ];

        $this->client->request(
            'POST',
            '/api/consumables',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($createData2)
        );

        $this->client->request(
            'GET',
            '/api/consumables/interval?vehicleId=1&type=Engine Oil',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('averageInterval', $data);
        $this->assertSame(10000, $data['averageInterval']);
    }

    public function testUserCannotAccessOtherUsersConsumables(): void
    {
        $createData = [
            'vehicleId' => 1,
            'type' => 'Engine Oil',
            'brand' => 'Castrol',
            'quantity' => 5.0,
            'unit' => 'litres',
            'cost' => 35.00,
            'purchaseDate' => '2026-01-15',
            'replacementMileage' => 50000,
        ];

        $this->client->request(
            'POST',
            '/api/consumables',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($createData)
        );

        $createResponse = json_decode($this->client->getResponse()->getContent(), true);
        $consumableId = $createResponse['id'];

        $differentUserToken = 'Bearer different-user-token';

        $this->client->request(
            'GET',
            '/api/consumables/' . $consumableId,
            [],
            [],
            ['HTTP_AUTHORIZATION' => $differentUserToken]
        );

        $this->assertResponseStatusCodeSame(403);
    }
}
