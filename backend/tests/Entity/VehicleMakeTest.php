<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\VehicleMake;
use App\Entity\VehicleModel;
use PHPUnit\Framework\TestCase;

/**
 * Vehicle Make Entity Test
 * 
 * Unit tests for VehicleMake entity
 * 
 * @coversDefaultClass \App\Entity\VehicleMake
 */
class VehicleMakeTest extends TestCase
{
    private VehicleMake $make;

    protected function setUp(): void
    {
        $this->make = new VehicleMake();
    }

    public function testGetSetName(): void
    {
        $this->make->setName('Toyota');
        
        $this->assertSame('Toyota', $this->make->getName());
    }

    // Property removed - test disabled
    // public function testGetSetLogoUrl(): void
    // {
    //     $this->make->setLogoUrl('https://example.com/toyota-logo.png');
    //     
    //     $this->assertSame('https://example.com/toyota-logo.png', $this->make->getLogoUrl());
    // }

    // Property removed - test disabled
    // public function testGetSetCountryOfOrigin(): void
    // {
    //     $this->make->setCountryOfOrigin('Japan');
    //     
    //     $this->assertSame('Japan', $this->make->getCountryOfOrigin());
    // }

    public function testAddModel(): void
    {
        $model = new VehicleModel();
        $model->setName('Corolla');

        $this->make->addModel($model);

        $this->assertCount(1, $this->make->getModels());
        $this->assertTrue($this->make->getModels()->contains($model));
    }

    public function testRemoveModel(): void
    {
        $model = new VehicleModel();
        $model->setName('Corolla');

        $this->make->addModel($model);
        $this->make->removeModel($model);

        $this->assertCount(0, $this->make->getModels());
        $this->assertFalse($this->make->getModels()->contains($model));
    }

    public function testDoesNotAddDuplicateModels(): void
    {
        $model = new VehicleModel();
        $model->setName('Corolla');

        $this->make->addModel($model);
        $this->make->addModel($model);

        $this->assertCount(1, $this->make->getModels());
    }

    public function testGetSetIsActive(): void
    {
        $this->make->setIsActive(true);
        
        $this->assertTrue($this->make->isActive());

        $this->make->setIsActive(false);
        
        $this->assertFalse($this->make->isActive());
    }

    // Property removed - test disabled
    // public function testGetSetPopularity(): void
    // {
    //     $this->make->setPopularity(95);
    //     
    //     $this->assertSame(95, $this->make->getPopularity());
    // }

    public function testToString(): void
    {
        $this->make->setName('Toyota');
        
        $this->assertSame('Toyota', (string) $this->make);
    }

    public function testGetModelByName(): void
    {
        $corolla = new VehicleModel();
        $corolla->setName('Corolla');

        $camry = new VehicleModel();
        $camry->setName('Camry');

        $this->make->addModel($corolla);
        $this->make->addModel($camry);

        $found = $this->make->getModelByName('Corolla');

        $this->assertSame($corolla, $found);
    }

    public function testGetModelByNameReturnsNull(): void
    {
        $found = $this->make->getModelByName('NonExistent');

        $this->assertNull($found);
    }

    public function testGetModelCount(): void
    {
        $corolla = new VehicleModel();
        $camry = new VehicleModel();

        $this->make->addModel($corolla);
        $this->make->addModel($camry);

        $this->assertSame(2, $this->make->getModelCount());
    }

    // Property removed - test disabled
    // public function testGetSetFoundedYear(): void
    // {
    //     $this->make->setFoundedYear(1937);
    //     
    //     $this->assertSame(1937, $this->make->getFoundedYear());
    // }

    // Property removed - test disabled
    // public function testGetSetHeadquarters(): void
    // {
    //     $this->make->setHeadquarters('Toyota City, Japan');
    //     
    //     $this->assertSame('Toyota City, Japan', $this->make->getHeadquarters());
    // }
}
