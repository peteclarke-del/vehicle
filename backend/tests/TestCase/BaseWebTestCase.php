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
    public static function setUpBeforeClass(): void
    {
        // Intentionally left blank — seeding is performed lazily in setUp()
        parent::setUpBeforeClass();
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();

        if (!self::$usersSeeded) {
            $container = static::getContainer();
            $em = $container->get('doctrine')->getManager();
            $userRepo = $em->getRepository(User::class);

            $adminEmail = 'admin@vehicle.local';
            $otherEmail = 'other@vehicle.local';
            $password = 'changeme';

            $passwordHasher = $container->get('security.password_hasher');

            foreach ([$adminEmail, $otherEmail] as $email) {
                $user = $userRepo->findOneBy(['email' => $email]);
                if (!$user) {
                    $userClass = User::class;
                    $user = new $userClass();
                    $user->setEmail($email);
                    $user->setRoles(['ROLE_USER']);
                    $user->setFirstName('Test');
                    $user->setLastName(explode('@', $email)[0]);
                    $user->setPassword($passwordHasher->hashPassword($user, $password));
                    $em->persist($user);
                }
            }

            $em->flush();

            static::$authToken = $adminEmail;
            self::$usersSeeded = true;
        }
    }

    protected function getAuthToken(): string
    {
        return static::$authToken;
    }
}
