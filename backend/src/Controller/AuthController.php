<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Trait\JsonValidationTrait;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use App\Entity\RefreshToken;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/api')]
class AuthController extends AbstractController
{
    use JsonValidationTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager,
        private JWTEncoderInterface $jwtEncoder,
        private TagAwareCacheInterface $cache
    ) {
    }

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        // This method can be empty - the json_login firewall handles authentication
        // If we reach here, authentication succeeded
        $user = $this->getUser();

        if (!$user instanceof \App\Entity\User) {
            return $this->json(['error' => 'Authentication required'], 401);
        }

        // Invalidate all user-specific caches on login to ensure fresh data
        try {
            $this->cache->invalidateTags(['user.' . $user->getId(), 'vehicles', 'dashboard']);
        } catch (\Throwable $e) {
            // Log but don't fail login if cache invalidation fails
            error_log('Cache invalidation failed on login: ' . $e->getMessage());
        }

        return $this->json([
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName()
            ]
        ]);
    }

    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $validation = $this->validateJsonRequest($request);
        if ($validation['error']) {
            return $validation['error'];
        }
        $data = $validation['data'];

        if (!isset($data['email']) || !isset($data['password'])) {
            return $this->json(['error' => 'Email and password required'], 400);
        }

        // Validate password
        $passwordPolicy = $_ENV['REACT_APP_PASSWORD_POLICY'] ?? null;
        if ($passwordPolicy) {
            // Add delimiters if not present (for PHP preg_match compatibility)
            if (!preg_match('/^\/.*\/[a-z]*$/', $passwordPolicy)) {
                $passwordPolicy = '/' . $passwordPolicy . '/';
            }
            if (!preg_match($passwordPolicy, $data['password'])) {
                return $this->json(['error' => 'Invalid password format'], 400);
            }
        } else {
            if (strlen($data['password']) < 8) {
                return $this->json(['error' => 'Password too short'], 400);
            }
        }

        $existingUser = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => $data['email']]);

        if ($existingUser) {
            return $this->json(['error' => 'User already exists'], 400);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setFirstName($data['firstName'] ?? '');
        $user->setLastName($data['lastName'] ?? '');
        $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));

        // Ensure new accounts have the default ROLE_USER
        $user->setRoles(['ROLE_USER']);

        $this->entityManager->persist($user);

        // Create default user preferences for new users (language, distance unit, theme)
        $langValue = isset($data['preferredLanguage']) ? (string) $data['preferredLanguage'] : 'en';
        $prefLang = new \App\Entity\UserPreference();
        $prefLang->setUser($user);
        $prefLang->setName('preferredLanguage');
        $prefLang->setValue($langValue);
        $this->entityManager->persist($prefLang);

        $distanceValue = 'mi';
        $prefDistance = new \App\Entity\UserPreference();
        $prefDistance->setUser($user);
        $prefDistance->setName('distanceUnit');
        $prefDistance->setValue($distanceValue);
        $this->entityManager->persist($prefDistance);

        $themeValue = 'light';
        $prefTheme = new \App\Entity\UserPreference();
        $prefTheme->setUser($user);
        $prefTheme->setName('theme');
        $prefTheme->setValue($themeValue);
        $this->entityManager->persist($prefTheme);

        $this->entityManager->flush();

        return $this->json([
            'message' => 'User created successfully',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName()
            ]
        ], 201);
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        // read theme from user_preferences (fallback to 'light')
        $repo = $this->entityManager->getRepository(\App\Entity\UserPreference::class);
        $pref = $repo->findOneBy(['user' => $user, 'name' => 'theme']);
        $theme = 'light';
        if ($pref) {
            $val = $pref->getValue();
            $decoded = null;
            if ($val !== null) {
                $decoded = json_decode($val, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $val = $decoded;
                }
            }
            $theme = $val ?? 'light';
        }

        // read preferredLanguage, sessionTimeout and distanceUnit from user_preferences (fallback to user entity if available)
        $repo = $this->entityManager->getRepository(\App\Entity\UserPreference::class);
        $prefLang = $repo->findOneBy(['user' => $user, 'name' => 'preferredLanguage']);
        $preferredLanguage = null;
        if ($prefLang) {
            $preferredLanguage = $prefLang->getValue();
        } elseif (method_exists($user, 'getPreferredLanguage')) {
            $preferredLanguage = $user->getPreferredLanguage();
        } else {
            $preferredLanguage = 'en';
        }

        $prefSession = $repo->findOneBy(['user' => $user, 'name' => 'sessionTimeout']);
        $sessionTimeout = $prefSession ? $prefSession->getValue() : (method_exists($user, 'getSessionTimeout') ? $user->getSessionTimeout() : 3600);

        $prefDistance = $repo->findOneBy(['user' => $user, 'name' => 'distanceUnit']);
        $distanceUnit = $prefDistance ? $prefDistance->getValue() : (method_exists($user, 'getDistanceUnit') ? $user->getDistanceUnit() : 'miles');

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'preferredLanguage' => $preferredLanguage,
            'theme' => $theme,
            'sessionTimeout' => $sessionTimeout,
            'distanceUnit' => $distanceUnit,
            'roles' => $user->getRoles(),
            'passwordChangeRequired' => $user->isPasswordChangeRequired()
        ]);
    }

    #[Route('/profile', name: 'api_profile_update', methods: ['PUT'])]
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $validation = $this->validateJsonRequest($request);
        if ($validation['error']) {
            return $validation['error'];
        }
        $data = $validation['data'];

        if (isset($data['firstName'])) {
            $user->setFirstName($data['firstName']);
        }

        if (isset($data['lastName'])) {
            $user->setLastName($data['lastName']);
        }

        if (isset($data['preferredLanguage'])) {
            $repo = $this->entityManager->getRepository(\App\Entity\UserPreference::class);
            $pref = $repo->findOneBy(['user' => $user, 'name' => 'preferredLanguage']);
            if (!$pref) {
                $pref = new \App\Entity\UserPreference();
                $pref->setUser($user);
                $pref->setName('preferredLanguage');
            }
            $pref->setValue((string) $data['preferredLanguage']);
            $this->entityManager->persist($pref);
        }

        if (isset($data['theme'])) {
            // persist theme into user_preferences table
            $repo = $this->entityManager->getRepository(\App\Entity\UserPreference::class);
            $pref = $repo->findOneBy(['user' => $user, 'name' => 'theme']);
            if (!$pref) {
                $pref = new \App\Entity\UserPreference();
                $pref->setUser($user);
                $pref->setName('theme');
            }
            $pref->setValue((string) $data['theme']);
            $this->entityManager->persist($pref);
        }

        if (isset($data['sessionTimeout'])) {
            $timeout = (int) $data['sessionTimeout'];
            // Validate timeout is between 5 minutes and 24 hours
            if ($timeout >= 300 && $timeout <= 86400) {
                $repo = $this->entityManager->getRepository(\App\Entity\UserPreference::class);
                $pref = $repo->findOneBy(['user' => $user, 'name' => 'sessionTimeout']);
                if (!$pref) {
                    $pref = new \App\Entity\UserPreference();
                    $pref->setUser($user);
                    $pref->setName('sessionTimeout');
                }
                $pref->setValue((string) $timeout);
                $this->entityManager->persist($pref);
            }
        }

        if (isset($data['distanceUnit'])) {
            $validUnits = ['km', 'mi', 'miles'];
            $unit = $data['distanceUnit'];

            // Normalize 'miles' to 'mi' for consistency
            if ($unit === 'miles') {
                $unit = 'mi';
            }

            if (in_array($unit, ['km', 'mi'])) {
                $repo = $this->entityManager->getRepository(\App\Entity\UserPreference::class);
                $pref = $repo->findOneBy(['user' => $user, 'name' => 'distanceUnit']);
                if (!$pref) {
                    $pref = new \App\Entity\UserPreference();
                    $pref->setUser($user);
                    $pref->setName('distanceUnit');
                }
                $pref->setValue((string) $unit);
                $this->entityManager->persist($pref);
            }
        }

        $this->entityManager->flush();

        return $this->json(['message' => 'Profile updated successfully']);
    }

    #[Route('/refresh-token', name: 'api_refresh_token', methods: ['POST'])]
    public function refreshToken(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        // Generate a new JWT token
        $token = $this->jwtManager->create($user);

        return $this->json([
            'message' => 'Token refreshed successfully',
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName()
            ]
        ]);
    }

    #[Route('/auth/issue-refresh', name: 'api_auth_issue_refresh', methods: ['POST'])]
    public function issueRefresh(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        // Read desired TTL from preferences or fallback to 30 days
        $ttl = 60 * 60 * 24 * 30; // 30 days
        $expires = new \DateTimeImmutable(sprintf('+%d seconds', $ttl));

        // create a secure random token
        $token = bin2hex(random_bytes(32));

        $refresh = new RefreshToken($user, $token, $expires);
        $this->entityManager->persist($refresh);
        $this->entityManager->flush();

        // Also return a JWT with expiry matching the user's session preference if available
        $sessionPref = $this->entityManager->getRepository(\App\Entity\UserPreference::class)->findOneBy(['user' => $user, 'name' => 'sessionTimeout']);
        $sessionTtl = $sessionPref ? (int)$sessionPref->getValue() : null;
        $jwt = null;
        if ($sessionTtl) {
            $payload = [
                'username' => $user->getUserIdentifier(),
                'iat' => time(),
                'exp' => time() + $sessionTtl,
            ];
            $jwt = $this->jwtEncoder->encode($payload);
        }

        return $this->json(['refreshToken' => $token, 'expiresAt' => $expires->format(DATE_ATOM), 'token' => $jwt]);
    }

    #[Route('/auth/refresh', name: 'api_auth_refresh_with_token', methods: ['POST'])]
    public function refreshWithToken(Request $request): JsonResponse
    {
        $validation = $this->validateJsonRequest($request);
        if ($validation['error']) {
            return $validation['error'];
        }
        $data = $validation['data'];
        
        $incoming = $data['refreshToken'] ?? null;
        if (!$incoming) {
            return $this->json(['error' => 'refreshToken required'], 400);
        }

        $repo = $this->entityManager->getRepository(RefreshToken::class);
        $refresh = $repo->findOneBy(['refreshToken' => $incoming]);
        if (!$refresh) {
            return $this->json(['error' => 'Invalid refresh token'], 401);
        }

        // check expiry
        $now = new \DateTimeImmutable();
        if ($refresh->getExpiresAt() < $now) {
            return $this->json(['error' => 'Refresh token expired'], 401);
        }

        $user = $refresh->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Invalid user'], 401);
        }

        // create new JWT using user's session timeout preference if available
        $sessionPref = $this->entityManager->getRepository(\App\Entity\UserPreference::class)->findOneBy(['user' => $user, 'name' => 'sessionTimeout']);
        $sessionTtl = $sessionPref ? (int)$sessionPref->getValue() : null;
        if ($sessionTtl) {
            $payload = [
                'username' => $user->getUserIdentifier(),
                'iat' => time(),
                'exp' => time() + $sessionTtl,
            ];
            $token = $this->jwtEncoder->encode($payload);
        } else {
            $token = $this->jwtManager->create($user);
        }

        return $this->json(['token' => $token, 'user' => [ 'id' => $user->getId(), 'email' => $user->getEmail() ]]);
    }

    #[Route('/auth/revoke', name: 'api_auth_revoke', methods: ['POST'])]
    public function revokeRefresh(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $validation = $this->validateJsonRequest($request);
        if ($validation['error']) {
            return $validation['error'];
        }
        $data = $validation['data'];
        
        $incoming = $data['refreshToken'] ?? null;

        $repo = $this->entityManager->getRepository(RefreshToken::class);
        if ($incoming) {
            $token = $repo->findOneBy(['refreshToken' => $incoming, 'user' => $user]);
            if ($token) {
                $this->entityManager->remove($token);
                $this->entityManager->flush();
            }
        } else {
            // revoke all tokens for this user
            $qb = $this->entityManager->createQueryBuilder();
            $qb->delete(RefreshToken::class, 'r')->where('r.user = :user')->setParameter('user', $user);
            $qb->getQuery()->execute();
        }

        // Invalidate all user-specific caches on logout to ensure clean state
        try {
            $this->cache->invalidateTags(['user.' . $user->getId(), 'vehicles', 'dashboard']);
        } catch (\Throwable $e) {
            // Log but don't fail logout if cache invalidation fails
            error_log('Cache invalidation failed on logout: ' . $e->getMessage());
        }

        return $this->json(['message' => 'Refresh token(s) revoked']);
    }

    #[Route('/change-password', name: 'api_change_password', methods: ['POST'])]
    public function changePassword(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $validation = $this->validateJsonRequest($request);
        if ($validation['error']) {
            return $validation['error'];
        }
        $data = $validation['data'];

        if (!isset($data['currentPassword']) || !isset($data['newPassword'])) {
            return $this->json(['error' => 'Current and new password required'], 400);
        }

        // Verify current password
        if (!$this->passwordHasher->isPasswordValid($user, $data['currentPassword'])) {
            return $this->json(['error' => 'Current password is incorrect'], 400);
        }

        // Validate new password
        $passwordPolicy = $_ENV['REACT_APP_PASSWORD_POLICY'] ?? null;
        if ($passwordPolicy) {
            // Add delimiters if not present (for PHP preg_match compatibility)
            if (!preg_match('/^\/.*\/[a-z]*$/', $passwordPolicy)) {
                $passwordPolicy = '/' . $passwordPolicy . '/';
            }
            if (!preg_match($passwordPolicy, $data['newPassword'])) {
                return $this->json(['error' => 'Invalid password format'], 400);
            }
        } else {
            if (strlen($data['newPassword']) < 8) {
                return $this->json(['error' => 'Password too short'], 400);
            }
        }

        // Update password and clear the change requirement flag
        $user->setPassword($this->passwordHasher->hashPassword($user, $data['newPassword']));
        $user->setPasswordChangeRequired(false);

        $this->entityManager->flush();

        return $this->json(['message' => 'Password changed successfully']);
    }

    #[Route('/force-password-change/{id}', name: 'api_force_password_change', methods: ['POST'])]
    public function forcePasswordChange(int $id): JsonResponse
    {
        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        // Only admins can force password change for other users
        if (!in_array('ROLE_ADMIN', $currentUser->getRoles())) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        $user = $this->entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        $user->setPasswordChangeRequired(true);
        $this->entityManager->flush();

        return $this->json(['message' => 'Password change requirement set for user']);
    }
}
