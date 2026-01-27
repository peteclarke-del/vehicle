<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Specification;
use App\Entity\Vehicle;
use App\Service\VehicleSpecificationScraperService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/vehicles')]
class SpecificationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private VehicleSpecificationScraperService $scraperService
    ) {
    }

    private function isAdminForUser(?\App\Entity\User $user): bool
    {
        if (!$user) return false;
        $roles = $user->getRoles() ?: [];
        return in_array('ROLE_ADMIN', $roles, true);
    }

    #[Route('/{id}/specifications', name: 'api_vehicle_specifications', methods: ['GET'])]
    public function getSpecifications(int $id): JsonResponse
    {
        $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($id);

        if (!$vehicle) {
            return $this->json(['error' => 'Vehicle not found'], 404);
        }

        // Check if user owns this vehicle (admins bypass)
        if (!$this->isAdminForUser($this->getUser()) && $vehicle->getOwner() !== $this->getUser()) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $specification = $this->entityManager
            ->getRepository(Specification::class)
            ->findOneBy(['vehicle' => $vehicle]);

        if (!$specification) {
            return $this->json(null);
        }

        return $this->json($this->serializeSpecification($specification));
    }

    #[Route('/{id}/specifications/scrape', name: 'api_vehicle_specifications_scrape', methods: ['POST'])]
    public function scrapeSpecifications(int $id): JsonResponse
    {
        $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($id);

        if (!$vehicle) {
            return $this->json(['error' => 'Vehicle not found'], 404);
        }

        // Check if user owns this vehicle (admins bypass)
        if (!$this->isAdminForUser($this->getUser()) && $vehicle->getOwner() !== $this->getUser()) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $specification = $this->scraperService->scrapeSpecifications($vehicle);

        if (!$specification) {
            return $this->json([
                'error' => 'Could not scrape specifications',
                'message' => 'Unable to find specifications for this vehicle online. Please enter them manually.'
            ], 404);
        }

        // Check if specification already exists for this vehicle
        $existingSpec = $this->entityManager
            ->getRepository(Specification::class)
            ->findOneBy(['vehicle' => $vehicle]);

        if ($existingSpec) {
            // Update existing specification with scraped data
            $this->updateSpecificationFromScraped($existingSpec, $specification);
            $specification = $existingSpec;
        } else {
            $specification->setVehicle($vehicle);
            $this->entityManager->persist($specification);
        }

        $this->entityManager->flush();

        return $this->json([
            'message' => 'Specifications scraped successfully',
            'specification' => $this->serializeSpecification($specification)
        ]);
    }

    #[Route('/{id}/specifications', name: 'api_vehicle_specifications_update', methods: ['PUT'])]
    public function updateSpecifications(int $id, Request $request): JsonResponse
    {
        $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($id);

        if (!$vehicle) {
            return $this->json(['error' => 'Vehicle not found'], 404);
        }

        // Check if user owns this vehicle (admins bypass)
        if (!$this->isAdminForUser($this->getUser()) && $vehicle->getOwner() !== $this->getUser()) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $data = json_decode($request->getContent(), true);

        $specification = $this->entityManager
            ->getRepository(Specification::class)
            ->findOneBy(['vehicle' => $vehicle]);

        if (!$specification) {
            $specification = new Specification();
            $specification->setVehicle($vehicle);
            $this->entityManager->persist($specification);
        }

        // Update all fields from request
        $this->updateSpecificationFromData($specification, $data);

        $this->entityManager->flush();

        return $this->json([
            'message' => 'Specifications updated successfully',
            'specification' => $this->serializeSpecification($specification)
        ]);
    }

    #[Route('/{id}/specifications', name: 'api_vehicle_specifications_delete', methods: ['DELETE'])]
    public function deleteSpecifications(int $id): JsonResponse
    {
        $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($id);

        if (!$vehicle) {
            return $this->json(['error' => 'Vehicle not found'], 404);
        }

        // Check if user owns this vehicle (admins bypass)
        if (!$this->isAdminForUser($this->getUser()) && $vehicle->getOwner() !== $this->getUser()) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        $specification = $this->entityManager
            ->getRepository(Specification::class)
            ->findOneBy(['vehicle' => $vehicle]);

        if ($specification) {
            $this->entityManager->remove($specification);
            $this->entityManager->flush();
        }

        return $this->json(['message' => 'Specifications deleted successfully']);
    }

    private function serializeSpecification(Specification $spec): array
    {
        return [
            'id' => $spec->getId(),
            'vehicleId' => $spec->getVehicle()?->getId(),
            'engineType' => $spec->getEngineType(),
            'displacement' => $spec->getDisplacement(),
            'power' => $spec->getPower(),
            'torque' => $spec->getTorque(),
            'compression' => $spec->getCompression(),
            'bore' => $spec->getBore(),
            'stroke' => $spec->getStroke(),
            'fuelSystem' => $spec->getFuelSystem(),
            'cooling' => $spec->getCooling(),
            'gearbox' => $spec->getGearbox(),
            'transmission' => $spec->getTransmission(),
            'clutch' => $spec->getClutch(),
            'frame' => $spec->getFrame(),
            'frontSuspension' => $spec->getFrontSuspension(),
            'rearSuspension' => $spec->getRearSuspension(),
            'frontBrakes' => $spec->getFrontBrakes(),
            'rearBrakes' => $spec->getRearBrakes(),
            'frontTyre' => $spec->getFrontTyre(),
            'rearTyre' => $spec->getRearTyre(),
            'frontWheelTravel' => $spec->getFrontWheelTravel(),
            'rearWheelTravel' => $spec->getRearWheelTravel(),
            'wheelbase' => $spec->getWheelbase(),
            'seatHeight' => $spec->getSeatHeight(),
            'groundClearance' => $spec->getGroundClearance(),
            'dryWeight' => $spec->getDryWeight(),
            'wetWeight' => $spec->getWetWeight(),
            'fuelCapacity' => $spec->getFuelCapacity(),
            'topSpeed' => $spec->getTopSpeed(),
            'additionalInfo' => $spec->getAdditionalInfo(),
            'scrapedAt' => $spec->getScrapedAt()?->format('Y-m-d H:i:s'),
            'sourceUrl' => $spec->getSourceUrl(),
        ];
    }

    private function updateSpecificationFromScraped(Specification $existing, Specification $scraped): void
    {
        // Only update fields that are empty in existing specification
        if (!$existing->getEngineType()) {
            $existing->setEngineType($scraped->getEngineType());
        }
        if (!$existing->getDisplacement()) {
            $existing->setDisplacement($scraped->getDisplacement());
        }
        if (!$existing->getPower()) {
            $existing->setPower($scraped->getPower());
        }
        if (!$existing->getTorque()) {
            $existing->setTorque($scraped->getTorque());
        }
        if (!$existing->getCompression()) {
            $existing->setCompression($scraped->getCompression());
        }
        if (!$existing->getBore()) {
            $existing->setBore($scraped->getBore());
        }
        if (!$existing->getStroke()) {
            $existing->setStroke($scraped->getStroke());
        }
        if (!$existing->getFuelSystem()) {
            $existing->setFuelSystem($scraped->getFuelSystem());
        }
        if (!$existing->getCooling()) {
            $existing->setCooling($scraped->getCooling());
        }
        if (!$existing->getGearbox()) {
            $existing->setGearbox($scraped->getGearbox());
        }
        if (!$existing->getTransmission()) {
            $existing->setTransmission($scraped->getTransmission());
        }
        if (!$existing->getClutch()) {
            $existing->setClutch($scraped->getClutch());
        }
        if (!$existing->getFrame()) {
            $existing->setFrame($scraped->getFrame());
        }
        if (!$existing->getFrontSuspension()) {
            $existing->setFrontSuspension($scraped->getFrontSuspension());
        }
        if (!$existing->getRearSuspension()) {
            $existing->setRearSuspension($scraped->getRearSuspension());
        }
        if (!$existing->getFrontBrakes()) {
            $existing->setFrontBrakes($scraped->getFrontBrakes());
        }
        if (!$existing->getRearBrakes()) {
            $existing->setRearBrakes($scraped->getRearBrakes());
        }
        if (!$existing->getFrontTyre()) {
            $existing->setFrontTyre($scraped->getFrontTyre());
        }
        if (!$existing->getRearTyre()) {
            $existing->setRearTyre($scraped->getRearTyre());
        }
        if (!$existing->getFrontWheelTravel()) {
            $existing->setFrontWheelTravel($scraped->getFrontWheelTravel());
        }
        if (!$existing->getRearWheelTravel()) {
            $existing->setRearWheelTravel($scraped->getRearWheelTravel());
        }
        if (!$existing->getWheelbase()) {
            $existing->setWheelbase($scraped->getWheelbase());
        }
        if (!$existing->getSeatHeight()) {
            $existing->setSeatHeight($scraped->getSeatHeight());
        }
        if (!$existing->getGroundClearance()) {
            $existing->setGroundClearance($scraped->getGroundClearance());
        }
        if (!$existing->getDryWeight()) {
            $existing->setDryWeight($scraped->getDryWeight());
        }
        if (!$existing->getWetWeight()) {
            $existing->setWetWeight($scraped->getWetWeight());
        }
        if (!$existing->getFuelCapacity()) {
            $existing->setFuelCapacity($scraped->getFuelCapacity());
        }
        if (!$existing->getTopSpeed()) {
            $existing->setTopSpeed($scraped->getTopSpeed());
        }

        $existing->setScrapedAt($scraped->getScrapedAt());
        $existing->setSourceUrl($scraped->getSourceUrl());
    }

    private function updateSpecificationFromData(Specification $spec, array $data): void
    {
        if (isset($data['engineType'])) {
            $spec->setEngineType($data['engineType']);
        }
        if (isset($data['displacement'])) {
            $spec->setDisplacement($data['displacement']);
        }
        if (isset($data['power'])) {
            $spec->setPower($data['power']);
        }
        if (isset($data['torque'])) {
            $spec->setTorque($data['torque']);
        }
        if (isset($data['compression'])) {
            $spec->setCompression($data['compression']);
        }
        if (isset($data['bore'])) {
            $spec->setBore($data['bore']);
        }
        if (isset($data['stroke'])) {
            $spec->setStroke($data['stroke']);
        }
        if (isset($data['fuelSystem'])) {
            $spec->setFuelSystem($data['fuelSystem']);
        }
        if (isset($data['cooling'])) {
            $spec->setCooling($data['cooling']);
        }
        if (isset($data['gearbox'])) {
            $spec->setGearbox($data['gearbox']);
        }
        if (isset($data['transmission'])) {
            $spec->setTransmission($data['transmission']);
        }
        if (isset($data['clutch'])) {
            $spec->setClutch($data['clutch']);
        }
        if (isset($data['frame'])) {
            $spec->setFrame($data['frame']);
        }
        if (isset($data['frontSuspension'])) {
            $spec->setFrontSuspension($data['frontSuspension']);
        }
        if (isset($data['rearSuspension'])) {
            $spec->setRearSuspension($data['rearSuspension']);
        }
        if (isset($data['frontBrakes'])) {
            $spec->setFrontBrakes($data['frontBrakes']);
        }
        if (isset($data['rearBrakes'])) {
            $spec->setRearBrakes($data['rearBrakes']);
        }
        if (isset($data['frontTyre'])) {
            $spec->setFrontTyre($data['frontTyre']);
        }
        if (isset($data['rearTyre'])) {
            $spec->setRearTyre($data['rearTyre']);
        }
        if (isset($data['frontWheelTravel'])) {
            $spec->setFrontWheelTravel($data['frontWheelTravel']);
        }
        if (isset($data['rearWheelTravel'])) {
            $spec->setRearWheelTravel($data['rearWheelTravel']);
        }
        if (isset($data['wheelbase'])) {
            $spec->setWheelbase($data['wheelbase']);
        }
        if (isset($data['seatHeight'])) {
            $spec->setSeatHeight($data['seatHeight']);
        }
        if (isset($data['groundClearance'])) {
            $spec->setGroundClearance($data['groundClearance']);
        }
        if (isset($data['dryWeight'])) {
            $spec->setDryWeight($data['dryWeight']);
        }
        if (isset($data['wetWeight'])) {
            $spec->setWetWeight($data['wetWeight']);
        }
        if (isset($data['fuelCapacity'])) {
            $spec->setFuelCapacity($data['fuelCapacity']);
        }
        if (isset($data['topSpeed'])) {
            $spec->setTopSpeed($data['topSpeed']);
        }
        if (isset($data['additionalInfo'])) {
            $spec->setAdditionalInfo($data['additionalInfo']);
        }
    }
}
