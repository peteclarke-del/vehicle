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

namespace App\Tests\Integration;

use App\Entity\User;
use App\Entity\StockItem;
use App\Tests\TestCase\BaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class RoundTripExportImportTest extends BaseWebTestCase
{
    private string $testUserEmail = '';
    private string $importUserEmail = '';

    protected function setUp(): void
    {
        $this->testUserEmail = 'roundtrip-' . uniqid() . '@example.com';
        $this->importUserEmail = 'roundtrip-import-' . uniqid() . '@example.com';
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Cleanup test data
        try {
            $em = self::getContainer()->get(EntityManagerInterface::class);
            $user = $em->getRepository(User::class)->findOneBy(['email' => $this->testUserEmail]);
            if ($user) {
                $em->remove($user);
                $em->flush();
            }

            $importUser = $em->getRepository(User::class)->findOneBy(['email' => $this->importUserEmail]);
            if ($importUser) {
                $em->remove($importUser);
                $em->flush();
            }
        } catch (\Throwable $e) {
            // Ignore teardown errors
        }
    }

    public function testExportImportRoundTrip(): void
    {
        $client = self::createClient();
        $em = $this->getEntityManager();

        // Create test user
        $user = new User();
        $user->setEmail($this->testUserEmail);
        $user->setPassword('hashed');
        $user->setRoles(['ROLE_USER']);
        $user->setFirstName('Test');
        $user->setLastName('User');

        $em->persist($user);
        $em->flush();

        $headers = ['HTTP_X_TEST_MOCK_AUTH' => $this->testUserEmail];

        // Step 1: Create test data
        $testVehicleCount = 3;
        $testStockCount = 2;

        $user = $em->getRepository(User::class)->findOneBy(['email' => $this->testUserEmail]);
        $this->assertInstanceOf(User::class, $user);
        
        // Create vehicles using helper method
        for ($i = 0; $i < $testVehicleCount; $i++) {
            $this->createTestVehicle(
                $user,
                'TEST' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT)
            );
        }

        for ($i = 0; $i < $testStockCount; $i++) {
            $stock = new StockItem();
            $stock->setUser($user);
            $stock->setItemType('Part');
            $stock->setCategory('Engine');
            $stock->setDescription('Stock Item ' . ($i + 1));
            $stock->setQuantity('5');

            $em->persist($stock);
        }

        $em->flush();

        // Step 2: Export ZIP
        $client->request('GET', '/api/vehicles/export-zip', [], [], $headers);

        $response = $client->getResponse();
        $this->assertEquals(
            200,
            $response->getStatusCode(),
            'Export should succeed. Status: ' . $response->getStatusCode());

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $sourceZipPath = (string) $response->getFile()->getPathname();
        $this->assertFileExists($sourceZipPath);

        // Step 3: Write ZIP to temp file
        $zipPath = sys_get_temp_dir() . '/roundtrip-' . uniqid() . '.zip';

        $copied = @copy($sourceZipPath, $zipPath);
        $this->assertTrue($copied, 'Could not copy exported ZIP to test temp path');

        $zip = new \ZipArchive();
        $opened = $zip->open($zipPath);
        $this->assertTrue($opened === true, 'Failed to open exported ZIP');

        $zipFiles = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $zipFiles[$name] = $zip->statIndex($i);
        }

        $this->assertArrayHasKey('backup.json', $zipFiles);
        $this->assertArrayHasKey('manifest.json', $zipFiles);

        // Extract and inspect backup.json
        $backupJson = $zip->getFromName('backup.json');
        $this->assertNotFalse($backupJson);
        $backup = json_decode((string) $backupJson, true);
        $this->assertIsArray($backup);

        $exportStats = [
            'vehicles' => count($backup['vehicles'] ?? []),
            'stockItems' => count($backup['stockItems'] ?? []),
            'globalState' => count($backup['globalState'] ?? []),
        ];

        // Show manifest
        $manifestJson = $zip->getFromName('manifest.json');
        $this->assertNotFalse($manifestJson);

        $zip->close();

        // Step 4: Import ZIP back
        $importUser = new User();
        $importUser->setEmail($this->importUserEmail);
        $importUser->setPassword('hashed');
        $importUser->setRoles(['ROLE_USER']);
        $importUser->setFirstName('Import');
        $importUser->setLastName('User');
        $em->persist($importUser);
        $em->flush();

        $importHeaders = ['HTTP_X_TEST_MOCK_AUTH' => $this->importUserEmail];

        $uploadedFile = new UploadedFile(
            $zipPath,
            'vehicles-export.zip',
            'application/zip',
            null,
            true
        );

        $client->request(
            'POST',
            '/api/vehicles/import-zip',
            [],
            ['file' => $uploadedFile],
            $importHeaders
        );

        $this->assertEquals(
            200,
            $client->getResponse()->getStatusCode(),
            'Import should succeed. Response: ' . $client->getResponse()->getContent());

        $importData = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($importData, 'Import response should be valid JSON');

        // Step 5: Verify statistics
        $stats = $importData['statistics'] ?? [];

        $importStats = [
            'vehiclesImported' => $stats['vehiclesImported'] ?? 0,
            'stockItemsImported' => $stats['stockItemsImported'] ?? 0,
            'attachmentsImported' => $stats['attachmentsImported'] ?? 0,
            'globalEntitiesImported' => $stats['globalEntitiesImported'] ?? 0,
        ];

        // Step 6: Data integrity check
        $this->assertEquals(
            $exportStats['vehicles'],
            $importStats['vehiclesImported'],
            'Vehicle count should match export'
        );

        $this->assertEquals(
            $exportStats['stockItems'],
            $importStats['stockItemsImported'],
            'Stock count should match export'
        );

        // Step 7: Check errors
        $errors = $importData['errors'] ?? [];
        $this->assertIsArray($errors);
        $this->assertCount(0, $errors, 'Import reported errors');

        $this->assertIsInt($importStats['globalEntitiesImported']);

        // Cleanup
        @unlink($zipPath);
    }
}

