<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\VehicleType;
use Doctrine\Persistence\ObjectManager;

class VehicleTypeFixtures extends \App\DataFixtures\AbstractJsonFixture
{
    private array $_existingNames = [];

    protected function beforeLoad(ObjectManager $manager, array $data): void
    {
        // Targeted preload: collect type names referenced by the dataset
        $names = [];
        foreach ($data as $item) {
            if (is_array($item)) {
                $names[] = $item['type'] ?? $item['vehicleType'] ?? null;
            } else {
                $names[] = (string) $item;
            }
        }
        $names = array_values(array_unique($names));
        if (count($names) === 0) {
            return;
        }

        $existing = $manager->getRepository(VehicleType::class)->findBy(['name' => $names]);
        foreach ($existing as $e) {
            $this->_existingNames[$e->getName()] = true;
        }
    }
    protected function getDataFilename(): string
    {
        // discover types by scanning per-type directories
        return '*/*.json';
    }

    protected function processItem(mixed $item, ObjectManager $manager): void
    {
        if (is_array($item)) {
            $typeName = (string) ($item['type'] ?? $item['vehicleType'] ?? '');
        } else {
            $typeName = (string) $item;
        }
        if (isset($this->_existingNames[$typeName])) {
            return;
        }

        $type = new VehicleType();
        $type->setName($typeName);

        $manager->persist($type);
        $this->_existingNames[$typeName] = true;
    }
}
