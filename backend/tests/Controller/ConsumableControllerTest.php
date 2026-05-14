<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ConsumableType;
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
        $em = $this->getEntityManager();
        $user = $em->getRepository(User::class)
            ->findOneBy(['email' => 'test@example.com']);
        $vehicle = $this->createTestVehicle($user, 'CON-' . uniqid());

        $consumableType = new ConsumableType();
        $consumableType->setVehicleType($vehicle->getVehicleType());
        $consumableType->setName('Engine Oil');
        $consumableType->setUnit('L');
        $em->persist($consumableType);
        $em->flush();

        $payload = [
            'vehicleId' => $vehicle->getId(),
            'consumableTypeName' => 'Engine Oil',
            'description' => 'Engine Oil',
            'quantity' => 5,
            'cost' => 35.00,
            'purchaseDate' => '2026-01-15',
        ];

        $this->client->request(
            'POST',
            '/api/consumables',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($payload)
        );

        $this->assertSame(201, $this->client->getResponse()->getStatusCode());
        $created = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true
        );
        $this->assertIsArray($created);
        $this->assertArrayHasKey('id', $created);

        $this->client->request(
            'PUT',
            '/api/consumables/' . $created['id'],
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['cost' => 36.00])
        );
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $updated = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true
        );
        $this->assertIsArray($updated);
        $this->assertArrayHasKey('cost', $updated);

        $this->client->request(
            'DELETE',
            '/api/consumables/' . $created['id'],
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
            ]
        );
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
        $deleted = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true
        );
        $this->assertIsArray($deleted);
        $this->assertSame('Consumable deleted successfully', $deleted['message']);
    }

    public function testCreateConsumableFromStockDecrementsStockQuantity(): void
    {
        $em = $this->getEntityManager();
        $user = $em->getRepository(User::class)
            ->findOneBy(['email' => 'test@example.com']);
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

        $vehicle = $this->createTestVehicle($user, 'CONS-' . uniqid());
        $payload = [
            'vehicleId' => $vehicle->getId(),
            'stockItemId' => $stockItem->getId(),
            'quantity' => 2,
            'consumableTypeName' => 'Oil Filter',
            'unit' => 'pcs',
        ];

        $this->client->request(
            'POST',
            '/api/consumables',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($payload)
        );

        $this->assertSame(201, $this->client->getResponse()->getStatusCode());
        $responseData = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true
        );
        $this->assertIsArray($responseData);
        $this->assertArrayHasKey('id', $responseData);

        $em->clear();
        $updatedStockItem = $em->getRepository(StockItem::class)
            ->find($stockItem->getId());
        $this->assertInstanceOf(StockItem::class, $updatedStockItem);
        $this->assertSame('3', $updatedStockItem->getQuantity());
    }
}
