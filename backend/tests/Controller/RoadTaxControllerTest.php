<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class RoadTaxControllerTest extends WebTestCase
{
    private ?KernelBrowser $client = null;
    private ?string $token = null;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->token = $this->getAuthToken();
    }

    private function getAuthToken(): string
    {
        $this->client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'test@example.com',
                'password' => 'testpassword'
            ])
        );

        $data = json_decode($this->client->getResponse()->getContent(), true);
        return $data['token'] ?? '';
    }

    public function testListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/road-tax?vehicleId=1');
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testCreateRoadTax(): void
    {
        $rtData = [
            'vehicleId' => 1,
            'startDate' => '2026-01-01',
            'expiryDate' => '2027-01-01',
            'amount' => 120.00,
            'notes' => 'Annual road tax'
        ];

        $this->client->request(
            'POST',
            '/api/road-tax',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json'
            ],
            json_encode($rtData)
        );

        $this->assertEquals(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('Annual road tax', $data['notes']);
    }

    public function testUpdateRoadTax(): void
    {
        // Create first
        $this->client->request(
            'POST',
            '/api/road-tax',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json'
            ],
            json_encode(['vehicleId' => 1, 'startDate' => '2026-01-01'])
        );

        $create = json_decode($this->client->getResponse()->getContent(), true);
        $id = $create['id'];

        $this->client->request(
            'PUT',
            '/api/road-tax/' . $id,
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json'
            ],
            json_encode(['notes' => 'Updated note', 'amount' => 150.00])
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Updated note', $data['notes']);
    }

    public function testDeleteRoadTax(): void
    {
        // Create first
        $this->client->request(
            'POST',
            '/api/road-tax',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json'
            ],
            json_encode(['vehicleId' => 1, 'startDate' => '2026-01-01'])
        );

        $create = json_decode($this->client->getResponse()->getContent(), true);
        $id = $create['id'];

        $this->client->request(
            'DELETE',
            '/api/road-tax/' . $id,
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->token]
        );

        $this->assertEquals(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
    }
}
