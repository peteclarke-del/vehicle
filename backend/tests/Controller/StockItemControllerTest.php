<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\StockItem;
use App\Entity\User;
use App\Tests\TestCase\BaseWebTestCase;

class StockItemControllerTest extends BaseWebTestCase
{
    public function testListStockItemsSupportsFiltersAndVehicleTypeField(): void
    {
        $em = $this->getEntityManager();
        $user = $em
            ->getRepository(User::class)
            ->findOneBy(['email' => 'test@example.com']);
        self::assertInstanceOf(User::class, $user);

        $vehicleType = $this->getVehicleType('Van');
        $seed = uniqid('stock-list-', true);

        $matching = new StockItem();
        $matching->setUser($user);
        $matching->setVehicleType($vehicleType);
        $matching->setItemType('part');
        $matching->setCategory('Brake Pads ' . $seed);
        $matching->setDescription('Matching stock item');
        $matching->setQuantity('2.00');
        $em->persist($matching);

        $differentType = new StockItem();
        $differentType->setUser($user);
        $differentType->setItemType('consumable');
        $differentType->setCategory('Oil');
        $differentType->setDescription('Should be filtered out');
        $differentType->setQuantity('5.00');
        $em->persist($differentType);

        $outOfStock = new StockItem();
        $outOfStock->setUser($user);
        $outOfStock->setVehicleType($vehicleType);
        $outOfStock->setItemType('part');
        $outOfStock->setCategory('Disc ' . $seed);
        $outOfStock->setDescription('Out of stock');
        $outOfStock->setQuantity('0.00');
        $em->persist($outOfStock);

        $em->flush();

        $query = sprintf(
            '/api/stock-items?itemType=part&vehicleTypeId=%d&inStock=true',
            $vehicleType->getId()
        );
        $this->client->request(
            'GET',
            $query,
            [],
            [],
            ['HTTP_AUTHORIZATION' => $this->getAuthToken()]
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $matchingItem = null;
        foreach ($data as $item) {
            if (($item['id'] ?? null) === $matching->getId()) {
                $matchingItem = $item;
                break;
            }
        }

        $this->assertNotNull($matchingItem);
        $this->assertSame('part', $matchingItem['itemType']);
        $this->assertSame($vehicleType->getId(), $matchingItem['vehicleTypeId']);
        $this->assertGreaterThan(0, (float) $matchingItem['quantity']);

        foreach ($data as $item) {
            $this->assertNotSame($outOfStock->getId(), $item['id'] ?? null);
        }
    }

    public function testAdjustStockItemByIdUpdatesQuantity(): void
    {
        $em = $this->getEntityManager();
        $user = $em
            ->getRepository(User::class)
            ->findOneBy(['email' => 'test@example.com']);
        self::assertInstanceOf(User::class, $user);
        $seed = uniqid('stock-adjust-', true);

        $stockItem = new StockItem();
        $stockItem->setUser($user);
        $stockItem->setItemType('part');
        $stockItem->setCategory('Battery ' . $seed);
        $stockItem->setQuantity('1.00');
        $em->persist($stockItem);
        $em->flush();

        $this->client->request(
            'POST',
            '/api/stock-items/adjust',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'stockItemId' => $stockItem->getId(),
                'delta' => 2,
            ])
        );

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue((bool) ($response['success'] ?? false));

        $em->clear();
        $updated = $em->getRepository(StockItem::class)->find($stockItem->getId());
        self::assertInstanceOf(StockItem::class, $updated);
        $this->assertEquals(3.0, (float) $updated->getQuantity());
    }

    public function testUpdateStockItemByIdUpdatesMetadataAndQuantity(): void
    {
        $em = $this->getEntityManager();
        $user = $em
            ->getRepository(User::class)
            ->findOneBy(['email' => 'test@example.com']);
        self::assertInstanceOf(User::class, $user);

        $stockItem = new StockItem();
        $stockItem->setUser($user);
        $stockItem->setItemType('part');
        $stockItem->setCategory('Filter Original');
        $stockItem->setDescription('Original description');
        $stockItem->setQuantity('2.00');
        $em->persist($stockItem);
        $em->flush();

        $this->client->request(
            'PUT',
            '/api/stock-items/' . $stockItem->getId(),
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => $this->getAuthToken(),
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'itemType' => 'part',
                'category' => 'Oil Filter Updated',
                'description' => 'Updated description',
                'supplier' => 'Updated Supplier',
                'quantity' => 5,
            ])
        );

        $this->assertResponseIsSuccessful();

        $em->clear();
        $updated = $em->getRepository(StockItem::class)->find($stockItem->getId());
        self::assertInstanceOf(StockItem::class, $updated);
        $this->assertSame('Oil Filter Updated', $updated->getCategory());
        $this->assertSame('Updated description', $updated->getDescription());
        $this->assertSame('Updated Supplier', $updated->getSupplier());
        $this->assertEquals(5.0, (float) $updated->getQuantity());
    }
}
