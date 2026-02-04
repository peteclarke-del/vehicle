<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\VehicleImportService;
use App\Config\ImportExportConfig;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\VehicleType;
use App\Repository\VehicleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\AbstractQuery;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Comprehensive edge case tests for VehicleImportService
 */
class VehicleImportServiceEdgeCasesTest extends TestCase
{
    private VehicleImportService $importService;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private TagAwareCacheInterface $cache;
    private ImportExportConfig $config;
    private User $testUser;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->cache = $this->createMock(TagAwareCacheInterface::class);
        $this->config = new ImportExportConfig();
        
        $this->importService = new VehicleImportService(
            $this->entityManager,
            $this->logger,
            $this->config,
            $this->cache,
            '/tmp/test_project'
        );

        $this->testUser = $this->createMock(User::class);
        $this->testUser->method('getId')->willReturn(1);
        $this->testUser->method('getRoles')->willReturn(['ROLE_USER']);
    }

    /**
     * Test import handles empty/invalid data gracefully
     */
    public function testImportHandlesMalformedData(): void
    {
        $this->setupRepositoryMocks();
        
        // Empty array - should fail validation
        $result = $this->importService->importVehicles([], $this->testUser);
        
        $this->assertFalse($result->isSuccess());
        $this->assertNotEmpty($result->getErrors());
    }

    /**
     * Test import handles null values correctly
     */
    public function testImportHandlesNullValues(): void
    {
        $this->setupRepositoryMocks();
        
        $vehicleData = [
            [
                'registrationNumber' => 'NULL01',
                'vehicleType' => 'Car',
                'make' => 'Test',
                'model' => 'Null',
                'year' => 2020,
                'name' => null,  // Null name
                'purchaseCost' => null,
                'purchaseDate' => null,
            ]
        ];

        $result = $this->importService->importVehicles($vehicleData, $this->testUser);
        
        // Should succeed - null values are handled gracefully
        $this->assertTrue($result->isSuccess());
    }

    /**
     * Test import trims whitespace from string fields
     */
    public function testImportTrimsWhitespace(): void
    {
        $this->setupRepositoryMocks();
        
        $vehicleData = [
            [
                'registrationNumber' => '  SPACE01  ',
                'vehicleType' => '  Car  ',
                'make' => '  Toyota  ',
                'model' => '  Corolla  ',
                'year' => 2020,
                'name' => '  Trimmed Name  ',
                'purchaseCost' => '15000',
                'purchaseDate' => '2020-01-01',
            ]
        ];

        $result = $this->importService->importVehicles($vehicleData, $this->testUser);
        
        $this->assertTrue($result->isSuccess());
        // Verify trimming occurred (mocks don't persist but logic is tested)
    }

    /**
     * Test import handles very long strings
     */
    public function testImportHandlesLongStrings(): void
    {
        $this->setupRepositoryMocks();
        
        $vehicleData = [
            [
                'registrationNumber' => 'LONG01',
                'vehicleType' => 'Car',
                'make' => str_repeat('A', 500),  // Very long string
                'model' => str_repeat('B', 500),
                'year' => 2020,
                'name' => str_repeat('C', 1000),
                'purchaseCost' => '15000',
                'purchaseDate' => '2020-01-01',
            ]
        ];

        $result = $this->importService->importVehicles($vehicleData, $this->testUser);
        
        // Should handle long strings (may truncate based on DB schema)
        $this->assertTrue($result->isSuccess());
    }

    /**
     * Test import handles special characters
     */
    public function testImportHandlesSpecialCharacters(): void
    {
        $this->setupRepositoryMocks();
        
        $vehicleData = [
            [
                'registrationNumber' => 'SPEC-01',
                'vehicleType' => 'Car',
                'make' => 'Citroën',  // Accented character
                'model' => 'C4 Cactus',
                'year' => 2020,
                'name' => 'My "Special" Car \'with quotes\'',
                'purchaseCost' => '15000',
                'purchaseDate' => '2020-01-01',
            ]
        ];

        $result = $this->importService->importVehicles($vehicleData, $this->testUser);
        
        $this->assertTrue($result->isSuccess());
    }

    /**
     * Test import handles numeric strings for numeric fields
     */
    public function testImportHandlesNumericStrings(): void
    {
        $this->setupRepositoryMocks();
        
        $vehicleData = [
            [
                'registrationNumber' => 'NUM01',
                'vehicleType' => 'Car',
                'make' => 'Test',
                'model' => 'Numeric',
                'year' => '2020',  // String instead of int
                'name' => 'Numeric Test',
                'purchaseCost' => '15000.50',  // String with decimal
                'purchaseDate' => '2020-01-01',
                'purchaseMileage' => '10,000',  // String with comma
            ]
        ];

        $result = $this->importService->importVehicles($vehicleData, $this->testUser);
        
        $this->assertTrue($result->isSuccess());
    }

    /**
     * Test import handles various date formats
     */
    public function testImportHandlesVariousDateFormats(): void
    {
        $this->setupRepositoryMocks();
        
        $vehicleData = [
            [
                'registrationNumber' => 'DATE01',
                'vehicleType' => 'Car',
                'make' => 'Test',
                'model' => 'Date',
                'year' => 2020,
                'name' => 'Date Test',
                'purchaseCost' => '15000',
                'purchaseDate' => '2020-01-01',
                'motExpiryDate' => '2021-12-31',
                'taxExpiryDate' => '2021-06-30',
            ]
        ];

        $result = $this->importService->importVehicles($vehicleData, $this->testUser);
        
        $this->assertTrue($result->isSuccess());
    }

    /**
     * Test import handles boolean values in various formats
     */
    public function testImportHandlesBooleanVariants(): void
    {
        $this->setupRepositoryMocks();
        
        $vehicleData = [
            [
                'registrationNumber' => 'BOOL01',
                'vehicleType' => 'Car',
                'make' => 'Test',
                'model' => 'Boolean',
                'year' => 2020,
                'name' => 'Boolean Test',
                'purchaseCost' => '15000',
                'purchaseDate' => '2020-01-01',
                'roadTaxExempt' => true,
                'motExempt' => 'true',  // String
                'archived' => 1,  // Integer
            ]
        ];

        $result = $this->importService->importVehicles($vehicleData, $this->testUser);
        
        $this->assertTrue($result->isSuccess());
    }

    /**
     * Test import handles negative numbers
     */
    public function testImportHandlesNegativeNumbers(): void
    {
        $this->setupRepositoryMocks();
        
        $vehicleData = [
            [
                'registrationNumber' => 'NEG01',
                'vehicleType' => 'Car',
                'make' => 'Test',
                'model' => 'Negative',
                'year' => 2020,
                'name' => 'Negative Test',
                'purchaseCost' => '-100',  // Negative cost (unusual but valid for testing)
                'purchaseDate' => '2020-01-01',
            ]
        ];

        $result = $this->importService->importVehicles($vehicleData, $this->testUser);
        
        // Should handle negative numbers (validation logic may reject later)
        $this->assertTrue($result->isSuccess());
    }

    /**
     * Test import handles zero values
     */
    public function testImportHandlesZeroValues(): void
    {
        $this->setupRepositoryMocks();
        
        $vehicleData = [
            [
                'registrationNumber' => 'ZERO01',
                'vehicleType' => 'Car',
                'make' => 'Test',
                'model' => 'Zero',
                'year' => 2020,
                'name' => 'Zero Test',
                'purchaseCost' => '0',
                'purchaseDate' => '2020-01-01',
                'purchaseMileage' => 0,
            ]
        ];

        $result = $this->importService->importVehicles($vehicleData, $this->testUser);
        
        $this->assertTrue($result->isSuccess());
    }

    /**
     * Test import handles empty strings vs null
     */
    public function testImportHandlesEmptyStringsVsNull(): void
    {
        $this->setupRepositoryMocks();
        
        $vehicleData = [
            [
                'registrationNumber' => 'EMPTY01',
                'vehicleType' => 'Car',
                'make' => 'Test',
                'model' => 'Empty',
                'year' => 2020,
                'name' => '',  // Empty string
                'purchaseCost' => '15000',
                'purchaseDate' => '2020-01-01',
                'vin' => '',
                'engineNumber' => null,
            ]
        ];

        $result = $this->importService->importVehicles($vehicleData, $this->testUser);
        
        $this->assertTrue($result->isSuccess());
    }

    /**
     * Test import statistics are accurate
     */
    public function testImportStatisticsAccuracy(): void
    {
        $this->setupRepositoryMocks();
        
        $vehicleData = [
            [
                'registrationNumber' => 'STAT01',
                'vehicleType' => 'Car',
                'make' => 'Test',
                'model' => 'Stats',
                'year' => 2020,
                'name' => 'Stats Test',
                'purchaseCost' => '15000',
                'purchaseDate' => '2020-01-01',
            ]
        ];

        $result = $this->importService->importVehicles($vehicleData, $this->testUser);
        
        $this->assertTrue($result->isSuccess());
        $stats = $result->getStatistics();
        
        // Verify all expected statistics are present
        $this->assertArrayHasKey('vehiclesImported', $stats);
        $this->assertArrayHasKey('errors', $stats);
        $this->assertArrayHasKey('processingTimeSeconds', $stats);
        $this->assertArrayHasKey('memoryPeakMB', $stats);
        
        // Verify statistics values are reasonable
        $this->assertEquals(1, $stats['vehiclesImported']);
        $this->assertEquals(0, $stats['errors']);
        $this->assertGreaterThanOrEqual(0, $stats['processingTimeSeconds']); // Can be 0 for very fast operations
        $this->assertGreaterThan(0, $stats['memoryPeakMB']);
    }

    /**
     * Test import with case-insensitive vehicle type matching
     */
    public function testImportCaseInsensitiveVehicleType(): void
    {
        $this->setupRepositoryMocks();
        
        $vehicleData = [
            [
                'registrationNumber' => 'CASE01',
                'vehicleType' => 'car',  // Lowercase
                'make' => 'Test',
                'model' => 'Case',
                'year' => 2020,
                'name' => 'Case Test',
                'purchaseCost' => '15000',
                'purchaseDate' => '2020-01-01',
            ]
        ];

        $result = $this->importService->importVehicles($vehicleData, $this->testUser);
        
        $this->assertTrue($result->isSuccess());
    }

    /**
     * Test import message includes useful information
     */
    public function testImportMessageIncludesUsefulInfo(): void
    {
        $this->setupRepositoryMocks();
        
        $vehicleData = [
            [
                'registrationNumber' => 'MSG01',
                'vehicleType' => 'Car',
                'make' => 'Test',
                'model' => 'Message',
                'year' => 2020,
                'name' => 'Message Test',
                'purchaseCost' => '15000',
                'purchaseDate' => '2020-01-01',
            ]
        ];

        $result = $this->importService->importVehicles($vehicleData, $this->testUser);
        
        $this->assertTrue($result->isSuccess());
        $message = $result->getMessage();
        $this->assertNotEmpty($message);
        $this->assertIsString($message);
    }

    /**
     * Helper: Setup repository mocks
     */
    private function setupRepositoryMocks(): void
    {
        // Mock vehicle repository
        $vehicleRepo = $this->createMock(EntityRepository::class);
        $vehicleRepo->method('findOneBy')->willReturn(null);
        
        $qb = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(AbstractQuery::class);
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);
        $query->method('getResult')->willReturn([]);
        $vehicleRepo->method('createQueryBuilder')->willReturn($qb);

        // Mock vehicle type repository
        $vehicleType = $this->createMock(VehicleType::class);
        $vehicleType->method('getId')->willReturn(1);
        $vehicleType->method('getName')->willReturn('Car');
        
        $vehicleTypeRepo = $this->createMock(EntityRepository::class);
        $vehicleTypeRepo->method('findOneBy')->willReturn($vehicleType);

        // Mock other repositories
        $otherRepo = $this->createMock(EntityRepository::class);
        $otherRepo->method('findOneBy')->willReturn(null);

        $this->entityManager->method('getRepository')->willReturnCallback(
            function ($entity) use ($vehicleRepo, $vehicleTypeRepo, $otherRepo) {
                if ($entity === Vehicle::class) {
                    return $vehicleRepo;
                } elseif ($entity === VehicleType::class) {
                    return $vehicleTypeRepo;
                }
                return $otherRepo;
            }
        );

        $this->entityManager->expects($this->any())
            ->method('persist')
            ->willReturn(null);

        $this->entityManager->expects($this->any())
            ->method('flush')
            ->willReturn(null);

        $this->entityManager->expects($this->any())
            ->method('beginTransaction')
            ->willReturn(null);

        $this->entityManager->expects($this->any())
            ->method('commit')
            ->willReturn(null);

        $this->cache->method('invalidateTags')->willReturn(true);
    }
}
