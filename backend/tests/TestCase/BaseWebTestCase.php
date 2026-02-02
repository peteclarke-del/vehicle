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
        
        // Create schema once per test class for in-memory SQLite
        if (!self::$schemaCreated) {
            try {
                static::bootKernel();
                $container = static::getContainer();
                if ($container->has('doctrine')) {
                    $doctrine = $container->get('doctrine');
                    $em = $doctrine->getManager();
                    $metadata = $em->getMetadataFactory()->getAllMetadata();
                    if (!empty($metadata)) {
                        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($em);
                        $schemaTool->createSchema($metadata);
                    }
                }
                self::$schemaCreated = true;
            } catch (\Throwable $e) {
                // Ignore schema creation errors
            } finally {
                try {
                    static::ensureKernelShutdown();
                } catch (\Throwable) {
                }
            }
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
            static::$authToken = 'test@example.com';
            self::$usersSeeded = true;
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
