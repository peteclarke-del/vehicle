<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vehicle Import/Export Controller Test
 * 
 * Integration tests for vehicle import/export functionality
 */
class VehicleImportExportControllerTest extends WebTestCase
{
    private string $token;

    protected function setUp(): void
    {
        $client = static::createClient();
        $this->token = $this->getAuthToken($client);
    }

    private function getAuthToken($client): string
    {
        $client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'test@example.com',
            'password' => 'password123'
        ]));

        $response = json_decode($client->getResponse()->getContent(), true);
        return $response['token'] ?? '';
    }

    public function testExportVehiclesRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/vehicles/export');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testExportVehiclesToCsv(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/vehicles/export?format=csv', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/csv; charset=utf-8');
        $this->assertResponseHeaderSame('Content-Disposition', 'attachment; filename="vehicles.csv"');
    }

    public function testExportVehiclesToJson(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/vehicles/export?format=json', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
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
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function testExportIncludesAllVehicleFields(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/vehicles/export?format=csv', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
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

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testImportVehiclesFromCsv(): void
    {
        $csvContent = "registration,make,model,year,colour\nAB12 CDE,Toyota,Corolla,2020,Blue";
        
        $client = static::createClient();
        $client->request('POST', '/api/vehicles/import', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
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
        $csvContent = "registration,make\nAB12 CDE,Toyota";
        
        $client = static::createClient();
        $client->request('POST', '/api/vehicles/import', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
            'CONTENT_TYPE' => 'text/csv',
        ], $csvContent);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(0, $data['imported']);
        $this->assertSame(1, $data['failed']);
        $this->assertStringContainsString('model', $data['errors'][0]);
    }

    public function testImportHandlesDuplicateRegistrations(): void
    {
        $csvContent = "registration,make,model,year\nAB12 CDE,Toyota,Corolla,2020\nAB12 CDE,Honda,Civic,2021";
        
        $client = static::createClient();
        $client->request('POST', '/api/vehicles/import', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
            'CONTENT_TYPE' => 'text/csv',
        ], $csvContent);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('duplicates', $data);
        $this->assertGreaterThan(0, count($data['duplicates']));
    }

    public function testImportSupportsUpdateMode(): void
    {
        $csvContent = "registration,make,model,year,colour\nAB12 CDE,Toyota,Corolla,2020,Blue";
        
        $client = static::createClient();
        $client->request('POST', '/api/vehicles/import?mode=update', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
            'CONTENT_TYPE' => 'text/csv',
        ], $csvContent);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('updated', $data);
    }

    public function testExportFiltersByDateRange(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/vehicles/export?format=csv&from=2020-01-01&to=2024-12-31', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
        ]);

        $this->assertResponseIsSuccessful();
    }

    public function testExportIncludesRelatedData(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/vehicles/export?format=json&include=records', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
        ]);

        $data = json_decode($client->getResponse()->getContent(), true);
        $firstVehicle = $data['vehicles'][0] ?? null;
        
        if ($firstVehicle) {
            $this->assertArrayHasKey('serviceRecords', $firstVehicle);
        }
    }

    public function testImportValidatesFileSize(): void
    {
        $largeContent = str_repeat("registration,make,model,year\nAB12 CDE,Toyota,Corolla,2020\n", 10000);
        
        $client = static::createClient();
        $client->request('POST', '/api/vehicles/import', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
            'CONTENT_TYPE' => 'text/csv',
        ], $largeContent);

        $this->assertResponseStatusCodeSame(Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
    }

    public function testExportGeneratesFilename(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/vehicles/export?format=csv', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
        ]);

        $contentDisposition = $client->getResponse()->headers->get('Content-Disposition');
        $this->assertStringContainsString('vehicles_', $contentDisposition);
        $this->assertStringContainsString('.csv', $contentDisposition);
    }

    public function testImportReturnsDetailedReport(): void
    {
        $csvContent = "registration,make,model,year\nAB12 CDE,Toyota,Corolla,2020";
        
        $client = static::createClient();
        $client->request('POST', '/api/vehicles/import', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
            'CONTENT_TYPE' => 'text/csv',
        ], $csvContent);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('imported', $data);
        $this->assertArrayHasKey('failed', $data);
        $this->assertArrayHasKey('total', $data);
    }
}
