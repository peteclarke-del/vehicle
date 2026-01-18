<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\FuelRecord;
use App\Entity\Vehicle;
use PHPUnit\Framework\TestCase;

/**
 * FuelRecord Entity Test
 * 
 * Unit tests for FuelRecord entity
 * 
 * @coversDefaultClass \App\Entity\FuelRecord
 */
class FuelRecordTest extends TestCase
{
    public function testFuelRecordCreation(): void
    {
        $record = new FuelRecord();
        
        $this->assertInstanceOf(FuelRecord::class, $record);
        $this->assertNull($record->getId());
    }

    public function testSetAndGetDate(): void
    {
        $record = new FuelRecord();
        $date = new \DateTime('2024-01-15');
        $record->setDate($date);
        
        $this->assertSame($date, $record->getDate());
    }

    public function testSetAndGetMileage(): void
    {
        $record = new FuelRecord();
        $record->setMileage(50500);
        
        $this->assertSame(50500, $record->getMileage());
    }

    public function testSetAndGetLitres(): void
    {
        $record = new FuelRecord();
        $record->setLitres(45.5);
        
        $this->assertSame('45.5', $record->getLitres());
    }

    public function testSetAndGetCost(): void
    {
        $record = new FuelRecord();
        $record->setCost(68.25);
        
        $this->assertSame('68.25', $record->getCost());
    }

    public function testCalculatePricePerLitre(): void
    {
        $record = new FuelRecord();
        $record->setLitres(45.5);
        $record->setCost(68.25);
        
        $pricePerLitre = $record->getPricePerLitre();
        
        $this->assertEqualsWithDelta(1.50, $pricePerLitre, 0.01);
    }

    public function testSetAndGetStation(): void
    {
        $record = new FuelRecord();
        $record->setStation('Shell');
        
        $this->assertSame('Shell', $record->getStation());
    }

    public function testSetAndGetFuelType(): void
    {
        $record = new FuelRecord();
        $record->setFuelType('Petrol');
        
        $this->assertSame('Petrol', $record->getFuelType());
    }

    public function testSetAndGetFullTank(): void
    {
        $record = new FuelRecord();
        $record->setFullTank(true);
        
        $this->assertTrue($record->isFullTank());
    }

    public function testSetAndGetPaymentMethod(): void
    {
        $record = new FuelRecord();
        $record->setPaymentMethod('Credit Card');
        
        $this->assertSame('Credit Card', $record->getPaymentMethod());
    }

    public function testSetAndGetNotes(): void
    {
        $record = new FuelRecord();
        $record->setNotes('Motorway driving');
        
        $this->assertSame('Motorway driving', $record->getNotes());
    }

    public function testCalculateMpg(): void
    {
        $previousRecord = new FuelRecord();
        $previousRecord->setMileage(50000);
        $previousRecord->setLitres(45.0);
        
        $record = new FuelRecord();
        $record->setMileage(50500);
        $record->setLitres(45.5);
        
        $mpg = $record->calculateMpg($previousRecord);
        
        // 500 miles / 45.5 litres * 4.546 = 50.0 mpg
        $this->assertEqualsWithDelta(50.0, $mpg, 0.5);
    }

    public function testCalculateLitresPer100km(): void
    {
        $mpg = 50.0;
        
        $record = new FuelRecord();
        $litres100km = $record->convertMpgToLitres100km($mpg);
        
        // 282.48 / 50.0 = 5.65 L/100km
        $this->assertEqualsWithDelta(5.65, $litres100km, 0.1);
    }

    public function testCalculateCostPerMile(): void
    {
        $previousRecord = new FuelRecord();
        $previousRecord->setMileage(50000);
        
        $record = new FuelRecord();
        $record->setMileage(50500);
        $record->setCost(70.00);
        
        $costPerMile = $record->calculateCostPerMile($previousRecord);
        
        // £70.00 / 500 miles = £0.14 per mile
        $this->assertNotNull($costPerMile);
        $this->assertEqualsWithDelta(0.14, $costPerMile, 0.01);
    }

    public function testVehicleRelationship(): void
    {
        $record = new FuelRecord();
        $vehicle = new Vehicle();
        
        $record->setVehicle($vehicle);
        
        $this->assertSame($vehicle, $record->getVehicle());
    }

    public function testSetAndGetTripComputerMpg(): void
    {
        $record = new FuelRecord();
        $record->setTripComputerMpg(52.0);
        
        $this->assertSame('52', $record->getTripComputerMpg());
    }

    public function testCompareTripComputerMpg(): void
    {
        $previousRecord = new FuelRecord();
        $previousRecord->setMileage(50000);
        
        $record = new FuelRecord();
        $record->setMileage(50500);
        $record->setLitres(45.5);
        $record->setTripComputerMpg(52.0);
        
        $calculatedMpg = $record->calculateMpg($previousRecord);
        $tripComputerMpg = (float)$record->getTripComputerMpg();
        
        // Check values are reasonably close (within 5 mpg)
        $this->assertNotNull($calculatedMpg);
        $this->assertLessThan(5, abs($tripComputerMpg - $calculatedMpg));
    }

    public function testCreatedAtTimestamp(): void
    {
        $record = new FuelRecord();
        $record->setCreatedAt(new \DateTime());
        
        $this->assertInstanceOf(\DateTime::class, $record->getCreatedAt());
    }
}
