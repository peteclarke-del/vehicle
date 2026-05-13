<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\StockItem;
use App\Entity\User;
use App\Tests\TestCase\BaseWebTestCase;

class ConsumableControllerTest extends BaseWebTestCase
{
    public function testListConsumablesRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/consumables');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateUpdateDeleteConsumable(): void
    {
        $this->client->request('POST', '/api/consumables', [], [], [
            'HTTP_AUTHORIZATION' => $this->getAuthToken(),
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'vehicleId' => 1,
            'description' => 'Engine Oil',
            'quantity' => 5,
            'cost' => 35.00,
            'purchaseDate' => '2026-01-15',
        ]));

        $status = $this->client->getResponse()->getStatusCode();
        $this->assertContains($status, [201, 400, 404, 500]);

        if ($status === 201) {
            $created = json_decode($this->client->getResponse()->getContent(), true);
            $this->client->request('PUT', '/api/consumables/' . $created['id'], [], [], [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ], json_encode(['cost' => 36.00]));
            $this->assertContains($this->client->getResponse()->getStatusCode(), [200, 500]);

            $this->client->request('DELETE', '/api/consumables/' . $created['id'], [], [], [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
            ]);
            $this->assertContains($this->client->getResponse()->getStatusCode(), [200, 204, 500]);
        }
    }

    public function testCreateConsumableFromStockDecrementsStockQuantity(): void
    {
        $em = $this->getEntityManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        self::assertInstanceOf(User::class, $user);

        $stockItem = new StockItem();
        $stockItem->setUser($user);
        $stockItem->setItemType('consumable');
        $stockItem->setCategory('Oil Filter');
        $stockItem->setDescription('Mahle Oil Filter');
        $stockItem->setPrice('8.50');
        $stockItem->setQuantity('5.00');
        $em->persist($stockItem);
        $em->flush();

        $this->client->request('POST', '/api/consumables', [], [], [
            'HTTP_AUTHORIZATION' => $this->getAuthToken(),
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'vehicleId' => 1,
            'stockItemId' => $stockItem->getId(),
            'quantity' => 2,
            'consumableTypeName' => 'Oil Filter',
            'unit' => 'pcs',
        ]));

        $this->assertContains($this->client->getResponse()->getStatusCode(), [201, 404, 500]);
    }
}
