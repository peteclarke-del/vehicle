<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\Vehicle;
use App\Service\CostCalculator;
use App\Service\DepreciationCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/vehicles')]
class VehicleController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CostCalculator $costCalculator,
        private DepreciationCalculator $depreciationCalculator
    ) {
    }

    #[Route('', name: 'api_vehicles_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $vehicles = $this->entityManager->getRepository(Vehicle::class)
            ->findBy(['owner' => $user]);

        $data = array_map(fn($v) => $this->serializeVehicle($v), $vehicles);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'api_vehicles_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function get(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($id);

        if (!$vehicle || $vehicle->getOwner()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Vehicle not found'], 404);
        }

        return $this->json($this->serializeVehicle($vehicle));
    }

    #[Route('', name: 'api_vehicles_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $data = json_decode($request->getContent(), true);

        $vehicle = new Vehicle();
        $vehicle->setOwner($user);
        $this->updateVehicleFromData($vehicle, $data);

        $this->entityManager->persist($vehicle);
        $this->entityManager->flush();

        return $this->json($this->serializeVehicle($vehicle), 201);
    }

    #[Route('/{id}', name: 'api_vehicles_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($id);

        if (!$vehicle || $vehicle->getOwner()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Vehicle not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $this->updateVehicleFromData($vehicle, $data);

        $vehicle->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        return $this->json($this->serializeVehicle($vehicle));
    }

    #[Route('/{id}', name: 'api_vehicles_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($id);

        if (!$vehicle || $vehicle->getOwner()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Vehicle not found'], 404);
        }

        $this->entityManager->remove($vehicle);
        $this->entityManager->flush();

        return $this->json(['message' => 'Vehicle deleted successfully']);
    }

    #[Route('/{id}/stats', name: 'api_vehicles_stats', methods: ['GET'])]
    public function stats(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($id);

        if (!$vehicle || $vehicle->getOwner()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Vehicle not found'], 404);
        }

        $stats = $this->costCalculator->getVehicleStats($vehicle);
        $schedule = $this->depreciationCalculator->getDepreciationSchedule($vehicle);

        return $this->json([
            'stats' => $stats,
            'depreciationSchedule' => $schedule
        ]);
    }

    private function serializeVehicle(Vehicle $vehicle): array
    {
        return [
            'id' => $vehicle->getId(),
            'name' => $vehicle->getName(),
            'make' => $vehicle->getMake(),
            'model' => $vehicle->getModel(),
            'year' => $vehicle->getYear(),
            'vin' => $vehicle->getVin(),
            'vinDecodedData' => $vehicle->getVinDecodedData(),
            'vinDecodedAt' => $vehicle->getVinDecodedAt()?->format('Y-m-d H:i:s'),
            'registrationNumber' => $vehicle->getRegistrationNumber(),
            'engineNumber' => $vehicle->getEngineNumber(),
            'v5DocumentNumber' => $vehicle->getV5DocumentNumber(),
            'purchaseCost' => $vehicle->getPurchaseCost(),
            'purchaseDate' => $vehicle->getPurchaseDate()?->format('Y-m-d'),
            'currentMileage' => $vehicle->getCurrentMileage(),
            'lastServiceDate' => $vehicle->getLastServiceDate()?->format('Y-m-d'),
            'motExpiryDate' => $vehicle->getMotExpiryDate()?->format('Y-m-d'),
            'roadTaxExpiryDate' => $vehicle->getRoadTaxExpiryDate()?->format('Y-m-d'),
            'insuranceExpiryDate' => $vehicle->getInsuranceExpiryDate()
                ?->format('Y-m-d'),
            'securityFeatures' => $vehicle->getSecurityFeatures(),
            'vehicleColor' => $vehicle->getVehicleColor(),
            'serviceIntervalMonths' => $vehicle->getServiceIntervalMonths(),
            'serviceIntervalMiles' => $vehicle->getServiceIntervalMiles(),
            'depreciationMethod' => $vehicle->getDepreciationMethod(),
            'depreciationYears' => $vehicle->getDepreciationYears(),
            'depreciationRate' => $vehicle->getDepreciationRate(),
            'vehicleType' => [
                'id' => $vehicle->getVehicleType()->getId(),
                'name' => $vehicle->getVehicleType()->getName()
            ],
            'createdAt' => $vehicle->getCreatedAt()?->format('c'),
            'updatedAt' => $vehicle->getUpdatedAt()?->format('c')
        ];
    }

    private function updateVehicleFromData(Vehicle $vehicle, array $data): void
    {
        if (isset($data['vehicleTypeId'])) {
            $vehicleType = $this->entityManager->getRepository(\App\Entity\VehicleType::class)
                ->find($data['vehicleTypeId']);
            if ($vehicleType) {
                $vehicle->setVehicleType($vehicleType);
            }
        }

        if (isset($data['name'])) {
            $vehicle->setName($data['name']);
        }
        if (isset($data['make'])) {
            $vehicle->setMake($data['make']);
        }
        if (isset($data['model'])) {
            $vehicle->setModel($data['model']);
        }
        if (isset($data['year'])) {
            $vehicle->setYear($data['year']);
        }
        if (isset($data['vin'])) {
            $vehicle->setVin($data['vin']);
        }
        if (isset($data['registrationNumber'])) {
            $vehicle->setRegistrationNumber($data['registrationNumber']);
        }
        if (isset($data['engineNumber'])) {
            $vehicle->setEngineNumber($data['engineNumber']);
        }
        if (isset($data['v5DocumentNumber'])) {
            $vehicle->setV5DocumentNumber($data['v5DocumentNumber']);
        }
        if (isset($data['purchaseCost'])) {
            $vehicle->setPurchaseCost($data['purchaseCost']);
        }
        if (isset($data['purchaseDate'])) {
            $vehicle->setPurchaseDate(new \DateTime($data['purchaseDate']));
        }
        if (isset($data['currentMileage'])) {
            $vehicle->setCurrentMileage($data['currentMileage']);
        }
        if (isset($data['lastServiceDate'])) {
            $vehicle->setLastServiceDate(new \DateTime($data['lastServiceDate']));
        }
        if (isset($data['motExpiryDate'])) {
            $vehicle->setMotExpiryDate(new \DateTime($data['motExpiryDate']));
        }
        if (isset($data['roadTaxExpiryDate'])) {
            $vehicle->setRoadTaxExpiryDate(
                new \DateTime($data['roadTaxExpiryDate'])
            );
        }
        if (isset($data['insuranceExpiryDate'])) {
            $vehicle->setInsuranceExpiryDate(
                new \DateTime($data['insuranceExpiryDate'])
            );
        }
        if (isset($data['securityFeatures'])) {
            $vehicle->setSecurityFeatures($data['securityFeatures']);
        }
        if (isset($data['vehicleColor'])) {
            $vehicle->setVehicleColor($data['vehicleColor']);
        }
        if (isset($data['serviceIntervalMonths'])) {
            $vehicle->setServiceIntervalMonths($data['serviceIntervalMonths']);
        }
        if (isset($data['serviceIntervalMiles'])) {
            $vehicle->setServiceIntervalMiles($data['serviceIntervalMiles']);
        }
        if (isset($data['depreciationMethod'])) {
            $vehicle->setDepreciationMethod($data['depreciationMethod']);
        }
        if (isset($data['depreciationYears'])) {
            $vehicle->setDepreciationYears($data['depreciationYears']);
        }
        if (isset($data['depreciationRate'])) {
            $vehicle->setDepreciationRate($data['depreciationRate']);
        }
    }
}
