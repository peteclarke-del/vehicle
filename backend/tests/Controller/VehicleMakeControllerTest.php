<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Tests\TestCase\BaseWebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vehicle Make Controller Test
 * 
 * Integration tests for vehicle make management endpoints
 */
class VehicleMakeControllerTest extends BaseWebTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = $this->getAuthToken();
    }


    public function testGetAllMakesDoesNotRequireAuthentication(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/vehicle-makes');

        $this->assertResponseIsSuccessful();
    }

    public function testGetAllMakes(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/vehicle-makes');

        $this->assertResponseIsSuccessful();
        
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        if (!empty($data)) {
            $this->assertArrayHasKey('id', $data[0]);
            $this->assertArrayHasKey('name', $data[0]);
            $this->assertArrayHasKey('vehicleTypeId', $data[0]);
        }
    }

    public function testCreateMakeWithoutAuthentication(): void
    {
        $client = $this->client;
        $client->request('POST', '/api/vehicle-makes', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'vehicleTypeId' => 1,
            'name' => 'Tesla Public',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Tesla Public', $data['name']);
    }

    public function testCreateMakeWithUserToken(): void
    {
        $client = $this->client;
        $client->request('POST', '/api/vehicle-makes', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'vehicleTypeId' => 1,
            'name' => 'Tesla User Token',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Tesla User Token', $data['name']);
    }

    public function testCreateMake(): void
    {
        $client = $this->client;
        $adminToken = $this->getAdminToken();
        
        $client->request('POST', '/api/vehicle-makes', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'vehicleTypeId' => 1,
            'name' => 'Tesla',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertSame('Tesla', $data['name']);
        $this->assertSame(1, $data['vehicleTypeId']);
    }

    public function testCreateMakeValidatesRequiredFields(): void
    {
        $client = $this->client;
        $adminToken = $this->getAdminToken();
        
        $client->request('POST', '/api/vehicle-makes', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'No Type']));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $client->request('POST', '/api/vehicle-makes', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['vehicleTypeId' => 1]));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testCreateMakeReturns404ForInvalidVehicleType(): void
    {
        $client = $this->client;
        $adminToken = $this->getAdminToken();
        
        $client->request('POST', '/api/vehicle-makes', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'vehicleTypeId' => 999999,
            'name' => 'GhostMake',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testListMakesCanBeFilteredByVehicleTypeId(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/vehicle-makes?vehicleTypeId=1');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        foreach ($data as $make) {
            $this->assertSame(1, (int) ($make['vehicleTypeId'] ?? 0));
        }
    }

    public function testSearchMakes(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/vehicle-makes?search=Toyota');

        $this->assertResponseIsSuccessful();
        
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testGetPopularMakes(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/vehicle-makes?popular=true');

        $this->assertResponseIsSuccessful();
        
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testGetMakesSortedByName(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/vehicle-makes?sort=name');

        $this->assertResponseIsSuccessful();
        
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        
        if (count($data) > 1) {
            $this->assertLessThanOrEqual($data[1]['name'], $data[0]['name']);
        }
    }
}
