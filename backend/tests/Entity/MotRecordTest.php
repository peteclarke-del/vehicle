<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\MotRecord;
use App\Entity\Vehicle;
use PHPUnit\Framework\TestCase;

/**
 * MotRecord Entity Test
 * 
 * Unit tests for MotRecord entity
 * 
 * @coversDefaultClass \App\Entity\MotRecord
 */
class MotRecordTest extends TestCase
{
    public function testMotRecordCreation(): void
    {
        $record = new MotRecord();
        
        $this->assertInstanceOf(MotRecord::class, $record);
        $this->assertNull($record->getId());
    }

    public function testSetAndGetTestDate(): void
    {
        $record = new MotRecord();
        $date = new \DateTime('2024-01-15');
        $record->setTestDate($date);
        
        $this->assertSame($date, $record->getTestDate());
    }

    public function testSetAndGetExpiryDate(): void
    {
        $record = new MotRecord();
        $date = new \DateTime('2025-01-15');
        $record->setExpiryDate($date);
        
        $this->assertSame($date, $record->getExpiryDate());
    }

    public function testSetAndGetTestResult(): void
    {
        $record = new MotRecord();
        $record->setTestResult('PASSED');
        
        $this->assertSame('PASSED', $record->getTestResult());
    }

    public function testSetAndGetMileage(): void
    {
        $record = new MotRecord();
        $record->setMileage(50000);
        
        $this->assertSame(50000, $record->getMileage());
    }

    public function testSetAndGetTestCenter(): void
    {
        $record = new MotRecord();
        $record->setTestCenter('MOT Centre Ltd');
        
        $this->assertSame('MOT Centre Ltd', $record->getTestCenter());
    }

    public function testSetAndGetCost(): void
    {
        $record = new MotRecord();
        $record->setCost(54.85);
        
        $this->assertSame('54.85', $record->getCost());
    }

    public function testSetAndGetMotTestNumber(): void
    {
        $record = new MotRecord();
        $record->setMotTestNumber('MOT123456789');
        
        $this->assertSame('MOT123456789', $record->getMotTestNumber());
    }

    public function testSetAndGetAdvisoryItems(): void
    {
        $record = new MotRecord();
        $advisoryItems = ['Brake pads worn', 'Tyre tread low'];
        $record->setAdvisoryItems($advisoryItems);
        
        $this->assertSame($advisoryItems, $record->getAdvisoryItems());
    }

    public function testSetAndGetFailureItems(): void
    {
        $record = new MotRecord();
        $failureItems = ['Brake pads below minimum', 'Headlight alignment incorrect'];
        $record->setFailureItems($failureItems);
        
        $this->assertSame($failureItems, $record->getFailureItems());
    }

    public function testIsPassed(): void
    {
        $record = new MotRecord();
        $record->setTestResult('pass');
        
        $this->assertTrue($record->isPassed());
        
        $record->setTestResult('fail');
        $this->assertFalse($record->isPassed());
    }

    public function testIsFailed(): void
    {
        $record = new MotRecord();
        $record->setTestResult('fail');
        
        $this->assertTrue($record->isFailed());
        
        $record->setTestResult('pass');
        $this->assertFalse($record->isFailed());
    }

    public function testIsValid(): void
    {
        $record = new MotRecord();
        $record->setTestResult('pass');
        $record->setExpiryDate(new \DateTime('+30 days'));
        
        $this->assertTrue($record->isValid());
        
        $record->setTestResult('fail');
        $this->assertFalse($record->isValid());
    }

    public function testIsExpired(): void
    {
        $record = new MotRecord();
        $record->setExpiryDate(new \DateTime('-30 days'));
        
        $this->assertTrue($record->isExpired());
        
        $record->setExpiryDate(new \DateTime('+30 days'));
        $this->assertFalse($record->isExpired());
    }

    public function testGetDaysUntilExpiry(): void
    {
        $record = new MotRecord();
        $record->setExpiryDate(new \DateTime('+30 days'));
        
        $daysUntilExpiry = $record->getDaysUntilExpiry();
        
        $this->assertEqualsWithDelta(30, $daysUntilExpiry, 1);
    }

    public function testIsDueSoon(): void
    {
        $record = new MotRecord();
        $record->setExpiryDate(new \DateTime('+10 days'));
        
        $this->assertTrue($record->isDueSoon());
        
        $record->setExpiryDate(new \DateTime('+60 days'));
        $this->assertFalse($record->isDueSoon());
    }

    public function testVehicleRelationship(): void
    {
        $record = new MotRecord();
        $vehicle = new Vehicle();
        
        $record->setVehicle($vehicle);
        
        $this->assertSame($vehicle, $record->getVehicle());
    }

    public function testSetAndGetTesterName(): void
    {
        $record = new MotRecord();
        $record->setTesterName('John Smith');
        
        $this->assertSame('John Smith', $record->getTesterName());
    }

    public function testSetAndGetIsRetest(): void
    {
        $record = new MotRecord();
        $record->setIsRetest(true);
        
        $this->assertTrue($record->isRetest());
    }

    public function testSetAndGetNotes(): void
    {
        $record = new MotRecord();
        $record->setNotes('Passed with minor advisories');
        
        $this->assertSame('Passed with minor advisories', $record->getNotes());
    }

    public function testHasAdvisoryItems(): void
    {
        $record = new MotRecord();
        
        $this->assertFalse($record->hasAdvisoryItems());
        
        $record->setAdvisoryItems(['Brake pads worn']);
        $this->assertTrue($record->hasAdvisoryItems());
    }

    public function testHasFailureItems(): void
    {
        $record = new MotRecord();
        
        $this->assertFalse($record->hasFailureItems());
        
        $record->setFailureItems(['Brake pads below minimum']);
        $this->assertTrue($record->hasFailureItems());
    }

    public function testCreatedAtTimestamp(): void
    {
        $record = new MotRecord();
        $record->setCreatedAt(new \DateTime());
        
        $this->assertInstanceOf(\DateTime::class, $record->getCreatedAt());
    }
}
