<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Tests\TestCase\BaseWebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class RoadTaxControllerTest extends BaseWebTestCase
{


    public function testListRequiresAuthentication(): void
    {
        $client = $this->client;
$client->request('GET', '/api/road-tax?vehicleId=1');
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testCreateRoadTax(): void
    {
        $client = $this->client;
$rtData = [
            'vehicleId' => 1,
            'startDate' => '2026-01-01',
            'expiryDate' => '2027-01-01',
            'amount' => 120.00,
            'notes' => 'Annual road tax'
        ];

        $client->request(
            'POST',
            '/api/road-tax',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json'
            ],
            json_encode($rtData)
        );

        $this->assertEquals(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('Annual road tax', $data['notes']);
    }

    public function testUpdateRoadTax(): void
    {
        $client = $this->client;
// Create first
        $client->request(
            'POST',
            '/api/road-tax',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json'
            ],
            json_encode(['vehicleId' => 1, 'startDate' => '2026-01-01'])
        );

        $create = json_decode($client->getResponse()->getContent(), true);
        $id = $create['id'];

        $client->request(
            'PUT',
            '/api/road-tax/' . $id,
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json'
            ],
            json_encode(['notes' => 'Updated note', 'amount' => 150.00])
        );

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Updated note', $data['notes']);
    }

    public function testDeleteRoadTax(): void
    {
        $client = $this->client;
// Create first
        $client->request(
            'POST',
            '/api/road-tax',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json'
            ],
            json_encode(['vehicleId' => 1, 'startDate' => '2026-01-01'])
        );

        $create = json_decode($client->getResponse()->getContent(), true);
        $id = $create['id'];

        $client->request(
            'DELETE',
            '/api/road-tax/' . $id,
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertEquals(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
    }
}
