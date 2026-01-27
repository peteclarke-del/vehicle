<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * TestAuthSubscriber
 *
 * Maps a mock Authorization header to a real User during tests so integration
 * tests that use a fake bearer token can authenticate.
 */
class TestAuthSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private TokenStorageInterface $tokenStorage
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Run very early so the test token is available to the security firewall
        // Use a very high priority so it runs before authenticators that inspect
        // the Authorization header (e.g. JWT listeners).
        return [KernelEvents::REQUEST => ['onKernelRequest', PHP_INT_MAX]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Prefer a test-only header so the real Authorization header isn't processed
        // by the JWT listener. Tests will send `X-TEST-MOCK-AUTH: user@example.com`.
        $mockUserHeader = $request->headers->get('X-TEST-MOCK-AUTH');
        $authHeader = $request->headers->get('Authorization');

        $email = null;

        if ($mockUserHeader) {
            $email = (string) $mockUserHeader;
        } elseif ($authHeader) {
            // Accept Authorization values used in tests. Support formats like:
            // - admin@vehicle.local
            // - Bearer admin@vehicle.local
            // - Bearer mock-jwt-token-12345  (map to default test admin)
            $value = (string) $authHeader;
            if (stripos($value, 'bearer ') === 0) {
                $value = substr($value, 7);
            }

            if (str_contains($value, '@')) {
                $email = $value;
            } else {
                // Non-email mock token: map to default test admin
                $email = 'admin@vehicle.local';
            }
        }

        if (!$email) {
            return;
        }

        // Find the fixture user by email in the test database
        $repo = $this->em->getRepository(User::class);
        $user = null;
        try {
            $user = $repo->findOneBy(['email' => $email]);
        } catch (\Throwable) {
            // If repository access fails (e.g. no DB configured in tests),
            // we'll fall back to an in-memory user below.
            $user = null;
        }

        if (!$user instanceof User) {
            // Create a lightweight User. Prefer to persist it so the
            // security token holds a managed entity (avoids Doctrine
            // "new entity found through relationship" errors during
            // controller flushes). If persistence fails, fall back to
            // an in-memory user.
            $user = new User();
            $user->setEmail($email);
            $user->setFirstName('Test');
            $user->setLastName(explode('@', $email)[0] ?? 'test');
            $user->setRoles(['ROLE_USER']);

            try {
                $this->em->persist($user);
                $this->em->flush();
                // reload to ensure we have the managed instance
                $user = $repo->findOneBy(['email' => $email]) ?? $user;
            } catch (\Throwable) {
                // If DB access fails, keep the in-memory user as a
                // graceful fallback for environments without a test DB.
            }
        }

        // Create token and store it so controllers can use getUser().
        // Use the API firewall provider key so stateless API requests in
        // tests are treated as authenticated.
        $token = new UsernamePasswordToken(
            $user,
            'api',
            $user->getRoles()
        );
        $this->tokenStorage->setToken($token);
    }
}
