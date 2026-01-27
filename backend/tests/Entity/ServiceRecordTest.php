<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\ServiceRecord;
use App\Entity\Vehicle;
use PHPUnit\Framework\TestCase;

/**
 * ServiceRecord Entity Test
 * 
 * Unit tests for ServiceRecord entity
 * 
 * @coversDefaultClass \App\Entity\ServiceRecord
 */
class ServiceRecordTest extends TestCase
{
    public function testServiceRecordCreation(): void
    {
        $record = new ServiceRecord();
        
        $this->assertInstanceOf(ServiceRecord::class, $record);
        $this->assertNull($record->getId());
    }

    public function testSetAndGetServiceDate(): void
    {
        $record = new ServiceRecord();
        $date = new \DateTime('2024-01-15');
        $record->setServiceDate($date);
        
        $this->assertSame($date, $record->getServiceDate());
    }

    public function testSetAndGetServiceType(): void
    {
        $record = new ServiceRecord();
        $record->setServiceType('Annual Service');
        
        $this->assertSame('Annual Service', $record->getServiceType());
    }

    public function testSetAndGetDescription(): void
    {
        $record = new ServiceRecord();
        $record->setWorkPerformed('Full service including oil change');

        $this->assertSame('Full service including oil change', $record->getWorkPerformed());
    }

    public function testSetAndGetLabourCost(): void
    {
        $record = new ServiceRecord();
        $record->setLabourCost(150.00);
        
        $this->assertSame('150', $record->getLabourCost());
    }

    public function testSetAndGetPartsCost(): void
    {
        $record = new ServiceRecord();
        $record->setPartsCost(100.00);
        
        $this->assertSame('100', $record->getPartsCost());
    }

    public function testSetAndGetAdditionalCosts(): void
    {
        $record = new ServiceRecord();
        $record->setAdditionalCosts(50.00);
        
        $this->assertSame('50', $record->getAdditionalCosts());
    }

    public function testCalculateTotalCost(): void
    {
        $record = new ServiceRecord();
        $record->setLabourCost(150.00);
        $record->setPartsCost(100);
        $record->setAdditionalCosts(50);
        
        // getTotalCost() only adds laborCost + partsCost (not additionalCosts)
        $total = (float)$record->getTotalCost();
        $this->assertEqualsWithDelta(250.0, $total, 0.01);
    }

    public function testSetAndGetMileage(): void
    {
        $record = new ServiceRecord();
        $record->setMileage(50000);
        
        $this->assertSame(50000, $record->getMileage());
    }

    public function testSetAndGetWorkshop(): void
    {
        $record = new ServiceRecord();
        $record->setServiceProvider('Main Dealer');

        $this->assertSame('Main Dealer', $record->getServiceProvider());
    }

    public function testVehicleRelationship(): void
    {
        $record = new ServiceRecord();
        $vehicle = new Vehicle();
        
        $record->setVehicle($vehicle);
        
        $this->assertSame($vehicle, $record->getVehicle());
    }

    public function testSetAndGetNextServiceDate(): void
    {
        $record = new ServiceRecord();
        $date = new \DateTime('2025-01-15');
        $record->setNextServiceDate($date);
        
        $this->assertSame($date, $record->getNextServiceDate());
    }

    public function testSetAndGetNextServiceMileage(): void
    {
        $record = new ServiceRecord();
        $record->setNextServiceMileage(60000);
        
        $this->assertSame(60000, $record->getNextServiceMileage());
    }

    public function testIsOverdue(): void
    {
        $record = new ServiceRecord();
        $record->setNextServiceDate(new \DateTime('-30 days'));
        
        $this->assertTrue($record->isOverdue());
        
        $record->setNextServiceDate(new \DateTime('+30 days'));
        $this->assertFalse($record->isOverdue());
    }

    public function testIsDueSoon(): void
    {
        $record = new ServiceRecord();
        $record->setNextServiceDate(new \DateTime('+10 days'));
        
        $this->assertTrue($record->isDueSoon());
        
        $record->setNextServiceDate(new \DateTime('+60 days'));
        $this->assertFalse($record->isDueSoon());
    }

    public function testAttachmentsCollection(): void
    {
        $record = new ServiceRecord();
        
        $this->assertCount(0, $record->getAttachments());
    }

    public function testPartsUsedCollection(): void
    {
        $record = new ServiceRecord();
        
        $this->assertCount(0, $record->getPartsUsed());
    }

    public function testCreatedAtTimestamp(): void
    {
        $record = new ServiceRecord();
        $record->setCreatedAt(new \DateTime());
        
        $this->assertInstanceOf(\DateTime::class, $record->getCreatedAt());
    }
}
