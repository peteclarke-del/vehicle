<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\ConsumableType;
use App\Entity\Consumable;
use PHPUnit\Framework\TestCase;

/**
 * Consumable Type Entity Test
 * 
 * Unit tests for ConsumableType entity
 * 
 * @coversDefaultClass \App\Entity\ConsumableType
 */
class ConsumableTypeTest extends TestCase
{
    private ConsumableType $type;

    protected function setUp(): void
    {
        $this->type = new ConsumableType();
    }

    public function testGetSetName(): void
    {
        $this->type->setName('Engine Oil');
        
        $this->assertSame('Engine Oil', $this->type->getName());
    }

    public function testGetSetCategory(): void
    {
        $this->type->setCategory('Fluids');
        
        $this->assertSame('Fluids', $this->type->getCategory());
    }

    public function testGetSetDefaultIntervalMiles(): void
    {
        $this->type->setDefaultIntervalMiles(10000);
        
        $this->assertSame(10000, $this->type->getDefaultIntervalMiles());
    }

    public function testGetSetDefaultIntervalMonths(): void
    {
        $this->type->setDefaultIntervalMonths(12);
        
        $this->assertSame(12, $this->type->getDefaultIntervalMonths());
    }

    public function testGetSetDescription(): void
    {
        $description = 'Engine oil lubricates moving parts';
        $this->type->setDescription($description);

        $this->assertSame($description, $this->type->getDescription());
    }

    public function testAddConsumable(): void
    {
        $consumable = new Consumable();
        $consumable->setName('Castrol 5W-30');

        $this->type->addConsumable($consumable);

        $this->assertCount(1, $this->type->getConsumables());
        $this->assertTrue($this->type->getConsumables()->contains($consumable));
    }

    public function testRemoveConsumable(): void
    {
        $consumable = new Consumable();
        $consumable->setName('Castrol 5W-30');

        $this->type->addConsumable($consumable);
        $this->type->removeConsumable($consumable);

        $this->assertCount(0, $this->type->getConsumables());
    }

    public function testGetSetIconName(): void
    {
        $this->type->setIconName('oil_barrel');
        
        $this->assertSame('oil_barrel', $this->type->getIconName());
    }

    public function testGetSetIsCommon(): void
    {
        $this->type->setIsCommon(true);
        
        $this->assertTrue($this->type->isCommon());
    }

    public function testToString(): void
    {
        $this->type->setName('Brake Fluid');
        
        $this->assertSame('Brake Fluid', (string) $this->type);
    }

    public function testGetSetTypicalCost(): void
    {
        $this->type->setTypicalCost(45.99);
        
        $this->assertSame('45.99', $this->type->getTypicalCost());
    }

    public function testGetSetManufacturerRecommendation(): void
    {
        $recommendation = 'Change every 10,000 miles or annually';
        $this->type->setManufacturerRecommendation($recommendation);

        $this->assertSame($recommendation, $this->type->getManufacturerRecommendation());
    }

    public function testGetConsumableCount(): void
    {
        $consumable1 = new Consumable();
        $consumable1->setName('Castrol 5W-30');

        $consumable2 = new Consumable();
        $consumable2->setName('Mobil 1 5W-30');

        $this->type->addConsumable($consumable1);
        $this->type->addConsumable($consumable2);

        $this->assertSame(2, $this->type->getConsumableCount());
    }

    public function testGetSetRequiresSpecialization(): void
    {
        $this->type->setRequiresSpecialization(true);
        
        $this->assertTrue($this->type->requiresSpecialization());
    }
}
