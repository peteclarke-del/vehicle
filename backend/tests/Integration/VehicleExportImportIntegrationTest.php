<?php

namespace App\Tests\Integration;

use App\Entity\User;
use App\Service\VehicleExportService;
use App\Service\VehicleImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class VehicleExportImportIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private VehicleExportService $exportService;
    private VehicleImportService $importService;
    private User $testUser;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->exportService = $container->get(VehicleExportService::class);
        $this->importService = $container->get(VehicleImportService::class);

        $this->entityManager->beginTransaction();

        $this->testUser = new User();
        $this->testUser->setEmail('integration-test@example.com');
        $this->testUser->setPassword('test');
        $this->testUser->setFirstName('Integration');
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

    public function testServicesAreAvailable(): void
    {
        $this->assertInstanceOf(VehicleExportService::class, $this->exportService);
        $this->assertInstanceOf(VehicleImportService::class, $this->importService);
    }

    public function testImportValidatesRequiredFields(): void
    {
        $invalidData = [
            ['registrationNumber' => ''],
        ];

        $result = $this->importService->importVehicles($invalidData, $this->testUser);
        $this->assertFalse($result->isSuccess());
    }
}
