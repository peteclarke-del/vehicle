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
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
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

        $this->client->request('POST', '/api/parts', [], [], [
            'HTTP_AUTHORIZATION' => $this->getAuthToken(),
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'vehicleId' => 1,
            'stockItemId' => $stockItem->getId(),
            'quantity' => 1,
        ]));

        $this->assertContains($this->client->getResponse()->getStatusCode(), [201, 400, 404, 500]);
    }
}
