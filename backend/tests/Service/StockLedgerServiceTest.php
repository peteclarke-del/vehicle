<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\StockItem;
use App\Entity\User;
use App\Service\StockLedgerService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class StockLedgerServiceTest extends TestCase
{
    public function testAdjustCreatesStockItemForPositiveDeltaWhenNoExistingBucket(): void
    {
        $user = (new User())
            ->setEmail('stock-test@example.com')
            ->setPassword('secret')
            ->setRoles(['ROLE_USER'])
            ->setFirstName('Stock')
            ->setLastName('Tester');

        $repo = $this->createMock(EntityRepository::class);
        $repo->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('getRepository')
            ->with(StockItem::class)
            ->willReturn($repo);

        $em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($item): bool {
                if (!$item instanceof StockItem) {
                    return false;
                }

                return $item->getItemType() === 'part'
                    && $item->getCategory() === 'Oil Filter'
                    && $item->getSupplier() === 'Supplier A'
                    && (float) $item->getQuantity() === 2.5;
            }));

        $service = new StockLedgerService($em);
        $service->adjust($user, null, 'part', 'Oil Filter', 'Supplier A', 2.5);
    }

    public function testAdjustDoesNotCreateStockItemForNegativeDeltaWhenNoExistingBucket(): void
    {
        $user = (new User())
            ->setEmail('stock-test@example.com')
            ->setPassword('secret')
            ->setRoles(['ROLE_USER'])
            ->setFirstName('Stock')
            ->setLastName('Tester');

        $repo = $this->createMock(EntityRepository::class);
        $repo->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('getRepository')
            ->with(StockItem::class)
            ->willReturn($repo);

        $em->expects($this->never())->method('persist');

        $service = new StockLedgerService($em);
        $service->adjust($user, null, 'part', 'Oil Filter', 'Supplier A', -1.0);
    }

    public function testAdjustUpdatesExistingStockItemAndCapsAtZero(): void
    {
        $user = (new User())
            ->setEmail('stock-test@example.com')
            ->setPassword('secret')
            ->setRoles(['ROLE_USER'])
            ->setFirstName('Stock')
            ->setLastName('Tester');

        $existing = (new StockItem())
            ->setUser($user)
            ->setItemType('consumable')
            ->setCategory('Coolant')
            ->setSupplier('Supplier B')
            ->setQuantity('1.00');

        $repo = $this->createMock(EntityRepository::class);
        $repo->expects($this->once())
            ->method('findOneBy')
            ->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('getRepository')
            ->with(StockItem::class)
            ->willReturn($repo);
        $em->expects($this->never())->method('persist');

        $service = new StockLedgerService($em);
        $service->adjust($user, null, 'consumable', 'Coolant', 'Supplier B', -5.0);

        $this->assertSame('0.00', $existing->getQuantity());
    }

    public function testCategoryFallbackHelpers(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $service = new StockLedgerService($em);

        $this->assertSame('Brakes', $service->categoryForPart('Front brake pads', 'Brakes'));
        $this->assertSame('Front brake pads', $service->categoryForPart('Front brake pads', null));
        $this->assertSame('Part', $service->categoryForPart('', null));

        $this->assertSame('Oil', $service->categoryForConsumable('5W30 Engine Oil', 'Oil'));
        $this->assertSame('5W30 Engine Oil', $service->categoryForConsumable('5W30 Engine Oil', null));
        $this->assertSame('Consumable', $service->categoryForConsumable('', null));
    }
}
