<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ConsumableType;
use App\Entity\VehicleType;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ConsumableTypeFixtures extends \App\DataFixtures\AbstractJsonFixture implements DependentFixtureInterface
{
    private array $_typeMap = [];
    private array $_existing = [];

    protected function beforeLoad(ObjectManager $manager, array $data): void
    {
        // Extract vehicleType names and consumable names from JSON data
        $typeNames = [];
        $consumableNames = [];
        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }
            $typeNames[] = $item['vehicleType'] ?? null;
            $consumableNames[] = $item['name'] ?? null;
        }
        $typeNames = array_values(array_unique(array_filter($typeNames)));
        $consumableNames = array_filter($consumableNames);
        $consumableNames = array_values(array_unique($consumableNames));

        if (count($typeNames) === 0) {
            return;
        }

        // preload only the vehicle types we need
        $typeRepo = $manager->getRepository(VehicleType::class);
        $types = $typeRepo->findBy(['name' => $typeNames]);
        foreach ($types as $t) {
            $this->_typeMap[$t->getName()] = $t;
        }

        // preload existing consumables for the relevant types and names
        if (count($consumableNames) > 0 && count($types) > 0) {
            $existing = $manager->getRepository(ConsumableType::class)
                ->findBy(['vehicleType' => $types, 'name' => $consumableNames]);
            foreach ($existing as $e) {
                $k = $e->getVehicleType()->getId() . '-' . $e->getName();
                $this->_existing[$k] = true;
            }
        }
    }

    protected function getDataFilename(): string
    {
        // consumables are under data/<Type>/consumables.json
        return '*/consumables.json';
    }

    protected function processItem(mixed $item, ObjectManager $manager): void
    {
        if (!is_array($item)) {
            return;
        }

        $typeName = $item['vehicleType'] ?? null;
        $name = $item['name'] ?? null;
        $unit = $item['unit'] ?? 'unit';
        $description = $item['description'] ?? '';

        if (!$typeName || !$name) {
            return;
        }

        $vehicleType = $this->_typeMap[$typeName] ?? null;
        if (!$vehicleType) {
            return;
        }

        $key = $vehicleType->getId() . '-' . $name;
        if (isset($this->_existing[$key])) {
            return;
        }

        $consumableType = new ConsumableType();
        $consumableType->setVehicleType($vehicleType);
        $consumableType->setName($name);
        $consumableType->setUnit($unit);
        $consumableType->setDescription($description);
        $manager->persist($consumableType);
        $this->_existing[$key] = true;
    }

    public function getDependencies(): array
    {
        // ensure all vehicle data is loaded first (types/makes/models)
        return [VehicleModelFixtures::class];
    }
}
