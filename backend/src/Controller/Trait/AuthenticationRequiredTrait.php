<?php

namespace App\Controller\Trait;

use App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides common authentication requirement checks for controllers
 */
trait AuthenticationRequiredTrait
{
    /**
     * Get authenticated user or return 401 error response
     *
     * @return User|JsonResponse User entity if authenticated, JsonResponse error if not
     */
    protected function requireAuthenticatedUser(): User|JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }
        return $user;
    }

    /**
     * Check if result is an error response (for use after requireAuthenticatedUser)
     *
     * @param mixed $result
     * @return bool
     */
    protected function isErrorResponse(mixed $result): bool
    {
        return $result instanceof JsonResponse;
    }
}
