<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Part Controller Test
 * 
 * Integration tests for Part CRUD operations with scraping
 */
class PartControllerTest extends WebTestCase
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

    public function testListPartsRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/parts');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testListPartsForVehicle(): void
    {
        $this->client->request(
            'GET',
            '/api/parts?vehicleId=1',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
    }

    public function testCreatePart(): void
    {
        $partData = [
            'vehicleId' => 1,
            'name' => 'Air Filter',
            'partNumber' => 'AF-12345',
            'manufacturer' => 'Mann Filter',
            'price' => 25.99,
            'quantity' => 1,
            'purchaseDate' => '2026-01-15',
            'supplier' => 'AutoParts Ltd',
            'category' => 'Filters',
        ];

        $this->client->request(
            'POST',
            '/api/parts',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($partData)
        );

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('id', $data);
        $this->assertSame('Air Filter', $data['name']);
        $this->assertSame(25.99, $data['price']);
    }

    public function testScrapePartFromUrl(): void
    {
        $scrapeData = [
            'vehicleId' => 1,
            'url' => 'https://www.amazon.com/dp/B001234567',
        ];

        $this->client->request(
            'POST',
            '/api/parts/scrape',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($scrapeData)
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('price', $data);
        $this->assertArrayHasKey('manufacturer', $data);
    }

    public function testScrapeFromShopify(): void
    {
        $scrapeData = [
            'vehicleId' => 1,
            'url' => 'https://shop.example.com/products/brake-pads',
        ];

        $this->client->request(
            'POST',
            '/api/parts/scrape',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($scrapeData)
        );

        $this->assertResponseIsSuccessful();
    }

    public function testScrapeFromEbay(): void
    {
        $scrapeData = [
            'vehicleId' => 1,
            'url' => 'https://www.ebay.com/itm/1234567890',
        ];

        $this->client->request(
            'POST',
            '/api/parts/scrape',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($scrapeData)
        );

        $this->assertResponseIsSuccessful();
    }

    public function testUpdatePart(): void
    {
        $createData = [
            'vehicleId' => 1,
            'name' => 'Brake Pads',
            'price' => 45.00,
            'quantity' => 1,
            'purchaseDate' => '2026-01-10',
        ];

        $this->client->request(
            'POST',
            '/api/parts',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($createData)
        );

        $createResponse = json_decode($this->client->getResponse()->getContent(), true);
        $partId = $createResponse['id'];

        $updateData = [
            'price' => 42.50,
            'quantity' => 2,
        ];

        $this->client->request(
            'PUT',
            '/api/parts/' . $partId,
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
        
        $this->assertSame(42.50, $data['price']);
        $this->assertSame(2, $data['quantity']);
    }

    public function testDeletePart(): void
    {
        $createData = [
            'vehicleId' => 1,
            'name' => 'Test Part',
            'price' => 10.00,
            'quantity' => 1,
            'purchaseDate' => '2026-01-10',
        ];

        $this->client->request(
            'POST',
            '/api/parts',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($createData)
        );

        $createResponse = json_decode($this->client->getResponse()->getContent(), true);
        $partId = $createResponse['id'];

        $this->client->request(
            'DELETE',
            '/api/parts/' . $partId,
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseStatusCodeSame(204);
    }

    public function testSearchPartsByName(): void
    {
        $this->client->request(
            'GET',
            '/api/parts/search?query=filter&vehicleId=1',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
    }

    public function testFilterPartsByCategory(): void
    {
        $this->client->request(
            'GET',
            '/api/parts?vehicleId=1&category=Filters',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
    }

    public function testCalculateTotalPartsCost(): void
    {
        $this->client->request(
            'GET',
            '/api/parts/total-cost?vehicleId=1',
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('totalCost', $data);
        $this->assertArrayHasKey('partCount', $data);
    }

    public function testGetPartDetails(): void
    {
        $createData = [
            'vehicleId' => 1,
            'name' => 'Oil Filter',
            'partNumber' => 'OF-9876',
            'price' => 12.50,
            'quantity' => 1,
            'purchaseDate' => '2026-01-10',
        ];

        $this->client->request(
            'POST',
            '/api/parts',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($createData)
        );

        $createResponse = json_decode($this->client->getResponse()->getContent(), true);
        $partId = $createResponse['id'];

        $this->client->request(
            'GET',
            '/api/parts/' . $partId,
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->assertSame('Oil Filter', $data['name']);
        $this->assertSame('OF-9876', $data['partNumber']);
    }

    public function testInvalidUrlForScraping(): void
    {
        $scrapeData = [
            'vehicleId' => 1,
            'url' => 'not-a-valid-url',
        ];

        $this->client->request(
            'POST',
            '/api/parts/scrape',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($scrapeData)
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function testUserCannotAccessOtherUsersParts(): void
    {
        $createData = [
            'vehicleId' => 1,
            'name' => 'User1 Part',
            'price' => 25.00,
            'quantity' => 1,
            'purchaseDate' => '2026-01-10',
        ];

        $this->client->request(
            'POST',
            '/api/parts',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($createData)
        );

        $createResponse = json_decode($this->client->getResponse()->getContent(), true);
        $partId = $createResponse['id'];

        $differentUserToken = 'Bearer different-user-token';

        $this->client->request(
            'GET',
            '/api/parts/' . $partId,
            [],
            [],
            ['HTTP_AUTHORIZATION' => $differentUserToken]
        );

        $this->assertResponseStatusCodeSame(403);
    }
}
