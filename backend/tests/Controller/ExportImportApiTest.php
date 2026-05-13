<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\StockItem;
use App\Entity\User;
use App\Tests\TestCase\BaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
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

        $contentType = (string) $this->client->getResponse()->headers->get('Content-Type');
        $contentDisposition = (string) $this->client->getResponse()->headers->get('Content-Disposition');

        $this->assertStringContainsString('zip', strtolower($contentType));
        $this->assertStringContainsString('.zip', $contentDisposition);
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
