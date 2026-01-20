<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ServiceRecord;
use App\Entity\ServiceItem;
use App\Entity\Vehicle;
use App\Entity\Part;
use App\Entity\Consumable;
use App\Service\ReceiptOcrService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class ServiceRecordController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        private ReceiptOcrService $ocrService
    ) {
        $this->entityManager = $entityManager;
    }
    private function getUserEntity(): ?\App\Entity\User
    {
        $user = $this->getUser();
        return $user instanceof \App\Entity\User ? $user : null;
    }
    #[Route('/service-records', methods: ['GET'])]
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

        $serviceRecords = $this->entityManager->getRepository(ServiceRecord::class)
            ->findBy(['vehicle' => $vehicle], ['serviceDate' => 'DESC']);

        return new JsonResponse(array_map(fn($svc) => $this->serializeServiceRecord($svc), $serviceRecords));
    }

    #[Route('/service-records/{id}', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $serviceRecord = $this->entityManager->getRepository(ServiceRecord::class)->find($id);
        $user = $this->getUserEntity();
        if (!$serviceRecord || !$user || $serviceRecord->getVehicle()->getOwner()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Service record not found'], 404);
        }

        return new JsonResponse($this->serializeServiceRecord($serviceRecord, true));
    }

    #[Route('/service-records', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($data['vehicleId']);
        $user = $this->getUserEntity();
        if (!$vehicle || !$user || $vehicle->getOwner()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Vehicle not found'], 404);
        }

        $serviceRecord = new ServiceRecord();
        $serviceRecord->setVehicle($vehicle);
        $this->updateServiceRecordFromData($serviceRecord, $data);

        $this->entityManager->persist($serviceRecord);
        $this->entityManager->flush();

        return new JsonResponse($this->serializeServiceRecord($serviceRecord), 201);
    }

    #[Route('/service-records/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $serviceRecord = $this->entityManager->getRepository(ServiceRecord::class)->find($id);
        $user = $this->getUserEntity();
        if (!$serviceRecord || !$user || $serviceRecord->getVehicle()->getOwner()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Service record not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $this->updateServiceRecordFromData($serviceRecord, $data);

        $this->entityManager->flush();

        return new JsonResponse($this->serializeServiceRecord($serviceRecord));
    }

    #[Route('/service-records/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $serviceRecord = $this->entityManager->getRepository(ServiceRecord::class)->find($id);
        $user = $this->getUserEntity();
        if (!$serviceRecord || !$user || $serviceRecord->getVehicle()->getOwner()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Service record not found'], 404);
        }

        $this->entityManager->remove($serviceRecord);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Service record deleted']);
    }

    #[Route('/service-records/{id}/items', methods: ['GET'])]
    public function getItems(int $id): JsonResponse
    {
        $serviceRecord = $this->entityManager->getRepository(ServiceRecord::class)->find($id);
        $user = $this->getUserEntity();
        if (!$serviceRecord || !$user || $serviceRecord->getVehicle()->getOwner()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Service record not found'], 404);
        }

        $parts = $this->entityManager->getRepository(Part::class)
            ->findBy(['serviceRecord' => $serviceRecord]);

        $consumables = $this->entityManager->getRepository(Consumable::class)
            ->findBy(['serviceRecord' => $serviceRecord]);

        return new JsonResponse([
            'serviceRecord' => $this->serializeServiceRecord($serviceRecord),
            'parts' => array_map(fn($p) => $this->serializePart($p), $parts),
            'consumables' => array_map(fn($c) => $this->serializeConsumable($c), $consumables),
        ]);
    }

    private function serializeServiceRecord(ServiceRecord $service, bool $detailed = false): array
    {
        $laborCost = $service->getLaborCost();
        $partsCost = $service->getPartsCost();

        // If itemised entries exist, compute costs from them
        $items = $service->getItems();
        if (count($items) > 0) {
            $laborCost = $service->sumItemsByType('labour');
            $partsCost = $service->sumItemsByType('part');
        }

        $data = [
            'id' => $service->getId(),
            'vehicleId' => $service->getVehicle()->getId(),
            'serviceDate' => $service->getServiceDate()?->format('Y-m-d'),
            'serviceType' => $service->getServiceType(),
            'laborCost' => $laborCost,
            'partsCost' => $partsCost,
            'totalCost' => $service->getTotalCost(),
            'mileage' => $service->getMileage(),
            'serviceProvider' => $service->getServiceProvider(),
            'workPerformed' => $service->getWorkPerformed(),
            'notes' => $service->getNotes(),
            'receiptAttachmentId' => $service->getReceiptAttachmentId(),
            'createdAt' => $service->getCreatedAt()?->format('c'),
        ];

        // include items when detailed or available
        if ($detailed || count($items) > 0) {
            $data['items'] = array_map(fn($it) => $this->serializeItem($it), $items);
        }

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

    private function serializeItem(ServiceItem $item): array
    {
        return [
            'id' => $item->getId(),
            'type' => $item->getType(),
            'description' => $item->getDescription(),
            'cost' => $item->getCost(),
            'quantity' => $item->getQuantity(),
            'total' => $item->getTotal(),
        ];
    }

    private function updateServiceRecordFromData(ServiceRecord $service, array $data): void
    {
        if (isset($data['serviceDate'])) {
            $service->setServiceDate(new \DateTime($data['serviceDate']));
        }
        if (isset($data['serviceType'])) {
            $service->setServiceType($data['serviceType']);
        }
        if (isset($data['laborCost'])) {
            $service->setLaborCost($data['laborCost']);
        }
        if (isset($data['partsCost'])) {
            $service->setPartsCost($data['partsCost']);
        }
        if (isset($data['mileage'])) {
            $service->setMileage($data['mileage']);
        }
        if (isset($data['serviceProvider'])) {
            $service->setServiceProvider($data['serviceProvider']);
        }
        if (isset($data['workPerformed'])) {
            $service->setWorkPerformed($data['workPerformed']);
        }
        if (isset($data['notes'])) {
            $service->setNotes($data['notes']);
        }
        if (isset($data['receiptAttachmentId'])) {
            $service->setReceiptAttachmentId($data['receiptAttachmentId']);
        }

        // Handle itemised entries (parts / labour / consumables)
        if (isset($data['items']) && is_array($data['items'])) {
            // remove existing items
            $existing = $this->entityManager->getRepository(ServiceItem::class)
                ->findBy(['serviceRecord' => $service]);
            foreach ($existing as $ex) {
                $this->entityManager->remove($ex);
            }

            foreach ($data['items'] as $it) {
                $si = new ServiceItem();
                $si->setServiceRecord($service);
                $si->setType($it['type'] ?? 'part');
                $si->setDescription($it['description'] ?? null);
                $si->setCost($it['cost'] ?? '0.00');
                $si->setQuantity(isset($it['quantity']) ? (int)$it['quantity'] : 1);
                $this->entityManager->persist($si);
            }
        }
    }
}
