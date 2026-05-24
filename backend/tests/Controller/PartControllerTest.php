<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\StockItem;
use App\Entity\User;
use App\Tests\TestCase\BaseWebTestCase;

class PartControllerTest extends BaseWebTestCase
{
    public function testCreatePartFromStockDecrementsStockQuantity(): void
    {
        $em = $this->getEntityManager();
        $user = $em->getRepository(User::class)
            ->findOneBy(['email' => 'test@example.com']);
        self::assertInstanceOf(User::class, $user);

        $stockItem = new StockItem();
        $stockItem->setUser($user);
        $stockItem->setItemType('part');
        $stockItem->setCategory('Oil Filter');
        $stockItem->setDescription('Bosch Oil Filter');
        $stockItem->setPartNumber('BOF-1');
        $stockItem->setSupplier('Euro Car Parts');
        $stockItem->setPrice('9.99');
        $stockItem->setQuantity('3.00');
        $em->persist($stockItem);
        $em->flush();

        $vehicle = $this->createTestVehicle($user, 'PART-' . uniqid());

        $payloadData = [
            'vehicleId' => $vehicle->getId(),
            'stockItemId' => $stockItem->getId(),
            'quantity' => 1,
        ];
        $payload = json_encode($payloadData);

        $this->client->request(
            'POST',
            '/api/parts',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
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
        $this->assertSame('2', $updatedStockItem->getQuantity());
    }
}
