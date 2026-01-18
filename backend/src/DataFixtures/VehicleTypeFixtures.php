<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\VehicleType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class VehicleTypeFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $types = ['Car', 'Motorcycle', 'Van', 'Truck'];

        foreach ($types as $typeName) {
            // Check if type already exists
            $existing = $manager->getRepository(VehicleType::class)
                ->findOneBy(['name' => $typeName]);

            if ($existing) {
                continue;
            }

            $type = new VehicleType();
            $type->setName($typeName);
            $manager->persist($type);
        }

        $manager->flush();
    }
}
