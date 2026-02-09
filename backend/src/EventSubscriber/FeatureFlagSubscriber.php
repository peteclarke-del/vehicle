<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\FeatureFlagService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * class FeatureFlagSubscriber
 *
 * Intercepts API requests and enforces feature flag restrictions.
 * Returns 403 if the authenticated user lacks the required feature flag.
 * Admins bypass all checks.
 */
class FeatureFlagSubscriber implements EventSubscriberInterface
{
    /**
     * function __construct
     *
     * @param FeatureFlagService $featureFlagService
     * @param TokenStorageInterface $tokenStorage
     *
     * @return void
     */
    public function __construct(
        private readonly FeatureFlagService $featureFlagService,
        private readonly TokenStorageInterface $tokenStorage
    ) {
    }

    /**
     * function getSubscribedEvents
     *
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 8], // After firewall (priority 8), before controller
        ];
    }

    /**
     * function onKernelRequest
     *
     * @param RequestEvent $event
     *
     * @return void
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Only enforce on API routes
        if (!str_starts_with($path, '/api/')) {
            return;
        }

        // Skip auth-related routes (login, register, me, profile, etc.)
        $skipPrefixes = [
            '/api/login',
            '/api/register',
            '/api/me',
            '/api/profile',
            '/api/auth/',
            '/api/change-password',
            '/api/force-password-change',
            '/api/user/preferences',
            '/api/admin/',
            '/api/system-check',
            '/api/health',
            '/api/client-logs',
            '/api/ebay/',
            '/api/dvsa/',
            '/api/dvla/',
            '/api/vehicle-types',
            '/api/vehicle-makes',
            '/api/vehicle-models',
            '/api/part-categories',
            '/api/fuel-records/fuel-types',
        ];

        foreach ($skipPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        // Resolve the feature key for this path+method
        $featureKey = $this->featureFlagService->resolveFeatureKey($path, $request->getMethod());
        if (!$featureKey) {
            return; // No feature restriction for this route
        }

        // Get authenticated user
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return; // Let the security layer handle unauthenticated requests
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        // Admins bypass all feature flags
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return;
        }

        // Check if the feature is enabled for this user
        if (!$this->featureFlagService->isFeatureEnabled($user, $featureKey)) {
            $event->setResponse(new JsonResponse([
                'error' => 'This feature has been disabled by your administrator',
                'featureKey' => $featureKey,
            ], 403));
        }
    }
}
