<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaValidator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DatabaseSchemaTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testDoctrineSchemaIsInSyncWithMetadata(): void
    {
        $validator = new SchemaValidator($this->entityManager);

        $this->assertTrue(
            $validator->schemaInSyncWithMetadata(),
            "Schema drift detected:\n" . implode("\n", $validator->getUpdateSchemaList())
        );
    }

    public function testMigrationFileCountMatchesConsolidatedPlusIncrementals(): void
    {
        $migrationsDir = self::getContainer()->getParameter('kernel.project_dir') . '/migrations';
        $files = glob($migrationsDir . '/Version*.php') ?: [];

        $this->assertGreaterThanOrEqual(
            2,
            count($files),
            'Expected at least a consolidated baseline migration and one incremental migration.'
        );

        $baselines = array_filter(
            $files,
            static fn (string $file): bool => str_contains($file, 'Version20260512220157.php')
        );
        $this->assertNotEmpty($baselines, 'Expected consolidated baseline migration Version20260512220157.php.');
    }

    public function testStockItemsTableHasExpectedColumns(): void
    {
        $schemaManager = $this->entityManager->getConnection()->createSchemaManager();
        $tables = array_map('strtolower', $schemaManager->listTableNames());

        $this->assertContains('stock_items', $tables);

        $columns = array_change_key_case($schemaManager->listTableColumns('stock_items'), CASE_LOWER);

        $this->assertArrayHasKey('user_id', $columns);
        $this->assertArrayHasKey('vehicle_type_id', $columns);
        $this->assertArrayHasKey('item_type', $columns);
        $this->assertArrayHasKey('category', $columns);
        $this->assertArrayHasKey('quantity', $columns);
        $this->assertArrayHasKey('supplier', $columns);
        $this->assertArrayHasKey('description', $columns);
        $this->assertArrayHasKey('price', $columns);
        $this->assertArrayHasKey('notes', $columns);
        $this->assertArrayHasKey('purchase_date', $columns);
        $this->assertArrayHasKey('part_number', $columns);
        $this->assertArrayHasKey('manufacturer', $columns);
        $this->assertArrayHasKey('warranty', $columns);
        $this->assertArrayHasKey('created_at', $columns);
        $this->assertArrayHasKey('updated_at', $columns);

        $this->assertArrayNotHasKey('mileage_at_installation', $columns);
    }

    public function testPartsAndConsumablesSupportGeneralStockOwnership(): void
    {
        $schemaManager = $this->entityManager->getConnection()->createSchemaManager();

        $partsColumns = array_change_key_case($schemaManager->listTableColumns('parts'), CASE_LOWER);
        $this->assertArrayHasKey('vehicle_id', $partsColumns);
        $this->assertArrayHasKey('user_id', $partsColumns);
        $this->assertFalse($partsColumns['vehicle_id']->getNotnull());
        $this->assertFalse($partsColumns['user_id']->getNotnull());

        $consumablesColumns = array_change_key_case($schemaManager->listTableColumns('consumables'), CASE_LOWER);
        $this->assertArrayHasKey('vehicle_id', $consumablesColumns);
        $this->assertArrayHasKey('user_id', $consumablesColumns);
        $this->assertFalse($consumablesColumns['vehicle_id']->getNotnull());
        $this->assertFalse($consumablesColumns['user_id']->getNotnull());
    }
}
