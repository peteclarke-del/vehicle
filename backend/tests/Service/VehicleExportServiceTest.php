<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Config\ImportExportConfig;
use App\Entity\User;
use App\Service\VehicleExportService;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class VehicleExportServiceTest extends TestCase
{
    private function createService(array $vehicleIds): VehicleExportService
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $slugger = $this->createMock(SluggerInterface::class);
        $config = new ImportExportConfig();

        $qb = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(AbstractQuery::class);

        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('leftJoin')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('addOrderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $query->method('getScalarResult')->willReturn(array_map(static fn (int $id): array => ['id' => $id], $vehicleIds));

        $stockRepo = $this->createMock(EntityRepository::class);
        $stockRepo->method('findBy')->willReturn([]);

        $em->method('createQueryBuilder')->willReturn($qb);
        $em->method('getRepository')->willReturn($stockRepo);

        return new VehicleExportService($em, $logger, $slugger, $config, '/tmp/project');
    }

    public function testExportWithNoVehicles(): void
    {
        $service = $this->createService([]);
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $result = $service->exportVehicles($user, false);

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('vehicles', $result->getData());
        $this->assertCount(0, $result->getData()['vehicles']);
    }
}
