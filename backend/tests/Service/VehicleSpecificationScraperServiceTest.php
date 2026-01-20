<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Specification;
use App\Entity\Vehicle;
use App\Service\VehicleSpecificationScraperService;
use App\Service\VehicleSpecAdapter\VehicleSpecAdapterInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class VehicleSpecificationScraperServiceTest extends TestCase
{
    public function testMergeDvlaThenApiNinjas(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setRegistrationNumber('BT14UDJ');
        $vehicle->setMake('Mazda');
        $vehicle->setModel('6');
        $vehicle->setYear(2015);

        // Base spec from DVLA (partial)
        $baseSpec = new Specification();
        $baseSpec->setPower('120 HP');
        // transmission missing in base
        $baseSpec->setAdditionalInfo(json_encode(['color' => 'Silver']));
        $baseSpec->setSourceUrl('dvla');

        // Secondary spec from API Ninjas (fills gaps)
        $otherSpec = new Specification();
        $otherSpec->setTransmission('Automatic');
        $otherSpec->setDisplacement('2.0 L');
        $otherSpec->setAdditionalInfo(json_encode(['doors' => 4]));
        $otherSpec->setSourceUrl('api-ninjas');

        // Mock DVLA adapter
        $dvlaAdapter = $this->createMock(VehicleSpecAdapterInterface::class);
        $dvlaAdapter->method('supports')->willReturn(true);
        $dvlaAdapter->method('fetchSpecifications')->willReturn($baseSpec);
        $dvlaAdapter->method('getPriority')->willReturn(100);
        $dvlaAdapter->method('searchModels')->willReturn([]);

        // Mock API-Ninjas adapter
        $apiAdapter = $this->createMock(VehicleSpecAdapterInterface::class);
        $apiAdapter->method('supports')->willReturn(true);
        $apiAdapter->method('fetchSpecifications')->willReturn($otherSpec);
        $apiAdapter->method('getPriority')->willReturn(85);
        $apiAdapter->method('searchModels')->willReturn([]);

        $scraper = new VehicleSpecificationScraperService(new NullLogger());
        // Register adapters (order doesn't matter; service sorts by priority)
        $scraper->registerAdapter($apiAdapter);
        $scraper->registerAdapter($dvlaAdapter);

        $merged = $scraper->scrapeSpecifications($vehicle);

        $this->assertInstanceOf(Specification::class, $merged);

        // Base values preserved
        $this->assertEquals('120 HP', $merged->getPower());

        // Values filled from API Ninjas
        $this->assertEquals('Automatic', $merged->getTransmission());
        $this->assertEquals('2.0 L', $merged->getDisplacement());

        // additionalInfo should contain both entries
        $info = json_decode($merged->getAdditionalInfo() ?? '{}', true);
        $this->assertArrayHasKey('color', $info);
        $this->assertArrayHasKey('doors', $info);
        $this->assertEquals('Silver', $info['color']);
        $this->assertEquals(4, $info['doors']);

        // Source URL should prefer base (DVLA) when present
        $this->assertEquals('dvla', $merged->getSourceUrl());
    }
}
