<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\InsurancePolicy;
use App\Entity\Vehicle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class InsuranceController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    private function getUserEntity(): ?\App\Entity\User
    {
        $user = $this->getUser();
        return $user instanceof \App\Entity\User ? $user : null;
    }

    private function isAdminForUser(?\App\Entity\User $user): bool
    {
        if (!$user) return false;
        $roles = $user->getRoles() ?: [];
        return in_array('ROLE_ADMIN', $roles, true);
    }

    #[Route('/insurance/policies', methods: ['GET'])]
    public function listPolicies(): JsonResponse
    {
        $user = $this->getUserEntity();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $policies = $this->entityManager->getRepository(InsurancePolicy::class)->findAll();
        $visible = [];
        foreach ($policies as $p) {
            if ($this->isAdminForUser($user)) {
                $visible[] = $this->_serializePolicy($p);
                continue;
            }
            foreach ($p->getVehicles() as $v) {
                if ($v->getOwner()?->getId() === $user->getId()) {
                    $visible[] = $this->_serializePolicy($p);
                    break;
                }
            }
        }

        return new JsonResponse($visible);
    }

    #[Route('/insurance/policies', methods: ['POST'])]
    public function createPolicy(Request $request): JsonResponse
    {
        $user = $this->getUserEntity();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $policy = new InsurancePolicy();
        if (isset($data['provider'])) {
            $policy->setProvider($data['provider']);
        }
        if (isset($data['policyNumber'])) {
            $policy->setPolicyNumber($data['policyNumber']);
        }
        if (!empty($data['startDate'])) {
            $policy->setStartDate(new \DateTime($data['startDate']));
        }
        if (!empty($data['expiryDate'])) {
            $policy->setExpiryDate(new \DateTime($data['expiryDate']));
        }
        if (isset($data['annualCost'])) {
            $policy->setAnnualCost($data['annualCost']);
        }
        if (isset($data['ncdYears'])) {
            $policy->setNcdYears((int)$data['ncdYears']);
        }
        // ncdPercentage removed; use ncdYears only
        // Additional fields supported by InsurancePolicy entity
        if (isset($data['coverageType'])) {
            $policy->setCoverageType($data['coverageType']);
        }
        if (isset($data['excess'])) {
            $policy->setExcess($data['excess']);
        }
        if (isset($data['mileageLimit'])) {
            $policy->setMileageLimit((int)$data['mileageLimit']);
        }
        if (isset($data['autoRenewal'])) {
            $policy->setAutoRenewal((bool)$data['autoRenewal']);
        }
        if (isset($data['notes'])) {
            $policy->setNotes($data['notes']);
        }

        $vehicleIds = $data['vehicleIds'] ?? [];
        foreach ($vehicleIds as $vid) {
            $v = $this->entityManager->getRepository(Vehicle::class)->find($vid);
            if ($v && ($this->isAdminForUser($user) || $v->getOwner()?->getId() === $user->getId())) {
                $policy->addVehicle($v);
            }
        }

        // Set holderId to the current user id (policies belong to the logged in user)
        $policy->setHolderId($user->getId());

        $this->entityManager->persist($policy);
        $this->entityManager->flush();

        // Link any pending attachments to the newly created policy
        if (!empty($data['pendingAttachmentIds'])
            && is_array($data['pendingAttachmentIds'])
        ) {
            foreach ($data['pendingAttachmentIds'] as $aid) {
                $aidInt = (int) $aid;
                $attachment = $this->entityManager
                    ->getRepository(\App\Entity\Attachment::class)
                    ->find($aidInt);

                if (! $attachment) {
                    continue;
                }
                if ($attachment->getUser()?->getId() !== $user->getId()) {
                    continue;
                }

                $attachment->setEntityType('insurancePolicy');
                $attachment->setEntityId($policy->getId());
            }

            $this->entityManager->flush();
        }

        return new JsonResponse($this->_serializePolicy($policy), 201);
    }

    #[Route('/insurance/policies/{id}', methods: ['GET'])]
    public function getPolicy(int $id): JsonResponse
    {
        $policy = $this->entityManager
            ->getRepository(InsurancePolicy::class)
            ->find($id);
        $user = $this->getUserEntity();

        if (! $policy || ! $user) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        $owns = false;
        if ($this->isAdminForUser($user)) {
            $owns = true;
        } else {
            foreach ($policy->getVehicles() as $v) {
                if ($v->getOwner()?->getId() === $user->getId()) {
                    $owns = true;
                    break;
                }
            }
        }

        if (! $owns) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        return new JsonResponse($this->_serializePolicy($policy));
    }

    #[Route('/insurance/policies/{id}', methods: ['PUT'])]
    public function updatePolicy(int $id, Request $request): JsonResponse
    {
        $policy = $this->entityManager
            ->getRepository(InsurancePolicy::class)
            ->find($id);
        $user = $this->getUserEntity();

        if (! $policy || ! $user) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        $owns = false;
        if ($this->isAdminForUser($user)) {
            $owns = true;
        } else {
            foreach ($policy->getVehicles() as $v) {
                if ($v->getOwner()?->getId() === $user->getId()) {
                    $owns = true;
                    break;
                }
            }
        }

        if (! $owns) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['provider'])) {
            $policy->setProvider($data['provider']);
        }

        if (isset($data['policyNumber'])) {
            $policy->setPolicyNumber($data['policyNumber']);
        }

        if (!empty($data['startDate'])) {
            $policy->setStartDate(new \DateTime($data['startDate']));
        }

        if (!empty($data['expiryDate'])) {
            $policy->setExpiryDate(new \DateTime($data['expiryDate']));
        }

        if (isset($data['annualCost'])) {
            $policy->setAnnualCost($data['annualCost']);
        }

        if (isset($data['ncdYears'])) {
            $policy->setNcdYears((int)$data['ncdYears']);
        }

        // ncdPercentage removed; use ncdYears only

        // Additional fields
        if (isset($data['coverageType'])) {
            $policy->setCoverageType($data['coverageType']);
        }

        if (isset($data['excess'])) {
            $policy->setExcess($data['excess']);
        }

        if (isset($data['mileageLimit'])) {
            $policy->setMileageLimit((int)$data['mileageLimit']);
        }


        if (isset($data['autoRenewal'])) {
            $policy->setAutoRenewal((bool)$data['autoRenewal']);
        }

        if (isset($data['notes'])) {
            $policy->setNotes($data['notes']);
        }

        // Replace attached vehicles if provided.
        if (isset($data['vehicleIds']) && is_array($data['vehicleIds'])) {
            $desired = array_map('intval', $data['vehicleIds']);
            $current = [];

            foreach ($policy->getVehicles() as $v) {
                $current[] = $v->getId();
            }

            // Remove vehicles not in desired
            foreach ($policy->getVehicles() as $v) {
                if (!in_array($v->getId(), $desired, true)) {
                    $policy->removeVehicle($v);
                }
            }

            // Add desired vehicles not currently attached
            foreach ($desired as $vid) {
                if (!in_array($vid, $current, true)) {
                    $v = $this->entityManager
                        ->getRepository(Vehicle::class)
                        ->find($vid);

                    if ($v && $v->getOwner()?->getId() === $user->getId()) {
                        $policy->addVehicle($v);
                    }
                }
            }
        }

        // Policies belong to the logged-in user; set holderId accordingly
        $policy->setHolderId($user->getId());

        $this->entityManager->flush();

        // Link any pending attachments to the updated policy
        if (!empty($data['pendingAttachmentIds'])
            && is_array($data['pendingAttachmentIds'])
        ) {
            foreach ($data['pendingAttachmentIds'] as $aid) {
                $attachment = $this->entityManager
                    ->getRepository(\App\Entity\Attachment::class)
                    ->find((int)$aid);

                if (! $attachment) {
                    continue;
                }

                if ($attachment->getUser()?->getId() !== $user->getId()) {
                    continue;
                }

                $attachment->setEntityType('insurancePolicy');
                $attachment->setEntityId($policy->getId());
            }

            $this->entityManager->flush();
        }

        return new JsonResponse($this->_serializePolicy($policy));
    }

    #[Route('/insurance/policies/{id}', methods: ['DELETE'])]
    /**
     * Delete an insurance policy if the current user owns a linked vehicle.
     */
    public function deletePolicy(int $id): JsonResponse
    {
        $policy = $this->entityManager
            ->getRepository(InsurancePolicy::class)
            ->find($id);
        $user = $this->getUserEntity();
        if (!$policy || !$user) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        // Ensure the user owns at least one vehicle on this policy (admins bypass)
        $owns = false;
        if ($this->isAdminForUser($user)) {
            $owns = true;
        } else {
            foreach ($policy->getVehicles() as $v) {
                if ($v->getOwner()?->getId() === $user->getId()) {
                    $owns = true;
                    break;
                }
            }
        }

        if (!$owns) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        $this->entityManager->remove($policy);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/insurance/policies/{id}/vehicles', methods: ['POST'])]
    /**
     * Attach an existing vehicle to a policy (must be owned by user).
     *
     * @param int    $id      Policy id.
     * @param Request $request HTTP request object.
     *
     * @return JsonResponse
     */
    public function attachVehicleToPolicy(int $id, Request $request): JsonResponse
    {
        $policy = $this->entityManager
            ->getRepository(InsurancePolicy::class)
            ->find($id);

        $user = $this->getUserEntity();

        if (!$policy || !$user) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $vehicle = $this->entityManager
            ->getRepository(Vehicle::class)
            ->find($data['vehicleId'] ?? null);

        if (!$vehicle || (!$this->isAdminForUser($user) && $vehicle->getOwner()?->getId() !== $user->getId())) {
            return new JsonResponse(['error' => 'Vehicle not found'], 404);
        }

        $policy->addVehicle($vehicle);
        $this->entityManager->flush();

        return new JsonResponse($this->_serializePolicy($policy));
    }

    #[Route('/insurance/policies/{id}/vehicles/{vehicleId}', methods: ['DELETE'])]
    /**
     * Detach a vehicle from a policy.
     *
     * @param int  $id        Policy id.
     * @param int  $vehicleId Vehicle id.
     *
     * @return JsonResponse
     */
    public function detachVehicleFromPolicy(int $id, int $vehicleId): JsonResponse
    {
        $policy = $this->entityManager
            ->getRepository(InsurancePolicy::class)
            ->find($id);

        $user = $this->getUserEntity();

        if (!$policy || !$user) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        $vehicle = $this->entityManager
            ->getRepository(Vehicle::class)
            ->find($vehicleId);

        if (!$vehicle || (!$this->isAdminForUser($user) && $vehicle->getOwner()?->getId() !== $user->getId())) {
            return new JsonResponse(['error' => 'Vehicle not found'], 404);
        }

        $policy->removeVehicle($vehicle);
        $this->entityManager->flush();

        return new JsonResponse($this->_serializePolicy($policy));
    }

    #[Route('/insurance', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $vehicleId = $request->query->get('vehicleId');

        if (!$vehicleId) {
            return new JsonResponse(['error' => 'vehicleId is required'], 400);
        }

        $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($vehicleId);
        $user = $this->getUserEntity();
        if (!$vehicle || !$user || (!$this->isAdminForUser($user) && $vehicle->getOwner()->getId() !== $user->getId())) {
            return new JsonResponse(['error' => 'Vehicle not found'], 404);
        }

        $policies = $this->entityManager->getRepository(InsurancePolicy::class)
            ->findBy(['holderId' => $user->getId()]);

        // Filter to only policies that include this vehicle
        $vehiclePolicies = array_filter($policies, fn($policy) => $policy->getVehicles()->contains($vehicle));

        return new JsonResponse(array_map(fn($policy) => $this->serializeInsurance($policy), $vehiclePolicies));
    }

    #[Route('/insurance', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($data['vehicleId']);
        $user = $this->getUserEntity();
        if (!$vehicle || !$user || (!$this->isAdminForUser($user) && $vehicle->getOwner()->getId() !== $user->getId())) {
            return new JsonResponse(['error' => 'Vehicle not found'], 404);
        }

        $policy = new InsurancePolicy();
        $policy->setHolderId($user->getId());
        $policy->addVehicle($vehicle);
        $this->updatePolicyFromData($policy, $data);

        $this->entityManager->persist($policy);
        $this->entityManager->flush();

        return new JsonResponse($this->serializeInsurance($policy), 201);
    }

    #[Route('/insurance/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $policy = $this->entityManager->getRepository(InsurancePolicy::class)->find($id);
        $user = $this->getUserEntity();
        if (!$policy || !$user || (!$this->isAdminForUser($user) && $policy->getHolderId() !== $user->getId())) {
            return new JsonResponse(['error' => 'Insurance policy not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $this->updatePolicyFromData($policy, $data);

        $this->entityManager->flush();

        return new JsonResponse($this->serializeInsurance($policy));
    }

    #[Route('/insurance/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $policy = $this->entityManager->getRepository(InsurancePolicy::class)->find($id);
        $user = $this->getUserEntity();
        if (!$policy || !$user || (!$this->isAdminForUser($user) && $policy->getHolderId() !== $user->getId())) {
            return new JsonResponse(['error' => 'Insurance policy not found'], 404);
        }

        $this->entityManager->remove($policy);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Insurance policy deleted']);
    }

    /**
     * Serialize an insurance policy to an array (for compatibility with frontend expecting insurance records)
     *
     * @param InsurancePolicy $policy The insurance policy entity
     *
     * @return array<string, mixed> The serialized insurance data
     */
    private function serializeInsurance(InsurancePolicy $policy): array
    {
        return [
            'id' => $policy->getId(),
            'vehicleId' => $policy->getVehicles()->first()?->getId(), // For compatibility, use first vehicle
            'provider' => $policy->getProvider(),
            'policyNumber' => $policy->getPolicyNumber(),
            'coverageType' => $policy->getCoverageType(),
            'annualCost' => $policy->getAnnualCost(),
            'startDate' => $policy->getStartDate()?->format('Y-m-d'),
            'expiryDate' => $policy->getExpiryDate()?->format('Y-m-d'),
            'notes' => $policy->getNotes(),
            'createdAt' => $policy->getCreatedAt()?->format('c'),
        ];
    }

    /**
     * Update policy entity from request data
     *
     * @param InsurancePolicy $policy The policy entity
     * @param array<string, mixed> $data The request data
     *
     * @return void
     */
    private function updatePolicyFromData(InsurancePolicy $policy, array $data): void
    {
        if (isset($data['provider'])) {
            $policy->setProvider($data['provider']);
        }
        if (isset($data['policyNumber'])) {
            $policy->setPolicyNumber($data['policyNumber']);
        }
        if (isset($data['coverageType'])) {
            $policy->setCoverageType($data['coverageType']);
        }
        if (isset($data['annualCost'])) {
            $policy->setAnnualCost($data['annualCost']);
        }
        if (isset($data['startDate'])) {
            $policy->setStartDate(new \DateTime($data['startDate']));
        }
        if (isset($data['expiryDate'])) {
            $policy->setExpiryDate(new \DateTime($data['expiryDate']));
        }
        if (isset($data['notes'])) {
            $policy->setNotes($data['notes']);
        }
        if (isset($data['ncdYears'])) {
            $policy->setNcdYears($data['ncdYears']);
        }
        if (isset($data['excess'])) {
            $policy->setExcess($data['excess']);
        }
        if (isset($data['mileageLimit'])) {
            $policy->setMileageLimit($data['mileageLimit']);
        }
        if (isset($data['autoRenewal'])) {
            $policy->setAutoRenewal($data['autoRenewal']);
        }
    }

    /**
     * Serialize policy to array for JSON responses.
     *
     * @param InsurancePolicy $policy Policy entity to serialize.
     *
     * @return array
     */
    private function _serializePolicy(InsurancePolicy $policy): array
    {
        $vehicles = [];
        foreach ($policy->getVehicles() as $v) {
            $vehicles[] = [
                'id' => $v->getId(),
                'registration' => $v->getRegistration(),
            ];
        }

        return [
            'id' => $policy->getId(),
            'provider' => $policy->getProvider(),
            'policyNumber' => $policy->getPolicyNumber(),
            'startDate' => $policy->getStartDate()?->format('Y-m-d'),
            'expiryDate' => $policy->getExpiryDate()?->format('Y-m-d'),
            'annualCost' => $policy->getAnnualCost(),
            'ncdYears' => $policy->getNcdYears(),
            'coverageType' => $policy->getCoverageType(),
            'excess' => $policy->getExcess(),
            'mileageLimit' => $policy->getMileageLimit(),
            'autoRenewal' => $policy->getAutoRenewal(),
            'notes' => $policy->getNotes(),
            'holderId' => $policy->getHolderId(),
            'vehicles' => $vehicles,
        ];
    }
}
