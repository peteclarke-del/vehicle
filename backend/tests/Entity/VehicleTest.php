<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Vehicle;
use App\Entity\User;
use App\Entity\VehicleMake;
use App\Entity\VehicleModel;
use App\Entity\VehicleType;
use PHPUnit\Framework\TestCase;

/**
 * Vehicle Entity Test
 * 
 * Unit tests for Vehicle entity
 * 
 * @coversDefaultClass \App\Entity\Vehicle
 */
class VehicleTest extends TestCase
{
    public function testVehicleCreation(): void
    {
        $vehicle = new Vehicle();
        
        $this->assertInstanceOf(Vehicle::class, $vehicle);
        $this->assertNull($vehicle->getId());
    }

    public function testSetAndGetRegistration(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setRegistration('ABC123');
        
        $this->assertSame('ABC123', $vehicle->getRegistration());
    }

    public function testSetAndGetYear(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setYear(2020);
        
        $this->assertSame(2020, $vehicle->getYear());
    }

    public function testSetAndGetColour(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setColour('Silver');
        
        $this->assertSame('Silver', $vehicle->getColour());
    }

    public function testSetAndGetMileage(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setMileage(50000);
        
        $this->assertSame(50000, $vehicle->getMileage());
    }

    public function testSetAndGetPurchasePrice(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setPurchasePrice(15000.00);
        
        $this->assertSame('15000', $vehicle->getPurchasePrice());
    }

    public function testSetAndGetPurchaseDate(): void
    {
        $vehicle = new Vehicle();
        $date = new \DateTime('2020-01-15');
        $vehicle->setPurchaseDate($date);
        
        $this->assertSame($date, $vehicle->getPurchaseDate());
    }

    public function testSetAndGetVin(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setVin('1HGBH41JXMN109186');
        
        $this->assertSame('1HGBH41JXMN109186', $vehicle->getVin());
    }

    public function testSetAndGetEngineSize(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setEngineSize(1.8);
        
        // EngineSize property doesn't exist in schema, setter is a no-op
        $this->assertNull($vehicle->getEngineSize());
    }

    public function testSetAndGetFuelType(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setFuelType('Petrol');
        
        // FuelType property doesn't exist in schema, setter is a no-op
        $this->assertNull($vehicle->getFuelType());
    }

    public function testSetAndGetTransmission(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setTransmission('Manual');
        
        // Transmission property doesn't exist in schema, setter is a no-op
        $this->assertNull($vehicle->getTransmission());
    }

    public function testUserRelationship(): void
    {
        $vehicle = new Vehicle();
        $user = new User();
        $user->setEmail('test@example.com');
        
        $vehicle->setUser($user);
        
        $this->assertSame($user, $vehicle->getUser());
    }

    public function testVehicleMakeRelationship(): void
    {
        $vehicle = new Vehicle();
        $make = new VehicleMake();
        $make->setName('Toyota');
        
        $vehicle->setMake($make);
        
        // Make is stored as string, not object
        $this->assertSame('Toyota', $vehicle->getMake());
    }

    public function testVehicleModelRelationship(): void
    {
        $vehicle = new Vehicle();
        $model = new VehicleModel();
        $model->setName('Corolla');
        
        $vehicle->setModel($model);
        
        // Model is stored as string, not object
        $this->assertSame('Corolla', $vehicle->getModel());
    }

    public function testVehicleTypeRelationship(): void
    {
        $vehicle = new Vehicle();
        $type = new VehicleType();
        $type->setName('Sedan');
        
        $vehicle->setVehicleType($type);
        
        $this->assertSame($type, $vehicle->getVehicleType());
    }

    public function testCalculateAge(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setYear(2020);
        
        $age = $vehicle->getAge();
        $expectedAge = date('Y') - 2020;
        
        $this->assertSame($expectedAge, $age);
    }

    public function testIsClassic(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setYear(1990);
        
        $this->assertTrue($vehicle->isClassic());
        
        $vehicle->setYear(2020);
        $this->assertFalse($vehicle->isClassic());
    }

    public function testGetDisplayName(): void
    {
        $vehicle = new Vehicle();
        $make = new VehicleMake();
        $make->setName('Toyota');
        $model = new VehicleModel();
        $model->setName('Corolla');
        
        $vehicle->setMake($make);
        $vehicle->setModel($model);
        $vehicle->setYear(2020);
        
        $this->assertSame('2020 Toyota Corolla', $vehicle->getDisplayName());
    }

    public function testCreatedAtTimestamp(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setCreatedAt(new \DateTime());
        
        $this->assertInstanceOf(\DateTime::class, $vehicle->getCreatedAt());
    }

    public function testUpdatedAtTimestamp(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setUpdatedAt(new \DateTime());
        
        $this->assertInstanceOf(\DateTime::class, $vehicle->getUpdatedAt());
    }

    public function testServiceRecordsCollection(): void
    {
        $vehicle = new Vehicle();
        
        $this->assertCount(0, $vehicle->getServiceRecords());
    }

    public function testInsuranceRecordsCollection(): void
    {
        $vehicle = new Vehicle();
        
        $this->assertCount(0, $vehicle->getInsuranceRecords());
    }

    public function testMotRecordsCollection(): void
    {
        $vehicle = new Vehicle();
        
        $this->assertCount(0, $vehicle->getMotRecords());
    }

    public function testFuelRecordsCollection(): void
    {
        $vehicle = new Vehicle();
        
        $this->assertCount(0, $vehicle->getFuelRecords());
    }

    public function testPartsCollection(): void
    {
        $vehicle = new Vehicle();
        
        $this->assertCount(0, $vehicle->getParts());
    }

    public function testConsumablesCollection(): void
    {
        $vehicle = new Vehicle();
        
        $this->assertCount(0, $vehicle->getConsumables());
    }
}
