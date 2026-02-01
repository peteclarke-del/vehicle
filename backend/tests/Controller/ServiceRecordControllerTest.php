<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Tests\TestCase\BaseWebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Service Record Controller Test
 * 
 * Integration tests for Service Record CRUD operations
 */
class ServiceRecordControllerTest extends BaseWebTestCase
{


    public function testListServiceRecordsRequiresAuthentication(): void
    {
        $client = $this->client;
$client->request('GET', '/api/service-records');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testListServiceRecordsRequiresVehicleId(): void
    {
        $client = $this->client;
$client->request(
            'GET',
            '/api/service-records',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );
        $this->assertResponseStatusCodeSame(400);
    }

    public function testListServiceRecordsForVehicle(): void
    {
        $client = $this->client;
$client->request(
            'GET',
            '/api/service-records?vehicleId=1',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
    }

    public function testCreateServiceRecord(): void
    {
        $client = $this->client;
$serviceData = [
            'vehicleId' => 1,
            'description' => 'Annual Service',
            'serviceDate' => '2026-01-15',
            'mileage' => 50000,
            'laborCost' => 150.00,
            'partsCost' => 75.00,
            'serviceType' => 'scheduled',
            'serviceProvider' => 'Test Garage',
            'notes' => 'All filters replaced',
        ];

        $client->request(
            'POST',
            '/api/service-records',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($serviceData)
        );

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('id', $data);
        $this->assertSame('Annual Service', $data['description']);
        $this->assertSame(150.00, (float)$data['laborCost']);
        $this->assertSame(75.00, (float)$data['partsCost']);
        $this->assertSame(225.00, (float)$data['totalCost']);
    }

    public function testCreateServiceRecordWithMissingFields(): void
    {
        $client = $this->client;
$serviceData = [
            'vehicleId' => 1,
            'description' => 'Service',
        ];

        $client->request(
            'POST',
            '/api/service-records',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($serviceData)
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function testUpdateServiceRecord(): void
    {
        $client = $this->client;
$createData = [
            'vehicleId' => 1,
            'description' => 'Oil Change',
            'serviceDate' => '2026-01-10',
            'mileage' => 48000,
            'laborCost' => 50.00,
            'partsCost' => 30.00,
        ];

        $client->request(
            'POST',
            '/api/service-records',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($createData)
        );

        $createResponse = json_decode($client->getResponse()->getContent(), true);
        $serviceId = $createResponse['id'];

        $updateData = [
            'labourCost' => 60.00,
            'notes' => 'Used premium oil',
        ];

        $client->request(
            'PUT',
            '/api/service-records/' . $serviceId,
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
        
        $this->assertSame(60.00, (float)$data['laborCost']);
        $this->assertSame('Used premium oil', $data['notes']);
        $this->assertSame(90.00, (float)$data['totalCost']);
    }

    public function testDeleteServiceRecord(): void
    {
        $client = $this->client;
$createData = [
            'vehicleId' => 1,
            'description' => 'Test Service',
            'serviceDate' => '2026-01-10',
            'mileage' => 45000,
            'laborCost' => 100.00,
        ];

        $client->request(
            'POST',
            '/api/service-records',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($createData)
        );

        $createResponse = json_decode($client->getResponse()->getContent(), true);
        $serviceId = $createResponse['id'];

        $client->request(
            'DELETE',
            '/api/service-records/' . $serviceId,
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseStatusCodeSame(200);
    }

    public function testUploadAttachment(): void
    {
        $client = $this->client;
$createData = [
            'vehicleId' => 1,
            'description' => 'Service with Receipt',
            'serviceDate' => '2026-01-10',
            'mileage' => 45000,
            'laborCost' => 100.00,
        ];

        $client->request(
            'POST',
            '/api/service-records',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($createData)
        );

        $createResponse = json_decode($client->getResponse()->getContent(), true);
        $serviceId = $createResponse['id'];

        // Mock file upload
        $uploadedFile = new \Symfony\Component\HttpFoundation\File\UploadedFile(
            __DIR__ . '/../fixtures/test-receipt.pdf',
            'receipt.pdf',
            'application/pdf',
            null,
            true
        );

        $client->request(
            'POST',
            '/api/service-records/' . $serviceId . '/attachments',
            [],
            ['file' => $uploadedFile],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseStatusCodeSame(201);
    }

    public function testCalculateTotalCost(): void
    {
        $client = $this->client;
$serviceData = [
            'vehicleId' => 1,
            'description' => 'Major Service',
            'serviceDate' => '2026-01-15',
            'mileage' => 50000,
            'laborCost' => 250.00,
            'partsCost' => 150.00,
            'additionalCosts' => 50.00,
        ];

        $client->request(
            'POST',
            '/api/service-records',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($serviceData)
        );

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(450.00, (float)$data['totalCost']);
    }

    public function testFilterByServiceType(): void
    {
        $client = $this->client;
$client->request(
            'GET',
            '/api/service-records?vehicleId=1&serviceType=scheduled',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
    }

    public function testFilterByDateRange(): void
    {
        $client = $this->client;
$client->request(
            'GET',
            '/api/service-records?vehicleId=1&startDate=2026-01-01&endDate=2026-12-31',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
    }

    public function testGetServiceHistory(): void
    {
        $client = $this->client;
$client->request(
            'GET',
            '/api/service-records/history?vehicleId=1',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('records', $data);
        $this->assertArrayHasKey('totalCost', $data);
        $this->assertArrayHasKey('totalServices', $data);
    }

    public function testUserCannotAccessOtherUsersServiceRecords(): void
    {
        $client = $this->client;
$createData = [
            'vehicleId' => 1,
            'description' => 'User1 Service',
            'serviceDate' => '2026-01-10',
            'mileage' => 45000,
            'laborCost' => 100.00,
        ];

        $client->request(
            'POST',
            '/api/service-records',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($createData)
        );

        $createResponse = json_decode($client->getResponse()->getContent(), true);
        $serviceId = $createResponse['id'];

        $differentUserToken = 'Bearer different-user-token';

        $client->request(
            'GET',
            '/api/service-records/' . $serviceId,
            [],
            [],
            ['HTTP_AUTHORIZATION' => $differentUserToken]
        );

        $this->assertResponseStatusCodeSame(403);
    }

    public function testScheduleNextService(): void
    {
        $client = $this->client;
$client->request(
            'GET',
            '/api/service-records/next-due?vehicleId=1',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('dueDate', $data);
        $this->assertArrayHasKey('dueMileage', $data);
        $this->assertArrayHasKey('serviceType', $data);
    }
}
