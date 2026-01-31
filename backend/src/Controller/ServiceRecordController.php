<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ServiceRecord;
use App\Entity\ServiceItem;
use App\Entity\Vehicle;
use App\Entity\Part;
use App\Entity\Consumable;
use App\Entity\MotRecord;
use App\Entity\Attachment;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\RepairCostCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
class ServiceRecordController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private RepairCostCalculator $repairCostCalculator,
        private ValidatorInterface $validator
    ) {
    }

    private function getUserEntity(): ?User
    {
        $user = $this->getUser();
        return $user instanceof User ? $user : null;
    }

    #[Route('/service-records', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUserEntity();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $vehicleId = $request->query->get('vehicleId');
        if ($vehicleId && !is_numeric($vehicleId)) {
            return new JsonResponse(['error' => 'Invalid vehicle ID'], 400);
        }

        if ($vehicleId) {
            $vehicleId = (int)$vehicleId;
            $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($vehicleId);
            if (!$vehicle || (!$this->isAdminForUser($user) && $vehicle->getOwner()->getId() !== $user->getId())) {
                return new JsonResponse(['error' => 'Vehicle not found'], 404);
            }

            // Eager load service records with all relationships
            $serviceRecords = $this->entityManager->createQueryBuilder()
                ->select('s', 'v', 'mot', 'att', 'items')
                ->from(ServiceRecord::class, 's')
                ->leftJoin('s.vehicle', 'v')
                ->leftJoin('s.motRecord', 'mot')
                ->leftJoin('s.receiptAttachment', 'att')
                ->leftJoin('s.items', 'items')
                ->where('s.vehicle = :vehicle')
                ->setParameter('vehicle', $vehicle)
                ->orderBy('s.serviceDate', 'DESC')
                ->getQuery()
                ->getResult();
        } else {
            $vehicleRepo = $this->entityManager->getRepository(Vehicle::class);
            $vehicles = $this->isAdminForUser($user) ? $vehicleRepo->findAll() : $vehicleRepo->findBy(['owner' => $user]);

            if (empty($vehicles)) {
                return new JsonResponse([]);
            }

            // Eager load service records with all relationships
            $qb = $this->entityManager->createQueryBuilder()
                ->select('s', 'v', 'mot', 'att', 'items')
                ->from(ServiceRecord::class, 's')
                ->leftJoin('s.vehicle', 'v')
                ->leftJoin('s.motRecord', 'mot')
                ->leftJoin('s.receiptAttachment', 'att')
                ->leftJoin('s.items', 'items')
                ->where('s.vehicle IN (:vehicles)')
                ->setParameter('vehicles', $vehicles)
                ->orderBy('s.serviceDate', 'DESC');

            $serviceRecords = $qb->getQuery()->getResult();
        }

        return new JsonResponse(array_map(fn($svc) => $this->serializeServiceRecord($svc), $serviceRecords));
    }

    #[Route('/service-records/{id}', methods: ['GET'])]
    public function get(int|string $id): JsonResponse
    {
        if (!is_numeric($id) || (int)$id <= 0) {
            return new JsonResponse(['error' => 'Invalid service record ID'], 400);
        }

        $id = (int) $id;
        $serviceRecord = $this->entityManager->getRepository(ServiceRecord::class)->find($id);
        $user = $this->getUserEntity();

        if (!$serviceRecord || !$user || (!$this->isAdminForUser($user) && $serviceRecord->getVehicle()->getOwner()->getId() !== $user->getId())) {
            return new JsonResponse(['error' => 'Service record not found'], 404);
        }

        return new JsonResponse($this->serializeServiceRecord($serviceRecord, true));
    }

    #[Route('/service-records', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUserEntity();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(['error' => 'Invalid JSON payload'], 400);
        }

        // Validate required fields
        if (!isset($data['vehicleId']) || !isset($data['serviceDate'])) {
            return new JsonResponse(['error' => 'Missing required fields: vehicleId and serviceDate are required'], 400);
        }

        if (!is_numeric($data['vehicleId']) || (int)$data['vehicleId'] <= 0) {
            return new JsonResponse(['error' => 'Invalid vehicle ID'], 400);
        }

        $vehicle = $this->entityManager->getRepository(Vehicle::class)->find((int)$data['vehicleId']);
        if (!$vehicle || (!$this->isAdminForUser($user) && $vehicle->getOwner()->getId() !== $user->getId())) {
            return new JsonResponse(['error' => 'Vehicle not found'], 404);
        }

        try {
            $serviceRecord = new ServiceRecord();
            $serviceRecord->setVehicle($vehicle);
            $this->updateServiceRecordFromData($serviceRecord, $data);

            // Validate entity
            $errors = $this->validator->validate($serviceRecord);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
                }
                return new JsonResponse(['error' => 'Validation failed', 'details' => $errorMessages], 400);
            }

            $this->entityManager->persist($serviceRecord);
            $this->entityManager->flush();

            return new JsonResponse($this->serializeServiceRecord($serviceRecord), 201);
        } catch (\Exception $e) {
            $this->logger->error('Failed to create service record', ['exception' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Failed to create service record'], 500);
        }
    }

    #[Route('/service-records/{id}', methods: ['PUT'])]
    public function update(int|string $id, Request $request): JsonResponse
    {
        if (!is_numeric($id) || (int)$id <= 0) {
            return new JsonResponse(['error' => 'Invalid service record ID'], 400);
        }

        $id = (int) $id;
        $serviceRecord = $this->entityManager->getRepository(ServiceRecord::class)->find($id);
        $user = $this->getUserEntity();

        if (!$serviceRecord || !$user || (!$this->isAdminForUser($user) && $serviceRecord->getVehicle()->getOwner()->getId() !== $user->getId())) {
            return new JsonResponse(['error' => 'Service record not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(['error' => 'Invalid JSON payload'], 400);
        }

        $this->entityManager->beginTransaction();
        try {
            $this->logger->info('ServiceRecord update payload', ['id' => $id, 'data' => $data]);
            $prevMotId = $serviceRecord->getMotRecord()?->getId();

            $this->updateServiceRecordFromData($serviceRecord, $data);

            // Validate entity
            $errors = $this->validator->validate($serviceRecord);
            if (count($errors) > 0) {
                $this->entityManager->rollback();
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
                }
                return new JsonResponse(['error' => 'Validation failed', 'details' => $errorMessages], 400);
            }

            $this->entityManager->flush();

            // Recalculate repairCost for affected MOTs
            $newMotId = $serviceRecord->getMotRecord()?->getId();
            if ($prevMotId && $prevMotId !== $newMotId) {
                $prevMot = $this->entityManager->getRepository(MotRecord::class)->find($prevMotId);
                if ($prevMot) {
                    $this->repairCostCalculator->recalculateAndPersist($prevMot);
                }
            }
            if ($newMotId) {
                $newMot = $this->entityManager->getRepository(MotRecord::class)->find($newMotId);
                if ($newMot) {
                    $this->repairCostCalculator->recalculateAndPersist($newMot);
                }
            }

            $this->entityManager->commit();
            return new JsonResponse($this->serializeServiceRecord($serviceRecord));
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            $this->logger->error('Error updating service record', ['exception' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Failed to update service record'], 500);
        }
    }

    #[Route('/service-records/{id}', methods: ['DELETE'])]
    public function delete(int|string $id): JsonResponse
    {
        if (!is_numeric($id) || (int)$id <= 0) {
            return new JsonResponse(['error' => 'Invalid service record ID'], 400);
        }

        $id = (int) $id;
        $serviceRecord = $this->entityManager->getRepository(ServiceRecord::class)->find($id);
        $user = $this->getUserEntity();

        if (!$serviceRecord || !$user || (!$this->isAdminForUser($user) && $serviceRecord->getVehicle()->getOwner()->getId() !== $user->getId())) {
            return new JsonResponse(['error' => 'Service record not found'], 404);
        }

        $this->entityManager->beginTransaction();
        try {
            $motId = $serviceRecord->getMotRecord()?->getId();

            $this->entityManager->remove($serviceRecord);
            $this->entityManager->flush();

            if ($motId) {
                $mot = $this->entityManager->getRepository(MotRecord::class)->find($motId);
                if ($mot) {
                    $this->repairCostCalculator->recalculateAndPersist($mot);
                }
            }

            $this->entityManager->commit();
            return new JsonResponse(['message' => 'Service record deleted']);
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            $this->logger->error('Error deleting service record', ['exception' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Failed to delete service record'], 500);
        }
    }

    #[Route('/service-records/{id}/items', methods: ['GET'])]
    public function getItems(int|string $id): JsonResponse
    {
        if (!is_numeric($id) || (int)$id <= 0) {
            return new JsonResponse(['error' => 'Invalid service record ID'], 400);
        }

        $id = (int) $id;
        $serviceRecord = $this->entityManager->getRepository(ServiceRecord::class)->find($id);
        $user = $this->getUserEntity();

        if (!$serviceRecord || !$user || (!$this->isAdminForUser($user) && $serviceRecord->getVehicle()->getOwner()->getId() !== $user->getId())) {
            return new JsonResponse(['error' => 'Service record not found'], 404);
        }

        $parts = $this->entityManager->getRepository(Part::class)
            ->findBy(['serviceRecord' => $serviceRecord]);

        $consumables = $this->entityManager->getRepository(Consumable::class)
            ->findBy(['serviceRecord' => $serviceRecord]);

        $serializedConsumables = array_map(fn($c) => $this->serializeConsumable($c), $consumables);
        $serviceData = $this->serializeServiceRecord($serviceRecord, true);

        // Normalize items list
        $serviceData['items'] = $serviceData['items'] ?? [];

        // Build a set of consumableIds already linked to items
        $linkedConsumableIds = [];
        foreach ($serviceData['items'] as $it) {
            if (isset($it['consumableId'])) {
                $linkedConsumableIds[(int)$it['consumableId']] = true;
            }
        }

        // Append any serializedConsumables that weren't associated with an item
        foreach ($serializedConsumables as $c) {
            $cid = (int)$c['id'];
            if (isset($linkedConsumableIds[$cid])) {
                continue;
            }

            $quantity = $c['quantity'] ?? 1;
            $cost = (string)($c['cost'] ?? '0.00');
            $total = $this->calculateTotal($cost, (string)$quantity);

            $serviceData['items'][] = [
                'id' => null,
                'type' => 'consumable',
                'description' => $c['description'] ?? '',
                'cost' => $cost,
                'quantity' => $quantity,
                'total' => $total,
                'consumableId' => $cid,
            ];
        }

        $serializedParts = array_map(fn($p) => $this->serializePart($p), $parts);

        return new JsonResponse([
            'serviceRecord' => $serviceData,
            'parts' => $serializedParts,
            'consumables' => $serializedConsumables,
        ]);
    }

    private function isAdminForUser(?User $user): bool
    {
        if (!$user) {
            return false;
        }
        $roles = $user->getRoles() ?: [];
        return in_array('ROLE_ADMIN', $roles, true);
    }

    private function serializeServiceRecord(ServiceRecord $service, bool $detailed = false): array
    {
        // Initialize cost variables with defaults or explicit entity values
        $laborCost = $service->getLaborCost() ?? '0.00';
        $partsCost = $service->getPartsCost() ?? '0.00';
        $consumablesCost = $service->getConsumablesCost() ?? null;

        // If itemised entries exist, compute costs from them when no explicit consumablesCost provided
        $items = $service->getItems();
        if (count($items) > 0) {
            $laborCost = method_exists($service, 'sumItemsByType')
                ? $service->sumItemsByType('labour')
                : $laborCost;
            $partsCost = method_exists($service, 'sumItemsByType')
                ? $service->sumItemsByType('part')
                : $partsCost;
            if ($consumablesCost === null) {
                $consumablesCost = method_exists($service, 'sumItemsByType')
                    ? $service->sumItemsByType('consumable')
                    : '0.00';
            }
        }

        // Ensure consumablesCost is always a string for serialization
        $consumablesCost = $consumablesCost ?? '0.00';

        $data = [
            'id' => $service->getId(),
            'vehicleId' => $service->getVehicle()->getId(),
            'serviceDate' => $service->getServiceDate()?->format('Y-m-d'),
            'serviceType' => $service->getServiceType(),
            'motRecordId' => $service->getMotRecord()?->getId(),
            'motTestNumber' => $service->getMotRecord()?->getMotTestNumber(),
            'motTestDate' => $service->getMotRecord()?->getTestDate()?->format('Y-m-d'),
            'laborCost' => $laborCost,
            'partsCost' => $partsCost,
            'consumablesCost' => $consumablesCost,
            'totalCost' => $service->getTotalCost() ?? '0.00',
            'mileage' => $service->getMileage(),
            'serviceProvider' => $service->getServiceProvider(),
            'workPerformed' => $service->getWorkPerformed(),
            'additionalCosts' => $service->getAdditionalCosts(),
            'nextServiceDate' => $service->getNextServiceDate()?->format('Y-m-d'),
            'nextServiceMileage' => $service->getNextServiceMileage(),
            'notes' => $service->getNotes(),
            'receiptAttachmentId' => $service->getReceiptAttachment()?->getId(),
            'createdAt' => $service->getCreatedAt()?->format('c'),
        ];

        // Include items when detailed or available
        if ($detailed || count($items) > 0) {
            $data['items'] = [];
            foreach ($items as $it) {
                $itemData = $this->serializeItem($it);
                $data['items'][] = $itemData;
            }
        }

        return $data;
    }

    private function serializePart(Part $part): array
    {
        return [
            'id' => $part->getId(),
            'description' => $part->getDescription(),
            'price' => $part->getPrice(),
            'quantity' => $part->getQuantity(),
            'cost' => $part->getCost(),
            'partCategory' => $part->getPartCategory() ? [
                'id' => $part->getPartCategory()->getId(),
                'name' => $part->getPartCategory()->getName(),
            ] : null,
        ];
    }

    private function serializeConsumable(Consumable $consumable): array
    {
        return [
            'id' => $consumable->getId(),
            'description' => $consumable->getDescription(),
            'cost' => $consumable->getCost(),
            'quantity' => $consumable->getQuantity() ?? 1,
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
            'consumableId' => $item->getConsumable()?->getId(),
            'partId' => $item->getPart()?->getId(),
        ];
    }

    private function updateServiceRecordFromData(ServiceRecord $service, array $data): void
    {
        if (isset($data['serviceDate'])) {
            try {
                $service->setServiceDate(new \DateTime($data['serviceDate']));
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Invalid service date format');
            }
        }

        if (isset($data['serviceType'])) {
            $service->setServiceType($data['serviceType']);
        }

        if (isset($data['laborCost'])) {
            if (!is_numeric($data['laborCost']) || (float)$data['laborCost'] < 0) {
                throw new \InvalidArgumentException('Invalid labor cost');
            }
            $service->setLaborCost($data['laborCost']);
        }

        if (isset($data['partsCost'])) {
            if (!is_numeric($data['partsCost']) || (float)$data['partsCost'] < 0) {
                throw new \InvalidArgumentException('Invalid parts cost');
            }
            $service->setPartsCost($data['partsCost']);
        }

        if (array_key_exists('consumablesCost', $data)) {
            if ($data['consumablesCost'] === null || $data['consumablesCost'] === '') {
                $service->setConsumablesCost(null);
            } else {
                if (!is_numeric($data['consumablesCost']) || (float)$data['consumablesCost'] < 0) {
                    throw new \InvalidArgumentException('Invalid consumables cost');
                }
                $service->setConsumablesCost($data['consumablesCost']);
            }
        }

        if (isset($data['mileage'])) {
            if (!is_numeric($data['mileage']) || (int)$data['mileage'] < 0) {
                throw new \InvalidArgumentException('Invalid mileage');
            }
            $service->setMileage($data['mileage']);
        }

        if (isset($data['serviceProvider'])) {
            $service->setServiceProvider($data['serviceProvider']);
        }

        if (isset($data['workPerformed'])) {
            $service->setWorkPerformed($data['workPerformed']);
        }

        if (isset($data['additionalCosts'])) {
            if (!is_numeric($data['additionalCosts']) || (float) $data['additionalCosts'] < 0) {
                throw new \InvalidArgumentException('Invalid additional costs');
            }
            $service->setAdditionalCosts($data['additionalCosts']);
        }

        if (array_key_exists('nextServiceDate', $data)) {
            if ($data['nextServiceDate'] === null || $data['nextServiceDate'] === '') {
                $service->setNextServiceDate(null);
            } else {
                try {
                    $service->setNextServiceDate(new \DateTime($data['nextServiceDate']));
                } catch (\Exception $e) {
                    throw new \InvalidArgumentException('Invalid next service date format');
                }
            }
        }

        if (array_key_exists('nextServiceMileage', $data)) {
            if ($data['nextServiceMileage'] === null || $data['nextServiceMileage'] === '') {
                $service->setNextServiceMileage(null);
            } elseif (!is_numeric($data['nextServiceMileage']) || (int) $data['nextServiceMileage'] < 0) {
                throw new \InvalidArgumentException('Invalid next service mileage');
            } else {
                $service->setNextServiceMileage((int) $data['nextServiceMileage']);
            }
        }

        if (isset($data['notes'])) {
            $service->setNotes($data['notes']);
        }

        if (array_key_exists('receiptAttachmentId', $data)) {
            $this->logger->info('ServiceRecord receiptAttachmentId present in payload', [
                'id' => $service->getId(),
                'receiptAttachmentId' => $data['receiptAttachmentId']
            ]);

            if ($data['receiptAttachmentId'] === null || $data['receiptAttachmentId'] === '') {
                $service->setReceiptAttachment(null);
            } else {
                $att = $this->entityManager->getRepository(Attachment::class)->find($data['receiptAttachmentId']);
                if ($att) {
                    $service->setReceiptAttachment($att);
                } else {
                    $service->setReceiptAttachment(null);
                    $this->logger->warning('Attachment not found', ['attachmentId' => $data['receiptAttachmentId']]);
                }
            }
        }

        if (array_key_exists('motRecordId', $data)) {
            $this->logger->info('ServiceRecord motRecordId present in payload', [
                'id' => $service->getId(),
                'motRecordId' => $data['motRecordId']
            ]);

            if ($data['motRecordId'] === null || $data['motRecordId'] === '') {
                $service->setMotRecord(null);
                $this->logger->info('ServiceRecord disassociated from MOT (explicit null)', [
                    'serviceId' => $service->getId()
                ]);
            } else {
                $motId = is_numeric($data['motRecordId']) ? (int)$data['motRecordId'] : $data['motRecordId'];
                $mot = $this->entityManager->getRepository(MotRecord::class)->find($motId);
                if ($mot) {
                    $service->setMotRecord($mot);
                    $this->logger->info('ServiceRecord associated with MOT', [
                        'serviceId' => $service->getId(),
                        'motId' => $mot->getId()
                    ]);
                } else {
                    $service->setMotRecord(null);
                    $this->logger->info('ServiceRecord disassociated from MOT (not found)', [
                        'serviceId' => $service->getId(),
                        'motId' => $motId
                    ]);
                }
            }
        }

        // Handle itemised entries (parts / labour / consumables)
        if (isset($data['items']) && is_array($data['items'])) {
            // Remove existing items
            $existing = $this->entityManager->getRepository(ServiceItem::class)
                ->findBy(['serviceRecord' => $service]);
            foreach ($existing as $ex) {
                $this->entityManager->remove($ex);
            }

            // Pre-load all consumables and parts to avoid N queries in loop
            $consumableIds = array_filter(array_column($data['items'], 'consumableId'));
            $partIds = array_filter(array_column($data['items'], 'partId'));

            $consumablesMap = [];
            if (!empty($consumableIds)) {
                $consumables = $this->entityManager->getRepository(Consumable::class)
                    ->findBy(['id' => $consumableIds]);
                foreach ($consumables as $c) {
                    $consumablesMap[$c->getId()] = $c;
                }
            }

            $partsMap = [];
            if (!empty($partIds)) {
                $parts = $this->entityManager->getRepository(Part::class)
                    ->findBy(['id' => $partIds]);
                foreach ($parts as $p) {
                    $partsMap[$p->getId()] = $p;
                }
            }

            foreach ($data['items'] as $index => $it) {
                if (!isset($it['type']) || !isset($it['description']) || !isset($it['cost'])) {
                    throw new \InvalidArgumentException("Item at index $index is missing required fields");
                }

                if (!is_numeric($it['cost']) || (float)$it['cost'] < 0) {
                    throw new \InvalidArgumentException("Invalid cost in item at index $index");
                }

                $quantity = isset($it['quantity']) ? (string)$it['quantity'] : '1';
                if (!is_numeric($quantity) || (float)$quantity <= 0) {
                    throw new \InvalidArgumentException("Invalid quantity in item at index $index");
                }

                $si = new ServiceItem();
                $si->setServiceRecord($service);
                $si->setType($it['type']);
                $si->setDescription($it['description']);
                $si->setCost($it['cost']);
                $si->setQuantity($quantity);
                // If a consumableId is provided, link the ServiceItem to the Consumable entity
                if (isset($it['consumableId']) && is_numeric($it['consumableId']) && (int)$it['consumableId'] > 0) {
                    $consumable = $consumablesMap[(int)$it['consumableId']] ?? null;
                    if ($consumable) {
                        $si->setConsumable($consumable);
                        $consumable->setServiceRecord($service);
                    } else {
                        // If provided id not found, leave consumable null but log
                        $this->logger->warning('Linked consumable not found', ['consumableId' => $it['consumableId']]);
                    }
                } else {
                    $si->setConsumable(null);
                }

                if (isset($it['partId']) && is_numeric($it['partId']) && (int) $it['partId'] > 0) {
                    $part = $partsMap[(int)$it['partId']] ?? null;
                    if ($part) {
                        $part->setServiceRecord($service);
                        $si->setPart($part);
                    } else {
                        $this->logger->warning('Linked part not found', ['partId' => $it['partId']]);
                    }
                }

                $this->entityManager->persist($si);
            }
        }
    }

    private function calculateTotal(string $cost, string $quantity): string
    {
        if (function_exists('bcmul')) {
            return bcmul($cost, $quantity, 2);
        }

        // Fallback calculation
        $result = (float)$cost * (float)$quantity;
        return number_format($result, 2, '.', '');
    }

    #[Route('/service-items/{id}', methods: ['PATCH'])]
    public function updateServiceItem(Request $request, int|string $id): JsonResponse
    {
        if (!is_numeric($id) || (int)$id <= 0) {
            return new JsonResponse(['error' => 'Invalid service item ID'], 400);
        }

        $id = (int)$id;
        $item = $this->entityManager->getRepository(ServiceItem::class)->find($id);
        $user = $this->getUserEntity();

        if (!$item || !$user) {
            return new JsonResponse(['error' => 'Service item not found'], 404);
        }

        $service = $item->getServiceRecord();
        if (!$service || (!$this->isAdminForUser($user) && $service->getVehicle()->getOwner()->getId() !== $user->getId())) {
            return new JsonResponse(['error' => 'Not authorized to update this item'], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON payload'], 400);
        }

        try {
            if (isset($data['cost'])) {
                $item->setCost($data['cost']);
            }
            if (isset($data['description'])) {
                $item->setDescription($data['description']);
            }
            if (isset($data['quantity'])) {
                $item->setQuantity($data['quantity']);
            }

            $this->entityManager->persist($item);
            $this->entityManager->flush();

            return new JsonResponse([
                'message' => 'Service item updated',
                'item' => $this->serializeItem($item)
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Error updating service item', ['exception' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Failed to update service item'], 500);
        }
    }

    #[Route('/service-items/{id}', methods: ['DELETE'])]
    public function deleteServiceItem(Request $request, int|string $id): JsonResponse
    {
        if (!is_numeric($id) || (int)$id <= 0) {
            return new JsonResponse(['error' => 'Invalid service item ID'], 400);
        }

        $id = (int)$id;
        $removeLinked = (int) $request->query->get('removeLinked', 0);

        $item = $this->entityManager->getRepository(ServiceItem::class)->find($id);
        $user = $this->getUserEntity();

        if (!$item || !$user) {
            return new JsonResponse(['error' => 'Service item not found'], 404);
        }

        $service = $item->getServiceRecord();
        if (!$service || (!$this->isAdminForUser($user) && $service->getVehicle()->getOwner()->getId() !== $user->getId())) {
            return new JsonResponse(['error' => 'Not authorized to delete this item'], 403);
        }

        $this->entityManager->beginTransaction();
        try {
            $consumable = $item->getConsumable();
            $part = $item->getPart();

            // If disassociate requested (removeLinked==0), clear serviceRecord on linked entities
            if ($removeLinked === 0) {
                if ($consumable && $consumable->getServiceRecord() && $consumable->getServiceRecord()->getId() === $service->getId()) {
                    $consumable->setServiceRecord(null);
                    $this->entityManager->persist($consumable);
                }
                if ($part && $part->getServiceRecord() && $part->getServiceRecord()->getId() === $service->getId()) {
                    $part->setServiceRecord(null);
                    $this->entityManager->persist($part);
                }
            }

            // Remove the service item
            $this->entityManager->remove($item);

            // If delete requested (removeLinked==1), also remove linked entities if they are associated only with this service
            if ($removeLinked === 1) {
                if ($consumable && ($consumable->getServiceRecord() === null || $consumable->getServiceRecord()->getId() === $service->getId())) {
                    $this->entityManager->remove($consumable);
                }
                if ($part && ($part->getServiceRecord() === null || $part->getServiceRecord()->getId() === $service->getId())) {
                    $this->entityManager->remove($part);
                }
            }

            $this->entityManager->flush();

            $motId = $service->getMotRecord()?->getId();
            if ($motId) {
                $mot = $this->entityManager->getRepository(MotRecord::class)->find($motId);
                if ($mot) {
                    $this->repairCostCalculator->recalculateAndPersist($mot);
                }
            }

            $this->entityManager->commit();
            return new JsonResponse(['message' => 'Service item deleted']);
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            $this->logger->error('Error deleting service item', ['exception' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Failed to delete service item'], 500);
        }
    }
}
