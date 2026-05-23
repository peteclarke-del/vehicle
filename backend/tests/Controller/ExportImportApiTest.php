<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\StockItem;
use App\Entity\User;
use App\Entity\VehicleImage;
use App\Tests\TestCase\BaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ExportImportApiTest extends BaseWebTestCase
{
    private EntityManagerInterface $entityManager;
    private string $apiUserEmail;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = $this->getEntityManager();
        $this->apiUserEmail = 'export-import-api-' . uniqid() . '@example.com';

        $user = new User();
        $user->setEmail($this->apiUserEmail);
        $user->setPassword('hashed');
        $user->setRoles(['ROLE_USER']);
        $user->setFirstName('Export');
        $user->setLastName('Api');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function testExportJsonIncludesStockItems(): void
    {
        $user = $this->getTestUser();
        $this->createTestStockItem($user, 'API Export Stock ' . uniqid());

        $this->client->request('GET', '/api/vehicles/export?format=json', [], [], [
            'HTTP_X_TEST_MOCK_AUTH' => $this->apiUserEmail,
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('vehicles', $data);
        $this->assertArrayHasKey('stockItems', $data);
        $this->assertIsArray($data['vehicles']);
        $this->assertIsArray($data['stockItems']);
    }

    public function testExportStockEndpointWorks(): void
    {
        $user = $this->getTestUser();
        $this->createTestStockItem($user, 'API Stock A ' . uniqid());
        $this->createTestStockItem($user, 'API Stock B ' . uniqid());

        $this->client->request('GET', '/api/vehicles/export-stock', [], [], [
            'HTTP_X_TEST_MOCK_AUTH' => $this->apiUserEmail,
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('stockItems', $data);
        $this->assertIsArray($data['stockItems']);
    }

    public function testExportZipIncludesStockFile(): void
    {
        $user = $this->getTestUser();
        $this->createTestStockItem($user, 'ZIP Stock ' . uniqid());

        $this->client->request('GET', '/api/vehicles/export-zip', [], [], [
            'HTTP_X_TEST_MOCK_AUTH' => $this->apiUserEmail,
        ]);

        $this->assertResponseIsSuccessful();

        $response = $this->client->getResponse();
        $contentType = (string) $response->headers->get('Content-Type');
        $contentDisposition = (string) $response->headers->get('Content-Disposition');

        $this->assertStringContainsString('zip', strtolower($contentType));
        $this->assertStringContainsString('.zip', $contentDisposition);
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertNull($response->headers->get('Content-Encoding'));

        $zipPath = (string) $response->getFile()?->getPathname();
        $this->assertFileExists($zipPath);

        $zip = new \ZipArchive();
        $opened = $zip->open($zipPath);
        $this->assertTrue($opened === true, 'Failed to open exported ZIP');
        $this->assertNotFalse($zip->locateName('backup.json'));
        $this->assertNotFalse($zip->locateName('vehicles.json'));
        $this->assertNotFalse($zip->locateName('stock.json'));
        $zip->close();
    }

    public function testExportZipExcludeImagesWhenIncludeImagesFalse(): void
    {
        $user = $this->getTestUser();
        $registration = 'IMG' . random_int(1000, 9999);
        $vehicle = $this->createTestVehicle($user, $registration);

        $projectDir = (string) self::getContainer()->getParameter('kernel.project_dir');
        $vehicleSlug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $registration));
        $vehicleSlug = trim($vehicleSlug, '-');
        $relativePath = '/uploads/vehicles/' . $vehicleSlug . '/test-image.jpg';
        $absolutePath = $projectDir . $relativePath;

        $imageDir = dirname($absolutePath);
        if (!is_dir($imageDir)) {
            mkdir($imageDir, 0777, true);
        }
        file_put_contents($absolutePath, 'fake-image-bytes');

        $image = new VehicleImage();
        $image->setVehicle($vehicle);
        $image->setPath($relativePath);
        $image->setCaption('Test image');
        $image->setIsPrimary(true);
        $this->entityManager->persist($image);
        $this->entityManager->flush();

        $this->client->request('GET', '/api/vehicles/export-zip?includeImages=false', [], [], [
            'HTTP_X_TEST_MOCK_AUTH' => $this->apiUserEmail,
        ]);

        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $this->assertInstanceOf(BinaryFileResponse::class, $response);

        $zipPath = (string) $response->getFile()?->getPathname();
        $this->assertFileExists($zipPath);

        $zip = new \ZipArchive();
        $opened = $zip->open($zipPath);
        $this->assertTrue($opened === true, 'Failed to open exported ZIP');

        $hasImageFiles = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (str_starts_with($name, 'images/')) {
                $hasImageFiles = true;
                break;
            }
        }

        $backupJson = $zip->getFromName('backup.json');
        $this->assertNotFalse($backupJson);
        $backup = json_decode((string) $backupJson, true);
        $this->assertIsArray($backup);

        $vehiclePayload = null;
        foreach (($backup['vehicles'] ?? []) as $row) {
            if (($row['registrationNumber'] ?? null) === $registration) {
                $vehiclePayload = $row;
                break;
            }
        }

        $zip->close();

        $this->assertFalse($hasImageFiles, 'ZIP should not include images/ files when includeImages=false');
        $this->assertNotNull($vehiclePayload);
        $this->assertTrue(
            !array_key_exists('vehicleImages', $vehiclePayload)
            || empty($vehiclePayload['vehicleImages']),
            'Vehicle payload should not include vehicleImages data when includeImages=false'
        );
    }

    public function testExportZipAsyncQueueStatusAndDownload(): void
    {
        $this->client->request('POST', '/api/vehicles/export-zip-async', [], [], [
            'HTTP_X_TEST_MOCK_AUTH' => $this->apiUserEmail,
        ]);

        $this->assertResponseStatusCodeSame(202);
        $queued = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertIsArray($queued);
        $this->assertArrayHasKey('jobId', $queued);
        $this->assertArrayHasKey('statusUrl', $queued);

        $statusUrl = (string) ($queued['statusUrl'] ?? '');
        $this->assertNotSame('', $statusUrl);

        $statusData = null;
        for ($attempt = 0; $attempt < 25; $attempt++) {
            $this->client->request('GET', $statusUrl, [], [], [
                'HTTP_X_TEST_MOCK_AUTH' => $this->apiUserEmail,
            ]);
            $this->assertResponseIsSuccessful();

            $statusData = json_decode($this->client->getResponse()->getContent(), true);
            if (($statusData['status'] ?? null) === 'completed') {
                break;
            }

            usleep(100000);
        }

        $this->assertIsArray($statusData);
        $this->assertSame('completed', $statusData['status'] ?? null);
        $this->assertArrayHasKey('downloadUrl', $statusData);
        $this->assertNotEmpty($statusData['downloadUrl']);

        $this->client->request('GET', (string) $statusData['downloadUrl'], [], [], [
            'HTTP_X_TEST_MOCK_AUTH' => $this->apiUserEmail,
        ]);

        $this->assertResponseIsSuccessful();
        $downloadResponse = $this->client->getResponse();
        $this->assertInstanceOf(BinaryFileResponse::class, $downloadResponse);

        $contentType = (string) $downloadResponse->headers->get('Content-Type');
        $this->assertStringContainsString('zip', strtolower($contentType));

        $zipPath = (string) $downloadResponse->getFile()?->getPathname();
        $this->assertFileExists($zipPath);
    }

    public function testImportJsonWithStockItems(): void
    {
        $uniqueDesc = 'API Import Stock ' . uniqid();

        $payload = [
            'stockItems' => [
                [
                    'itemType' => 'Part',
                    'category' => 'Engine',
                    'quantity' => '3',
                    'description' => $uniqueDesc,
                    'price' => '42.50',
                ],
            ],
        ];

        $this->client->request('POST', '/api/vehicles/import', [], [], [
            'HTTP_X_TEST_MOCK_AUTH' => $this->apiUserEmail,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $this->assertResponseIsSuccessful();

        $items = $this->entityManager->getRepository(StockItem::class)->findBy([
            'user' => $this->getTestUser(),
            'description' => $uniqueDesc,
        ]);
        $this->assertGreaterThanOrEqual(1, count($items));
    }

    public function testImportZipWithStockFile(): void
    {
        $uniqueDesc = 'API ZIP Import Stock ' . uniqid();

        $vehiclesData = [];
        $stockData = [
            'stockItems' => [
                [
                    'itemType' => 'Consumable',
                    'category' => 'Oil',
                    'quantity' => '2',
                    'description' => $uniqueDesc,
                    'price' => '19.99',
                ],
            ],
        ];

        $zipPath = sys_get_temp_dir() . '/api-import-' . uniqid() . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('vehicles.json', json_encode($vehiclesData));
        $zip->addFromString('stock.json', json_encode($stockData));
        $zip->close();

        $uploaded = new UploadedFile(
            $zipPath,
            'import.zip',
            'application/zip',
            null,
            true
        );

        $this->client->request('POST', '/api/vehicles/import-zip', [], [
            'file' => $uploaded,
        ], [
            'HTTP_X_TEST_MOCK_AUTH' => $this->apiUserEmail,
        ]);

        $this->assertResponseIsSuccessful();

        $items = $this->entityManager->getRepository(StockItem::class)->findBy([
            'user' => $this->getTestUser(),
            'description' => $uniqueDesc,
        ]);
        $this->assertGreaterThanOrEqual(1, count($items));

        @unlink($zipPath);
    }

    private function getTestUser(): User
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $this->apiUserEmail]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function createTestStockItem(User $user, string $description): StockItem
    {
        $stock = new StockItem();
        $stock->setUser($user);
        $stock->setItemType('Part');
        $stock->setCategory('General');
        $stock->setQuantity('5.00');
        $stock->setDescription($description);
        $stock->setPrice('99.99');

        $this->entityManager->persist($stock);
        $this->entityManager->flush();

        return $stock;
    }
}
