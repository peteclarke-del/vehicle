<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Part;
use App\Entity\PartCategory;
use App\Entity\ServiceItem;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\Attachment;
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

#[Route('/api/parts')]
class PartController extends AbstractController
{
    use UserSecurityTrait;
    use AttachmentFileOrganizerTrait;

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

    #[Route('', name: 'api_parts_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        if ($this->isAdminForUser($user)) {
            // Admins may request parts for any vehicle; keep existing behavior when vehicleId provided
        }

        $vehicleId = $request->query->get('vehicleId');
        if ($vehicleId) {
            $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($vehicleId);
            if (!$vehicle || (!$this->isAdminForUser($user) && $vehicle->getOwner()->getId() !== $user->getId())) {
                return $this->json(['error' => 'Vehicle not found'], 404);
            }
            $parts = $this->entityManager->getRepository(Part::class)
                ->findBy(['vehicle' => $vehicle], ['purchaseDate' => 'DESC']);
        } else {
            // Fetch parts for all vehicles the user can see
            $vehicleRepo = $this->entityManager->getRepository(Vehicle::class);
            $vehicles = $this->isAdminForUser($user) ? $vehicleRepo->findAll() : $vehicleRepo->findBy(['owner' => $user]);
            if (empty($vehicles)) {
                $parts = [];
            } else {
                $qb = $this->entityManager->createQueryBuilder()
                    ->select('p')
                    ->from(Part::class, 'p')
                    ->where('p.vehicle IN (:vehicles)')
                    ->setParameter('vehicles', $vehicles)
                    ->orderBy('p.purchaseDate', 'DESC');

                $parts = $qb->getQuery()->getResult();
            }
        }

        $data = array_map(fn($p) => $this->serializer->serializePart($p), $parts);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'api_parts_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $part = $this->entityManager->getRepository(Part::class)->find($id);

        if (!$part || (!$this->isAdminForUser($user) && $part->getVehicle()->getOwner()->getId() !== $user->getId())) {
            return $this->json(['error' => 'Part not found'], 404);
        }

        return $this->json($this->serializer->serializePart($part));
    }

    #[Route('', name: 'api_parts_create', methods: ['POST'])]
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

        $part = new Part();
        $part->setVehicle($vehicle);
        $this->updatePartFromData($part, $data);

        // Ensure required dates are set to now when not provided to avoid DB NOT NULL errors
        if (null === $part->getPurchaseDate()) {
            $part->setPurchaseDate(new \DateTime());
        }
        // Do not force-installationDate: leave as null unless explicitly provided

        $this->entityManager->persist($part);
        $this->entityManager->flush();

        return $this->json($this->serializer->serializePart($part), 201);
    }

    #[Route('/{id}', name: 'api_parts_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $part = $this->entityManager->getRepository(Part::class)->find($id);

        if (!$part || (!$this->isAdminForUser($user) && $part->getVehicle()->getOwner()->getId() !== $user->getId())) {
            return $this->json(['error' => 'Part not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $this->logger->info('Part update payload', ['id' => $id, 'data' => $data]);

        $prevMotId = $part->getMotRecord()?->getId();
        $this->updatePartFromData($part, $data);

        $serviceItem = $this->entityManager->getRepository(ServiceItem::class)
            ->findOneBy(['part' => $part]);
        if ($serviceItem) {
            $serviceItem->setCost($part->getPrice() ?? $part->getCost());
            $serviceItem->setQuantity($part->getQuantity() ?? 1);
            $serviceItem->setDescription($part->getDescription() ?? $part->getName());
            $this->entityManager->persist($serviceItem);
        }

        try {
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $this->logger->error('Error flushing Part update', ['exception' => $e->getMessage()]);
            return $this->json(['error' => 'Failed to update part: ' . $e->getMessage()], 500);
        }

        // Recalculate repair costs for affected MOT records (previous and/or current)
        $newMotId = $part->getMotRecord()?->getId();
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
            $this->logger->error('Error recalculating repair cost after Part update', ['exception' => $e->getMessage()]);
        }

        return $this->json($this->serializer->serializePart($part));
    }

    #[Route('/{id}', name: 'api_parts_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $part = $this->entityManager->getRepository(Part::class)->find($id);

        if (!$part || (!$this->isAdminForUser($user) && $part->getVehicle()->getOwner()->getId() !== $user->getId())) {
            return $this->json(['error' => 'Part not found'], 404);
        }

        $motId = $part->getMotRecord()?->getId();

        $this->entityManager->remove($part);
        $this->entityManager->flush();

        if ($motId) {
            try {
                $mot = $this->entityManager->getRepository(\App\Entity\MotRecord::class)->find($motId);
                if ($mot) {
                    $this->repairCostCalculator->recalculateAndPersist($mot);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Error recalculating repair cost after Part delete', ['exception' => $e->getMessage()]);
            }
        }

        return $this->json(['message' => 'Part deleted successfully']);
    }

    private function updatePartFromData(Part $part, array $data): void
    {
        if (isset($data['purchaseDate'])) {
            $part->setPurchaseDate(new \DateTime($data['purchaseDate']));
        }
        if (isset($data['description'])) {
            $part->setDescription($data['description']);
        }
        if (isset($data['partNumber'])) {
            $part->setPartNumber($data['partNumber']);
        }
        if (isset($data['manufacturer'])) {
            $part->setManufacturer($data['manufacturer']);
        }
        if (isset($data['supplier'])) {
            $part->setSupplier($data['supplier']);
        }
        if (isset($data['price'])) {
            $part->setPrice($data['price']);
        }
        if (isset($data['quantity'])) {
            $part->setQuantity((int)$data['quantity']);
        }
        if (isset($data['cost'])) {
            // Ensure entity expects a string — cast numeric values to string
            $part->setCost((string) $data['cost']);
        }

        // If price/quantity provided (or both exist on entity), compute cost
        if (array_key_exists('price', $data) || array_key_exists('quantity', $data)) {
            $p = $data['price'] ?? $part->getPrice();
            $q = array_key_exists('quantity', $data) ? (int)$data['quantity'] : $part->getQuantity();
            if ($p !== null && $q !== null) {
                $computed = number_format(((float)$p) * ((int)$q), 2, '.', '');
                $part->setCost((string)$computed);
            }
        }

        // Accept partCategory by id or name. Prefer explicit id if provided.
        if (array_key_exists('partCategoryId', $data) || array_key_exists('partCategory', $data) || array_key_exists('partCategoryName', $data)) {
            $pcRepo = $this->entityManager->getRepository(PartCategory::class);
            $vehicleType = $part->getVehicle()?->getVehicleType() ?? null;

            $found = null;
            if (!empty($data['partCategoryId'])) {
                $found = $pcRepo->find((int)$data['partCategoryId']);
            } elseif (!empty($data['partCategory']) && is_array($data['partCategory']) && !empty($data['partCategory']['id'])) {
                $found = $pcRepo->find((int)$data['partCategory']['id']);
            } else {
                $name = $data['partCategoryName'] ?? null;
                if (!$name && !empty($data['partCategory']) && is_string($data['partCategory'])) {
                    $name = $data['partCategory'];
                }
                if (!$name && isset($data['partCategory']['name'])) {
                    $name = $data['partCategory']['name'];
                }
                if ($name) {
                    $criteria = ['name' => trim($name)];
                    if ($vehicleType) {
                        $criteria['vehicleType'] = $vehicleType;
                    }
                    $found = $pcRepo->findOneBy($criteria);
                    if (!$found) {
                        // create a new PartCategory
                        $found = new PartCategory();
                        $found->setName(trim($name));
                        if ($vehicleType) {
                            $found->setVehicleType($vehicleType);
                        }
                        if (!empty($data['partCategory']['description'] ?? $data['description'] ?? null)) {
                            $found->setDescription($data['partCategory']['description'] ?? $data['description']);
                        }
                        $this->entityManager->persist($found);
                    }
                }
            }

            if ($found instanceof PartCategory) {
                $part->setPartCategory($found);
            } else {
                // explicit disassociate
                if (array_key_exists('partCategoryId', $data) && empty($data['partCategoryId'])) {
                    $part->setPartCategory(null);
                }
            }
        }
        if (isset($data['installationDate'])) {
            if (!empty($data['installationDate'])) {
                $part->setInstallationDate(new \DateTime($data['installationDate']));
            }
        }
        if (isset($data['mileageAtInstallation'])) {
            $part->setMileageAtInstallation($data['mileageAtInstallation']);
        }
        if (isset($data['warranty'])) {
            // Frontend sends warranty as a string/number under `warranty`
            $part->setWarranty($data['warranty']);
        }
        if (isset($data['notes'])) {
            $part->setNotes($data['notes']);
        }
        if (array_key_exists('motRecordId', $data)) {
            $this->logger->info('Part motRecordId present in payload', ['id' => $part->getId(), 'motRecordId' => $data['motRecordId']]);

            // Normalize potential shapes: scalar id, numeric string, or object/array containing an `id` key
            $motId = $data['motRecordId'];
            if (is_array($motId)) {
                $motId = $motId['id'] ?? $motId['motRecordId'] ?? null;
            }

            // If frontend explicitly sent null/empty/0, treat as disassociate
            if ($motId === null || $motId === '' || $motId === 0 || $motId === '0') {
                $part->setMotRecord(null);
                $this->logger->info('Part disassociated from MOT (explicit)', ['partId' => $part->getId()]);
            } else {
                $motId = is_numeric($motId) ? (int)$motId : $motId;
                $mot = $this->entityManager->getRepository(\App\Entity\MotRecord::class)->find($motId);
                if ($mot) {
                    $part->setMotRecord($mot);
                    $this->logger->info('Part associated with MOT', ['partId' => $part->getId(), 'motId' => $mot->getId()]);
                } else {
                    $part->setMotRecord(null);
                    $this->logger->info('Part disassociated from MOT (not found)', ['partId' => $part->getId(), 'motId' => $motId]);
                }
            }
        }
        if (array_key_exists('serviceRecordId', $data)) {
            $this->logger->info('Part serviceRecordId present in payload', ['id' => $part->getId(), 'serviceRecordId' => $data['serviceRecordId']]);
            $svcId = $data['serviceRecordId'];
            if (is_array($svcId)) {
                $svcId = $svcId['id'] ?? $svcId['serviceRecordId'] ?? null;
            }
            if ($svcId === null || $svcId === '' || $svcId === 0 || $svcId === '0') {
                $part->setServiceRecord(null);
                $this->logger->info('Part disassociated from Service (explicit)', ['partId' => $part->getId()]);
            } else {
                $svcId = is_numeric($svcId) ? (int)$svcId : $svcId;
                $svc = $this->entityManager->getRepository(\App\Entity\ServiceRecord::class)->find($svcId);
                if ($svc) {
                    $part->setServiceRecord($svc);
                    $this->logger->info('Part associated with Service', ['partId' => $part->getId(), 'serviceId' => $svc->getId()]);
                } else {
                    $part->setServiceRecord(null);
                    $this->logger->info('Part disassociated from Service (not found)', ['partId' => $part->getId(), 'serviceId' => $svcId]);
                }
            }
        }
        if (isset($data['receiptAttachmentId'])) {
            $att = $this->entityManager->getRepository(Attachment::class)->find($data['receiptAttachmentId']);
            if ($att) {
                $part->setReceiptAttachment($att);
                // Update attachment's entity_id to link it to this part
                $att->setEntityId($part->getId());
                $att->setEntityType('part');
                
                // Reorganize file into proper directory structure
                $this->reorganizeReceiptFile($att, $part->getVehicle());
            }
        }
        if (isset($data['productUrl'])) {
            $part->setProductUrl($data['productUrl']);
        }
    }

    #[Route('/scrape-url', name: 'api_parts_scrape_url', methods: ['POST'])]
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
