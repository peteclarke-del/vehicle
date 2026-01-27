<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\UserPreference;
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
        $manager->persist($admin);
        
        // store default preferred language, distance unit and theme in user_preferences
        $prefLang = new UserPreference();
        $prefLang->setUser($admin);
        $prefLang->setName('preferredLanguage');
        $prefLang->setValue('en');
        $manager->persist($prefLang);

        $pref = new UserPreference();
        $pref->setUser($admin);
        $pref->setName('distanceUnit');
        $pref->setValue('mi');
        $manager->persist($pref);

        $prefTheme = new UserPreference();
        $prefTheme->setUser($admin);
        $prefTheme->setName('theme');
        $prefTheme->setValue('light');
        $manager->persist($prefTheme);
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
