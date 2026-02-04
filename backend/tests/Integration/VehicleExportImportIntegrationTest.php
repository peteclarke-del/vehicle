<?php

namespace App\Tests\Integration;

use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\VehicleType;
use App\Service\VehicleExportService;
use App\Service\VehicleImportService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;

/**
 * Integration tests for the complete export/import cycle
 * Tests the full workflow with real database interactions
 * 
 * @group integration
 * @group database
 * 
 * Note: These tests require a properly configured test database.
 * Run migrations before executing: php bin/console doctrine:migrations:migrate --env=test
 */
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

        // Start transaction for test isolation
        $this->entityManager->beginTransaction();

        // Create test user
        $this->testUser = new User();
        $this->testUser->setEmail('integration-test@example.com');
        $this->testUser->setPassword('test');
        $this->testUser->setRoles(['ROLE_USER']);
        $this->testUser->setCreatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($this->testUser);
        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        // Rollback transaction to clean up test data
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }
        
        parent::tearDown();
    }

    /**
     * Test complete export/import cycle with basic vehicle data
     */
    public function testExportAndReimportBasicVehicle(): void
    {
        // Create a test vehicle
        $vehicleType = $this->getOrCreateVehicleType('Car');
        $vehicle = $this->createTestVehicle('TEST001', 'Toyota', 'Corolla', 2020, $vehicleType);
        
        $this->entityManager->flush();
        $this->entityManager->clear(); // Clear to ensure fresh load

        // Export the vehicle
        $exportResult = $this->exportService->exportVehicles($this->testUser, false, false);
        
        $this->assertTrue($exportResult->isSuccess(), 'Export should succeed');
        $exportedData = $exportResult->getData();
        $this->assertNotEmpty($exportedData, 'Should have exported data');
        $this->assertCount(1, $exportedData, 'Should have exported 1 vehicle');
        
        // Verify exported data structure
        $exportedVehicle = $exportedData[0];
        $this->assertEquals('TEST001', $exportedVehicle['registrationNumber']);
        $this->assertEquals('Toyota', $exportedVehicle['make']);
        $this->assertEquals('Corolla', $exportedVehicle['model']);
        $this->assertEquals(2020, $exportedVehicle['year']);

        // Delete the original vehicle
        $originalId = $vehicle->getId();
        $this->entityManager->remove($vehicle);
        $this->entityManager->flush();

        // Import the exported data
        $importResult = $this->importService->importVehicles($exportedData, $this->testUser);
        
        $this->assertTrue($importResult->isSuccess(), 'Import should succeed: ' . $importResult->getMessage());
        $stats = $importResult->getStatistics();
        $this->assertEquals(1, $stats['vehiclesImported'], 'Should have imported 1 vehicle');
        $this->assertEquals(0, $stats['errors'], 'Should have no errors');

        // Verify the vehicle was re-created
        $reimportedVehicle = $this->entityManager->getRepository(Vehicle::class)
            ->findOneBy(['registrationNumber' => 'TEST001']);
        
        $this->assertNotNull($reimportedVehicle, 'Vehicle should be re-imported');
        $this->assertNotEquals($originalId, $reimportedVehicle->getId(), 'Should be a new entity');
        $this->assertEquals('Toyota', $reimportedVehicle->getMake());
        $this->assertEquals('Corolla', $reimportedVehicle->getModel());
        $this->assertEquals(2020, $reimportedVehicle->getYear());
    }

    /**
     * Test duplicate detection prevents re-importing existing vehicles
     */
    public function testDuplicateDetectionPreventsReimport(): void
    {
        // Create a test vehicle
        $vehicleType = $this->getOrCreateVehicleType('Car');
        $vehicle = $this->createTestVehicle('DUP001', 'Honda', 'Civic', 2021, $vehicleType);
        
        $this->entityManager->flush();

        // Export the vehicle
        $exportResult = $this->exportService->exportVehicles($this->testUser, false, false);
        $exportedData = $exportResult->getData();
        
        $this->assertCount(1, $exportedData);

        // Try to import the same vehicle (should be rejected as duplicate)
        $importResult = $this->importService->importVehicles($exportedData, $this->testUser);
        
        // Import should complete but with errors for duplicates
        $this->assertFalse($importResult->isSuccess(), 'Import should fail due to duplicate');
        $errors = $importResult->getErrors();
        $this->assertNotEmpty($errors, 'Should have duplicate error');
        $this->assertStringContainsString('already exists', strtolower($errors[0]));
    }

    /**
     * Test export/import with multiple vehicles
     */
    public function testExportImportMultipleVehicles(): void
    {
        // Create multiple test vehicles
        $vehicleType = $this->getOrCreateVehicleType('Car');
        $vehicles = [
            $this->createTestVehicle('MULTI01', 'Toyota', 'Camry', 2019, $vehicleType),
            $this->createTestVehicle('MULTI02', 'Honda', 'Accord', 2020, $vehicleType),
            $this->createTestVehicle('MULTI03', 'Mazda', 'CX-5', 2021, $vehicleType),
        ];
        
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Export all vehicles
        $exportResult = $this->exportService->exportVehicles($this->testUser, false, false);
        
        $this->assertTrue($exportResult->isSuccess());
        $exportedData = $exportResult->getData();
        $this->assertCount(3, $exportedData, 'Should have exported 3 vehicles');

        // Delete all vehicles
        foreach ($vehicles as $v) {
            $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($v->getId());
            if ($vehicle) {
                $this->entityManager->remove($vehicle);
            }
        }
        $this->entityManager->flush();

        // Import all vehicles back
        $importResult = $this->importService->importVehicles($exportedData, $this->testUser);
        
        $this->assertTrue($importResult->isSuccess(), 'Import should succeed: ' . $importResult->getMessage());
        $stats = $importResult->getStatistics();
        $this->assertEquals(3, $stats['vehiclesImported'], 'Should have imported 3 vehicles');
        $this->assertEquals(0, $stats['errors'], 'Should have no errors');

        // Verify all vehicles were re-created
        $registrations = ['MULTI01', 'MULTI02', 'MULTI03'];
        foreach ($registrations as $reg) {
            $vehicle = $this->entityManager->getRepository(Vehicle::class)
                ->findOneBy(['registrationNumber' => $reg]);
            $this->assertNotNull($vehicle, "Vehicle $reg should be re-imported");
        }
    }

    /**
     * Test dry-run mode doesn't persist data
     */
    public function testDryRunModeDoesNotPersistData(): void
    {
        // Get initial vehicle count
        $initialCount = count($this->entityManager->getRepository(Vehicle::class)->findAll());

        // Prepare valid import data
        $vehicleType = $this->getOrCreateVehicleType('Car');
        $this->entityManager->flush();
        
        $importData = [
            [
                'registrationNumber' => 'DRYRUN01',
                'vehicleType' => 'Car',
                'make' => 'Test',
                'model' => 'DryRun',
                'year' => 2022,
                'name' => 'Dry Run Test',
                'purchaseCost' => '10000',
                'purchaseDate' => '2022-01-01'
            ]
        ];

        // Import in dry-run mode
        $importResult = $this->importService->importVehicles($importData, $this->testUser, null, true);
        
        $this->assertTrue($importResult->isSuccess(), 'Dry run should succeed');
        
        // Clear entity manager to force reload from database
        $this->entityManager->clear();
        
        // Verify no data was persisted
        $finalCount = count($this->entityManager->getRepository(Vehicle::class)->findAll());
        $this->assertEquals($initialCount, $finalCount, 'Vehicle count should not change in dry-run mode');
        
        $dryRunVehicle = $this->entityManager->getRepository(Vehicle::class)
            ->findOneBy(['registrationNumber' => 'DRYRUN01']);
        $this->assertNull($dryRunVehicle, 'Dry-run vehicle should not be persisted');
    }

    /**
     * Test export includes vehicle statistics and metadata
     */
    public function testExportIncludesStatisticsAndMetadata(): void
    {
        // Create a test vehicle
        $vehicleType = $this->getOrCreateVehicleType('Car');
        $this->createTestVehicle('STATS01', 'Ford', 'Focus', 2020, $vehicleType);
        
        $this->entityManager->flush();

        // Export with statistics
        $exportResult = $this->exportService->exportVehicles($this->testUser, false, false);
        
        $this->assertTrue($exportResult->isSuccess());
        
        // Check statistics
        $stats = $exportResult->getStatistics();
        $this->assertArrayHasKey('vehicleCount', $stats);
        $this->assertArrayHasKey('processingTimeSeconds', $stats);
        $this->assertArrayHasKey('memoryPeakMB', $stats);
        
        $this->assertEquals(1, $stats['vehicleCount']);
        $this->assertGreaterThan(0, $stats['processingTimeSeconds']);
        $this->assertGreaterThan(0, $stats['memoryPeakMB']);
    }

    /**
     * Test import validates required fields
     */
    public function testImportValidatesRequiredFields(): void
    {
        // Import data missing required fields
        $invalidData = [
            [
                'vehicleType' => 'Car',
                // Missing: name, registrationNumber, AND make+model
            ]
        ];

        $importResult = $this->importService->importVehicles($invalidData, $this->testUser);
        
        $this->assertFalse($importResult->isSuccess(), 'Import should fail with missing required fields');
        $errors = $importResult->getErrors();
        $this->assertNotEmpty($errors, 'Should have validation errors');
    }

    /**
     * Test import creates vehicle type if it doesn't exist
     */
    public function testImportCreatesVehicleTypeIfNotExists(): void
    {
        // Check if NewType exists and delete it
        $existingType = $this->entityManager->getRepository(VehicleType::class)
            ->findOneBy(['name' => 'NewType']);
        if ($existingType) {
            $this->entityManager->remove($existingType);
            $this->entityManager->flush();
        }

        // Import data with a new vehicle type
        $importData = [
            [
                'registrationNumber' => 'NEWTYPE01',
                'vehicleType' => 'NewType',
                'make' => 'Test',
                'model' => 'NewType',
                'year' => 2023,
                'name' => 'New Type Test',
                'purchaseCost' => '15000',
                'purchaseDate' => '2023-01-01'
            ]
        ];

        $importResult = $this->importService->importVehicles($importData, $this->testUser);
        
        $this->assertTrue($importResult->isSuccess(), 'Import should succeed and create new type');
        
        // Verify the new vehicle type was created
        $newType = $this->entityManager->getRepository(VehicleType::class)
            ->findOneBy(['name' => 'NewType']);
        $this->assertNotNull($newType, 'New vehicle type should be created');
        
        // Verify the vehicle was created with the new type
        $vehicle = $this->entityManager->getRepository(Vehicle::class)
            ->findOneBy(['registrationNumber' => 'NEWTYPE01']);
        $this->assertNotNull($vehicle, 'Vehicle should be created');
        $this->assertEquals('NewType', $vehicle->getVehicleType()->getName());
    }

    /**
     * Test export/import performance with larger dataset
     */
    public function testPerformanceWithLargerDataset(): void
    {
        // Create 20 test vehicles (reasonable size for integration test)
        $vehicleType = $this->getOrCreateVehicleType('Car');
        for ($i = 1; $i <= 20; $i++) {
            $this->createTestVehicle(
                sprintf('PERF%03d', $i),
                'Make' . $i,
                'Model' . $i,
                2020 + ($i % 5),
                $vehicleType
            );
        }
        
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Test export performance
        $exportStart = microtime(true);
        $exportResult = $this->exportService->exportVehicles($this->testUser, false, false);
        $exportTime = microtime(true) - $exportStart;
        
        $this->assertTrue($exportResult->isSuccess());
        $this->assertCount(20, $exportResult->getData());
        $this->assertLessThan(5.0, $exportTime, 'Export should complete in under 5 seconds');

        // Delete all vehicles
        $vehicles = $this->entityManager->getRepository(Vehicle::class)
            ->findBy(['user' => $this->testUser]);
        foreach ($vehicles as $vehicle) {
            $this->entityManager->remove($vehicle);
        }
        $this->entityManager->flush();

        // Test import performance
        $importStart = microtime(true);
        $importResult = $this->importService->importVehicles($exportResult->getData(), $this->testUser);
        $importTime = microtime(true) - $importStart;
        
        $this->assertTrue($importResult->isSuccess(), 'Import should succeed: ' . $importResult->getMessage());
        $stats = $importResult->getStatistics();
        $this->assertEquals(20, $stats['vehiclesImported']);
        $this->assertLessThan(10.0, $importTime, 'Import should complete in under 10 seconds');
    }

    /**
     * Test import handles transaction rollback on error
     */
    public function testImportRollsBackTransactionOnError(): void
    {
        // Get initial vehicle count
        $initialCount = count($this->entityManager->getRepository(Vehicle::class)->findAll());

        // Prepare data with one valid and one invalid vehicle
        $vehicleType = $this->getOrCreateVehicleType('Car');
        $this->entityManager->flush();
        
        $importData = [
            [
                'registrationNumber' => 'VALID01',
                'vehicleType' => 'Car',
                'make' => 'Valid',
                'model' => 'Vehicle',
                'year' => 2022,
                'name' => 'Valid Vehicle',
                'purchaseCost' => '10000',
                'purchaseDate' => '2022-01-01'
            ],
            [
                // Invalid: missing all required fields
                'vehicleType' => 'Car',
            ]
        ];

        // Import should fail
        $importResult = $this->importService->importVehicles($importData, $this->testUser);
        
        $this->assertFalse($importResult->isSuccess(), 'Import should fail due to invalid data');
        
        // Clear entity manager to force reload from database
        $this->entityManager->clear();
        
        // Verify transaction was rolled back - no vehicles should be added
        $finalCount = count($this->entityManager->getRepository(Vehicle::class)->findAll());
        $this->assertEquals($initialCount, $finalCount, 'No vehicles should be persisted after rollback');
    }

    /**
     * Helper: Get or create a vehicle type
     */
    private function getOrCreateVehicleType(string $name): VehicleType
    {
        $type = $this->entityManager->getRepository(VehicleType::class)
            ->findOneBy(['name' => $name]);
        
        if (!$type) {
            $type = new VehicleType();
            $type->setName($name);
            $type->setCreatedAt(new \DateTimeImmutable());
            $this->entityManager->persist($type);
        }
        
        return $type;
    }

    /**
     * Helper: Create a test vehicle
     */
    private function createTestVehicle(
        string $registration,
        string $make,
        string $model,
        int $year,
        VehicleType $type
    ): Vehicle {
        $vehicle = new Vehicle();
        $vehicle->setUser($this->testUser);
        $vehicle->setVehicleType($type);
        $vehicle->setRegistrationNumber($registration);
        $vehicle->setMake($make);
        $vehicle->setModel($model);
        $vehicle->setYear($year);
        $vehicle->setName("$make $model");
        $vehicle->setPurchaseCost('10000');
        $vehicle->setPurchaseDate(new \DateTime('2020-01-01'));
        $vehicle->setCreatedAt(new \DateTimeImmutable());
        
        $this->entityManager->persist($vehicle);
        
        return $vehicle;
    }
}
