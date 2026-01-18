<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\MotRecord;
use App\Entity\Vehicle;
use App\Entity\Part;
use App\Entity\Consumable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class MotRecordController extends AbstractController
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
    #[Route('/mot-records', methods: ['GET'])]
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

        $motRecords = $this->entityManager->getRepository(MotRecord::class)
            ->findBy(['vehicle' => $vehicle], ['testDate' => 'DESC']);

        return new JsonResponse(array_map(fn($mot) => $this->serializeMotRecord($mot), $motRecords));
    }

    #[Route('/mot-records/{id}', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $motRecord = $this->entityManager->getRepository(MotRecord::class)->find($id);
        $user = $this->getUserEntity();
        if (!$motRecord || !$user || $motRecord->getVehicle()->getOwner()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'MOT record not found'], 404);
        }

        return new JsonResponse($this->serializeMotRecord($motRecord, true));
    }

    #[Route('/mot-records', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($data['vehicleId']);
        $user = $this->getUserEntity();
        if (!$vehicle || !$user || $vehicle->getOwner()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Vehicle not found'], 404);
        }

        $motRecord = new MotRecord();
        $motRecord->setVehicle($vehicle);
        $this->updateMotRecordFromData($motRecord, $data);

        $this->entityManager->persist($motRecord);
        $this->entityManager->flush();

        return new JsonResponse($this->serializeMotRecord($motRecord), 201);
    }

    #[Route('/mot-records/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $motRecord = $this->entityManager->getRepository(MotRecord::class)->find($id);
        $user = $this->getUserEntity();
        if (!$motRecord || !$user || $motRecord->getVehicle()->getOwner()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'MOT record not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $this->updateMotRecordFromData($motRecord, $data);

        $this->entityManager->flush();

        return new JsonResponse($this->serializeMotRecord($motRecord));
    }

    #[Route('/mot-records/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $motRecord = $this->entityManager->getRepository(MotRecord::class)->find($id);
        $user = $this->getUserEntity();
        if (!$motRecord || !$user || $motRecord->getVehicle()->getOwner()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'MOT record not found'], 404);
        }

        $this->entityManager->remove($motRecord);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'MOT record deleted']);
    }

    #[Route('/mot-records/{id}/items', methods: ['GET'])]
    public function getItems(int $id): JsonResponse
    {
        $motRecord = $this->entityManager->getRepository(MotRecord::class)->find($id);
        $user = $this->getUserEntity();
        if (!$motRecord || !$user || $motRecord->getVehicle()->getOwner()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'MOT record not found'], 404);
        }

        $parts = $this->entityManager->getRepository(Part::class)
            ->findBy(['motRecord' => $motRecord]);

        $consumables = $this->entityManager->getRepository(Consumable::class)
            ->findBy(['motRecord' => $motRecord]);

        return new JsonResponse([
            'motRecord' => $this->serializeMotRecord($motRecord),
            'parts' => array_map(fn($p) => $this->serializePart($p), $parts),
            'consumables' => array_map(fn($c) => $this->serializeConsumable($c), $consumables),
        ]);
    }

    private function serializeMotRecord(MotRecord $mot, bool $detailed = false): array
    {
        $data = [
            'id' => $mot->getId(),
            'vehicleId' => $mot->getVehicle()->getId(),
            'testDate' => $mot->getTestDate()?->format('Y-m-d'),
            'result' => $mot->getResult(),
            'testCost' => $mot->getTestCost(),
            'repairCost' => $mot->getRepairCost(),
            'totalCost' => $mot->getTotalCost(),
            'mileage' => $mot->getMileage(),
            'testCenter' => $mot->getTestCenter(),
            'advisories' => $mot->getAdvisories(),
            'failures' => $mot->getFailures(),
            'repairDetails' => $mot->getRepairDetails(),
            'notes' => $mot->getNotes(),
            'createdAt' => $mot->getCreatedAt()?->format('c'),
        ];

        return $data;
    }

    private function serializePart(Part $part): array
    {
        return [
            'id' => $part->getId(),
            'description' => $part->getDescription(),
            'cost' => $part->getCost(),
        ];
    }

    private function serializeConsumable(Consumable $consumable): array
    {
        return [
            'id' => $consumable->getId(),
            'specification' => $consumable->getSpecification(),
            'cost' => $consumable->getCost(),
        ];
    }

    private function updateMotRecordFromData(MotRecord $mot, array $data): void
    {
        if (isset($data['testDate'])) {
            $mot->setTestDate(new \DateTime($data['testDate']));
        }
        if (isset($data['result'])) {
            $mot->setResult($data['result']);
        }
        if (isset($data['testCost'])) {
            $mot->setTestCost($data['testCost']);
        }
        if (isset($data['repairCost'])) {
            $mot->setRepairCost($data['repairCost']);
        }
        if (isset($data['mileage'])) {
            $mot->setMileage($data['mileage']);
        }
        if (isset($data['testCenter'])) {
            $mot->setTestCenter($data['testCenter']);
        }
        if (isset($data['advisories'])) {
            $mot->setAdvisories($data['advisories']);
        }
        if (isset($data['failures'])) {
            $mot->setFailures($data['failures']);
        }
        if (isset($data['repairDetails'])) {
            $mot->setRepairDetails($data['repairDetails']);
        }
        if (isset($data['notes'])) {
            $mot->setNotes($data['notes']);
        }
    }
}
