<?php

declare(strict_types=1);

use App\Entity\Consumable;
use App\Entity\ConsumableType;
use App\Entity\Vehicle;
use PHPUnit\Framework\TestCase;

class ConsumableTest extends TestCase
{
    public function testConsumableCreation(): void
    {
        $consumable = new Consumable();
        $this->assertInstanceOf(Consumable::class, $consumable);
        $this->assertNull($consumable->getId());
    }

    public function testBasicSetters(): void
    {
        $consumable = new Consumable();
        $type = new ConsumableType();
        $vehicle = new Vehicle();

        $type->setName('Engine Oil');
        $consumable->setConsumableType($type);
        $consumable->setDescription('Oil change');
        $consumable->setBrand('Castrol');
        $consumable->setPartNumber('OIL-001');
        $consumable->setCost('45.00');
        $consumable->setVehicle($vehicle);

        $this->assertSame($type, $consumable->getConsumableType());
        $this->assertSame('Oil change', $consumable->getDescription());
        $this->assertSame('Castrol', $consumable->getBrand());
        $this->assertSame('OIL-001', $consumable->getPartNumber());
        $this->assertSame('45.00', $consumable->getCost());
        $this->assertSame($vehicle, $consumable->getVehicle());
    }
}
