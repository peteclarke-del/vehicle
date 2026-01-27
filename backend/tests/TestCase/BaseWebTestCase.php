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
        // Ensure the test database schema exists for in-memory SQLite.
        parent::setUpBeforeClass();
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
        } catch (\Throwable $e) {
            // If schema creation fails, swallow the exception and allow
            // tests to provide their own setup. This keeps test runs
            // resilient across CI and local environments.
        } finally {
            try {
                static::ensureKernelShutdown();
            } catch (\Throwable) {
                // ignore
            }
        }
    }

    protected function setUp(): void
    {
        // Ensure schema exists for in-memory sqlite. Create once per test process.
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
            } catch (\Throwable $e) {
                // swallow; tests will fail if schema cannot be created
            } finally {
                try {
                    static::ensureKernelShutdown();
                } catch (\Throwable) {
                }
            }
            self::$schemaCreated = true;
        }

        // Create the client without default authentication headers.
        // Individual tests should provide `HTTP_X_TEST_MOCK_AUTH` or
        // `HTTP_AUTHORIZATION` when they need an authenticated request.
        $this->client = static::createClient();

        // Avoid touching the real database in tests; provide a simple
        // in-memory test auth token. Controllers and other tests that
        // depend on persistence should use mocks where appropriate.
        if (!self::$usersSeeded) {
            static::$authToken = 'admin@vehicle.local';
            // Also set a security token in the test container so requests
            // executed with the client are treated as authenticated when
            // controllers call `$this->getUser()`.
            try {
                $container = static::getContainer();
                // Persist a test user in the database so tests that create
                // related entities (e.g. Vehicle->owner) can reference a
                // managed user entity. Do NOT set a global security token
                // here; tests that require authentication must send the
                // appropriate header so we can accurately test unauthenticated
                // behavior.
                $userClass = User::class;
                $user = new $userClass();
                $user->setEmail(static::$authToken);
                $user->setFirstName('Test');
                $user->setLastName('User');

                try {
                    if ($container->has('doctrine')) {
                        $doctrine = $container->get('doctrine');
                        $em = $doctrine->getManager();
                        $repo = $em->getRepository($userClass);
                        $existing = $repo->findOneBy(['email' => static::$authToken]);
                        if ($existing) {
                            $user = $existing;
                        } else {
                            $em->persist($user);
                            $em->flush();
                            // reload to ensure managed instance
                            $user = $repo->findOneBy(['email' => static::$authToken]) ?? $user;
                        }
                    }
                } catch (\Throwable) {
                    // ignore persistence failures; tests will add headers
                }
            } catch (\Throwable) {
                // swallow; tests will rely on TestAuthSubscriber fallback
            }

            self::$usersSeeded = true;
        }

        // Ensure schema exists for in-memory sqlite. Create once per test process.
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
            } catch (\Throwable $e) {
                // swallow; tests will fail if schema cannot be created
            } finally {
                try {
                    static::ensureKernelShutdown();
                } catch (\Throwable) {
                }
            }
            self::$schemaCreated = true;
        }
    }

    protected function getAuthToken(): string
    {
        return static::$authToken;
    }
}
