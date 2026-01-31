<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\VehicleMake;
use App\Entity\VehicleType;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class VehicleMakeFixtures extends \App\DataFixtures\AbstractJsonFixture implements DependentFixtureInterface
{
    private array $_existingMakes = [];
    private array $_typeMap = [];

    protected function beforeLoad(ObjectManager $manager, array $data): void
    {
        // Build list of needed type names and make names from data
        $typeNames = [];
        $makeNames = [];
        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }
            $typeNames[] = $item['type'] ?? $item['vehicleType'] ?? null;
            // make name may be in 'make' or (rarely) 'name' when files contain make objects
            $makeNames[] = $item['make'] ?? $item['name'] ?? null;
        }
        $typeNames = array_values(array_unique(array_filter($typeNames)));
        $makeNames = array_values(array_unique(array_filter($makeNames)));

        if (count($typeNames) === 0) {
            return;
        }

        // preload only the vehicle types we need
        $types = $manager->getRepository(VehicleType::class)->findBy(['name' => $typeNames]);
        foreach ($types as $t) {
            $this->_typeMap[$t->getName()] = $t;
        }

        if (count($makeNames) > 0 && count($types) > 0) {
            // preload existing makes only for the types and names we care about
            $existingMakes = $manager->getRepository(VehicleMake::class)
                ->findBy(['vehicleType' => $types, 'name' => $makeNames]);
            foreach ($existingMakes as $m) {
                $this->_existingMakes[$m->getVehicleType()->getId() . '-' . $m->getName()] = true;
            }
        }
    }

    protected function getDataFilename(): string
    {
        // discover make files under data/<Type>/makes/<Make>.json
        return '*/makes/*.json';
    }

    protected function processItem(mixed $item, ObjectManager $manager): void
    {
        if (!is_array($item)) {
            return;
        }

        $typeName = $item['type'] ?? $item['vehicleType'] ?? null;
        $name = $item['make'] ?? $item['name'] ?? null;
        if (!$typeName || !$name) {
            return;
        }

        $type = $this->_typeMap[$typeName] ?? null;
        if (!$type) {
            return;
        }

        $key = $type->getId() . '-' . $name;
        if (isset($this->_existingMakes[$key])) {
            return;
        }

        $make = new VehicleMake();
        $make->setName($name);
        $make->setVehicleType($type);
        $make->setIsActive($item['isActive'] ?? true);

        $manager->persist($make);
        $this->_existingMakes[$key] = true;
    }

    public function getDependencies(): array
    {
        return [
            VehicleTypeFixtures::class,
        ];
    }
}
