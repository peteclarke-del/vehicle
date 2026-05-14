<?php

/**
 * Authentication controller endpoints.
 *
 * @category   Controller
 * @package    App\Controller
 * @author     Vehicle Team <devnull@example.com>
 * @phpversion PHP 8
 * @license    https://opensource.org/licenses/MIT MIT License
 * @version    GIT: <git_id>
 * @link       https://example.com
 */

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Trait\JsonValidationTrait;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Entity\UserPreference;
use App\Service\FeatureFlagService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/api')]

/**
 * Authentication and profile endpoints.
 *
 * @category Controller
 * @package  App\Controller
 * @author   Vehicle Team <devnull@example.com>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://example.com
 */
class AuthController extends AbstractController
{
    use JsonValidationTrait;

    /**
     * Build the controller with required service dependencies.
     *
     * @param EntityManagerInterface      $entityManager       Entity manager.
     * @param UserPasswordHasherInterface $passwordHasher      Password hasher.
     * @param JWTTokenManagerInterface    $jwtManager          JWT manager.
     * @param JWTEncoderInterface         $jwtEncoder          JWT encoder service.
     * @param TagAwareCacheInterface      $cache               Tag-aware cache.
     * @param FeatureFlagService          $featureFlagService  Feature flag resolver.
     * @param LoggerInterface             $logger              Logger service.
     * @param RateLimiterFactory          $registerLimiter     Register limiter.
     * @param RateLimiterFactory          $refreshTokenLimiter Refresh limiter.
     *
     * @return void
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager,
        private JWTEncoderInterface $jwtEncoder,
        private TagAwareCacheInterface $cache,
        private FeatureFlagService $featureFlagService,
        private LoggerInterface $logger,
        #[Autowire(service: 'limiter.register')]
        private RateLimiterFactory $registerLimiter,
        #[Autowire(service: 'limiter.refresh_token')]
        private RateLimiterFactory $refreshTokenLimiter
    ) {
    }

    #[Route('/login', name: 'api_login', methods: ['POST'])]

    /**
     * Login success callback for json_login firewall.
     *
     * @return JsonResponse
     */
    public function login(): JsonResponse
    {
        // This method can be empty - the json_login firewall handles authentication
        // If we reach here, authentication succeeded
        $user = $this->getUser();

        if (!$user instanceof \App\Entity\User) {
            return $this->json(['error' => 'Authentication required'], 401);
        }

        $this->_invalidateUserCaches($user, 'login');

        return $this->json(
            [
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName()
            ]
            ]
        );
    }

    #[Route('/register', name: 'api_register', methods: ['POST'])]

    /**
     * Register a new user account.
     *
     * @param Request $request Request payload.
     *
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        $registerLimit = $this->registerLimiter
            ->create($request->getClientIp() ?? 'unknown')
            ->consume(1);

        if (!$registerLimit->isAccepted()) {
            $retryAfter = $registerLimit->getRetryAfter();
            return $this->json(
                [
                    'error' =>
                        'Too many registration attempts. Please try again later.',
                    'retryAfter' => $retryAfter->format(DATE_ATOM),
                ],
                429
            );
        }

        // Block registration entirely when running in demo mode
        $demoMode = filter_var(
            $_ENV['DEMO_MODE'] ?? 'false',
            FILTER_VALIDATE_BOOLEAN
        );
        if ($demoMode) {
            return $this->json(
                ['error' => 'Registration is disabled in the demo'],
                403
            );
        }

        $validation = $this->validateJsonRequest($request);
        if ($validation['error']) {
            return $validation['error'];
        }
        $data = $validation['data'];

        if (!isset($data['email']) || !isset($data['password'])) {
            return $this->json(['error' => 'Email and password required'], 400);
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Invalid email address'], 400);
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
        $user->setPassword(
            $this->passwordHasher->hashPassword($user, $data['password'])
        );

        // Ensure new accounts have the default ROLE_USER
        $user->setRoles(['ROLE_USER']);

        $this->entityManager->persist($user);

        // Create default user preferences for new users.
        $langValue = isset($data['preferredLanguage'])
            ? (string) $data['preferredLanguage']
            : 'en';
        $defaultPreferences = [
            'preferredLanguage' => $langValue,
            'distanceUnit' => 'mi',
            'theme' => 'light',
        ];
        foreach ($defaultPreferences as $name => $value) {
            $pref = new UserPreference();
            $pref->setUser($user);
            $pref->setName($name);
            $pref->setValue($value);
            $this->entityManager->persist($pref);
        }

        $this->entityManager->flush();

        return $this->json(
            [
                'message' => 'User created successfully',
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'firstName' => $user->getFirstName(),
                    'lastName' => $user->getLastName(),
                ],
            ],
            201
        );
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]

    /**
     * Return authenticated user profile and feature flags.
     *
     * @return JsonResponse
     */
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        // read theme from user_preferences (fallback to 'light')
        $repo = $this->entityManager->getRepository(UserPreference::class);
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

        // Read language/session/unit preferences from user_preferences.
        $prefLang = $repo->findOneBy(
            ['user' => $user, 'name' => 'preferredLanguage']
        );
        $preferredLanguage = null;
        if ($prefLang) {
            $preferredLanguage = $prefLang->getValue();
        } elseif (method_exists($user, 'getPreferredLanguage')) {
            $preferredLanguage = $user->getPreferredLanguage();
        } else {
            $preferredLanguage = 'en';
        }

        $prefSession = $repo->findOneBy(
            ['user' => $user, 'name' => 'sessionTimeout']
        );
        $sessionTimeout = $prefSession
            ? $prefSession->getValue()
            : (
                method_exists($user, 'getSessionTimeout')
                ? $user->getSessionTimeout()
                : 3600
            );

        $prefDistance = $repo->findOneBy(
            ['user' => $user, 'name' => 'distanceUnit']
        );
        $distanceUnit = $prefDistance
            ? $prefDistance->getValue()
            : (
                method_exists($user, 'getDistanceUnit')
                ? $user->getDistanceUnit()
                : 'miles'
            );

        return $this->json(
            [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'preferredLanguage' => $preferredLanguage,
            'theme' => $theme,
            'sessionTimeout' => $sessionTimeout,
            'distanceUnit' => $distanceUnit,
            'roles' => $user->getRoles(),
            'passwordChangeRequired' => $user->isPasswordChangeRequired(),
            'features' => $this->featureFlagService->getEffectiveFlags($user),
            'vehicleAssignments' => $this->featureFlagService
                ->getSerializedAssignments($user),
            ]
        );
    }

    #[Route('/profile', name: 'api_profile_update', methods: ['PUT'])]

    /**
     * Update user profile preferences.
     *
     * @param Request $request Request payload.
     *
     * @return JsonResponse
     */
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
            $repo = $this->entityManager->getRepository(UserPreference::class);
            $pref = $repo->findOneBy(
                ['user' => $user, 'name' => 'preferredLanguage']
            );
            if (!$pref) {
                $pref = new UserPreference();
                $pref->setUser($user);
                $pref->setName('preferredLanguage');
            }
            $pref->setValue((string) $data['preferredLanguage']);
            $this->entityManager->persist($pref);
        }

        if (isset($data['theme'])) {
            // persist theme into user_preferences table
            $repo = $this->entityManager->getRepository(UserPreference::class);
            $pref = $repo->findOneBy(['user' => $user, 'name' => 'theme']);
            if (!$pref) {
                $pref = new UserPreference();
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
                $repo = $this->entityManager->getRepository(UserPreference::class);
                $pref = $repo->findOneBy(
                    ['user' => $user, 'name' => 'sessionTimeout']
                );
                if (!$pref) {
                    $pref = new UserPreference();
                    $pref->setUser($user);
                    $pref->setName('sessionTimeout');
                }
                $pref->setValue((string) $timeout);
                $this->entityManager->persist($pref);
            }
        }

        if (isset($data['distanceUnit'])) {
            $unit = $data['distanceUnit'];

            // Normalize 'miles' to 'mi' for consistency
            if ($unit === 'miles') {
                $unit = 'mi';
            }

            if (in_array($unit, ['km', 'mi'], true)) {
                $repo = $this->entityManager->getRepository(UserPreference::class);
                $pref = $repo->findOneBy(
                    ['user' => $user, 'name' => 'distanceUnit']
                );
                if (!$pref) {
                    $pref = new UserPreference();
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

    /**
     * Refresh access token for authenticated user.
     *
     * @param Request $request Request payload.
     *
     * @return JsonResponse
     */
    public function refreshToken(Request $request): JsonResponse
    {
        // Add rate limiting for refresh-token endpoint
        $refreshLimit = $this->refreshTokenLimiter
            ->create($request->getClientIp() ?? 'unknown')
            ->consume(1);
        if (!$refreshLimit->isAccepted()) {
            $retryAfter = $refreshLimit->getRetryAfter();
            return $this->json(
                [
                'error' => [
                    'message' =>
                        'Too many refresh attempts. Please try again later.',
                    'retryAfter' => $retryAfter->format(DATE_ATOM),
                ]
                ], 429
            );
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => ['message' => 'Not authenticated']], 401);
        }

        // Generate a new JWT token
        $token = $this->jwtManager->create($user);

        return $this->json(
            [
            'data' => [
                'message' => 'Token refreshed successfully',
                'token' => $token,
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'firstName' => $user->getFirstName(),
                    'lastName' => $user->getLastName()
                ]
            ]
            ]
        );
    }

    #[Route(
        '/auth/issue-refresh',
        name: 'api_auth_issue_refresh',
        methods: ['POST']
    )]

    /**
     * Issue a long-lived refresh token and optional JWT.
     *
     * @param Request $request Request payload.
     *
     * @return JsonResponse
     */
    public function issueRefresh(Request $request): JsonResponse
    {
        // Add rate limiting for issue-refresh endpoint
        $refreshLimit = $this->refreshTokenLimiter
            ->create($request->getClientIp() ?? 'unknown')
            ->consume(1);
        if (!$refreshLimit->isAccepted()) {
            $retryAfter = $refreshLimit->getRetryAfter();
            return $this->json(
                [
                'error' => [
                    'message' =>
                        'Too many refresh attempts. Please try again later.',
                    'retryAfter' => $retryAfter->format(DATE_ATOM),
                ]
                ], 429
            );
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => ['message' => 'Not authenticated']], 401);
        }
        // Read desired TTL from preferences or fallback to 30 days
        $ttl = 60 * 60 * 24 * 30; // 30 days
        $expires = new \DateTimeImmutable(sprintf('+%d seconds', $ttl));

        // create a secure random token
        $token = bin2hex(random_bytes(32));

        $refresh = new RefreshToken($user, $token, $expires);
        $this->entityManager->persist($refresh);
        $this->entityManager->flush();

        // Also return a JWT matching the user's session preference when present.
        $sessionPref = $this->entityManager
            ->getRepository(UserPreference::class)
            ->findOneBy(['user' => $user, 'name' => 'sessionTimeout']);
        $sessionTtl = $sessionPref ? (int) $sessionPref->getValue() : null;
        $jwt = null;
        if ($sessionTtl) {
            $payload = [
                'username' => $user->getUserIdentifier(),
                'iat' => time(),
                'exp' => time() + $sessionTtl,
            ];
            $jwt = $this->jwtEncoder->encode($payload);
        }

        return $this->json(
            [
            'data' => [
                'refreshToken' => $token,
                'expiresAt' => $expires->format(DATE_ATOM),
                'token' => $jwt
            ]
            ]
        );
    }

    #[Route('/auth/refresh', name: 'api_auth_refresh_with_token', methods: ['POST'])]

    /**
     * Exchange refresh token for new JWT.
     *
     * @param Request $request Request payload.
     *
     * @return JsonResponse
     */
    public function refreshWithToken(Request $request): JsonResponse
    {
        $refreshLimit = $this->refreshTokenLimiter
            ->create($request->getClientIp() ?? 'unknown')
            ->consume(1);

        if (!$refreshLimit->isAccepted()) {
            $retryAfter = $refreshLimit->getRetryAfter();
            return $this->json(
                [
                'error' => 'Too many refresh attempts. Please try again later.',
                'retryAfter' => $retryAfter->format(DATE_ATOM),
                ], 429
            );
        }

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

        // create new JWT using user's session timeout preference if available
        $sessionPref = $this->entityManager
            ->getRepository(UserPreference::class)
            ->findOneBy(['user' => $user, 'name' => 'sessionTimeout']);
        $sessionTtl = $sessionPref ? (int) $sessionPref->getValue() : null;
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

        return $this->json(
            [
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
            ],
            ]
        );
    }

    #[Route('/auth/revoke', name: 'api_auth_revoke', methods: ['POST'])]

    /**
     * Revoke one refresh token or all refresh tokens for user.
     *
     * @param Request $request Request payload.
     *
     * @return JsonResponse
     */
    public function revokeRefresh(Request $request): JsonResponse
    {
        // Add rate limiting for revoke endpoint
        $refreshLimit = $this->refreshTokenLimiter
            ->create($request->getClientIp() ?? 'unknown')
            ->consume(1);
        if (!$refreshLimit->isAccepted()) {
            $retryAfter = $refreshLimit->getRetryAfter();
            return $this->json(
                [
                'error' => [
                    'message' => 'Too many revoke attempts. Please try again later.',
                    'retryAfter' => $retryAfter->format(DATE_ATOM),
                ]
                ], 429
            );
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => ['message' => 'Not authenticated']], 401);
        }

        $validation = $this->validateJsonRequest($request);
        if ($validation['error']) {
            return $validation['error'];
        }
        $data = $validation['data'];

        $incoming = $data['refreshToken'] ?? null;

        $repo = $this->entityManager->getRepository(RefreshToken::class);
        if ($incoming) {
            $token = $repo->findOneBy(
                ['refreshToken' => $incoming, 'user' => $user]
            );
            if ($token) {
                $this->entityManager->remove($token);
                $this->entityManager->flush();
            }
        } else {
            // revoke all tokens for this user
            $qb = $this->entityManager->createQueryBuilder();
            $qb->delete(RefreshToken::class, 'r')
                ->where('r.user = :user')
                ->setParameter('user', $user);
            $qb->getQuery()->execute();
        }

        $this->_invalidateUserCaches($user, 'logout');

        return $this->json(
            [
            'data' => [
                'message' => 'Refresh token(s) revoked'
            ]
            ]
        );
    }

    #[Route('/change-password', name: 'api_change_password', methods: ['POST'])]

    /**
     * Change authenticated user password.
     *
     * @param Request $request Request payload.
     *
     * @return JsonResponse
     */
    public function changePassword(Request $request): JsonResponse
    {
        // Add rate limiting for change-password endpoint
        $refreshLimit = $this->refreshTokenLimiter
            ->create($request->getClientIp() ?? 'unknown')
            ->consume(1);
        if (!$refreshLimit->isAccepted()) {
            $retryAfter = $refreshLimit->getRetryAfter();
            return $this->json(
                [
                'error' => [
                    'message' =>
                        'Too many password change attempts. Please try again later.',
                    'retryAfter' => $retryAfter->format(DATE_ATOM),
                ]
                ], 429
            );
        }

        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => ['message' => 'Not authenticated']], 401);
        }

        $validation = $this->validateJsonRequest($request);
        if ($validation['error']) {
            return $validation['error'];
        }
        $data = $validation['data'];

        if (!isset($data['currentPassword']) || !isset($data['newPassword'])) {
            return $this->json(
                ['error' => ['message' => 'Current and new password required']],
                400
            );
        }

        // Verify current password
        if (!$this->passwordHasher->isPasswordValid($user, $data['currentPassword'])
        ) {
            return $this->json(
                ['error' => ['message' => 'Current password is incorrect']],
                400
            );
        }

        // Validate new password
        $passwordPolicy = $_ENV['REACT_APP_PASSWORD_POLICY'] ?? null;
        if ($passwordPolicy) {
            // Add delimiters if not present (for PHP preg_match compatibility)
            if (!preg_match('/^\/.*\/[a-z]*$/', $passwordPolicy)) {
                $passwordPolicy = '/' . $passwordPolicy . '/';
            }
            if (!preg_match($passwordPolicy, $data['newPassword'])) {
                return $this->json(
                    ['error' => ['message' => 'Invalid password format']],
                    400
                );
            }
        } else {
            if (strlen($data['newPassword']) < 8) {
                return $this->json(
                    ['error' => ['message' => 'Password too short']],
                    400
                );
            }
        }

        // Update password and clear the change requirement flag
        $user->setPassword(
            $this->passwordHasher->hashPassword($user, $data['newPassword'])
        );
        $user->setPasswordChangeRequired(false);

        $this->entityManager->flush();

        return $this->json(
            [
            'data' => [
                'message' => 'Password changed successfully'
            ]
            ]
        );
    }

    #[Route(
        '/force-password-change/{id}',
        name: 'api_force_password_change',
        methods: ['POST']
    )]

    /**
     * Force password change requirement for a user.
     *
     * @param int     $id      User ID.
     * @param Request $request Request payload.
     *
     * @return JsonResponse
     */
    public function forcePasswordChange(int $id, Request $request): JsonResponse
    {
        // Add rate limiting for force-password-change endpoint
        $refreshLimit = $this->refreshTokenLimiter
            ->create($request->getClientIp() ?? 'unknown')
            ->consume(1);
        if (!$refreshLimit->isAccepted()) {
            $retryAfter = $refreshLimit->getRetryAfter();
            return $this->json(
                [
                'error' => [
                    'message' =>
                        'Too many force password change attempts. '
                        . 'Please try again later.',
                    'retryAfter' => $retryAfter->format(DATE_ATOM),
                ]
                ], 429
            );
        }

        $currentUser = $this->getUser();

        if (!$currentUser instanceof User) {
            return $this->json(['error' => ['message' => 'Not authenticated']], 401);
        }

        // Only admins can force password change for other users
        if (!in_array('ROLE_ADMIN', $currentUser->getRoles(), true)) {
            return $this->json(['error' => ['message' => 'Access denied']], 403);
        }

        $user = $this->entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            return $this->json(['error' => ['message' => 'User not found']], 404);
        }

        $user->setPasswordChangeRequired(true);
        $this->entityManager->flush();

        return $this->json(
            [
            'data' => [
                'message' => 'Password change requirement set for user'
            ]
            ]
        );
    }

    /**
     * Invalidate user-scoped caches after authentication state changes.
     *
     * @param User   $user   Authenticated user.
     * @param string $action Cache invalidation context.
     *
     * @return void
     */
    private function _invalidateUserCaches(User $user, string $action): void
    {
        try {
            $this->cache->invalidateTags(
                [
                'user.' . $user->getId(),
                'vehicles',
                'dashboard',
                ]
            );
        } catch (\Throwable $e) {
            // Cache failures must not block auth flows.
            $this->logger->warning(
                sprintf(
                    'Cache invalidation failed on %s: %s',
                    $action,
                    $e->getMessage()
                )
            );
        }
    }
}
    #[Route('/auth/request-password-reset', name: 'api_request_password_reset', methods: ['POST'])]

    /**
     * Request a password reset token via email.
     * Publicly accessible endpoint with rate limiting.
     *
     * @param Request $request Request payload.
     *
     * @return JsonResponse
     */
    public function requestPasswordReset(Request $request): JsonResponse
    {
        // Rate limit: 3 reset requests per hour per IP
        $resetLimit = $this->registerLimiter
            ->create($request->getClientIp() ?? 'unknown')
            ->consume(1);

        if (!$resetLimit->isAccepted()) {
            $retryAfter = $resetLimit->getRetryAfter();
            return $this->json(
                [
                    'error' => 'Too many password reset requests. Please try again later.',
                    'retryAfter' => $retryAfter->format(DATE_ATOM),
                ],
                429
            );
        }

        $validation = $this->validateJsonRequest($request);
        if ($validation['error']) {
            return $validation['error'];
        }
        $data = $validation['data'];

        if (!isset($data['email'])) {
            return $this->json(['error' => 'Email is required'], 400);
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Invalid email address'], 400);
        }

        $user = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => $data['email']]);

        // Always return success to prevent email enumeration attacks
        if (!$user) {
            return $this->json(
                ['message' => 'If that email exists, a password reset link has been sent'],
                200
            );
        }

        // Generate reset token (32 bytes = 256 bits)
        $token = bin2hex(random_bytes(32));

        // Token expires in 1 hour
        $expiresAt = new \DateTimeImmutable('+1 hour');

        // Create reset token record
        $resetToken = new \App\Entity\PasswordResetToken($user, $token, $expiresAt);
        $this->entityManager->persist($resetToken);
        $this->entityManager->flush();

        // TODO: Send email with reset link
        // In production, implement EmailService to send reset link:
        // $resetUrl = sprintf('%s/reset-password/%s', $this->getParameter('app.frontend_url'), $token);
        // $this->emailService->sendPasswordResetEmail($user, $resetUrl);

        $this->logger->info(sprintf('Password reset requested for user: %s', $user->getEmail()));

        // Always return success
        return $this->json(
            ['message' => 'If that email exists, a password reset link has been sent'],
            200
        );
    }

    #[Route('/auth/reset-password/{token}', name: 'api_reset_password', methods: ['POST'])]

    /**
     * Reset user password with valid reset token.
     *
     * @param string  $token   Reset token from email link.
     * @param Request $request Request payload with new password.
     *
     * @return JsonResponse
     */
    public function resetPassword(string $token, Request $request): JsonResponse
    {
        // Rate limit: 5 attempts per minute
        $resetLimit = $this->refreshTokenLimiter
            ->create($request->getClientIp() ?? 'unknown')
            ->consume(1);

        if (!$resetLimit->isAccepted()) {
            $retryAfter = $resetLimit->getRetryAfter();
            return $this->json(
                [
                    'error' => 'Too many reset attempts. Please try again later.',
                    'retryAfter' => $retryAfter->format(DATE_ATOM),
                ],
                429
            );
        }

        // Validate token format (should be 64 hex chars = 32 bytes)
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return $this->json(['error' => 'Invalid reset token'], 400);
        }

        $validation = $this->validateJsonRequest($request);
        if ($validation['error']) {
            return $validation['error'];
        }
        $data = $validation['data'];

        if (!isset($data['newPassword'])) {
            return $this->json(['error' => 'New password is required'], 400);
        }

        // Look up reset token
        $resetTokenRepo = $this->entityManager->getRepository(\App\Entity\PasswordResetToken::class);
        $resetToken = $resetTokenRepo->findOneBy(['token' => $token]);

        if (!$resetToken) {
            return $this->json(['error' => 'Invalid or expired reset token'], 400);
        }

        // Check if token is valid
        if (!$resetToken->isValid()) {
            return $this->json(['error' => 'Invalid or expired reset token'], 400);
        }

        $user = $resetToken->getUser();

        // Validate new password
        $passwordPolicy = $_ENV['REACT_APP_PASSWORD_POLICY'] ?? null;
        if ($passwordPolicy) {
            // Add delimiters if not present
            if (!preg_match('/^\/.*\/[a-z]*$/', $passwordPolicy)) {
                $passwordPolicy = '/' . $passwordPolicy . '/';
            }
            if (!preg_match($passwordPolicy, $data['newPassword'])) {
                return $this->json(['error' => 'Password does not meet policy requirements'], 400);
            }
        } else {
            if (strlen($data['newPassword']) < 8) {
                return $this->json(['error' => 'Password must be at least 8 characters'], 400);
            }
        }

        // Update password and clear password change requirement
        $user->setPassword(
            $this->passwordHasher->hashPassword($user, $data['newPassword'])
        );
        $user->setPasswordChangeRequired(false);

        // Mark reset token as used
        $resetToken->markAsUsed();

        // Revoke all existing refresh tokens for security
        $refreshTokenRepo = $this->entityManager->getRepository(\App\Entity\RefreshToken::class);
        $refreshTokens = $refreshTokenRepo->findBy(['user' => $user]);
        foreach ($refreshTokens as $rt) {
            $this->entityManager->remove($rt);
        }

        $this->entityManager->flush();

        $this->logger->info(sprintf('Password reset completed for user: %s', $user->getEmail()));

        return $this->json(
            ['message' => 'Password has been reset successfully. Please log in with your new password.'],
            200
        );
    }

