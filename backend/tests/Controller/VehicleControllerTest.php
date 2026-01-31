<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Vehicle Controller Test
 * 
 * Integration tests for Vehicle CRUD operations
 */
use App\Tests\TestCase\BaseWebTestCase;

class VehicleControllerTest extends BaseWebTestCase
{
    protected KernelBrowser $client;

    /**
     * Set up test environment before each test
     */
    /**
     * Helper method to get authentication token
     * 
     * @return string JWT token
     */
    protected function getAuthToken(): string
    {
        // Return the test mock header value (email) set by BaseWebTestCase
        return self::$authToken;
    }

    /**
     * Test listing vehicles requires authentication
     */
    public function testListVehiclesRequiresAuthentication(): void
    {
        $client = static::createClient();
$client->request('GET', '/api/vehicles');

        $this->assertResponseStatusCodeSame(401);
    }

    /**
     * Test listing vehicles for authenticated user
     */
    public function testListVehiclesForAuthenticatedUser(): void
    {
        $client = static::createClient();
$client->request(
            'GET',
            '/api/vehicles',
            [],
            [],
            ['HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    /**
     * Test creating a vehicle with valid data
     */
    public function testCreateVehicle(): void
    {
        $client = static::createClient();
$vehicleData = [
            'registration' => 'ABC123',
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2020,
            'colour' => 'Silver',
            'fuelType' => 'Petrol',
            'engineSize' => 1.8,
            'purchaseDate' => '2020-01-15',
            'purchasePrice' => 15000.00,
            'currentMileage' => 25000,
        ];

        $client->request(
            'POST',
            '/api/vehicles',
            [],
            [],
            [
                'HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($vehicleData)
        );

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertSame('ABC123', $data['registration']);
        $this->assertSame('Toyota', $data['make']);
        $this->assertSame('Corolla', $data['model']);
        $this->assertSame(2020, $data['year']);
    }

    /**
     * Test creating a vehicle with missing required fields
     */
    public function testCreateVehicleWithMissingFields(): void
    {
        $client = static::createClient();
$vehicleData = [
            'registration' => 'ABC123',
            // Missing make, model, year
        ];

        $client->request(
            'POST',
            '/api/vehicles',
            [],
            [],
            [
                'HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($vehicleData)
        );

        $this->assertResponseStatusCodeSame(400);
    }

    /**
     * Test getting a specific vehicle
     */
    public function testGetVehicle(): void
    {
        $client = static::createClient();
// First create a vehicle
        $vehicleData = [
            'registration' => 'XYZ789',
            'make' => 'Honda',
            'model' => 'Civic',
            'year' => 2019,
        ];

        $client->request(
            'POST',
            '/api/vehicles',
            [],
            [],
            [
                'HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($vehicleData)
        );

        $createResponse = json_decode($client->getResponse()->getContent(), true);
        $vehicleId = $createResponse['id'];

        // Now get the vehicle
        $client->request(
            'GET',
            '/api/vehicles/' . $vehicleId,
            [],
            [],
            ['HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame($vehicleId, $data['id']);
        $this->assertSame('XYZ789', $data['registration']);
    }

    /**
     * Test getting a non-existent vehicle returns 404
     */
    public function testGetNonExistentVehicle(): void
    {
        $client = static::createClient();
$client->request(
            'GET',
            '/api/vehicles/99999',
            [],
            [],
            ['HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken()]
        );

        $this->assertResponseStatusCodeSame(404);
    }

    /**
     * Test updating a vehicle
     */
    public function testUpdateVehicle(): void
    {
        $client = static::createClient();
// First create a vehicle
        $vehicleData = [
            'registration' => 'UPD123',
            'make' => 'Ford',
            'model' => 'Focus',
            'year' => 2018,
            'currentMileage' => 30000,
        ];

        $client->request(
            'POST',
            '/api/vehicles',
            [],
            [],
            [
                'HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($vehicleData)
        );

        $createResponse = json_decode($client->getResponse()->getContent(), true);
        $vehicleId = $createResponse['id'];

        // Update the vehicle
        $updateData = [
            'currentMileage' => 35000,
            'colour' => 'Blue',
        ];

        $client->request(
            'PUT',
            '/api/vehicles/' . $vehicleId,
            [],
            [],
            [
                'HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($updateData)
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(35000, $data['currentMileage']);
        $this->assertSame('Blue', $data['colour']);
    }

    /**
     * Test deleting a vehicle
     */
    public function testDeleteVehicle(): void
    {
        $client = static::createClient();
// First create a vehicle
        $vehicleData = [
            'registration' => 'DEL123',
            'make' => 'Nissan',
            'model' => 'Qashqai',
            'year' => 2021,
        ];

        $client->request(
            'POST',
            '/api/vehicles',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($vehicleData)
        );

        $createResponse = json_decode($client->getResponse()->getContent(), true);
        $vehicleId = $createResponse['id'];

        // Delete the vehicle
        $client->request(
            'DELETE',
            '/api/vehicles/' . $vehicleId,
            [],
            [],
            ['HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken()]
        );

        $this->assertResponseStatusCodeSame(204);

        // Verify vehicle is deleted
        $client->request(
            'GET',
            '/api/vehicles/' . $vehicleId,
            [],
            [],
            ['HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken()]
        );

        $this->assertResponseStatusCodeSame(404);
    }

    /**
     * Test user cannot access other users' vehicles
     */
    public function testUserCannotAccessOtherUsersVehicles(): void
    {
        $client = static::createClient();
// Create vehicle as user 1
        $vehicleData = [
            'registration' => 'USR1-VEH',
            'make' => 'BMW',
            'model' => 'X5',
            'year' => 2022,
        ];

        $client->request(
            'POST',
            '/api/vehicles',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($vehicleData)
        );

        $createResponse = json_decode($client->getResponse()->getContent(), true);
        $vehicleId = $createResponse['id'];

        // Try to access as different user (different token)
        $differentUserToken = 'other@vehicle.local';

        $client->request(
            'GET',
            '/api/vehicles/' . $vehicleId,
            [],
            [],
            ['HTTP_X_TEST_MOCK_AUTH' => $differentUserToken]
        );

        $this->assertResponseStatusCodeSame(403);
    }

    /**
     * Test vehicle depreciation calculation
     */
    public function testVehicleDepreciationCalculation(): void
    {
        $client = static::createClient();
$vehicleData = [
            'registration' => 'DEP123',
            'make' => 'Mercedes',
            'model' => 'C-Class',
            'year' => 2020,
            'purchaseDate' => '2020-01-01',
            'purchasePrice' => 30000.00,
        ];

        $client->request(
            'POST',
            '/api/vehicles',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($vehicleData)
        );

        $createResponse = json_decode($client->getResponse()->getContent(), true);
        $vehicleId = $createResponse['id'];

        // Get depreciation schedule
        $client->request(
            'GET',
            '/api/vehicles/' . $vehicleId . '/depreciation',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('schedule', $data);
        $this->assertIsArray($data['schedule']);
        $this->assertNotEmpty($data['schedule']);
    }

    /**
     * Test vehicle cost summary
     */
    public function testVehicleCostSummary(): void
    {
        $client = static::createClient();
$vehicleData = [
            'registration' => 'COST123',
            'make' => 'Audi',
            'model' => 'A4',
            'year' => 2021,
        ];

        $client->request(
            'POST',
            '/api/vehicles',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($vehicleData)
        );

        $createResponse = json_decode($client->getResponse()->getContent(), true);
        $vehicleId = $createResponse['id'];

        // Get cost summary
        $client->request(
            'GET',
            '/api/vehicles/' . $vehicleId . '/costs',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('totalCosts', $data);
        $this->assertArrayHasKey('breakdown', $data);
    }
}
