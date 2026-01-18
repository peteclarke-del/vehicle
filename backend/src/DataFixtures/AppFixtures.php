<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\VehicleType;
use App\Entity\ConsumableType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Create default admin user
        $admin = new User();
        $admin->setEmail('admin@vehicle.local');
        $admin->setFirstName('Admin');
        $admin->setLastName('User');
        $admin->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'changeme'));
        $admin->setPasswordChangeRequired(true);
        $admin->setDistanceUnit('miles'); // Default to miles for UK
        $manager->persist($admin);
        $manager->flush();

        echo "\n✓ Created admin user: admin@vehicle.local / changeme (password change required)\n";
        // Create vehicle types
        $carType = new VehicleType();
        $carType->setName('Car');
        $manager->persist($carType);

        $truckType = new VehicleType();
        $truckType->setName('Truck');
        $manager->persist($truckType);

        $bikeType = new VehicleType();
        $bikeType->setName('Motorcycle');
        $manager->persist($bikeType);

        $manager->flush();

        // Create consumable types for cars
        $this->createConsumableType($manager, $carType, 'Engine Oil', 'litres', 'Engine oil type and capacity');
        $this->createConsumableType($manager, $carType, 'Transmission Oil', 'litres', 'Gearbox/transmission oil');
        $this->createConsumableType($manager, $carType, 'Front Tyres', 'psi', 'Front tyre specification and pressure');
        $this->createConsumableType($manager, $carType, 'Rear Tyres', 'psi', 'Rear tyre specification and pressure');
        $this->createConsumableType($manager, $carType, 'Brake Fluid', 'ml', 'Brake fluid type');
        $this->createConsumableType($manager, $carType, 'Coolant', 'litres', 'Engine coolant type');
        $this->createConsumableType($manager, $carType, 'Air Filter', 'unit', 'Air filter specification');
        $this->createConsumableType($manager, $carType, 'Cabin Filter', 'unit', 'Cabin air filter');

        // Create consumable types for trucks
        $this->createConsumableType($manager, $truckType, 'Engine Oil', 'litres', 'Engine oil type and capacity');
        $this->createConsumableType($manager, $truckType, 'Transmission Oil', 'litres', 'Gearbox/transmission oil');
        $this->createConsumableType(
            $manager,
            $truckType,
            'Front Tyres',
            'psi',
            'Front tyre specification and pressure'
        );
        $this->createConsumableType($manager, $truckType, 'Rear Tyres', 'psi', 'Rear tyre specification and pressure');
        $this->createConsumableType($manager, $truckType, 'Differential Oil', 'litres', 'Differential oil type');
        $this->createConsumableType($manager, $truckType, 'Brake Fluid', 'ml', 'Brake fluid type');
        $this->createConsumableType($manager, $truckType, 'Coolant', 'litres', 'Engine coolant type');

        // Create consumable types for motorcycles
        $this->createConsumableType($manager, $bikeType, 'Engine Oil', 'litres', 'Engine oil type and capacity');
        $this->createConsumableType($manager, $bikeType, 'Front Tyre', 'psi', 'Front tyre specification and pressure');
        $this->createConsumableType($manager, $bikeType, 'Rear Tyre', 'psi', 'Rear tyre specification and pressure');
        $this->createConsumableType($manager, $bikeType, 'Chain Lube', 'ml', 'Chain lubricant type');
        $this->createConsumableType($manager, $bikeType, 'Brake Fluid', 'ml', 'Brake fluid type');
        $this->createConsumableType($manager, $bikeType, 'Coolant', 'litres', 'Engine coolant type (if applicable)');

        $manager->flush();
    }

    private function createConsumableType(
        ObjectManager $manager,
        VehicleType $vehicleType,
        string $name,
        string $unit,
        string $description
    ): void {
        $consumableType = new ConsumableType();
        $consumableType->setVehicleType($vehicleType);
        $consumableType->setName($name);
        $consumableType->setUnit($unit);
        $consumableType->setDescription($description);
        $manager->persist($consumableType);
    }
}
