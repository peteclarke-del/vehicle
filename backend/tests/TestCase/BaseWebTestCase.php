<?php

declare(strict_types=1);

namespace App\Tests\TestCase;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Base test case that ensures a shared test user is available and
 * obtains a JWT once for use across all integration tests.
 */
abstract class BaseWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected static string $authToken = '';
    protected static bool $usersSeeded = false;
    protected static bool $schemaCreated = false;
    
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        
        // Schema is created once via doctrine:schema:create before running tests.
        // We don't need to create it here as we use a file-backed SQLite database.
        // The static flag helps us know if we already checked this session.
        if (!self::$schemaCreated) {
            self::$schemaCreated = true;
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure kernel is shut down before creating client
        static::ensureKernelShutdown();

        // Create the client
        $this->client = static::createClient();

        // Seed test user once
        if (!self::$usersSeeded) {
            $this->seedTestUser();
            static::$authToken = 'test@example.com';
            self::$usersSeeded = true;
        }
    }

    /**
     * Create the test user in the database if it doesn't exist
     */
    protected function seedTestUser(): void
    {
        $em = $this->getEntityManager();
        $existingUser = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        
        if (!$existingUser) {
            $user = new User();
            $user->setEmail('test@example.com');
            $user->setPassword('$2y$13$hashed_password_for_testing');
            $user->setRoles(['ROLE_USER']);
            $user->setFirstName('Test');
            $user->setLastName('User');
            $em->persist($user);
            $em->flush();
        }
    }

    protected function getAuthToken(): string
    {
        return static::$authToken;
    }

    protected function getAdminToken(): string
    {
        // For tests, return the same token
        return static::$authToken;
    }

    protected function getEntityManager(): \Doctrine\ORM\EntityManagerInterface
    {
        return static::getContainer()->get('doctrine')->getManager();
    }

    /**
     * Get or create a VehicleType for testing
     */
    protected function getVehicleType(string $name = 'Car'): \App\Entity\VehicleType
    {
        $em = $this->getEntityManager();
        $type = $em->getRepository(\App\Entity\VehicleType::class)->findOneBy(['name' => $name]);
        
        if (!$type) {
            $type = new \App\Entity\VehicleType();
            $type->setName($name);
            $em->persist($type);
            $em->flush();
        }
        
        return $type;
    }

    /**
     * Create a minimal valid vehicle for testing
     */
    protected function createTestVehicle(\App\Entity\User $owner, ?string $registration = null): \App\Entity\Vehicle
    {
        $em = $this->getEntityManager();
        
        $vehicle = new \App\Entity\Vehicle();
        $vehicle->setOwner($owner);
        $vehicle->setName($registration ?? 'Test-' . uniqid());
        $vehicle->setRegistration($registration ?? 'TEST' . rand(100, 999));
        $vehicle->setVehicleType($this->getVehicleType('Car'));
        $vehicle->setMileage(10000);
        $vehicle->setPurchaseCost('5000.00');
        $vehicle->setPurchaseDate(new \DateTime('-1 year'));
        
        $em->persist($vehicle);
        $em->flush();
        
        return $vehicle;
    }
}
