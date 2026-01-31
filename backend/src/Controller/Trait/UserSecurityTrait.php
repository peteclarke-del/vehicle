<?php

namespace App\Controller\Trait;

use App\Entity\User;

/**
 * Provides common user authentication and authorization methods for controllers
 */
trait UserSecurityTrait
{
    /**
     * Get the authenticated user entity
     *
     * @return User|null
     */
    private function getUserEntity(): ?User
    {
        $user = $this->getUser();
        return $user instanceof User ? $user : null;
    }

    /**
     * Check if user has admin role
     *
     * @param User|null $user
     * @return bool
     */
    private function isAdminForUser(?User $user): bool
    {
        if (!($user instanceof User)) {
            return false;
        }

        $roles = $user->getRoles();
        return in_array('ROLE_ADMIN', $roles, true);
    }
}
