<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\VehicleType;
use App\Entity\Vehicle;
use PHPUnit\Framework\TestCase;

/**
 * Vehicle Type Entity Test
 * 
 * Unit tests for VehicleType entity
 * 
 * @coversDefaultClass \App\Entity\VehicleType
 */
class VehicleTypeTest extends TestCase
{
    private VehicleType $type;

    protected function setUp(): void
    {
        $this->type = new VehicleType();
    }

    public function testGetSetName(): void
    {
        $this->type->setName('Sedan');
        
        $this->assertSame('Sedan', $this->type->getName());
    }

    public function testGetSetCategory(): void
    {
        $this->type->setCategory('Passenger Car');
        
        $this->assertSame('Passenger Car', $this->type->getCategory());
    }

    public function testGetSetDescription(): void
    {
        $description = 'A sedan is a passenger car with a three-box configuration';
        $this->type->setDescription($description);

        $this->assertSame($description, $this->type->getDescription());
    }

    public function testAddVehicle(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setRegistration('AB12 CDE');

        $this->type->addVehicle($vehicle);

        $this->assertCount(1, $this->type->getVehicles());
        $this->assertTrue($this->type->getVehicles()->contains($vehicle));
    }

    public function testRemoveVehicle(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setRegistration('AB12 CDE');

        $this->type->addVehicle($vehicle);
        $this->type->removeVehicle($vehicle);

        $this->assertCount(0, $this->type->getVehicles());
    }

    public function testDoesNotAddDuplicateVehicles(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setRegistration('AB12 CDE');

        $this->type->addVehicle($vehicle);
        $this->type->addVehicle($vehicle);

        $this->assertCount(1, $this->type->getVehicles());
    }

    public function testGetSetTypicalSeatingCapacity(): void
    {
        $this->type->setTypicalSeatingCapacity(5);
        
        $this->assertSame(5, $this->type->getTypicalSeatingCapacity());
    }

    public function testGetSetTypicalDoors(): void
    {
        $this->type->setTypicalDoors(4);
        
        $this->assertSame(4, $this->type->getTypicalDoors());
    }

    public function testToString(): void
    {
        $this->type->setName('SUV');
        
        $this->assertSame('SUV', (string) $this->type);
    }

    public function testGetVehicleCount(): void
    {
        $vehicle1 = new Vehicle();
        $vehicle1->setRegistration('AB12 CDE');

        $vehicle2 = new Vehicle();
        $vehicle2->setRegistration('XY34 FGH');

        $this->type->addVehicle($vehicle1);
        $this->type->addVehicle($vehicle2);

        $this->assertSame(2, $this->type->getVehicleCount());
    }

    public function testGetSetIconName(): void
    {
        $this->type->setIconName('directions_car');
        
        $this->assertSame('directions_car', $this->type->getIconName());
    }

    public function testGetSetIsPopular(): void
    {
        $this->type->setIsPopular(true);
        
        $this->assertTrue($this->type->isPopular());
    }

    public function testGetSetAvgInsuranceGroup(): void
    {
        $this->type->setAvgInsuranceGroup(15);
        
        $this->assertSame(15, $this->type->getAvgInsuranceGroup());
    }

    public function testGetSetFuelEfficiencyRating(): void
    {
        $this->type->setFuelEfficiencyRating(3.5);
        
        $this->assertSame('3.5', $this->type->getFuelEfficiencyRating());
    }
}
