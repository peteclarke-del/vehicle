<?php

namespace App\Controller\Trait;

use App\Entity\User;
use App\Entity\Vehicle;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides common vehicle and entity ownership verification methods
 */
trait OwnershipVerificationTrait
{
    /**
     * Check if user has access to a vehicle (owner or admin)
     *
     * @param Vehicle|null $vehicle
     * @param User $user
     * @param string $errorMessage Custom error message (default: 'Vehicle not found')
     * @return JsonResponse|null Returns error response if access denied, null if allowed
     */
    protected function checkVehicleAccess(?Vehicle $vehicle, User $user, string $errorMessage = 'Vehicle not found'): ?JsonResponse
    {
        if (!$vehicle) {
            return $this->json(['error' => $errorMessage], 404);
        }

        if (!$this->isAdminForUser($user) && $vehicle->getOwner()->getId() !== $user->getId()) {
            return $this->json(['error' => $errorMessage], 404);
        }

        return null;
    }

    /**
     * Check if user has access to an entity via its vehicle relationship
     *
     * @param object|null $entity Entity with getVehicle() method
     * @param User $user
     * @param string $errorMessage Custom error message
     * @return JsonResponse|null Returns error response if access denied, null if allowed
     */
    protected function checkEntityVehicleAccess(?object $entity, User $user, string $errorMessage = 'Record not found'): ?JsonResponse
    {
        if (!$entity) {
            return $this->json(['error' => $errorMessage], 404);
        }

        if (!method_exists($entity, 'getVehicle')) {
            throw new \InvalidArgumentException('Entity must have getVehicle() method');
        }

        $vehicle = $entity->getVehicle();
        if (!$vehicle) {
            return $this->json(['error' => $errorMessage], 404);
        }

        if (!$this->isAdminForUser($user) && $vehicle->getOwner()->getId() !== $user->getId()) {
            return $this->json(['error' => $errorMessage], 404);
        }

        return null;
    }
}
