<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * MOT Record Controller Test
 * 
 * Integration tests for MOT Record CRUD operations and DVSA integration
 */
class MotRecordControllerTest extends WebTestCase
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

    public function testListMotRecordsRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/mot-records');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testListMotRecordsForVehicle(): void
    {
        $this->client->request(
            'GET',
            '/api/mot-records?vehicleId=1',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
    }

    public function testCreateMotRecord(): void
    {
        $motData = [
            'vehicleId' => 1,
            'testDate' => '2026-01-15',
            'expiryDate' => '2027-01-15',
            'testResult' => 'Pass',
            'mileage' => 50000,
            'motTestNumber' => 'MOT123456789',
            'testCenter' => 'Test MOT Center',
            'advisoryItems' => ['Brake pads worn', 'Tyre tread low'],
            'failureItems' => [],
            'cost' => 40.00,
        ];

        $this->client->request(
            'POST',
            '/api/mot-records',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($motData)
        );

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('id', $data);
        $this->assertSame('Pass', $data['testResult']);
        $this->assertSame('MOT123456789', $data['motTestNumber']);
    }

    public function testCreateFailedMotRecord(): void
    {
        $motData = [
            'vehicleId' => 1,
            'testDate' => '2026-01-10',
            'testResult' => 'Fail',
            'mileage' => 48000,
            'motTestNumber' => 'MOT987654321',
            'failureItems' => ['Brake pads below minimum', 'Headlight alignment incorrect'],
            'cost' => 40.00,
        ];

        $this->client->request(
            'POST',
            '/api/mot-records',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($motData)
        );

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertSame('Fail', $data['testResult']);
        $this->assertCount(2, $data['failureItems']);
    }

    public function testUpdateMotRecord(): void
    {
        $createData = [
            'vehicleId' => 1,
            'testDate' => '2026-01-10',
            'expiryDate' => '2027-01-10',
            'testResult' => 'Pass',
            'mileage' => 48000,
            'cost' => 40.00,
        ];

        $this->client->request(
            'POST',
            '/api/mot-records',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($createData)
        );

        $createResponse = json_decode($this->client->getResponse()->getContent(), true);
        $motId = $createResponse['id'];

        $updateData = [
            'testCenter' => 'Updated MOT Center',
            'cost' => 45.00,
        ];

        $this->client->request(
            'PUT',
            '/api/mot-records/' . $motId,
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
        
        $this->assertSame('Updated MOT Center', $data['testCenter']);
        $this->assertSame(45.00, $data['cost']);
    }

    public function testDeleteMotRecord(): void
    {
        $createData = [
            'vehicleId' => 1,
            'testDate' => '2026-01-10',
            'testResult' => 'Pass',
            'mileage' => 45000,
            'cost' => 40.00,
        ];

        $this->client->request(
            'POST',
            '/api/mot-records',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($createData)
        );

        $createResponse = json_decode($this->client->getResponse()->getContent(), true);
        $motId = $createResponse['id'];

        $this->client->request(
            'DELETE',
            '/api/mot-records/' . $motId,
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseStatusCodeSame(204);
    }

    public function testFetchMotHistoryFromDvsa(): void
    {
        $this->client->request(
            'GET',
            '/api/mot-records/dvsa-history?registration=ABC123',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertIsArray($data);
    }

    public function testGetNextMotDueDate(): void
    {
        $this->client->request(
            'GET',
            '/api/mot-records/next-due?vehicleId=1',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('dueDate', $data);
        $this->assertArrayHasKey('daysUntilDue', $data);
    }

    public function testGetMotHistory(): void
    {
        $this->client->request(
            'GET',
            '/api/mot-records/history?vehicleId=1',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('records', $data);
        $this->assertArrayHasKey('passRate', $data);
        $this->assertArrayHasKey('averageMileage', $data);
    }

    public function testCheckMotStatus(): void
    {
        $this->client->request(
            'GET',
            '/api/mot-records/status?vehicleId=1',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('expiryDate', $data);
        $this->assertArrayHasKey('isValid', $data);
    }

    public function testImportFromDvsa(): void
    {
        $importData = [
            'vehicleId' => 1,
            'registration' => 'ABC123',
        ];

        $this->client->request(
            'POST',
            '/api/mot-records/import-dvsa',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($importData)
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('imported', $data);
        $this->assertIsInt($data['imported']);
    }

    public function testGetAdvisoryItems(): void
    {
        $this->client->request(
            'GET',
            '/api/mot-records/advisories?vehicleId=1',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertIsArray($data);
    }

    public function testUserCannotAccessOtherUsersMotRecords(): void
    {
        $createData = [
            'vehicleId' => 1,
            'testDate' => '2026-01-10',
            'testResult' => 'Pass',
            'mileage' => 45000,
            'cost' => 40.00,
        ];

        $this->client->request(
            'POST',
            '/api/mot-records',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($createData)
        );

        $createResponse = json_decode($this->client->getResponse()->getContent(), true);
        $motId = $createResponse['id'];

        $differentUserToken = 'Bearer different-user-token';

        $this->client->request(
            'GET',
            '/api/mot-records/' . $motId,
            [],
            [],
            ['HTTP_AUTHORIZATION' => $differentUserToken]
        );

        $this->assertResponseStatusCodeSame(403);
    }
}
