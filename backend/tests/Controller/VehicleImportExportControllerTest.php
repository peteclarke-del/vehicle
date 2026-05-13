<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\TestCase\BaseWebTestCase;
use Symfony\Component\HttpFoundation\Response;

class VehicleImportExportControllerTest extends BaseWebTestCase
{
    public function testExportVehiclesRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/vehicles/export');
        $this->assertTrue(in_array($this->client->getResponse()->getStatusCode(), [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN], true));
    }

    public function testExportVehiclesToCsvAndJson(): void
    {
        $this->client->request('GET', '/api/vehicles/export?format=csv', [], [], [
            'HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken(),
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/csv; charset=utf-8');
        $this->assertStringContainsString('vehicles_', (string) $this->client->getResponse()->headers->get('Content-Disposition'));

        $this->client->request('GET', '/api/vehicles/export?format=json', [], [], [
            'HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken(),
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('vehicles', $data);
    }

    public function testExportStockReturnsStockItemsPayload(): void
    {
        $this->client->request('GET', '/api/vehicles/export-stock', [], [], [
            'HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken(),
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('stockItems', $data);
        $this->assertIsArray($data['stockItems']);
    }

    public function testImportVehiclesFromCsvReturnsSummaryShape(): void
    {
        $csvContent = "registration,make,model,year\nAB12 CDE,Toyota,Corolla,2020";

        $this->client->request('POST', '/api/vehicles/import', [], [], [
            'HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken(),
            'CONTENT_TYPE' => 'text/csv',
        ], $csvContent);

        $status = $this->client->getResponse()->getStatusCode();
        $this->assertContains($status, [200, 207, 400, 500]);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('imported', $data);
        $this->assertArrayHasKey('failed', $data);
        $this->assertArrayHasKey('total', $data);
    }

    public function testImportValidatesFileSize(): void
    {
        $largeContent = str_repeat("registration,make,model,year\nAB12 CDE,Toyota,Corolla,2020\n", 10000);

        $this->client->request('POST', '/api/vehicles/import', [], [], [
            'HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken(),
            'CONTENT_TYPE' => 'text/csv',
        ], $largeContent);

        $this->assertResponseStatusCodeSame(Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
    }

    public function testPurgeAllWithCascadeRemovesUserVehicles(): void
    {
        $this->client->request('DELETE', '/api/vehicles/purge-all?cascade=true', [], [], [
            'HTTP_X_TEST_MOCK_AUTH' => $this->getAuthToken(),
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('deleted', $data);
    }
}
