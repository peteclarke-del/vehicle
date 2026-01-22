<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Consumable;
use App\Entity\ConsumableType;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Service\ReceiptOcrService;
use App\Service\UrlScraperService;
use Psr\Log\LoggerInterface;
use App\Service\RepairCostCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/consumables')]
class ConsumableController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ReceiptOcrService $ocrService,
        private UrlScraperService $scraperService,
        private LoggerInterface $logger,
        private RepairCostCalculator $repairCostCalculator
    ) {
    }

    #[Route('', name: 'api_consumables_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $vehicleId = $request->query->get('vehicleId');

        if ($vehicleId) {
            $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($vehicleId);
            if (!$vehicle || $vehicle->getOwner()->getId() !== $user->getId()) {
                return $this->json(['error' => 'Vehicle not found'], 404);
            }
            $consumables = $this->entityManager->getRepository(Consumable::class)
                ->findBy(['vehicle' => $vehicle]);
        } else {
            $consumables = [];
        }

        $data = array_map(fn($c) => $this->serializeConsumable($c), $consumables);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'api_consumables_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $consumable = $this->entityManager->getRepository(Consumable::class)->find($id);

        if (!$consumable || $consumable->getVehicle()->getOwner()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Consumable not found'], 404);
        }

        return $this->json($this->serializeConsumable($consumable));
    }

    #[Route('', name: 'api_consumables_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $data = json_decode($request->getContent(), true);

        $vehicle = $this->entityManager->getRepository(Vehicle::class)
            ->find($data['vehicleId']);

        if (!$vehicle || $vehicle->getOwner()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Vehicle not found'], 404);
        }

        $consumableType = $this->entityManager->getRepository(ConsumableType::class)
            ->find($data['consumableTypeId']);

        if (!$consumableType) {
            return $this->json(['error' => 'Consumable type not found'], 404);
        }

        $consumable = new Consumable();
        $consumable->setVehicle($vehicle);
        $consumable->setConsumableType($consumableType);
        // Ensure required non-nullable fields have sensible defaults
        $spec = $data['specification'] ?? $consumableType->getName() ?? '';
        $consumable->setSpecification((string) $spec);
        // lastChanged is NOT NULL in DB; default to now if not provided
        if (empty($data['lastChanged'])) {
            $consumable->setLastChanged(new \DateTime());
        }
        $this->updateConsumableFromData($consumable, $data);

        $this->entityManager->persist($consumable);
        $this->entityManager->flush();

        return $this->json($this->serializeConsumable($consumable), 201);
    }

    #[Route('/{id}', name: 'api_consumables_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $consumable = $this->entityManager->getRepository(Consumable::class)->find($id);

        if (!$consumable || $consumable->getVehicle()->getOwner()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Consumable not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $this->logger->info('Consumable update payload', ['id' => $id, 'data' => $data]);

        $prevMotId = $consumable->getMotRecord()?->getId();
        $this->updateConsumableFromData($consumable, $data);
        $consumable->setUpdatedAt(new \DateTime());

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

        return $this->json($this->serializeConsumable($consumable));
    }

    #[Route('/{id}', name: 'api_consumables_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $consumable = $this->entityManager->getRepository(Consumable::class)->find($id);

        if (!$consumable || $consumable->getVehicle()->getOwner()->getId() !== $user->getId()) {
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

    private function serializeConsumable(Consumable $consumable): array
    {
        return [
            'id' => $consumable->getId(),
            'vehicleId' => $consumable->getVehicle()->getId(),
            'consumableType' => [
                'id' => $consumable->getConsumableType()->getId(),
                'name' => $consumable->getConsumableType()->getName(),
                'unit' => $consumable->getConsumableType()->getUnit()
            ],
            'specification' => $consumable->getSpecification(),
            'quantity' => $consumable->getQuantity(),
            'lastChanged' => $consumable->getLastChanged()?->format('Y-m-d'),
            'mileageAtChange' => $consumable->getMileageAtChange(),
            'cost' => $consumable->getCost(),
            'notes' => $consumable->getNotes(),
            'receiptAttachmentId' => $consumable->getReceiptAttachmentId(),
            'productUrl' => $consumable->getProductUrl(),
            'motRecordId' => $consumable->getMotRecord()?->getId(),
            'motTestNumber' => $consumable->getMotRecord()?->getMotTestNumber(),
            'motTestDate' => $consumable->getMotRecord()?->getTestDate()?->format('Y-m-d'),
            'createdAt' => $consumable->getCreatedAt()?->format('c'),
            'updatedAt' => $consumable->getUpdatedAt()?->format('c')
        ];
    }

    private function updateConsumableFromData(Consumable $consumable, array $data): void
    {
        if (isset($data['specification'])) {
            $consumable->setSpecification($data['specification']);
        }
        if (isset($data['quantity'])) {
            $consumable->setQuantity($data['quantity']);
        }
        if (isset($data['lastChanged'])) {
            $consumable->setLastChanged(new \DateTime($data['lastChanged']));
        }
        if (isset($data['mileageAtChange'])) {
            $consumable->setMileageAtChange($data['mileageAtChange']);
        }
        if (isset($data['cost'])) {
            $consumable->setCost($data['cost']);
        }
        if (isset($data['notes'])) {
            $consumable->setNotes($data['notes']);
        }
        if (isset($data['receiptAttachmentId'])) {
            $consumable->setReceiptAttachmentId($data['receiptAttachmentId']);
        }
        if (isset($data['productUrl'])) {
            $consumable->setProductUrl($data['productUrl']);
        }
        if (array_key_exists('motRecordId', $data)) {
            $this->logger->info('Consumable motRecordId present in payload', ['id' => $consumable->getId(), 'motRecordId' => $data['motRecordId']]);
            $mot = $this->entityManager->getRepository(\App\Entity\MotRecord::class)->find($data['motRecordId']);
            if ($mot) {
                $consumable->setMotRecord($mot);
                $this->logger->info('Consumable associated with MOT', ['consumableId' => $consumable->getId(), 'motId' => $mot->getId()]);
            } else {
                $consumable->setMotRecord(null);
                $this->logger->info('Consumable disassociated from MOT', ['consumableId' => $consumable->getId()]);
            }
        }
    }

    #[Route('/scrape-url', name: 'api_consumables_scrape_url', methods: ['POST'])]
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
