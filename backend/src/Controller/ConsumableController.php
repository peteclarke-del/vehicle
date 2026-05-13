<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Consumable;
use App\Entity\ConsumableType;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\VehicleAssignment;
use App\Entity\VehicleType;
use App\Entity\ServiceItem;
use App\Entity\StockItem;
use App\Service\ReceiptOcrService;
use App\Service\UrlScraperService;
use Psr\Log\LoggerInterface;
use App\Service\RepairCostCalculator;
use App\Service\EntitySerializerService;
use App\Service\AttachmentLinkingService;
use App\Service\StockLedgerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Controller\Trait\UserSecurityTrait;
use App\Controller\Trait\JsonValidationTrait;

#[Route('/api/consumables')]

/**
 * class ConsumableController
 */
class ConsumableController extends AbstractController
{
    use UserSecurityTrait;
    use JsonValidationTrait;

    /**
     * function __construct
     *
     * @param EntityManagerInterface $entityManager
     * @param ReceiptOcrService $ocrService
     * @param UrlScraperService $scraperService
     * @param LoggerInterface $logger
     * @param RepairCostCalculator $repairCostCalculator
     * @param EntitySerializerService $serializer
     * @param AttachmentLinkingService $attachmentLinkingService
     *
     * @return void
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ReceiptOcrService $ocrService,
        private UrlScraperService $scraperService,
        private LoggerInterface $logger,
        private RepairCostCalculator $repairCostCalculator,
        private EntitySerializerService $serializer,
        private AttachmentLinkingService $attachmentLinkingService,
        private StockLedgerService $stockLedgerService
    ) {
    }

    #[Route('', name: 'api_consumables_list', methods: ['GET'])]

    /**
     * function list
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $vehicleId = $request->query->get('vehicleId');
        $unassociated = $request->query->get('unassociated') === 'true';
        $generalStock = $request->query->get('generalStock') === 'true';

        if ($generalStock) {
            // Return only general stock (no vehicle) consumables owned by this user
            $qb = $this->entityManager->createQueryBuilder()
                ->select('c')
                ->from(Consumable::class, 'c')
                ->where('c.vehicle IS NULL');
            if (!$this->isAdminForUser($user)) {
                $qb->andWhere('c.user = :user')->setParameter('user', $user);
            }
            if ($unassociated) {
                $qb->andWhere('c.serviceRecord IS NULL')
                   ->andWhere('c.motRecord IS NULL');
            }
            $qb->orderBy('c.lastChanged', 'DESC');
            $consumables = $qb->getQuery()->getResult();
        } elseif ($vehicleId) {
            $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($vehicleId);
            $assignment = null;
            if (!$this->isAdminForUser($user) && $vehicle) {
                $assignment = $this->entityManager->getRepository(VehicleAssignment::class)
                    ->findOneBy(['assignedTo' => $user, 'vehicle' => $vehicle]);
            }
            if (
                !$vehicle
                || (!$this->isAdminForUser($user)
                    && $vehicle->getOwner()->getId() !== $user->getId()
                    && (!$assignment || !$assignment->canView()))
            ) {
                return $this->json(['error' => 'Vehicle not found'], 404);
            }

            if ($unassociated) {
                // Include both vehicle-specific and general stock unassociated consumables
                $qb = $this->entityManager->createQueryBuilder()
                    ->select('c')
                    ->from(Consumable::class, 'c')
                    ->where('c.vehicle = :vehicle')
                    ->andWhere('c.serviceRecord IS NULL')
                    ->andWhere('c.motRecord IS NULL')
                    ->setParameter('vehicle', $vehicle)
                    ->orderBy('c.lastChanged', 'DESC');
                $vehicleConsumables = $qb->getQuery()->getResult();

                $sqb = $this->entityManager->createQueryBuilder()
                    ->select('c')
                    ->from(Consumable::class, 'c')
                    ->where('c.vehicle IS NULL')
                    ->andWhere('c.serviceRecord IS NULL')
                    ->andWhere('c.motRecord IS NULL')
                    ->orderBy('c.lastChanged', 'DESC');
                if (!$this->isAdminForUser($user)) {
                    $sqb->andWhere('c.user = :user')->setParameter('user', $user);
                }
                $stockConsumables = $sqb->getQuery()->getResult();

                $consumables = array_merge($vehicleConsumables, $stockConsumables);
            } else {
                $consumables = $this->entityManager->getRepository(Consumable::class)
                    ->findBy(['vehicle' => $vehicle]);
            }
        } else {
            // Fetch consumables for all vehicles the user can see, plus general stock
            $vehicleRepo = $this->entityManager->getRepository(Vehicle::class);
            if ($this->isAdminForUser($user)) {
                $vehicles = $vehicleRepo->findAll();
            } else {
                $ownVehicles = $vehicleRepo->findBy(['owner' => $user]);
                $assignments = $this->entityManager->getRepository(VehicleAssignment::class)
                    ->findBy(['assignedTo' => $user]);
                $ownIds = array_map(fn($v) => $v->getId(), $ownVehicles);
                $assignedVehicles = [];
                foreach ($assignments as $a) {
                    if ($a->canView() && !in_array($a->getVehicle()->getId(), $ownIds, true)) {
                        $assignedVehicles[] = $a->getVehicle();
                    }
                }
                $vehicles = array_merge($ownVehicles, $assignedVehicles);
            }

            if (!$this->isAdminForUser($user)) {
                $vehicleConsumables = [];
                if (!empty($vehicles)) {
                    $vqb = $this->entityManager->createQueryBuilder()
                        ->select('c')
                        ->from(Consumable::class, 'c')
                        ->where('c.vehicle IN (:vehicles)')
                        ->setParameter('vehicles', $vehicles);
                    if ($unassociated) {
                        $vqb->andWhere('c.serviceRecord IS NULL')
                            ->andWhere('c.motRecord IS NULL');
                    }
                    $vehicleConsumables = $vqb->orderBy('c.lastChanged', 'DESC')->getQuery()->getResult();
                }

                $sqb = $this->entityManager->createQueryBuilder()
                    ->select('c')
                    ->from(Consumable::class, 'c')
                    ->where('c.vehicle IS NULL')
                    ->andWhere('c.user = :user')
                    ->setParameter('user', $user);
                if ($unassociated) {
                    $sqb->andWhere('c.serviceRecord IS NULL')
                        ->andWhere('c.motRecord IS NULL');
                }
                $stockConsumables = $sqb->orderBy('c.lastChanged', 'DESC')->getQuery()->getResult();

                $consumables = array_merge($vehicleConsumables, $stockConsumables);
            } else {
                $qb = $this->entityManager->createQueryBuilder()
                    ->select('c')
                    ->from(Consumable::class, 'c');

                if (!empty($vehicles)) {
                    $qb->where(
                        $qb->expr()->orX(
                            $qb->expr()->in('c.vehicle', ':vehicles'),
                            $qb->expr()->isNull('c.vehicle')
                        )
                    )->setParameter('vehicles', $vehicles);
                } else {
                    $qb->where('c.vehicle IS NULL');
                }

                if ($unassociated) {
                    $qb->andWhere('c.serviceRecord IS NULL')
                       ->andWhere('c.motRecord IS NULL');
                }
                $qb->orderBy('c.lastChanged', 'DESC');
                $consumables = $qb->getQuery()->getResult();
            }
        }

        $data = array_map(fn($c) => $this->serializer->serializeConsumable($c), $consumables);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'api_consumables_get', methods: ['GET'])]

    /**
     * function get
     *
     * @param mixed $id
     *
     * @return JsonResponse
     */
    public function get(int|string $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $id = (int) $id;
        $consumable = $this->entityManager->getRepository(Consumable::class)->find($id);

        if (!$consumable || (!$this->isAdminForUser($user) && !$this->consumableBelongsToUser($consumable, $user))) {
            return $this->json(['error' => 'Consumable not found'], 404);
        }

        return $this->json($this->serializer->serializeConsumable($consumable));
    }

    #[Route('', name: 'api_consumables_create', methods: ['POST'])]

    /**
     * function create
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $validation = $this->validateJsonRequest($request);
        if ($validation['error']) {
            return $validation['error'];
        }
        $data = $validation['data'];

        $vehicle = null;
        if (!empty($data['vehicleId'])) {
            $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($data['vehicleId']);
            if (!$vehicle || (!$this->isAdminForUser($user) && $vehicle->getOwner()->getId() !== $user->getId())) {
                return $this->json(['error' => 'Vehicle not found'], 404);
            }
        }

        $stockItem = null;
        $stockConsumeQty = 0.0;
        if (!empty($data['stockItemId'])) {
            $stockItem = $this->entityManager->getRepository(StockItem::class)->find((int) $data['stockItemId']);
            if (!$stockItem || (!$this->isAdminForUser($user) && $stockItem->getUser()?->getId() !== $user->getId())) {
                return $this->json(['error' => 'Stock item not found'], 404);
            }
            if ($stockItem->getItemType() !== 'consumable') {
                return $this->json(['error' => 'Selected stock item is not a consumable'], 400);
            }
            if ($vehicle && $stockItem->getVehicleType() && $vehicle->getVehicleType() && $stockItem->getVehicleType()->getId() !== $vehicle->getVehicleType()->getId()) {
                return $this->json(['error' => 'Stock item does not match vehicle type'], 400);
            }

            $stockConsumeQty = (float) ($data['quantity'] ?? 1);
            if ($stockConsumeQty <= 0) {
                $stockConsumeQty = 1;
            }
            $available = (float) ($stockItem->getQuantity() ?? '0');
            if ($available < $stockConsumeQty) {
                return $this->json(['error' => 'Insufficient stock quantity'], 400);
            }

            $data['description'] = $data['description'] ?? $stockItem->getDescription() ?? $stockItem->getCategory();
            $data['partNumber'] = $data['partNumber'] ?? $stockItem->getPartNumber();
            $data['brand'] = $data['brand'] ?? $stockItem->getManufacturer();
            $data['supplier'] = $data['supplier'] ?? $stockItem->getSupplier();
            $data['cost'] = $data['cost'] ?? $stockItem->getPrice();
            $data['lastChanged'] = $data['lastChanged'] ?? $stockItem->getPurchaseDate()?->format('Y-m-d');
        }

        $consumableType = $this->resolveConsumableType($data, $vehicle);
        if (!$consumableType) {
            return $this->json(['error' => 'Consumable type not found'], 404);
        }

        $consumable = new Consumable();
        $consumable->setVehicle($vehicle);
        // For general stock consumables (no vehicle), track ownership via user
        if ($vehicle === null) {
            $consumable->setUser($user);
        }
        $consumable->setConsumableType($consumableType);
        
        // New consumables are always included in service cost by default.
        // Only consumables that were pre-existing (purchased separately) and later linked
        // to a service should have includedInServiceCost=false. That is handled by the
        // pre-bought logic in updateConsumableFromData for the update/link path.
        $consumable->setIncludedInServiceCost(true);
        
        // Ensure description is set. Accept legacy `name` too.
        $desc = $data['description'] ?? $data['name'] ?? $consumableType->getName() ?? null;
        $desc = is_string($desc) ? trim($desc) : $desc;
        if ($desc !== null && $desc !== '') {
            $consumable->setDescription((string) $desc);
        }
        // Do not force lastChanged: leave null unless explicitly provided
        $this->updateConsumableFromData($consumable, $data);

        $this->entityManager->persist($consumable);
        $this->entityManager->flush();

        if ($consumable->getVehicle() === null && $consumable->getUser() instanceof User) {
            $this->stockLedgerService->adjust(
                $consumable->getUser(),
                null,
                'consumable',
                $this->stockLedgerService->categoryForConsumable($consumable->getDescription() ?? '', $consumable->getConsumableType()?->getName()),
                $consumable->getSupplier(),
                (float) ($consumable->getQuantity() ?? '0')
            );
            $this->entityManager->flush();
        }

        if ($stockItem instanceof StockItem) {
            $remaining = max(0, (float) ($stockItem->getQuantity() ?? '0') - $stockConsumeQty);
            $stockItem->setQuantity(number_format($remaining, 2, '.', ''));
            $stockItem->touch();
            $this->entityManager->flush();
        }

        // Finalize attachment link after flush (entity now has ID)
        $this->attachmentLinkingService->finalizeAttachmentLink($consumable);
        $this->entityManager->flush();

        return $this->json($this->serializer->serializeConsumable($consumable), 201);
    }

    #[Route('/{id}', name: 'api_consumables_update', methods: ['PUT'])]

    /**
     * function update
     *
     * @param mixed $id
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function update(int|string $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $id = (int) $id;
        $consumable = $this->entityManager->getRepository(Consumable::class)->find($id);

        if (!$consumable || (!$this->isAdminForUser($user) && !$this->consumableBelongsToUser($consumable, $user))) {
            return $this->json(['error' => 'Consumable not found'], 404);
        }

        $validation = $this->validateJsonRequest($request);
        if ($validation['error']) {
            return $validation['error'];
        }
        $data = $validation['data'];
        $this->logger->info('Consumable update payload', ['id' => $id, 'data' => $data]);

        $wasStock = $consumable->getVehicle() === null;
        $oldStockUser = $consumable->getUser();
        $oldStockQty = (float) ($consumable->getQuantity() ?? '0');
        $oldStockCategory = $this->stockLedgerService->categoryForConsumable($consumable->getDescription() ?? '', $consumable->getConsumableType()?->getName());
        $oldStockSupplier = $consumable->getSupplier();

        if (array_key_exists('vehicleId', $data)) {
            if (!empty($data['vehicleId'])) {
                $vehicle = $this->entityManager->getRepository(Vehicle::class)->find((int) $data['vehicleId']);
                if (!$vehicle || (!$this->isAdminForUser($user) && $vehicle->getOwner()->getId() !== $user->getId())) {
                    return $this->json(['error' => 'Vehicle not found'], 404);
                }
                $consumable->setVehicle($vehicle);
                // Clear user field when moving to a vehicle (ownership inferred from vehicle)
                $consumable->setUser(null);
            } else {
                // Move to general stock
                $consumable->setVehicle(null);
                $consumable->setUser($user);
            }
        }

        $prevMotId = $consumable->getMotRecord()?->getId();
        $this->updateConsumableFromData($consumable, $data);
        $consumable->setUpdatedAt(new \DateTime());

        $serviceItem = $this->entityManager->getRepository(ServiceItem::class)
            ->findOneBy(['consumable' => $consumable]);
        if ($serviceItem) {
            // If consumable is not included in service cost (existing item purchased separately),
            // set ServiceItem cost to 0 to prevent double-counting
            $costToUse = $consumable->isIncludedInServiceCost() 
                ? $consumable->getCost() 
                : '0.00';
            $serviceItem->setCost($costToUse);
            $serviceItem->setQuantity($consumable->getQuantity() ?? 1);
            $serviceItem->setDescription($consumable->getDescription());
            $this->entityManager->persist($serviceItem);
        }

        try {
            $this->entityManager->flush();

            $isStock = $consumable->getVehicle() === null;
            if ($wasStock && !$isStock && $oldStockUser instanceof User) {
                $this->stockLedgerService->adjust($oldStockUser, null, 'consumable', $oldStockCategory, $oldStockSupplier, -$oldStockQty);
            } elseif (!$wasStock && $isStock && $consumable->getUser() instanceof User) {
                $this->stockLedgerService->adjust(
                    $consumable->getUser(),
                    null,
                    'consumable',
                    $this->stockLedgerService->categoryForConsumable($consumable->getDescription() ?? '', $consumable->getConsumableType()?->getName()),
                    $consumable->getSupplier(),
                    (float) ($consumable->getQuantity() ?? '0')
                );
            } elseif ($wasStock && $isStock && $consumable->getUser() instanceof User && $oldStockUser instanceof User) {
                $newQty = (float) ($consumable->getQuantity() ?? '0');
                $newCategory = $this->stockLedgerService->categoryForConsumable($consumable->getDescription() ?? '', $consumable->getConsumableType()?->getName());
                $newSupplier = $consumable->getSupplier();
                $sameBucket = $newCategory === $oldStockCategory && (string) ($newSupplier ?? '') === (string) ($oldStockSupplier ?? '') && $consumable->getUser()->getId() === $oldStockUser->getId();
                if ($sameBucket) {
                    $this->stockLedgerService->adjust($consumable->getUser(), null, 'consumable', $newCategory, $newSupplier, $newQty - $oldStockQty);
                } else {
                    $this->stockLedgerService->adjust($oldStockUser, null, 'consumable', $oldStockCategory, $oldStockSupplier, -$oldStockQty);
                    $this->stockLedgerService->adjust($consumable->getUser(), null, 'consumable', $newCategory, $newSupplier, $newQty);
                }
            }
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $this->logger->error('Error flushing Consumable update', ['exception' => $e->getMessage()]);
            return $this->json(['error' => 'Failed to update consumable: ' . $e->getMessage()], 500);
        }

        // Recalculate repairCost for affected MOTs
        $newMotId = $consumable->getMotRecord()?->getId();
        try {
            if ($prevMotId && $prevMotId !== $newMotId) {
                $prevMot = $this->entityManager->getRepository(\App\Entity\MotRecord::class)->find($prevMotId);
                if ($prevMot) {
                    $this->repairCostCalculator->recalculateAndPersist($prevMot);
                }
            }
            if ($newMotId) {
                $newMot = $this->entityManager->getRepository(\App\Entity\MotRecord::class)->find($newMotId);
                if ($newMot) {
                    $this->repairCostCalculator->recalculateAndPersist($newMot);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error recalculating repair cost after Consumable update', ['exception' => $e->getMessage()]);
        }

        return $this->json($this->serializer->serializeConsumable($consumable));
    }

    #[Route('/{id}', name: 'api_consumables_delete', methods: ['DELETE'])]

    /**
     * function delete
     *
     * @param mixed $id
     *
     * @return JsonResponse
     */
    public function delete(int|string $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $id = (int) $id;
        $consumable = $this->entityManager->getRepository(Consumable::class)->find($id);

        if (!$consumable || (!$this->isAdminForUser($user) && !$this->consumableBelongsToUser($consumable, $user))) {
            return $this->json(['error' => 'Consumable not found'], 404);
        }
        $motId = $consumable->getMotRecord()?->getId();

        if ($consumable->getVehicle() === null && $consumable->getUser() instanceof User) {
            $this->stockLedgerService->adjust(
                $consumable->getUser(),
                null,
                'consumable',
                $this->stockLedgerService->categoryForConsumable($consumable->getDescription() ?? '', $consumable->getConsumableType()?->getName()),
                $consumable->getSupplier(),
                -((float) ($consumable->getQuantity() ?? '0'))
            );
        }

        $this->entityManager->remove($consumable);
        $this->entityManager->flush();

        if ($motId) {
            try {
                $mot = $this->entityManager->getRepository(\App\Entity\MotRecord::class)->find($motId);
                if ($mot) {
                    $this->repairCostCalculator->recalculateAndPersist($mot);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Error recalculating repair cost after Consumable delete', ['exception' => $e->getMessage()]);
            }
        }

        return $this->json(['message' => 'Consumable deleted successfully']);
    }

    /**
     * function updateConsumableFromData
     *
     * @param Consumable $consumable
     * @param array $data
     *
     * @return void
     */
    private function updateConsumableFromData(Consumable $consumable, array $data): void
    {
        if (array_key_exists('consumableTypeId', $data) || !empty($data['consumableTypeName'])) {
            $resolved = $this->resolveConsumableType($data, $consumable->getVehicle());
            if ($resolved) {
                $consumable->setConsumableType($resolved);
            }
        }
        // Accept `description` from clients (and fallback to legacy `name`).
        if (isset($data['description'])) {
            $desc = is_string($data['description']) ? trim($data['description']) : $data['description'];
            if ($desc !== '' && $desc !== null) {
                $consumable->setDescription((string) $desc);
            }
        } elseif (isset($data['name'])) {
            $name = is_string($data['name']) ? trim($data['name']) : $data['name'];
            if ($name !== '' && $name !== null) {
                $consumable->setDescription((string) $name);
            }
        }
        if (isset($data['quantity'])) {
            $consumable->setQuantity($data['quantity']);
        }
        if (isset($data['brand'])) {
            $consumable->setBrand($data['brand']);
        }
        if (isset($data['partNumber'])) {
            $consumable->setPartNumber($data['partNumber']);
        }
        if (isset($data['supplier'])) {
            $consumable->setSupplier($data['supplier']);
        }
        if (isset($data['lastChanged'])) {
            if (!empty($data['lastChanged'])) {
                $consumable->setLastChanged(new \DateTime($data['lastChanged']));
            }
        }
        if (isset($data['mileageAtChange'])) {
            $consumable->setMileageAtChange($data['mileageAtChange']);
        }
        if (isset($data['replacementIntervalMiles'])) {
            $consumable->setReplacementIntervalMiles($data['replacementIntervalMiles']);
        }
        if (isset($data['nextReplacementMileage'])) {
            $consumable->setNextReplacementMileage($data['nextReplacementMileage']);
        }
        if (isset($data['cost'])) {
            $consumable->setCost($data['cost']);
        }
        if (isset($data['notes'])) {
            $consumable->setNotes($data['notes']);
        }
        if (array_key_exists('receiptAttachmentId', $data)) {
            $attachmentId = $data['receiptAttachmentId'];
            if ($attachmentId === null || $attachmentId === '' || $attachmentId === 0) {
                if ($consumable->getReceiptAttachment()) {
                    $this->attachmentLinkingService->unlinkAttachment($consumable->getReceiptAttachment(), $consumable);
                }
                $consumable->setReceiptAttachment(null);
            } else {
                $this->attachmentLinkingService->processReceiptAttachmentId(
                    (int) $attachmentId,
                    $consumable,
                    'consumable'
                );
            }
        }
        if (isset($data['productUrl'])) {
            $consumable->setProductUrl($data['productUrl']);
        }
        if (array_key_exists('motRecordId', $data)) {
            $this->logger->info('Consumable motRecordId present in payload', ['id' => $consumable->getId(), 'motRecordId' => $data['motRecordId']]);

            $motId = $data['motRecordId'];
            if (is_array($motId)) {
                $motId = $motId['id'] ?? $motId['motRecordId'] ?? null;
            }

            if ($motId === null || $motId === '' || $motId === 0 || $motId === '0') {
                $consumable->setMotRecord(null);
                $this->logger->info('Consumable disassociated from MOT (explicit)', ['consumableId' => $consumable->getId()]);
            } else {
                $motId = is_numeric($motId) ? (int)$motId : $motId;
                $mot = $this->entityManager->getRepository(\App\Entity\MotRecord::class)->find($motId);
                if ($mot) {
                    // Apply pre-bought logic for existing consumables being linked to a MOT for the first time,
                    // mirroring the same guard used for serviceRecordId linkage.
                    $isExistingConsumable = $consumable->getId() !== null;
                    $wasUnassociated = $consumable->getServiceRecord() === null && $consumable->getMotRecord() === null;
                    $consumable->setMotRecord($mot);
                    if ($isExistingConsumable && $wasUnassociated) {
                        $consumable->setIncludedInServiceCost(false);
                        $this->logger->info('Existing consumable linked to MOT (pre-bought)', ['consumableId' => $consumable->getId(), 'motId' => $mot->getId()]);
                    } else {
                        $this->logger->info('Consumable associated with MOT', ['consumableId' => $consumable->getId(), 'motId' => $mot->getId()]);
                    }
                } else {
                    $consumable->setMotRecord(null);
                    $this->logger->info('Consumable disassociated from MOT (not found)', ['consumableId' => $consumable->getId(), 'motId' => $motId]);
                }
            }
        }

        if (array_key_exists('serviceRecordId', $data)) {
            $this->logger->info('Consumable serviceRecordId present in payload', [
                'id' => $consumable->getId(),
                'serviceRecordId' => $data['serviceRecordId'],
            ]);

            $svcId = $data['serviceRecordId'];
            if (is_array($svcId)) {
                $svcId = $svcId['id'] ?? $svcId['serviceRecordId'] ?? null;
            }

            if ($svcId === null || $svcId === '' || $svcId === 0 || $svcId === '0') {
                $consumable->setServiceRecord(null);
                // Only mark as pre-bought when an EXISTING consumable is being explicitly
                // disassociated. New consumables (no ID yet) should keep their default of true.
                if ($consumable->getId() !== null) {
                    $consumable->setIncludedInServiceCost(false);
                }
                $this->logger->info('Consumable disassociated from Service (explicit)', [
                    'consumableId' => $consumable->getId(),
                ]);
            } else {
                $svcId = is_numeric($svcId) ? (int) $svcId : $svcId;
                $svc = $this->entityManager->getRepository(\App\Entity\ServiceRecord::class)->find($svcId);
                if ($svc) {
                    // Only apply pre-bought logic when UPDATING an existing consumable (has ID)
                    // For new consumables, create() method already sets includedInServiceCost correctly
                    $isExistingConsumable = $consumable->getId() !== null;
                    $wasUnassociated = $consumable->getServiceRecord() === null && $consumable->getMotRecord() === null;
                    $consumable->setServiceRecord($svc);
                    if ($isExistingConsumable && $wasUnassociated) {
                        // Existing consumable being linked for the first time = pre-bought
                        $consumable->setIncludedInServiceCost(false);
                        $this->logger->info('Existing consumable linked to Service (pre-bought)', [
                            'consumableId' => $consumable->getId(),
                            'serviceId' => $svc->getId(),
                        ]);
                    } else {
                        $this->logger->info('Consumable associated with Service', [
                            'consumableId' => $consumable->getId(),
                            'serviceId' => $svc->getId(),
                        ]);
                    }
                } else {
                    $consumable->setServiceRecord(null);
                    // Only set includedInServiceCost to false for EXISTING consumables
                    if ($consumable->getId() !== null) {
                        $consumable->setIncludedInServiceCost(false);
                    }
                    $this->logger->info('Consumable disassociated from Service (not found)', [
                        'consumableId' => $consumable->getId(),
                        'serviceId' => $svcId,
                    ]);
                }
            }
        }
    }

    /**
     * function resolveConsumableType
     *
     * @param array $data
     * @param Vehicle $vehicle
     *
     * @return ConsumableType
     */
    private function resolveConsumableType(array $data, ?Vehicle $vehicle = null): ?ConsumableType
    {
        $typeId = $data['consumableTypeId'] ?? null;
        if ($typeId === 'other') {
            $typeId = null;
        }

        if (!empty($typeId)) {
            $type = $this->entityManager->getRepository(ConsumableType::class)->find($typeId);
            if ($type) {
                return $type;
            }
        }

        if (!$vehicle && !empty($data['vehicleId'])) {
            $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($data['vehicleId']);
        }

        $vehicleType = $vehicle?->getVehicleType();
        if (!$vehicleType && !empty($data['vehicleTypeId'])) {
            $vehicleType = $this->entityManager->getRepository(VehicleType::class)->find((int) $data['vehicleTypeId']);
        }

        $typeName = $data['consumableTypeName'] ?? null;
        $typeName = is_string($typeName) ? trim($typeName) : null;
        if ($typeName === '') {
            $typeName = null;
        }

        if ($typeName) {
            $criteria = ['name' => $typeName];
            if ($vehicleType) {
                $criteria['vehicleType'] = $vehicleType;
            }

            $type = $this->entityManager->getRepository(ConsumableType::class)
                ->findOneBy($criteria);
            if (!$type) {
                $type = new ConsumableType();
                $type->setName($typeName);
                if ($vehicleType) {
                    $type->setVehicleType($vehicleType);
                } else {
                    $this->logger->warning('Cannot create consumable type without vehicle type', [
                        'consumableTypeName' => $typeName,
                        'vehicleId' => $vehicle?->getId(),
                    ]);
                    return null;
                }
                $this->entityManager->persist($type);
            }
            return $type;
        }

        return null;
    }

    #[Route('/scrape-url', name: 'api_consumables_scrape_url', methods: ['POST'])]

    /**
     * function scrapeUrl
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function scrapeUrl(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $validation = $this->validateJsonRequest($request);
        if ($validation['error']) {
            return $validation['error'];
        }
        $data = $validation['data'];
        $url = $data['url'] ?? null;

        if (!$url) {
            return $this->json(['error' => 'URL is required'], 400);
        }

        try {
            $scrapedData = $this->scraperService->scrapeProductDetails($url);

            if (empty($scrapedData)) {
                return $this->json(['error' => 'Could not scrape data from URL'], 400);
            }

            return $this->json($scrapedData);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to scrape URL: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Determine whether a consumable belongs to the given user (via vehicle owner or direct user ownership).
     */
    private function consumableBelongsToUser(\App\Entity\Consumable $consumable, User $user): bool
    {
        if ($consumable->getVehicle() !== null) {
            return $consumable->getVehicle()->getOwner()->getId() === $user->getId();
        }
        return $consumable->getUser()?->getId() === $user->getId();
    }
}
