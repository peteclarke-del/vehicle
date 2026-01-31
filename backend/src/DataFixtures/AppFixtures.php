<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\UserPreference;
use App\Entity\VehicleType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * class AppFixtures
 */
class AppFixtures extends Fixture
{
    /**
     * function __construct
     *
     * @param UserPasswordHasherInterface $passwordHasher
     * @param ParameterBagInterface $params
     *
     * @return void
     */
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private ParameterBagInterface $params
    ) {
    }

    /**
     * function load
     *
     * @param ObjectManager $manager
     *
     * @return void
     */
    public function load(ObjectManager $manager): void
    {
        // Load users from JSON file
        $this->loadUsers($manager);

        // Create vehicle types based on data directory structure
        $this->loadVehicleTypes($manager);

        $manager->flush();
    }

    /**
     * function loadUsers
     *
     * Load users from JSON file or create default admin user
     *
     * @param ObjectManager $manager
     *
     * @return void
     */
    private function loadUsers(ObjectManager $manager): void
    {
        $dataDir = $this->params->get('kernel.project_dir') . '/data';
        $usersFile = $dataDir . '/users.json';

        if (file_exists($usersFile)) {
            $usersData = json_decode(file_get_contents($usersFile), true);

            if (!is_array($usersData)) {
                echo "✗ Error: Invalid users.json format\n";
                return;
            }

            foreach ($usersData as $userData) {
                $user = new User();
                $user->setEmail($userData['email']);
                $user->setFirstName($userData['firstName']);
                $user->setLastName($userData['lastName']);
                $user->setRoles($userData['roles'] ?? ['ROLE_USER']);
                $user->setPassword($this->passwordHasher->hashPassword($user, $userData['password']));
                $user->setPasswordChangeRequired($userData['passwordChangeRequired'] ?? false);
                $manager->persist($user);

                // Load user preferences
                if (isset($userData['preferences']) && is_array($userData['preferences'])) {
                    foreach ($userData['preferences'] as $name => $value) {
                        $pref = new UserPreference();
                        $pref->setUser($user);
                        $pref->setName($name);
                        $pref->setValue($value);
                        $manager->persist($pref);
                    }
                }

                echo sprintf("✓ Created user: %s (%s %s)\n", $userData['email'], $userData['firstName'], $userData['lastName']);
            }

            $manager->flush();
        } else {
            // Fallback: Create default admin user if no JSON file exists
            echo "⚠ No users.json found, creating default admin user\n";

            $admin = new User();
            $admin->setEmail('admin@vehicle.local');
            $admin->setFirstName('Admin');
            $admin->setLastName('User');
            $admin->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
            $admin->setPassword($this->passwordHasher->hashPassword($admin, 'changeme'));
            $admin->setPasswordChangeRequired(true);
            $manager->persist($admin);

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
            echo "✓ Created admin user: admin@vehicle.local / changeme (password change required)\n";
        }
    }

    /**
     * function loadVehicleTypes
     *
     * Load vehicle types based on data directory structure
     *
     * @param ObjectManager $manager
     *
     * @return void
     */
    private function loadVehicleTypes(ObjectManager $manager): void
    {
        $dataDir = $this->params->get('kernel.project_dir') . '/data';

        // Get all directories in data folder - these represent vehicle types
        $vehicleTypes = [];
        if (is_dir($dataDir)) {
            $entries = scandir($dataDir);
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..' || $entry === 'users.json') {
                    continue;
                }

                $path = $dataDir . '/' . $entry;
                if (is_dir($path)) {
                    $vehicleTypes[] = $entry;
                }
            }
        }

        // If no directories found, create default types
        if (empty($vehicleTypes)) {
            $vehicleTypes = ['Car', 'Truck', 'Motorcycle', 'Van', 'EV'];
        }

        foreach ($vehicleTypes as $typeName) {
            $type = new VehicleType();
            $type->setName($typeName);
            $manager->persist($type);
            echo sprintf("✓ Created vehicle type: %s\n", $typeName);
        }

        $manager->flush();
    }
}
