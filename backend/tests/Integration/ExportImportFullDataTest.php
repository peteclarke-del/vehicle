<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\User;
use App\Entity\StockItem;
use App\Service\VehicleExportService;
use App\Service\VehicleImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests for export/import with stock items
 */
class ExportImportFullDataTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private VehicleExportService $exportService;
    private VehicleImportService $importService;
    private User $testUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->exportService = self::getContainer()->get(VehicleExportService::class);
        $this->importService = self::getContainer()->get(VehicleImportService::class);
        
        $this->entityManager->beginTransaction();
        
        $this->testUser = new User();
        $this->testUser->setEmail('stocktest@example.com');
        $this->testUser->setPassword('hashed');
        $this->testUser->setFirstName('Stock');
        $this->testUser->setLastName('Tester');
        $this->testUser->setRoles(['ROLE_USER']);
        $this->testUser->setCreatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($this->testUser);
        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }
        parent::tearDown();
    }

    public function testExportIncludesStockItems(): void
    {
        $stock = new StockItem();
        $stock->setUser($this->testUser);
        $stock->setItemType('Part');
        $stock->setCategory('Engine');
        $stock->setQuantity('5');
        $stock->setDescription('Test Item');
        $stock->setPrice('99.99');
        $this->entityManager->persist($stock);
        $this->entityManager->flush();
        
        $result = $this->exportService->exportVehicles($this->testUser, false);
        $this->assertTrue($result->isSuccess());
        
        $data = $result->getData();
        $this->assertArrayHasKey('stockItems', $data);
        $this->assertCount(1, $data['stockItems']);
        $this->assertEquals('Test Item', $data['stockItems'][0]['description']);
    }

    public function testImportWithStockItems(): void
    {
        $importData = [
            'stockItems' => [
                [
                    'itemType' => 'Fluid',
                    'category' => 'Coolant',
                    'quantity' => '10',
                    'description' => 'Import Test',
                    'price' => '25.00',
                ],
            ]
        ];
        
        $result = $this->importService->importVehicles($importData, $this->testUser);
        $this->assertTrue($result->isSuccess(), implode(', ', $result->getErrors()));
        
        $stats = $result->getStatistics();
        $this->assertEquals(1, $stats['stockItemsImported'] ?? 0);
        
        $items = $this->entityManager->getRepository(StockItem::class)
            ->findBy(['user' => $this->testUser, 'description' => 'Import Test']);
        $this->assertCount(1, $items);
    }
}
