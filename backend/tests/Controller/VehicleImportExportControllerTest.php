<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\TestCase\BaseWebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vehicle Import/Export Controller Test
 * 
 * Integration tests for vehicle import/export functionality
 */
class VehicleImportExportControllerTest extends BaseWebTestCase
{
    private string $token;

    public function testExportVehiclesRequiresAuthentication(): void
    {
        $client = static::createClient();
$client->request('GET', '/api/vehicles/export');

        $this->assertTrue(in_array($client->getResponse()->getStatusCode(), [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN]));
    }

    public function testExportVehiclesToCsv(): void
    {
        $client = static::createClient();
$client->request('GET', '/api/vehicles/export?format=csv', [], [], [
            'HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken(),
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/csv; charset=utf-8');
        $this->assertResponseHeaderSame('Content-Disposition', 'attachment; filename="vehicles.csv"');
    }

    public function testExportVehiclesToJson(): void
    {
        $client = static::createClient();
$client->request('GET', '/api/vehicles/export?format=json', [], [], [
            'HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken(),
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('vehicles', $data);
        $this->assertIsArray($data['vehicles']);
    }

    public function testExportVehiclesToExcel(): void
    {
        $client = static::createClient();
$client->request('GET', '/api/vehicles/export?format=xlsx', [], [], [
            'HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken(),
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function testExportIncludesAllVehicleFields(): void
    {
        $client = static::createClient();
$client->request('GET', '/api/vehicles/export?format=csv', [], [], [
            'HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken(),
        ]);

        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString('registration', $content);
        $this->assertStringContainsString('make', $content);
        $this->assertStringContainsString('model', $content);
        $this->assertStringContainsString('year', $content);
    }

    public function testImportVehiclesRequiresAuthentication(): void
    {
        $client = static::createClient();
$client->request('POST', '/api/vehicles/import');

        $this->assertTrue(in_array($client->getResponse()->getStatusCode(), [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN]));
    }

    public function testImportVehiclesFromCsv(): void
    {
        $client = static::createClient();
$csvContent = "registration,make,model,year,colour\nAB12 CDE,Toyota,Corolla,2020,Blue";
        
        $client->request('POST', '/api/vehicles/import', [], [], [
            'HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken(),
            'CONTENT_TYPE' => 'text/csv',
        ], $csvContent);

        $this->assertResponseIsSuccessful();
        
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('imported', $data);
        $this->assertArrayHasKey('failed', $data);
        $this->assertSame(1, $data['imported']);
    }

    public function testImportValidatesRequiredFields(): void
    {
        $client = static::createClient();
$csvContent = "registration,make\nAB12 CDE,Toyota";
        
        $client->request('POST', '/api/vehicles/import', [], [], [
            'HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken(),
            'CONTENT_TYPE' => 'text/csv',
        ], $csvContent);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(0, $data['imported']);
        $this->assertSame(1, $data['failed']);
        $this->assertStringContainsString('model', $data['errors'][0]);
    }

    public function testImportHandlesDuplicateRegistrations(): void
    {
        $client = static::createClient();
$csvContent = "registration,make,model,year\nAB12 CDE,Toyota,Corolla,2020\nAB12 CDE,Honda,Civic,2021";
        
        $client->request('POST', '/api/vehicles/import', [], [], [
            'HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken(),
            'CONTENT_TYPE' => 'text/csv',
        ], $csvContent);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('duplicates', $data);
        $this->assertGreaterThan(0, count($data['duplicates']));
    }

    public function testImportSupportsUpdateMode(): void
    {
        $client = static::createClient();
$csvContent = "registration,make,model,year,colour\nAB12 CDE,Toyota,Corolla,2020,Blue";
        
        $client->request('POST', '/api/vehicles/import?mode=update', [], [], [
            'HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken(),
            'CONTENT_TYPE' => 'text/csv',
        ], $csvContent);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('updated', $data);
    }

    public function testExportFiltersByDateRange(): void
    {
        $client = static::createClient();
$client->request('GET', '/api/vehicles/export?format=csv&from=2020-01-01&to=2024-12-31', [], [], [
            'HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken(),
        ]);

        $this->assertResponseIsSuccessful();
    }

    public function testExportIncludesRelatedData(): void
    {
        $client = static::createClient();
$client->request('GET', '/api/vehicles/export?format=json&include=records', [], [], [
            'HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken(),
        ]);

        $data = json_decode($client->getResponse()->getContent(), true);
        $firstVehicle = $data['vehicles'][0] ?? null;
        
        if ($firstVehicle) {
            $this->assertArrayHasKey('serviceRecords', $firstVehicle);
        }
    }

    public function testImportValidatesFileSize(): void
    {
        $client = static::createClient();
$largeContent = str_repeat("registration,make,model,year\nAB12 CDE,Toyota,Corolla,2020\n", 10000);
        
        $client->request('POST', '/api/vehicles/import', [], [], [
            'HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken(),
            'CONTENT_TYPE' => 'text/csv',
        ], $largeContent);

        $this->assertResponseStatusCodeSame(Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
    }

    public function testExportGeneratesFilename(): void
    {
        $client = static::createClient();
$client->request('GET', '/api/vehicles/export?format=csv', [], [], [
            'HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken(),
        ]);

        $contentDisposition = $client->getResponse()->headers->get('Content-Disposition');
        $this->assertStringContainsString('vehicles_', $contentDisposition);
        $this->assertStringContainsString('.csv', $contentDisposition);
    }

    public function testImportReturnsDetailedReport(): void
    {
        $client = static::createClient();
$csvContent = "registration,make,model,year\nAB12 CDE,Toyota,Corolla,2020";
        
        $client->request('POST', '/api/vehicles/import', [], [], [
            'HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken(),
            'CONTENT_TYPE' => 'text/csv',
        ], $csvContent);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('imported', $data);
        $this->assertArrayHasKey('failed', $data);
        $this->assertArrayHasKey('total', $data);
    }

    public function testImportCreatesTodosAndLinksParts(): void
    {
        $client = static::createClient();
$vehiclesPayload = [
            [
                'name' => 'Import Todo Vehicle',
                'vehicleType' => 'Car',
                'registrationNumber' => 'IMP-TODO-1',
                'purchaseCost' => 1000,
                'purchaseDate' => '2024-01-01',
                'parts' => [
                    [
                        'partNumber' => 'P-001',
                        'description' => 'Test Part 1',
                        'cost' => 50,
                        // no installationDate -> available for linking
                    ]
                ],
                'todos' => [
                    [
                        'title' => 'Imported Todo',
                        'description' => 'Todo from import',
                        'done' => true,
                        'completedBy' => '2024-02-01',
                        'parts' => [ ['partNumber' => 'P-001'] ]
                    ]
                ]
            ]
        ];

        $client->request('POST', '/api/vehicles/import', [], [], [
            'HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken(),
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($vehiclesPayload));

        $this->assertResponseIsSuccessful();
        $result = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('imported', $result);

        // Now export JSON and assert the todo and linked part appear with installationDate applied
        $client->request('GET', '/api/vehicles/export?format=json', [], [], [
            'HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken(),
        ]);
        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $found = null;
        foreach ($data['vehicles'] ?? [] as $v) {
            if (!empty($v['registrationNumber']) && $v['registrationNumber'] === 'IMP-TODO-1') {
                $found = $v; break;
            }
        }
        $this->assertNotNull($found, 'Imported vehicle not found in export');
        $this->assertArrayHasKey('todos', $found);
        $this->assertNotEmpty($found['todos']);
        $todo = $found['todos'][0];
        $this->assertSame('Imported Todo', $todo['title']);
        $this->assertTrue($todo['done']);

        $this->assertArrayHasKey('parts', $todo);
        $this->assertNotEmpty($todo['parts']);
        $this->assertSame('P-001', $todo['parts'][0]['partNumber']);
        $this->assertSame('2024-02-01', $todo['parts'][0]['installationDate']);
    }
}
