<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Insurance;
use App\Entity\Vehicle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Insurance Controller
 *
 * Handles CRUD operations for vehicle insurance records
 */
#[Route('/api')]
class InsuranceController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    /**
     * Constructor
     *
     * @param EntityManagerInterface $entityManager The entity manager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Get the current authenticated user entity
     *
     * @return User|null The user entity or null
     */
    private function getUserEntity(): ?\App\Entity\User
    {
        $user = $this->getUser();
        return $user instanceof \App\Entity\User ? $user : null;
    }

    /**
     * List all insurance records for a vehicle
     *
     * @param Request $request The HTTP request
     *
     * @return JsonResponse The insurance records
     */
    #[Route('/insurance', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $vehicleId = $request->query->get('vehicleId');

        if (!$vehicleId) {
            return new JsonResponse(['error' => 'vehicleId is required'], 400);
        }

        $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($vehicleId);
        $user = $this->getUserEntity();
        if (!$vehicle || !$user || $vehicle->getOwner()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Vehicle not found'], 404);
        }

        $insurances = $this->entityManager->getRepository(Insurance::class)
            ->findBy(['vehicle' => $vehicle], ['startDate' => 'DESC']);

        return new JsonResponse(array_map(fn($ins) => $this->serializeInsurance($ins), $insurances));
    }

    /**
     * Create a new insurance record
     *
     * @param Request $request The HTTP request
     *
     * @return JsonResponse The created insurance record
     */
    #[Route('/insurance', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($data['vehicleId']);
        $user = $this->getUserEntity();
        if (!$vehicle || !$user || $vehicle->getOwner()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Vehicle not found'], 404);
        }

        $insurance = new Insurance();
        $insurance->setVehicle($vehicle);
        $this->updateInsuranceFromData($insurance, $data);

        $this->entityManager->persist($insurance);
        $this->entityManager->flush();

        return new JsonResponse($this->serializeInsurance($insurance), 201);
    }

    /**
     * Update an existing insurance record
     *
     * @param int     $id      The insurance record ID
     * @param Request $request The HTTP request
     *
     * @return JsonResponse The updated insurance record
     */
    #[Route('/insurance/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $insurance = $this->entityManager->getRepository(Insurance::class)->find($id);
        $user = $this->getUserEntity();
        if (!$insurance || !$user || $insurance->getVehicle()->getOwner()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Insurance record not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $this->updateInsuranceFromData($insurance, $data);

        $this->entityManager->flush();

        return new JsonResponse($this->serializeInsurance($insurance));
    }

    /**
     * Delete an insurance record
     *
     * @param int $id The insurance record ID
     *
     * @return JsonResponse The deletion confirmation
     */
    #[Route('/insurance/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $insurance = $this->entityManager->getRepository(Insurance::class)->find($id);
        $user = $this->getUserEntity();
        if (!$insurance || !$user || $insurance->getVehicle()->getOwner()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Insurance record not found'], 404);
        }

        $this->entityManager->remove($insurance);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Insurance record deleted']);
    }

    /**
     * Serialize an insurance entity to an array
     *
     * @param Insurance $insurance The insurance entity
     *
     * @return array<string, mixed> The serialized insurance data
     */
    private function serializeInsurance(Insurance $insurance): array
    {
        return [
            'id' => $insurance->getId(),
            'vehicleId' => $insurance->getVehicle()->getId(),
            'provider' => $insurance->getProvider(),
            'policyNumber' => $insurance->getPolicyNumber(),
            'coverageType' => $insurance->getCoverageType(),
            'annualCost' => $insurance->getAnnualCost(),
            'startDate' => $insurance->getStartDate()?->format('Y-m-d'),
            'expiryDate' => $insurance->getExpiryDate()?->format('Y-m-d'),
            'notes' => $insurance->getNotes(),
            'createdAt' => $insurance->getCreatedAt()?->format('c'),
        ];
    }

    /**
     * Update insurance entity from request data
     *
     * @param Insurance            $insurance The insurance entity
     * @param array<string, mixed> $data      The request data
     *
     * @return void
     */
    private function updateInsuranceFromData(Insurance $insurance, array $data): void
    {
        if (isset($data['provider'])) {
            $insurance->setProvider($data['provider']);
        }
        if (isset($data['policyNumber'])) {
            $insurance->setPolicyNumber($data['policyNumber']);
        }
        if (isset($data['coverageType'])) {
            $insurance->setCoverageType($data['coverageType']);
        }
        if (isset($data['annualCost'])) {
            $insurance->setAnnualCost($data['annualCost']);
        }
        if (isset($data['startDate'])) {
            $insurance->setStartDate(new \DateTime($data['startDate']));
        }
        if (isset($data['expiryDate'])) {
            $insurance->setExpiryDate(new \DateTime($data['expiryDate']));
        }
        if (isset($data['notes'])) {
            $insurance->setNotes($data['notes']);
        }
    }
}
