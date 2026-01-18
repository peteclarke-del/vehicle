<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Insurance;
use App\Entity\Vehicle;
use PHPUnit\Framework\TestCase;

/**
 * Insurance Entity Test
 * 
 * Unit tests for Insurance entity
 * 
 * @coversDefaultClass \App\Entity\Insurance
 */
class InsuranceTest extends TestCase
{
    public function testInsuranceCreation(): void
    {
        $insurance = new Insurance();
        
        $this->assertInstanceOf(Insurance::class, $insurance);
        $this->assertNull($insurance->getId());
    }

    public function testSetAndGetProvider(): void
    {
        $insurance = new Insurance();
        $insurance->setProvider('Direct Line');
        
        $this->assertSame('Direct Line', $insurance->getProvider());
    }

    public function testSetAndGetPolicyNumber(): void
    {
        $insurance = new Insurance();
        $insurance->setPolicyNumber('POL123456789');
        
        $this->assertSame('POL123456789', $insurance->getPolicyNumber());
    }

    public function testSetAndGetStartDate(): void
    {
        $insurance = new Insurance();
        $date = new \DateTime('2024-01-15');
        $insurance->setStartDate($date);
        
        $this->assertSame($date, $insurance->getStartDate());
    }

    public function testSetAndGetEndDate(): void
    {
        $insurance = new Insurance();
        $date = new \DateTime('2025-01-15');
        $insurance->setEndDate($date);
        
        $this->assertSame($date, $insurance->getEndDate());
    }

    public function testSetAndGetAnnualCost(): void
    {
        $insurance = new Insurance();
        $insurance->setAnnualCost(650.00);
        
        $this->assertSame('650', $insurance->getAnnualCost());
    }

    public function testCalculateMonthlyCost(): void
    {
        $insurance = new Insurance();
        $insurance->setAnnualCost(650.00);
        
        $monthlyCost = $insurance->getMonthlyCost();
        
        // £650 / 12 = £54.17
        $this->assertEqualsWithDelta(54.17, $monthlyCost, 0.01);
    }

    public function testSetAndGetExcess(): void
    {
        $insurance = new Insurance();
        $insurance->setExcess(250.00);
        
        $this->assertSame('250', $insurance->getExcess());
    }

    public function testSetAndGetCoverType(): void
    {
        $insurance = new Insurance();
        $insurance->setCoverType('Comprehensive');
        
        $this->assertSame('Comprehensive', $insurance->getCoverType());
    }

    public function testSetAndGetMileageLimit(): void
    {
        $insurance = new Insurance();
        $insurance->setMileageLimit(10000);
        
        $this->assertSame(10000, $insurance->getMileageLimit());
    }

    public function testSetAndGetNcdYears(): void
    {
        $insurance = new Insurance();
        $insurance->setNcdYears(5);
        
        $this->assertSame(5, $insurance->getNcdYears());
    }

    public function testIsActive(): void
    {
        $insurance = new Insurance();
        $insurance->setStartDate(new \DateTime('-30 days'));
        $insurance->setExpiryDate(new \DateTime('+30 days'));
        
        $this->assertTrue($insurance->isActive());
        
        $insurance->setExpiryDate(new \DateTime('-10 days'));
        $this->assertFalse($insurance->isActive());
    }

    public function testIsExpired(): void
    {
        $insurance = new Insurance();
        $insurance->setStartDate(new \DateTime('-60 days'));
        $insurance->setExpiryDate(new \DateTime('-10 days'));
        
        $this->assertTrue($insurance->isExpired());
        
        $insurance->setExpiryDate(new \DateTime('+30 days'));
        $this->assertFalse($insurance->isExpired());
    }

    public function testGetDaysUntilExpiry(): void
    {
        $insurance = new Insurance();
        $insurance->setStartDate(new \DateTime('-30 days'));
        $insurance->setExpiryDate(new \DateTime('+30 days'));
        
        $daysUntilExpiry = $insurance->getDaysUntilExpiry();
        
        $this->assertNotNull($daysUntilExpiry);
        $this->assertEqualsWithDelta(30, $daysUntilExpiry, 1);
    }

    public function testIsDueSoon(): void
    {
        $insurance = new Insurance();
        $insurance->setStartDate(new \DateTime('-30 days'));
        $insurance->setExpiryDate(new \DateTime('+10 days'));
        
        $this->assertTrue($insurance->isDueSoon());
        
        $insurance->setExpiryDate(new \DateTime('+60 days'));
        $this->assertFalse($insurance->isDueSoon());
    }

    public function testVehicleRelationship(): void
    {
        $insurance = new Insurance();
        $vehicle = new Vehicle();
        
        $insurance->setVehicle($vehicle);
        
        $this->assertSame($vehicle, $insurance->getVehicle());
    }

    public function testSetAndGetNotes(): void
    {
        $insurance = new Insurance();
        $insurance->setNotes('Business use included');
        
        $this->assertSame('Business use included', $insurance->getNotes());
    }

    public function testSetAndGetAutoRenewal(): void
    {
        $insurance = new Insurance();
        $insurance->setAutoRenewal(true);
        
        $this->assertTrue($insurance->hasAutoRenewal());
    }

    public function testCalculateDailyRate(): void
    {
        $insurance = new Insurance();
        $insurance->setAnnualCost(650.00);
        
        $dailyRate = $insurance->getDailyRate();
        
        // £650 / 365 = £1.78
        $this->assertEqualsWithDelta(1.78, $dailyRate, 0.01);
    }

    public function testCreatedAtTimestamp(): void
    {
        $insurance = new Insurance();
        $insurance->setCreatedAt(new \DateTime());
        
        $this->assertInstanceOf(\DateTime::class, $insurance->getCreatedAt());
    }
}
