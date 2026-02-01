<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Tests\TestCase\BaseWebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Fuel Record Controller Test
 * 
 * Integration tests for Fuel Record CRUD operations and economy calculations
 */
class FuelRecordControllerTest extends BaseWebTestCase
{

    public function testListFuelRecordsRequiresAuthentication(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/fuel-records');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testListFuelRecordsForVehicle(): void
    {
        $client = $this->client;
        $client->request(
            'GET',
            '/api/fuel-records?vehicleId=1',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
    }

    public function testCreateFuelRecord(): void
    {
        $client = $this->client;
        $fuelData = [
            'vehicleId' => 1,
            'date' => '2026-01-15',
            'mileage' => 50000,
            'litres' => 45.5,
            'cost' => 68.25,
            'pricePerLitre' => 1.50,
            'fuelType' => 'Petrol',
            'station' => 'Shell Station',
            'fullTank' => true,
        ];

        $client->request(
            'POST',
            '/api/fuel-records',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($fuelData)
        );

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('id', $data);
        $this->assertSame(45.5, $data['litres']);
        $this->assertSame(68.25, $data['cost']);
    }

    public function testCalculateFuelEconomy(): void
    {
        $client = $this->client;
// First fill-up
        $fuel1 = [
            'vehicleId' => 1,
            'date' => '2026-01-01',
            'mileage' => 49000,
            'litres' => 50.0,
            'cost' => 75.00,
            'fullTank' => true,
        ];

        $client->request(
            'POST',
            '/api/fuel-records',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($fuel1)
        );

        // Second fill-up
        $fuel2 = [
            'vehicleId' => 1,
            'date' => '2026-01-15',
            'mileage' => 49500,
            'litres' => 45.0,
            'cost' => 67.50,
            'fullTank' => true,
        ];

        $client->request(
            'POST',
            '/api/fuel-records',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($fuel2)
        );

        $client->request(
            'GET',
            '/api/fuel-records/economy?vehicleId=1',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('averageMpg', $data);
        $this->assertArrayHasKey('averageLitresPer100km', $data);
        $this->assertArrayHasKey('costPerMile', $data);
    }

    public function testUpdateFuelRecord(): void
    {
        $client = $this->client;
$createData = [
            'vehicleId' => 1,
            'date' => '2026-01-10',
            'mileage' => 48000,
            'litres' => 50.0,
            'cost' => 75.00,
        ];

        $client->request(
            'POST',
            '/api/fuel-records',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($createData)
        );

        $createResponse = json_decode($client->getResponse()->getContent(), true);
        $fuelId = $createResponse['id'];

        $updateData = [
            'cost' => 72.50,
            'station' => 'BP Station',
        ];

        $client->request(
            'PUT',
            '/api/fuel-records/' . $fuelId,
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($updateData)
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertSame(72.50, $data['cost']);
        $this->assertSame('BP Station', $data['station']);
    }

    public function testDeleteFuelRecord(): void
    {
        $client = $this->client;
$createData = [
            'vehicleId' => 1,
            'date' => '2026-01-10',
            'mileage' => 45000,
            'litres' => 40.0,
            'cost' => 60.00,
        ];

        $client->request(
            'POST',
            '/api/fuel-records',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($createData)
        );

        $createResponse = json_decode($client->getResponse()->getContent(), true);
        $fuelId = $createResponse['id'];

        $client->request(
            'DELETE',
            '/api/fuel-records/' . $fuelId,
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseStatusCodeSame(204);
    }

    public function testGetTotalFuelCost(): void
    {
        $client = $this->client;
$client->request(
            'GET',
            '/api/fuel-records/total-cost?vehicleId=1',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('totalCost', $data);
        $this->assertArrayHasKey('totalLitres', $data);
        $this->assertArrayHasKey('recordCount', $data);
    }

    public function testGetFuelEconomyTrend(): void
    {
        $client = $this->client;
$client->request(
            'GET',
            '/api/fuel-records/economy-trend?vehicleId=1',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('trend', $data);
        $this->assertIsArray($data['trend']);
    }

    public function testFilterByDateRange(): void
    {
        $client = $this->client;
$client->request(
            'GET',
            '/api/fuel-records?vehicleId=1&startDate=2026-01-01&endDate=2026-12-31',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
    }

    public function testCalculateCostPerMile(): void
    {
        $client = $this->client;
$client->request(
            'GET',
            '/api/fuel-records/cost-per-mile?vehicleId=1',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('costPerMile', $data);
        $this->assertIsFloat($data['costPerMile']);
    }

    public function testGetMonthlyCostAverage(): void
    {
        $client = $this->client;
$client->request(
            'GET',
            '/api/fuel-records/monthly-average?vehicleId=1',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('monthlyAverage', $data);
    }

    public function testUserCannotAccessOtherUsersFuelRecords(): void
    {
        $client = $this->client;
$createData = [
            'vehicleId' => 1,
            'date' => '2026-01-10',
            'mileage' => 45000,
            'litres' => 40.0,
            'cost' => 60.00,
        ];

        $client->request(
            'POST',
            '/api/fuel-records',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($createData)
        );

        $createResponse = json_decode($client->getResponse()->getContent(), true);
        $fuelId = $createResponse['id'];

        $differentUserToken = 'Bearer different-user-token';

        $client->request(
            'GET',
            '/api/fuel-records/' . $fuelId,
            [],
            [],
            ['HTTP_AUTHORIZATION' => $differentUserToken]
        );

        $this->assertResponseStatusCodeSame(403);
    }
}
