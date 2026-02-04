<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\VehicleExportService;
use App\Config\ImportExportConfig;
use App\DTO\ExportResult;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\VehicleType;
use App\Exception\ExportException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\AbstractQuery;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * VehicleExportService Test
 * 
 * Unit tests for vehicle export functionality
 */
class VehicleExportServiceTest extends TestCase
{
    private VehicleExportService $exportService;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private SluggerInterface $slugger;
    private ImportExportConfig $config;
    private User $testUser;

    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->slugger = $this->createMock(SluggerInterface::class);
        $this->config = new ImportExportConfig();
        
        $this->exportService = new VehicleExportService(
            $this->entityManager,
            $this->logger,
            $this->slugger,
            $this->config,
            '/tmp/test_project'
        );

        $this->testUser = $this->createMock(User::class);
        $this->testUser->method('getId')->willReturn(1);
    }

    /**
     * Test successful export with no vehicles
     */
    public function testExportWithNoVehicles(): void
    {
        $this->setupEmptyVehicleQuery();

        $result = $this->exportService->exportVehicles($this->testUser, false);

        $this->assertInstanceOf(ExportResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertCount(0, $result->getData());
        $statistics = $result->getStatistics();
        $this->assertEquals(0, $statistics['vehicleCount']);
    }

    /**
     * Test successful export with vehicles
     */
    public function testExportWithVehicles(): void
    {
        $vehicles = $this->createMockVehicles(2);
        $this->setupVehicleQuery($vehicles);

        $result = $this->exportService->exportVehicles($this->testUser, false);

        $this->assertInstanceOf(ExportResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertCount(2, $result->getData());
        $statistics = $result->getStatistics();
        $this->assertEquals(2, $statistics['vehicleCount']);
    }

    /**
     * Test export includes all vehicle data
     */
    public function testExportIncludesAllVehicleData(): void
    {
        $vehicle = $this->createDetailedMockVehicle();
        $this->setupVehicleQuery([$vehicle]);

        $result = $this->exportService->exportVehicles($this->testUser, false);

        $this->assertTrue($result->isSuccess());
        $data = $result->getData();
        $this->assertCount(1, $data);
        
        $vehicleData = $data[0];
        $this->assertArrayHasKey('originalId', $vehicleData);
        $this->assertArrayHasKey('name', $vehicleData);
        $this->assertArrayHasKey('vehicleType', $vehicleData);
        $this->assertArrayHasKey('make', $vehicleData);
        $this->assertArrayHasKey('model', $vehicleData);
        $this->assertArrayHasKey('year', $vehicleData);
        $this->assertArrayHasKey('registrationNumber', $vehicleData);
        $this->assertArrayHasKey('fuelRecords', $vehicleData);
        $this->assertArrayHasKey('parts', $vehicleData);
        $this->assertArrayHasKey('consumables', $vehicleData);
        $this->assertArrayHasKey('serviceRecords', $vehicleData);
        $this->assertArrayHasKey('motRecords', $vehicleData);
    }

    /**
     * Test admin export includes all users' vehicles
     */
    public function testAdminExportIncludesAllVehicles(): void
    {
        $vehicles = $this->createMockVehicles(5);
        $this->setupVehicleQueryForAdmin($vehicles);

        $result = $this->exportService->exportVehicles($this->testUser, true);

        $this->assertTrue($result->isSuccess());
        $this->assertCount(5, $result->getData());
    }

    /**
     * Test export handles database errors gracefully
     */
    public function testExportHandlesDatabaseErrors(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(AbstractQuery::class);
        
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('addOrderBy')->willReturnSelf();
        $queryBuilder->method('expr')->willReturn(new \Doctrine\ORM\Query\Expr());
        $queryBuilder->method('getQuery')->willReturn($query);
        
        $query->method('getScalarResult')->willThrowException(
            new \RuntimeException('Database connection failed')
        );

        $this->entityManager->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->expectException(\App\Exception\ExportException::class);
        $result = $this->exportService->exportVehicles($this->testUser, false);
    }

    /**
     * Test export execution time is recorded
     */
    public function testExportRecordsExecutionTime(): void
    {
        $this->setupEmptyVehicleQuery();

        $result = $this->exportService->exportVehicles($this->testUser, false);

        $statistics = $result->getStatistics();
        $this->assertGreaterThanOrEqual(0, $statistics['processingTimeSeconds']);
        $this->assertArrayHasKey('memoryPeakMB', $statistics);
        $this->assertArrayHasKey('vehicleCount', $statistics);
    }

    /**
     * Test export with attachment references
     */
    public function testExportWithAttachmentReferences(): void
    {
        $vehicle = $this->createDetailedMockVehicle();
        $this->setupVehicleQuery([$vehicle]);

        $zipDir = '/tmp/test-export';
        $result = $this->exportService->exportVehicles($this->testUser, false, true, $zipDir);

        $this->assertTrue($result->isSuccess());
        // Note: Full attachment testing would require integration tests
        // as it involves file system operations
    }

    /**
     * Helper: Setup empty vehicle query
     */
    private function setupEmptyVehicleQuery(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(AbstractQuery::class);
        
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('addOrderBy')->willReturnSelf();
        $queryBuilder->method('expr')->willReturn(new \Doctrine\ORM\Query\Expr());
        $queryBuilder->method('getQuery')->willReturn($query);
        
        $query->method('getScalarResult')->willReturn([]);
        $query->method('getResult')->willReturn([]);

        $this->entityManager->method('createQueryBuilder')->willReturn($queryBuilder);
    }

    /**
     * Helper: Setup vehicle query with vehicles
     */
    private function setupVehicleQuery(array $vehicles): void
    {
        $vehicleIds = array_map(fn($v) => ['id' => $v->getId()], $vehicles);
        
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(AbstractQuery::class);
        
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('addOrderBy')->willReturnSelf();
        $queryBuilder->method('expr')->willReturn(new \Doctrine\ORM\Query\Expr());
        $queryBuilder->method('getQuery')->willReturn($query);
        
        // First query returns IDs, second query returns full entities
        $query->method('getScalarResult')->willReturn($vehicleIds);
        $query->method('getResult')->willReturn($vehicles);

        $this->entityManager->method('createQueryBuilder')->willReturn($queryBuilder);
        $this->entityManager->method('clear')->willReturn(null);
        $this->entityManager->method('getRepository')->willReturn($this->createMock(\Doctrine\ORM\EntityRepository::class));
    }

    /**
     * Helper: Setup vehicle query for admin
     */
    private function setupVehicleQueryForAdmin(array $vehicles): void
    {
        // Same as setupVehicleQuery but without user filter
        $this->setupVehicleQuery($vehicles);
    }

    /**
     * Helper: Create mock vehicles
     */
    private function createMockVehicles(int $count): array
    {
        $vehicles = [];
        for ($i = 1; $i <= $count; $i++) {
            $vehicle = $this->getMockBuilder(Vehicle::class)
                ->disableOriginalConstructor()
                ->addMethods(['getSpecification', 'getAttachments', 'getTodos'])
                ->onlyMethods(['getId', 'getName', 'getRegistrationNumber', 'getYear', 'getMake', 'getModel',
                    'getVehicleType', 'getFuelRecords', 'getParts', 'getConsumables', 'getServiceRecords',
                    'getMotRecords', 'getRoadTaxRecords', 'getStatusHistory', 'getInsurancePolicies',
                    'getVin', 'getPurchaseCost', 'getPurchaseDate', 'getStatus', 'getImages'])
                ->getMock();
                
            $vehicle->method('getId')->willReturn($i);
            $vehicle->method('getName')->willReturn("Test Vehicle $i");
            $vehicle->method('getRegistrationNumber')->willReturn("REG$i");
            $vehicle->method('getYear')->willReturn(2020 + $i);
            $vehicle->method('getMake')->willReturn("Make$i");
            $vehicle->method('getModel')->willReturn("Model$i");
            
            $vehicleType = $this->createMock(VehicleType::class);
            $vehicleType->method('getName')->willReturn('Car');
            $vehicle->method('getVehicleType')->willReturn($vehicleType);
            
            $vehicle->method('getFuelRecords')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());
            $vehicle->method('getParts')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());
            $vehicle->method('getConsumables')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());
            $vehicle->method('getServiceRecords')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());
            $vehicle->method('getMotRecords')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());
            $vehicle->method('getRoadTaxRecords')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());
            $vehicle->method('getStatusHistory')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());
            $vehicle->method('getInsurancePolicies')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());
            $vehicle->method('getSpecification')->willReturn(null);
            $vehicle->method('getTodos')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());
            $vehicle->method('getAttachments')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());
            $vehicle->method('getImages')->willReturn(new \Doctrine\Common\Collections\ArrayCollection());
            $vehicle->method('getVin')->willReturn(null);
            $vehicle->method('getPurchaseCost')->willReturn(null);
            $vehicle->method('getPurchaseDate')->willReturn(null);
            $vehicle->method('getStatus')->willReturn('Live');
            
            $vehicles[] = $vehicle;
        }
        return $vehicles;
    }

    /**
     * Helper: Create detailed mock vehicle
     */
    private function createDetailedMockVehicle(): Vehicle
    {
        $vehicles = $this->createMockVehicles(1);
        $vehicle = $vehicles[0];
        
        $vehicle->method('getVin')->willReturn('TEST123456789');
        $vehicle->method('getPurchaseCost')->willReturn('15000.00');
        $vehicle->method('getPurchaseDate')->willReturn(new \DateTime('2020-01-01'));
        $vehicle->method('getStatus')->willReturn('Live');
        
        return $vehicle;
    }
}
