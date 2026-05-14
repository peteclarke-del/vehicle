<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Attachment;
use App\Entity\User;
use App\Entity\StockItem;
use App\Entity\VehicleType;
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
    private string $projectDir;
    private array $createdFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->exportService = self::getContainer()->get(VehicleExportService::class);
        $this->importService = self::getContainer()->get(VehicleImportService::class);
        $this->projectDir = (string) self::getContainer()->getParameter('kernel.project_dir');
        
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
        foreach ($this->createdFiles as $filePath) {
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }

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

    public function testStockItemAttachmentAndVehicleTypeRoundTrip(): void
    {
        $vehicleType = new VehicleType();
        $vehicleType->setName('Motorcycle');
        $this->entityManager->persist($vehicleType);

        $relativePath = 'attachments/stock-roundtrip-' . uniqid('', true) . '.txt';
        $absolutePath = $this->projectDir . '/uploads/' . $relativePath;
        $absoluteDir = dirname($absolutePath);
        if (!is_dir($absoluteDir)) {
            mkdir($absoluteDir, 0777, true);
        }
        file_put_contents($absolutePath, 'stock attachment test');
        $this->createdFiles[] = $absolutePath;

        $attachment = new Attachment();
        $attachment->setUser($this->testUser);
        $attachment->setFilename(basename($relativePath));
        $attachment->setOriginalFilename('stock-receipt.txt');
        $attachment->setMimeType('text/plain');
        $attachment->setFileSize(filesize($absolutePath) ?: 0);
        $attachment->setStoragePath($relativePath);
        $attachment->setCategory('stock_item');
        $attachment->setDescription('Stock receipt');
        $this->entityManager->persist($attachment);

        $stock = new StockItem();
        $stock->setUser($this->testUser);
        $stock->setVehicleType($vehicleType);
        $stock->setItemType('Part');
        $stock->setCategory('Electrical');
        $stock->setQuantity('2.00');
        $stock->setSupplier('ACME');
        $stock->setDescription('Round trip stock item');
        $stock->setPrice('19.99');
        $stock->setReceiptAttachment($attachment);
        $this->entityManager->persist($stock);
        $this->entityManager->flush();

        $exportDir = sys_get_temp_dir() . '/vehicle-stock-export-' . uniqid();
        mkdir($exportDir, 0777, true);

        $exportResult = $this->exportService->exportVehicles(
            $this->testUser,
            false,
            true,
            $exportDir
        );

        $this->assertTrue(
            $exportResult->isSuccess(),
            $exportResult->getMessage() ?? 'Export failed'
        );

        $importUser = new User();
        $importUser->setEmail('stock-import@example.com');
        $importUser->setPassword('hashed');
        $importUser->setFirstName('Import');
        $importUser->setLastName('User');
        $importUser->setRoles(['ROLE_USER']);
        $importUser->setCreatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($importUser);
        $this->entityManager->flush();

        $importResult = $this->importService->importVehicles(
            $exportResult->getData(),
            $importUser,
            $exportDir
        );

        $this->assertTrue($importResult->isSuccess(), implode(', ', $importResult->getErrors()));

        $importedItems = $this->entityManager->getRepository(StockItem::class)
            ->findBy(['user' => $importUser, 'description' => 'Round trip stock item']);

        $this->assertCount(1, $importedItems);
        $this->assertSame('Motorcycle', $importedItems[0]->getVehicleType()?->getName());
        $this->assertNotNull($importedItems[0]->getReceiptAttachment());
        $this->assertSame(
            'stock_item',
            $importedItems[0]->getReceiptAttachment()?->getEntityType()
        );
        $this->assertSame(
            $importedItems[0]->getId(),
            $importedItems[0]->getReceiptAttachment()?->getEntityId()
        );

        @unlink($exportDir . '/attachments/' . ($exportResult->getData()['stockItems'][0]['receiptAttachment']['exportFilename'] ?? ''));
        @rmdir($exportDir . '/attachments');
        @rmdir($exportDir);
    }
}
