<?php

/**
 * Round-trip ZIP export/import test
 * Tests exporting data as ZIP, then importing it back to verify integrity
 * 
 * Usage:
 *   # Using HTTP_X_TEST_MOCK_AUTH (testing mode)
 *   php tests/test_export_import_roundtrip.php
 *
 * Requires:
 *   - Docker environment running (docker-compose up -d)
 *   - Kernel booted for test fixtures (uses Symfony test client)
 */

declare(strict_types=1);

// Load Symfony test environment
require_once __DIR__ . '/bootstrap.php';

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\VehicleType;
use App\Entity\StockItem;

class RoundTripTest extends WebTestCase
{
    private string $testUserEmail = '';
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->testUserEmail = 'roundtrip-' . uniqid() . '@example.com';
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        
        // Create test user
        $user = new User();
        $user->setEmail($this->testUserEmail);
        $user->setPassword('hashed');
        $user->setRoles(['ROLE_USER']);
        $user->setFirstName('Test');
        $user->setLastName('User');
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Cleanup test data
        try {
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $this->testUserEmail]);
            if ($user) {
                $this->entityManager->remove($user);
                $this->entityManager->flush();
            }
        } catch (Throwable $e) {
            // Ignore teardown errors
        }
    }

    public function testExportImportRoundTrip(): void
    {
        $client = self::createClient();
        $headers = ['HTTP_X_TEST_MOCK_AUTH' => $this->testUserEmail];
        
        echo "\n=== ZIP Round-Trip Export/Import Test ===\n";
        
        // Step 1: Create test data
        echo "\nStep 1: Creating test data\n";
        $testVehicleCount = 3;
        $testStockCount = 2;
        
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $this->testUserEmail]);
        for ($i = 0; $i < $testVehicleCount; $i++) {
            $vehicle = new Vehicle();
            $vehicle->setOwner($user);
            $vehicle->setName('Test Vehicle ' . ($i + 1));
            $vehicle->setRegistrationNumber('TEST' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT));
            
            $this->entityManager->persist($vehicle);
        }
        
        for ($i = 0; $i < $testStockCount; $i++) {
            $stock = new StockItem();
            $stock->setOwner($user);
            $stock->setItemType('Part');
            $stock->setDescription('Stock Item ' . ($i + 1));
            $stock->setQuantity('5');
            
            $this->entityManager->persist($stock);
        }
        
        $this->entityManager->flush();
        echo "  ✓ Created $testVehicleCount vehicles and $testStockCount stock items\n";
        
        // Step 2: Export ZIP
        echo "\nStep 2: Exporting ZIP\n";
        $client->request('GET', '/api/vehicles/export-zip', [], [], $headers);
        
        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 
            'Export should succeed. Response: ' . $client->getResponse()->getContent());
        
        $zipContent = $client->getResponse()->getContent();
        $zipSize = strlen($zipContent);
        
        echo "  ✓ Export successful (size: " . round($zipSize / 1024, 2) . " KB)\n";
        
        // Step 3: Write ZIP to temp file
        echo "\nStep 3: Analyzing ZIP contents\n";
        $zipPath = sys_get_temp_dir() . '/roundtrip-' . uniqid() . '.zip';
        file_put_contents($zipPath, $zipContent);
        
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Failed to open ZIP');
        }
        
        $zipFiles = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $zipFiles[$name] = $zip->statIndex($i);
        }
        
        echo "  ✓ ZIP contains " . count($zipFiles) . " files\n";
        echo "    Files: " . implode(', ', array_keys($zipFiles)) . "\n";
        
        // Extract and inspect backup.json
        $backupJson = $zip->getFromName('backup.json');
        $backup = json_decode($backupJson, true);
        
        $exportStats = [
            'vehicles' => count($backup['vehicles'] ?? []),
            'stockItems' => count($backup['stockItems'] ?? []),
            'globalState' => count($backup['globalState'] ?? []),
        ];
        
        echo "\n  Export Statistics:\n";
        echo "    - Vehicles exported: " . $exportStats['vehicles'] . "\n";
        echo "    - Stock items exported: " . $exportStats['stockItems'] . "\n";
        echo "    - Global state groups: " . $exportStats['globalState'] . "\n";
        
        // Show manifest
        $manifestJson = $zip->getFromName('MANIFEST.json');
        if ($manifestJson) {
            $manifest = json_decode($manifestJson, true);
            echo "    - Export timestamp: " . ($manifest['exportedAt'] ?? 'N/A') . "\n";
            echo "    - Include global state: " . ($manifest['exportOptions']['includeGlobalState'] ? 'YES' : 'NO') . "\n";
        }
        
        $zip->close();
        
        // Step 4: Import ZIP back
        echo "\nStep 4: Importing ZIP\n";
        
        $uploadedFile = new \Symfony\Component\HttpFoundation\File\UploadedFile(
            $zipPath,
            'vehicles-export.zip',
            'application/zip'
        );
        
        $client->request(
            'POST',
            '/api/vehicles/import-zip',
            [],
            ['file' => $uploadedFile],
            $headers
        );
        
        $this->assertEquals(200, $client->getResponse()->getStatusCode(),
            'Import should succeed. Response: ' . $client->getResponse()->getContent());
        
        $importData = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($importData, 'Import response should be valid JSON');
        
        echo "  ✓ Import successful\n";
        
        // Step 5: Verify statistics
        echo "\nStep 5: Import Statistics\n";
        $stats = $importData['statistics'] ?? [];
        
        $importStats = [
            'vehiclesImported' => $stats['vehiclesImported'] ?? 0,
            'stockItemsImported' => $stats['stockItemsImported'] ?? 0,
            'attachmentsImported' => $stats['attachmentsImported'] ?? 0,
            'globalEntitiesImported' => $stats['globalEntitiesImported'] ?? 0,
        ];
        
        echo "    - Vehicles imported: " . $importStats['vehiclesImported'] . "\n";
        echo "    - Stock items imported: " . $importStats['stockItemsImported'] . "\n";
        echo "    - Attachments imported: " . $importStats['attachmentsImported'] . "\n";
        echo "    - Global entities imported: " . $importStats['globalEntitiesImported'] . "\n";
        
        // Step 6: Data integrity check
        echo "\nStep 6: Data Integrity Check\n";
        
        $this->assertEquals(
            $exportStats['vehicles'],
            $importStats['vehiclesImported'],
            'Vehicle count should match export'
        );
        echo "  ✓ Vehicle count matches (exported: " . $exportStats['vehicles'] . ", imported: " . $importStats['vehiclesImported'] . ")\n";
        
        $this->assertEquals(
            $exportStats['stockItems'],
            $importStats['stockItemsImported'],
            'Stock count should match export'
        );
        echo "  ✓ Stock count matches (exported: " . $exportStats['stockItems'] . ", imported: " . $importStats['stockItemsImported'] . ")\n";
        
        // Step 7: Check errors
        echo "\nStep 7: Error Check\n";
        $errors = $importData['errors'] ?? [];
        if (empty($errors)) {
            echo "  ✓ No errors reported\n";
        } else {
            echo "  ⚠ " . count($errors) . " errors:\n";
            foreach ($errors as $error) {
                echo "    - $error\n";
            }
        }
        
        // Cleanup
        @unlink($zipPath);
        
        // Summary
        echo "\n=== Test Summary ===\n";
        echo "✓ Round-trip test PASSED\n";
        echo "  - Export: " . $exportStats['vehicles'] . " vehicles, " . $exportStats['stockItems'] . " stock items\n";
        echo "  - Import: " . $importStats['vehiclesImported'] . " vehicles, " . $importStats['stockItemsImported'] . " stock items\n";
        echo "  - Global entities imported: " . $importStats['globalEntitiesImported'] . "\n";
    }
}

