<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Consumable;
use App\Entity\ConsumableType;
use App\Entity\Vehicle;
use PHPUnit\Framework\TestCase;

/**
 * Consumable Entity Test
 * 
 * Unit tests for Consumable entity
 * 
 * @coversDefaultClass \App\Entity\Consumable
 */
class ConsumableTest extends TestCase
{
    public function testConsumableCreation(): void
    {
        $consumable = new Consumable();
        
        $this->assertInstanceOf(Consumable::class, $consumable);
        $this->assertNull($consumable->getId());
    }

    public function testSetAndGetType(): void
    {
        $consumable = new Consumable();
        $type = new ConsumableType();
        $type->setName('Engine Oil');
        $consumable->setType($type);
        
        $this->assertSame($type, $consumable->getType());
        $this->assertSame('Engine Oil', $consumable->getType()->getName());
    }

    public function testSetAndGetLastReplacementDate(): void
    {
        $consumable = new Consumable();
        $date = new \DateTime('2023-01-15');
        $consumable->setLastReplacementDate($date);
        
        $this->assertSame($date, $consumable->getLastReplacementDate());
    }

    public function testSetAndGetLastReplacementMileage(): void
    {
        $consumable = new Consumable();
        $consumable->setLastReplacementMileage(45000);
        
        $this->assertSame(45000, $consumable->getLastReplacementMileage());
    }

    public function testSetAndGetNextReplacementMileage(): void
    {
        $consumable = new Consumable();
        $consumable->setNextReplacementMileage(55000);
        
        $this->assertSame(55000, $consumable->getNextReplacementMileage());
    }

    public function testSetAndGetReplacementInterval(): void
    {
        $consumable = new Consumable();
        $consumable->setReplacementInterval(10000);
        
        $this->assertSame(10000, $consumable->getReplacementInterval());
    }

    public function testSetAndGetCost(): void
    {
        $consumable = new Consumable();
        $consumable->setCost(45.00);
        
        $this->assertSame('45', $consumable->getCost());
    }

    public function testCalculateNextReplacementMileage(): void
    {
        $consumable = new Consumable();
        $consumable->setLastReplacementMileage(45000);
        $consumable->setReplacementInterval(10000);
        
        $nextMileage = $consumable->calculateNextReplacementMileage();
        
        $this->assertSame(55000, $nextMileage);
    }

    public function testIsDueForReplacement(): void
    {
        $consumable = new Consumable();
        $consumable->setNextReplacementMileage(48000);
        
        $this->assertTrue($consumable->isDueForReplacement(50000));
        $this->assertFalse($consumable->isDueForReplacement(45000));
    }

    public function testGetMilesUntilReplacement(): void
    {
        $consumable = new Consumable();
        $consumable->setNextReplacementMileage(55000);
        
        $milesUntil = $consumable->getMilesUntilReplacement(50000);
        
        $this->assertSame(5000, $milesUntil);
    }

    public function testIsOverdue(): void
    {
        $consumable = new Consumable();
        $consumable->setNextReplacementMileage(48000);
        
        $this->assertTrue($consumable->isOverdue(50000));
        $this->assertFalse($consumable->isOverdue(45000));
    }

    public function testGetOverdueMiles(): void
    {
        $consumable = new Consumable();
        $consumable->setNextReplacementMileage(48000);
        
        $overdueMiles = $consumable->getOverdueMiles(50000);
        
        $this->assertSame(2000, $overdueMiles);
    }

    public function testVehicleRelationship(): void
    {
        $consumable = new Consumable();
        $vehicle = new Vehicle();
        
        $consumable->setVehicle($vehicle);
        
        $this->assertSame($vehicle, $consumable->getVehicle());
    }

    public function testSetAndGetBrand(): void
    {
        $consumable = new Consumable();
        $consumable->setBrand('Castrol');
        
        $this->assertSame('Castrol', $consumable->getBrand());
    }

    public function testSetAndGetPartNumber(): void
    {
        $consumable = new Consumable();
        $consumable->setPartNumber('OIL-5W30-1L');
        
        $this->assertSame('OIL-5W30-1L', $consumable->getPartNumber());
    }

    public function testCalculateAnnualCost(): void
    {
        $consumable = new Consumable();
        $consumable->setCost(45.00);
        $consumable->setReplacementInterval(10000);
        
        // If annual mileage is 10000, cost is £45
        $annualCost = $consumable->calculateAnnualCost(10000);
        
        $this->assertEqualsWithDelta(45.00, $annualCost, 0.01);
        
        // If annual mileage is 20000, cost is £90
        $annualCost = $consumable->calculateAnnualCost(20000);
        
        $this->assertEqualsWithDelta(90.00, $annualCost, 0.01);
    }

    public function testGetReplacementHistory(): void
    {
        $consumable = new Consumable();
        
        // Returns simplified history array with current record (1 item)
        $this->assertCount(1, $consumable->getReplacementHistory());
    }

    public function testCreatedAtTimestamp(): void
    {
        $consumable = new Consumable();
        $consumable->setCreatedAt(new \DateTime());
        
        $this->assertInstanceOf(\DateTime::class, $consumable->getCreatedAt());
    }
}
