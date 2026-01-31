<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Consumable;
use App\Entity\ConsumableType;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\ServiceItem;
use App\Service\ReceiptOcrService;
use App\Service\UrlScraperService;
use Psr\Log\LoggerInterface;
use App\Service\RepairCostCalculator;
use App\Service\EntitySerializerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Controller\Trait\UserSecurityTrait;
use App\Controller\Trait\AttachmentFileOrganizerTrait;

#[Route('/api/consumables')]

/**
 * class ConsumableController
 */
class ConsumableController extends AbstractController
{
    use UserSecurityTrait;
    use AttachmentFileOrganizerTrait;

    /**
     * function __construct
     *
     * @param EntityManagerInterface $entityManager
     * @param ReceiptOcrService $ocrService
     * @param UrlScraperService $scraperService
     * @param LoggerInterface $logger
     * @param RepairCostCalculator $repairCostCalculator
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
        private SluggerInterface $slugger
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

        if ($vehicleId) {
            $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($vehicleId);
            if (!$vehicle || (!$this->isAdminForUser($user) && $vehicle->getOwner()->getId() !== $user->getId())) {
                return $this->json(['error' => 'Vehicle not found'], 404);
            }
            $consumables = $this->entityManager->getRepository(Consumable::class)
                ->findBy(['vehicle' => $vehicle]);
        } else {
            // Fetch consumables for all vehicles the user can see
            $vehicleRepo = $this->entityManager->getRepository(Vehicle::class);
            $vehicles = $this->isAdminForUser($user) ? $vehicleRepo->findAll() : $vehicleRepo->findBy(['owner' => $user]);
            if (empty($vehicles)) {
                $consumables = [];
            } else {
                $qb = $this->entityManager->createQueryBuilder()
                    ->select('c')
                    ->from(Consumable::class, 'c')
                    ->where('c.vehicle IN (:vehicles)')
                    ->setParameter('vehicles', $vehicles)
                    ->orderBy('c.lastChanged', 'DESC');

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

        if (!$consumable || (!$this->isAdminForUser($user) && $consumable->getVehicle()->getOwner()->getId() !== $user->getId())) {
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

        $data = json_decode($request->getContent(), true);

        $vehicle = $this->entityManager->getRepository(Vehicle::class)
            ->find($data['vehicleId']);

        if (!$vehicle || (!$this->isAdminForUser($user) && $vehicle->getOwner()->getId() !== $user->getId())) {
            return $this->json(['error' => 'Vehicle not found'], 404);
        }

        $consumableType = $this->resolveConsumableType($data, $vehicle);
        if (!$consumableType) {
            return $this->json(['error' => 'Consumable type not found'], 404);
        }

        $consumable = new Consumable();
        $consumable->setVehicle($vehicle);
        $consumable->setConsumableType($consumableType);
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

        if (!$consumable || (!$this->isAdminForUser($user) && $consumable->getVehicle()->getOwner()->getId() !== $user->getId())) {
            return $this->json(['error' => 'Consumable not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $this->logger->info('Consumable update payload', ['id' => $id, 'data' => $data]);

        $prevMotId = $consumable->getMotRecord()?->getId();
        $this->updateConsumableFromData($consumable, $data);
        $consumable->setUpdatedAt(new \DateTime());

        $serviceItem = $this->entityManager->getRepository(ServiceItem::class)
            ->findOneBy(['consumable' => $consumable]);
        if ($serviceItem) {
            $serviceItem->setCost($consumable->getCost());
            $serviceItem->setQuantity($consumable->getQuantity() ?? 1);
            $serviceItem->setDescription($consumable->getDescription());
            $this->entityManager->persist($serviceItem);
        }

        try {
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

        if (!$consumable || (!$this->isAdminForUser($user) && $consumable->getVehicle()->getOwner()->getId() !== $user->getId())) {
            return $this->json(['error' => 'Consumable not found'], 404);
        }
        $motId = $consumable->getMotRecord()?->getId();

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
        if (isset($data['receiptAttachmentId'])) {
            if ($data['receiptAttachmentId'] === null || $data['receiptAttachmentId'] === '') {
                $consumable->setReceiptAttachment(null);
            } else {
                $att = $this->entityManager->getRepository(\App\Entity\Attachment::class)->find($data['receiptAttachmentId']);
                if ($att) {
                    $consumable->setReceiptAttachment($att);
                    // Update attachment's entity_id to link it to this consumable
                    $att->setEntityId($consumable->getId());
                    $att->setEntityType('consumable');
                    $this->reorganizeReceiptFile($att, $consumable->getVehicle());
                } else {
                    $consumable->setReceiptAttachment(null);
                }
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
                    $consumable->setMotRecord($mot);
                    $this->logger->info('Consumable associated with MOT', ['consumableId' => $consumable->getId(), 'motId' => $mot->getId()]);
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
                $this->logger->info('Consumable disassociated from Service (explicit)', [
                    'consumableId' => $consumable->getId(),
                ]);
            } else {
                $svcId = is_numeric($svcId) ? (int) $svcId : $svcId;
                $svc = $this->entityManager->getRepository(\App\Entity\ServiceRecord::class)->find($svcId);
                if ($svc) {
                    $consumable->setServiceRecord($svc);
                    $this->logger->info('Consumable associated with Service', [
                        'consumableId' => $consumable->getId(),
                        'serviceId' => $svc->getId(),
                    ]);
                } else {
                    $consumable->setServiceRecord(null);
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

        $data = json_decode($request->getContent(), true);
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
}
