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
        $this->assertArrayHasKey('id', $data[0]);
        $this->assertArrayHasKey('name', $data[0]);
    }

    public function testGetMakeById(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/vehicle-makes/1');

        $this->assertResponseIsSuccessful();
        
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('models', $data);
    }

    public function testGetMakeByIdReturns404ForInvalidId(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/vehicle-makes/99999');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testCreateMakeRequiresAuthentication(): void
    {
        $client = $this->client;
        $client->request('POST', '/api/vehicle-makes', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Tesla']));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testCreateMakeRequiresAdminRole(): void
    {
        $client = $this->client;
        $client->request('POST', '/api/vehicle-makes', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['name' => 'Tesla']));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCreateMake(): void
    {
        $client = $this->client;
        $adminToken = $this->getAdminToken($client);
        
        $client->request('POST', '/api/vehicle-makes', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'Tesla',
            'countryOfOrigin' => 'USA',
            'logoUrl' => 'https://example.com/tesla.png'
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Tesla', $data['name']);
        $this->assertSame('USA', $data['countryOfOrigin']);
    }

    public function testCreateMakeValidatesRequiredFields(): void
    {
        $client = $this->client;
        $adminToken = $this->getAdminToken($client);
        
        $client->request('POST', '/api/vehicle-makes', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([]));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testUpdateMake(): void
    {
        $client = $this->client;
        $adminToken = $this->getAdminToken($client);
        
        $client->request('PUT', '/api/vehicle-makes/1', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'Toyota Motors',
            'countryOfOrigin' => 'Japan'
        ]));

        $this->assertResponseIsSuccessful();
        
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Toyota Motors', $data['name']);
    }

    public function testDeleteMake(): void
    {
        $client = $this->client;
        $adminToken = $this->getAdminToken($client);
        
        $client->request('DELETE', '/api/vehicle-makes/999', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function testGetModelsByMake(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/vehicle-makes/1/models');

        $this->assertResponseIsSuccessful();
        
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
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
