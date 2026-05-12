<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Part;
use App\Entity\StockItem;
use App\Entity\User;
use App\Tests\TestCase\BaseWebTestCase;

class PartControllerTest extends BaseWebTestCase
{
    public function testCreatePartFromStockDecrementsStockQuantity(): void
    {
        $em = $this->getEntityManager();
        $user = $em
            ->getRepository(User::class)
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

        $payload = [
            'vehicleId' => 1,
            'stockItemId' => $stockItem->getId(),
            'quantity' => 1,
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
            json_encode($payload)
        );

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Bosch Oil Filter', $data['description']);

        $em->clear();
        $updatedStockItem = $em
            ->getRepository(StockItem::class)
            ->find($stockItem->getId());
        self::assertInstanceOf(StockItem::class, $updatedStockItem);
        $this->assertEquals(2.0, (float) $updatedStockItem->getQuantity());
    }
}
