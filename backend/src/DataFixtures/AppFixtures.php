<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\VehicleType;
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
    }
}
