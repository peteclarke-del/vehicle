<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Consumable;
use App\Entity\ConsumableType;
use App\Entity\VehicleType;
use PHPUnit\Framework\TestCase;

class ConsumableTypeTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $type = new ConsumableType();
        $vehicleType = new VehicleType();
        $vehicleType->setName('Car');

        $type->setName('Engine Oil');
        $type->setVehicleType($vehicleType);
        $type->setUnit('litres');
        $type->setDescription('Lubricant');

        $this->assertSame('Engine Oil', $type->getName());
        $this->assertSame('litres', $type->getUnit());
        $this->assertSame('Lubricant', $type->getDescription());
        $this->assertSame($vehicleType, $type->getVehicleType());
        $this->assertSame('Engine Oil', (string) $type);
    }

    public function testAddAndRemoveConsumable(): void
    {
        $type = new ConsumableType();
        $consumable = new Consumable();

        $type->addConsumable($consumable);
        $this->assertSame(1, $type->getConsumableCount());

        $type->removeConsumable($consumable);
        $this->assertSame(0, $type->getConsumableCount());
    }
}
