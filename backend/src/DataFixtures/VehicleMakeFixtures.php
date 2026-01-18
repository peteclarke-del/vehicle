<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\VehicleMake;
use App\Entity\VehicleType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class VehicleMakeFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $makeData = [
            // Car makes
            ['type' => 'Car', 'name' => 'Audi'],
            ['type' => 'Car', 'name' => 'BMW'],
            ['type' => 'Car', 'name' => 'Ford'],
            ['type' => 'Car', 'name' => 'Honda'],
            ['type' => 'Car', 'name' => 'Hyundai'],
            ['type' => 'Car', 'name' => 'Kia'],
            ['type' => 'Car', 'name' => 'Mercedes-Benz'],
            ['type' => 'Car', 'name' => 'Nissan'],
            ['type' => 'Car', 'name' => 'Peugeot'],
            ['type' => 'Car', 'name' => 'Renault'],
            ['type' => 'Car', 'name' => 'Seat'],
            ['type' => 'Car', 'name' => 'Skoda'],
            ['type' => 'Car', 'name' => 'Toyota'],
            ['type' => 'Car', 'name' => 'Vauxhall'],
            ['type' => 'Car', 'name' => 'Volkswagen'],
            ['type' => 'Car', 'name' => 'Volvo'],
            ['type' => 'Car', 'name' => 'Mazda'],
            ['type' => 'Car', 'name' => 'Mitsubishi'],
            ['type' => 'Car', 'name' => 'Citroen'],
            ['type' => 'Car', 'name' => 'Fiat'],
            ['type' => 'Car', 'name' => 'Chevrolet'],
            ['type' => 'Car', 'name' => 'Tesla'],
            ['type' => 'Car', 'name' => 'Porsche'],
            ['type' => 'Car', 'name' => 'Subaru'],
            ['type' => 'Car', 'name' => 'Jeep'],

            // Motorcycle makes
            ['type' => 'Motorcycle', 'name' => 'AJS'],
            ['type' => 'Motorcycle', 'name' => 'BMW'],
            ['type' => 'Motorcycle', 'name' => 'Ducati'],
            ['type' => 'Motorcycle', 'name' => 'Harley-Davidson'],
            ['type' => 'Motorcycle', 'name' => 'Honda'],
            ['type' => 'Motorcycle', 'name' => 'Kawasaki'],
            ['type' => 'Motorcycle', 'name' => 'KTM'],
            ['type' => 'Motorcycle', 'name' => 'Suzuki'],
            ['type' => 'Motorcycle', 'name' => 'Triumph'],
            ['type' => 'Motorcycle', 'name' => 'Yamaha'],
            ['type' => 'Motorcycle', 'name' => 'Aprilia'],
            ['type' => 'Motorcycle', 'name' => 'Benelli'],
            ['type' => 'Motorcycle', 'name' => 'Indian'],
            ['type' => 'Motorcycle', 'name' => 'Moto Guzzi'],
            ['type' => 'Motorcycle', 'name' => 'MV Agusta'],
            ['type' => 'Motorcycle', 'name' => 'Norton'],
            ['type' => 'Motorcycle', 'name' => 'Royal Enfield'],

            // Van makes
            ['type' => 'Van', 'name' => 'Citroen'],
            ['type' => 'Van', 'name' => 'Fiat'],
            ['type' => 'Van', 'name' => 'Ford'],
            ['type' => 'Van', 'name' => 'Mercedes-Benz'],
            ['type' => 'Van', 'name' => 'Peugeot'],
            ['type' => 'Van', 'name' => 'Renault'],
            ['type' => 'Van', 'name' => 'Vauxhall'],
            ['type' => 'Van', 'name' => 'Volkswagen'],
            ['type' => 'Van', 'name' => 'Nissan'],
            ['type' => 'Van', 'name' => 'Toyota'],
            ['type' => 'Van', 'name' => 'Ram'],
            ['type' => 'Van', 'name' => 'Chevrolet'],
            ['type' => 'Van', 'name' => 'GMC'],

            // Truck makes
            ['type' => 'Truck', 'name' => 'DAF'],
            ['type' => 'Truck', 'name' => 'Ford'],
            ['type' => 'Truck', 'name' => 'Iveco'],
            ['type' => 'Truck', 'name' => 'MAN'],
            ['type' => 'Truck', 'name' => 'Mercedes-Benz'],
            ['type' => 'Truck', 'name' => 'Renault'],
            ['type' => 'Truck', 'name' => 'Scania'],
            ['type' => 'Truck', 'name' => 'Volvo'],
        ];

        $existingMakes = [];
        foreach ($makeData as $data) {
            $type = $manager->getRepository(VehicleType::class)
                ->findOneBy(['name' => $data['type']]);

            if (!$type) {
                continue;
            }

            // Check if this make already exists for this type
            $key = $type->getId() . '-' . $data['name'];
            if (isset($existingMakes[$key])) {
                continue;
            }

            // Check database
            $existing = $manager->getRepository(VehicleMake::class)
                ->findOneBy(['name' => $data['name'], 'vehicleType' => $type]);

            if ($existing) {
                $existingMakes[$key] = true;
                continue;
            }

            $make = new VehicleMake();
            $make->setName($data['name']);
            $make->setVehicleType($type);

            $manager->persist($make);
            $existingMakes[$key] = true;
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            VehicleTypeFixtures::class,
        ];
    }
}
