<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\VehicleMake;
use App\Entity\VehicleModel;
use App\Entity\VehicleType;
use App\Entity\Vehicle;
use PHPUnit\Framework\TestCase;

/**
 * Vehicle Model Entity Test
 * 
 * Unit tests for VehicleModel entity
 * 
 * @coversDefaultClass \App\Entity\VehicleModel
 */
class VehicleModelTest extends TestCase
{
    private VehicleModel $model;

    protected function setUp(): void
    {
        $this->model = new VehicleModel();
    }

    public function testGetSetName(): void
    {
        $this->model->setName('Corolla');
        
        $this->assertSame('Corolla', $this->model->getName());
    }

    public function testGetSetMake(): void
    {
        $make = new VehicleMake();
        $make->setName('Toyota');

        $this->model->setMake($make);

        $this->assertSame($make, $this->model->getMake());
    }

    public function testGetSetVehicleType(): void
    {
        $type = new VehicleType();
        $type->setName('Sedan');

        $this->model->setVehicleType($type);

        $this->assertSame($type, $this->model->getVehicleType());
    }

    public function testGetSetStartYear(): void
    {
        $this->model->setStartYear(1966);
        
        $this->assertSame(1966, $this->model->getStartYear());
    }

    public function testGetSetEndYear(): void
    {
        $this->model->setEndYear(2024);
        
        $this->assertSame(2024, $this->model->getEndYear());
    }

    public function testAddVehicle(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setRegistration('AB12 CDE');

        $this->model->addVehicle($vehicle);

        $this->assertCount(1, $this->model->getVehicles());
        $this->assertTrue($this->model->getVehicles()->contains($vehicle));
    }

    public function testRemoveVehicle(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setRegistration('AB12 CDE');

        $this->model->addVehicle($vehicle);
        $this->model->removeVehicle($vehicle);

        $this->assertCount(0, $this->model->getVehicles());
    }

    public function testGetSetIsActive(): void
    {
        $this->model->setIsActive(true);
        
        $this->assertTrue($this->model->isActive());
    }

    public function testIsStillInProduction(): void
    {
        $this->model->setStartYear(1966);
        $this->model->setEndYear(null);

        $this->assertTrue($this->model->isStillInProduction());
    }

    public function testIsNotStillInProduction(): void
    {
        $this->model->setStartYear(1966);
        $this->model->setEndYear(2020);

        $this->assertFalse($this->model->isStillInProduction());
    }

    public function testToString(): void
    {
        $make = new VehicleMake();
        $make->setName('Toyota');

        $this->model->setName('Corolla');
        $this->model->setMake($make);

        $this->assertSame('Toyota Corolla', (string) $this->model);
    }

    public function testGetSetImageUrl(): void
    {
        $this->model->setImageUrl('https://example.com/corolla.jpg');
        
        $this->assertSame('https://example.com/corolla.jpg', $this->model->getImageUrl());
    }

    // Property removed - test disabled
    // public function testGetSetEngineOptions(): void
    // {
    //     $options = ['1.8L Petrol', '2.0L Hybrid'];
    //     $this->model->setEngineOptions($options);
    //
    //     $this->assertSame($options, $this->model->getEngineOptions());
    // }

    // Property removed - test disabled
    // public function testGetSetTransmissionOptions(): void
    // {
    //     $options = ['6-speed Manual', 'CVT'];
    //     $this->model->setTransmissionOptions($options);
    //
    //     $this->assertSame($options, $this->model->getTransmissionOptions());
    // }

    // Property removed - test disabled
    // public function testGetGenerationCount(): void
    // {
    //     $this->model->setGenerationCount(12);
    //     
    //     $this->assertSame(12, $this->model->getGenerationCount());
    // }
}
