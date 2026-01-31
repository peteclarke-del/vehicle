<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Trait\UserSecurityTrait;
use App\Entity\MotRecord;
use App\Entity\Vehicle;
use App\Entity\Part;
use App\Entity\Consumable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use App\Service\DvsaApiService;
use App\Service\RepairCostCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class MotRecordController extends AbstractController
{
    use UserSecurityTrait;
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DvsaApiService $dvsaService,
        private LoggerInterface $logger,
        private RepairCostCalculator $repairCostCalculator
    ) {
    }

    private function getUserEntity(): ?\App\Entity\User
    {
        $user = $this->getUser();
        return $user instanceof \App\Entity\User ? $user : null;
    }
    #[Route('/mot-records', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($data['vehicleId']);
        $user = $this->getUserEntity();
        if (!$vehicle || !$user || (!$this->isAdminForUser($user) && $vehicle->getOwner()->getId() !== $user->getId())) {
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
        if (!$motRecord || !$user || (!$this->isAdminForUser($user) && $motRecord->getVehicle()->getOwner()->getId() !== $user->getId())) {
            return new JsonResponse(['error' => 'MOT record not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $this->updateMotRecordFromData($motRecord, $data);

        $this->entityManager->flush();

        try {
            $this->repairCostCalculator->recalculateAndPersist($motRecord);
        } catch (\Throwable $e) {
            $this->logger->error('Error recalculating repair cost after MOT update', ['exception' => $e->getMessage()]);
        }

        return new JsonResponse($this->serializeMotRecord($motRecord));
    }

    #[Route('/mot-records/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $motRecord = $this->entityManager->getRepository(MotRecord::class)->find($id);
        $user = $this->getUserEntity();
        if (!$motRecord || !$user || (!$this->isAdminForUser($user) && $motRecord->getVehicle()->getOwner()->getId() !== $user->getId())) {
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
        if (!$motRecord || !$user || (!$this->isAdminForUser($user) && $motRecord->getVehicle()->getOwner()->getId() !== $user->getId())) {
            return new JsonResponse(['error' => 'MOT record not found'], 404);
        }

        $parts = $this->entityManager->getRepository(Part::class)
            ->findBy(['motRecord' => $motRecord]);

        $consumables = $this->entityManager->getRepository(Consumable::class)
            ->findBy(['motRecord' => $motRecord]);

        $serviceRecords = $this->entityManager->getRepository(\App\Entity\ServiceRecord::class)
            ->findBy(['motRecord' => $motRecord]);

        return new JsonResponse([
            'motRecord' => $this->serializeMotRecord($motRecord),
            'parts' => array_map(fn($p) => $this->serializePart($p), $parts),
            'consumables' => array_map(fn($c) => $this->serializeConsumable($c), $consumables),
            'serviceRecords' => array_map(fn($s) => [
                'id' => $s->getId(),
                'laborCost' => $s->getLaborCost(),
                'partsCost' => $s->getPartsCost(),
                'totalCost' => $s->getTotalCost(),
                'mileage' => $s->getMileage(),
                'serviceProvider' => $s->getServiceProvider(),
                'workPerformed' => $s->getWorkPerformed(),
                'items' => array_map(fn($it) => [
                    'id' => $it->getId(),
                    'type' => $it->getType(),
                    'description' => $it->getDescription(),
                    'cost' => $it->getCost(),
                    'quantity' => $it->getQuantity(),
                    'total' => $it->getTotal(),
                ], $s->getItems()),
            ], $serviceRecords),
        ]);
    }

    #[Route('/mot-records/dvsa-history', methods: ['GET'])]
    public function fetchDvsaHistory(Request $request): JsonResponse
    {
        $registration = (string) $request->query->get('registration');
        if (empty($registration)) {
            return new JsonResponse(['error' => 'registration is required'], 400);
        }

        $history = $this->dvsaService->parseMotHistory($registration);
        return new JsonResponse($history);
    }

    #[Route('/mot-records', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $vehicleId = $request->query->get('vehicleId');
        $user = $this->getUserEntity();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        if (!empty($vehicleId)) {
            $vehicle = $this->entityManager->getRepository(Vehicle::class)->find((int)$vehicleId);
            if (!$vehicle || (!$this->isAdminForUser($user) && $vehicle->getOwner()->getId() !== $user->getId())) {
                return new JsonResponse(['error' => 'Vehicle not found'], 404);
            }

            $records = $this->entityManager->getRepository(MotRecord::class)
                ->findBy(['vehicle' => $vehicle], ['testDate' => 'DESC']);
        } else {
            $vehicleRepo = $this->entityManager->getRepository(Vehicle::class);
            $vehicles = $this->isAdminForUser($user) ? $vehicleRepo->findAll() : $vehicleRepo->findBy(['owner' => $user]);
            if (empty($vehicles)) {
                return new JsonResponse([]);
            }

            $qb = $this->entityManager->createQueryBuilder()
                ->select('m')
                ->from(MotRecord::class, 'm')
                ->where('m.vehicle IN (:vehicles)')
                ->setParameter('vehicles', $vehicles)
                ->orderBy('m.testDate', 'DESC');

            $records = $qb->getQuery()->getResult();
        }

        $out = array_map(fn($r) => $this->serializeMotRecord($r), $records);
        return new JsonResponse($out);
    }

    #[Route('/mot-records/import-dvsa', methods: ['POST'])]
    public function importFromDvsa(Request $request): JsonResponse
    {
        $raw = $request->getContent();
        // Log raw request body for debugging registration vs name issues
        $this->logger->info('importFromDvsa raw body: ' . $raw);
        $data = json_decode($raw, true);
        $vehicleId = $data['vehicleId'] ?? null;
        $registration = $data['registration'] ?? null;

        $this->logger->info(sprintf('importFromDvsa parsed vehicleId=%s registration=%s', (string)$vehicleId, (string)$registration));

        if (!$vehicleId || !$registration) {
            return new JsonResponse(['error' => 'vehicleId and registration are required'], 400);
        }

        $vehicle = $this->entityManager->getRepository(Vehicle::class)->find((int)$vehicleId);
        $user = $this->getUserEntity();
        if (!$vehicle || !$user || (!$this->isAdminForUser($user) && $vehicle->getOwner()->getId() !== $user->getId())) {
            return new JsonResponse(['error' => 'Vehicle not found'], 404);
        }

        $motData = $this->dvsaService->getMotHistory($registration);
        if (!$motData || !isset($motData[0]['motTests'])) {
            // Provide diagnostic info when DVSA data is missing
            return new JsonResponse([
                'imported' => 0,
                'error' => 'No MOT history returned from DVSA',
            ], 502);
        }

        $tests = $motData[0]['motTests'];
        $imported = 0;
        $importedIds = [];

        foreach ($tests as $test) {
            // Only import tests that have a completedDate (otherwise we don't have a test date)
            if (empty($test['completedDate'])) {
                continue;
            }

            // Normalize values we will use for matching/creation
            $completedAt = new \DateTime($test['completedDate']);
            $dateKey = $completedAt->format('Y-m-d');
            $motTestNumber = !empty($test['motTestNumber']) ? (string)$test['motTestNumber'] : null;

            // Attempt to find existing record by motTestNumber (preferred) or by vehicle+testDate
            $repo = $this->entityManager->getRepository(MotRecord::class);
            $existing = null;
            if ($motTestNumber) {
                $existing = $repo->findOneBy(['motTestNumber' => $motTestNumber, 'vehicle' => $vehicle]);
            }
            if (!$existing) {
                $candidates = $repo->findBy(['vehicle' => $vehicle]);
                foreach ($candidates as $cand) {
                    $candDate = $cand->getTestDate()?->format('Y-m-d');
                    if ($candDate === $dateKey) {
                        $existing = $cand;
                        break;
                    }
                }
            }

            // Build data array from DVSA test for applying to entity
            $newData = [];
            $newData['testDate'] = $completedAt->format('Y-m-d');
            $rawResult = strtoupper((string)($test['testResult'] ?? ''));
            if (str_contains($rawResult, 'PASS')) {
                $newData['result'] = 'Pass';
            } elseif (str_contains($rawResult, 'FAIL')) {
                $newData['result'] = 'Fail';
            } else {
                $newData['result'] = 'Advisory';
            }

            if (!empty($test['expiryDate'])) {
                $newData['expiryDate'] = $test['expiryDate'];
            }
            // odometer may be miles - store numeric value
            if (!empty($test['odometerValue'])) {
                $rawOdo = $test['odometerValue'];
                $unit = $test['odometerUnit'] ?? null;

                // The database stores MOT mileage in kilometres. If DVSA
                // returned miles, convert to kilometres before storing.
                if ($unit && is_string($unit) && preg_match('/mi|mile/i', $unit)) {
                    // Convert miles to kilometres (1 mile = 1.609344 km)
                    $kms = (float)$rawOdo * 1.609344;
                    $newData['mileage'] = (int) round($kms);
                } else {
                    // Assume the value is already in kilometres
                    $newData['mileage'] = (int)$rawOdo;
                }
            }
            if (!empty($motTestNumber)) {
                $newData['motTestNumber'] = $motTestNumber;
            }

            // Use dataSource as test centre when present (per request) otherwise try common keys
            $testCenter = $test['dataSource'] ?? $test['testStationName'] ?? $test['testingStationName'] ?? $test['testingStation'] ?? $test['testCentre'] ?? $test['testCentreName'] ?? $test['testStation'] ?? $test['testCenter'] ?? null;
            if (!empty($testCenter)) {
                $newData['testCenter'] = $testCenter;
            }

            // Parse comments/defects into advisories and failures
            $comments = $test['rfrAndComments'] ?? $test['defects'] ?? [];
            $advisories = [];
            $failures = [];
            foreach ($comments as $c) {
                $type = isset($c['type']) ? strtoupper((string)$c['type']) : '';
                if ($type === 'ADVISORY') {
                    $advisories[] = $c;
                } else {
                    $failures[] = $c;
                }
            }
            if (!empty($advisories)) {
                $newData['advisories'] = $advisories;
            }
            if (!empty($failures)) {
                $newData['failures'] = $failures;
            }

            // Ensure non-nullable numeric fields have defaults to avoid DB constraint errors
            $newData['testCost'] = '0.00';

            if ($existing) {
                // Compare and update only if data differs
                $changed = false;
                // quick field comparison
                foreach (['result','expiryDate','mileage','motTestNumber','testCenter'] as $k) {
                    $existingVal = null;
                    switch ($k) {
                        case 'mileage':
                            $existingVal = $existing->getMileage();
                            break;
                        case 'motTestNumber':
                            $existingVal = $existing->getMotTestNumber();
                            break;
                        case 'testCenter':
                            $existingVal = $existing->getTestCenter();
                            break;
                        case 'expiryDate':
                            $existingVal = $existing->getExpiryDate()?->format('Y-m-d');
                            break;
                        case 'result':
                            $existingVal = $existing->getResult();
                            break;
                    }
                    $newVal = $newData[$k] ?? null;
                    if ($k === 'mileage') {
                        $newVal = isset($newVal) ? (int)$newVal : null;
                    }
                    if (($existingVal ?? null) != ($newVal ?? null)) {
                        $changed = true;
                        break;
                    }
                }

                // Also check advisories/failures differences by JSON
                if (!$changed) {
                    $existingAdv = $existing->getAdvisoryItems() ?: [];
                    $existingFail = $existing->getFailureItems() ?: [];
                    if (!empty($newData['advisories']) && json_encode($existingAdv) !== json_encode($newData['advisories'])) {
                        $changed = true;
                    }
                    if (!$changed && !empty($newData['failures']) && json_encode($existingFail) !== json_encode($newData['failures'])) {
                        $changed = true;
                    }
                }

                if ($changed) {
                    $this->updateMotRecordFromData($existing, $newData);
                    $this->entityManager->persist($existing);
                    $imported++;
                    $importedIds[] = $existing;
                }
            } else {
                // create new record
                $mot = new MotRecord();
                $mot->setVehicle($vehicle);
                $mot->setTestCost($newData['testCost']);
                if (isset($newData['testDate'])) {
                    $mot->setTestDate(new \DateTime($newData['testDate']));
                }
                $this->updateMotRecordFromData($mot, $newData);
                $this->entityManager->persist($mot);
                $imported++;
                $importedIds[] = $mot;
            }
        }

        $this->entityManager->flush();

        // Replace objects with their persisted IDs
        $importedIds = array_map(fn($m) => $m->getId(), $importedIds);

        return new JsonResponse(['imported' => $imported, 'importedIds' => $importedIds]);
    }

    private function serializeMotRecord(MotRecord $mot, bool $detailed = false): array
    {
        // Normalize result and advisory/failure items for frontend form consumption
        $result = $mot->getResult();
        $upper = strtoupper((string)$result);
        if (str_contains($upper, 'PASS')) {
            $result = 'Pass';
        } elseif (str_contains($upper, 'FAIL')) {
            $result = 'Fail';
        } else {
            $result = 'Advisory';
        }

        $advisoryItems = $mot->getAdvisoryItems();
        if (is_array($advisoryItems)) {
            $advisoriesText = implode("\n", array_map(fn($a) => is_array($a) ? ($a['text'] ?? json_encode($a)) : (string)$a, $advisoryItems));
        } else {
            $advisoriesText = $mot->getAdvisories();
        }

        $failureItems = $mot->getFailureItems();
        if (is_array($failureItems)) {
            $failuresText = implode("\n", array_map(fn($a) => is_array($a) ? ($a['text'] ?? json_encode($a)) : (string)$a, $failureItems));
        } else {
            $failuresText = $mot->getFailures();
        }

        $data = [
            'id' => $mot->getId(),
            'vehicleId' => $mot->getVehicle()->getId(),
            'testDate' => $mot->getTestDate()?->format('Y-m-d'),
            'result' => $result,
            'expiryDate' => $mot->getExpiryDate()?->format('Y-m-d'),
            'motTestNumber' => $mot->getMotTestNumber(),
            'testerName' => $mot->getTesterName(),
            'isRetest' => $mot->getIsRetest(),
            'testCost' => $mot->getTestCost(),
            'repairCost' => $mot->getRepairCost(),
            'totalCost' => $mot->getTotalCost(),
            'mileage' => $mot->getMileage(),
            'testCenter' => $mot->getTestCenter() ?? 'Unknown',
            'advisories' => $advisoriesText,
            'failures' => $failuresText,
            'advisoryItems' => $advisoryItems,
            'failureItems' => $failureItems,
            'receiptAttachmentId' => $mot->getReceiptAttachment()?->getId(),
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
            'vehicleId' => $part->getVehicle()?->getId(),
            'purchaseDate' => $part->getPurchaseDate()?->format('Y-m-d'),
            'description' => $part->getDescription(),
            'partNumber' => $part->getPartNumber(),
            'manufacturer' => $part->getManufacturer(),
            'supplier' => $part->getSupplier(),
            'price' => $part->getPrice(),
            'quantity' => $part->getQuantity(),
            'cost' => $part->getCost(),
            'category' => $part->getCategory(),
            'partCategory' => $part->getPartCategory() ? [
                'id' => $part->getPartCategory()->getId(),
                'name' => $part->getPartCategory()->getName(),
            ] : null,
            'installationDate' => $part->getInstallationDate()?->format('Y-m-d'),
            'mileageAtInstallation' => $part->getMileageAtInstallation(),
            'notes' => $part->getNotes(),
            'motRecordId' => $part->getMotRecord()?->getId(),
            'motTestNumber' => $part->getMotRecord()?->getMotTestNumber(),
            'motTestDate' => $part->getMotRecord()?->getTestDate()?->format('Y-m-d'),
            'warranty' => $part->getWarranty(),
            'receiptAttachmentId' => $part->getReceiptAttachment()?->getId(),
            'productUrl' => $part->getProductUrl(),
            'createdAt' => $part->getCreatedAt()?->format('c')
        ];
    }

    private function serializeConsumable(Consumable $consumable): array
    {
        return [
            'id' => $consumable->getId(),
            'vehicleId' => $consumable->getVehicle()?->getId(),
            'consumableType' => $consumable->getConsumableType() ? [
                'id' => $consumable->getConsumableType()->getId(),
                'name' => $consumable->getConsumableType()->getName(),
                'unit' => $consumable->getConsumableType()->getUnit()
            ] : null,
                'description' => $consumable->getDescription(),
            'quantity' => $consumable->getQuantity(),
            'lastChanged' => $consumable->getLastChanged()?->format('Y-m-d'),
            'mileageAtChange' => $consumable->getMileageAtChange(),
            'cost' => $consumable->getCost(),
            'notes' => $consumable->getNotes(),
            'receiptAttachmentId' => $consumable->getReceiptAttachment()?->getId(),
            'productUrl' => $consumable->getProductUrl(),
            'motRecordId' => $consumable->getMotRecord()?->getId(),
            'motTestNumber' => $consumable->getMotRecord()?->getMotTestNumber(),
            'motTestDate' => $consumable->getMotRecord()?->getTestDate()?->format('Y-m-d'),
            'createdAt' => $consumable->getCreatedAt()?->format('c')
        ];
    }

    private function updateMotRecordFromData(MotRecord $mot, array $data): void
    {
        if (isset($data['testDate'])) {
            $mot->setTestDate(new \DateTime($data['testDate']));
        }
        if (isset($data['expiryDate'])) {
            $mot->setExpiryDate(!empty($data['expiryDate']) ? new \DateTime($data['expiryDate']) : null);
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
        if (isset($data['motTestNumber'])) {
            $mot->setMotTestNumber($data['motTestNumber']);
        }
        if (isset($data['testerName'])) {
            $mot->setTesterName($data['testerName']);
        }
        if (isset($data['isRetest'])) {
            $mot->setIsRetest((bool)$data['isRetest']);
        }
        if (isset($data['testCenter'])) {
            $mot->setTestCenter($data['testCenter']);
        }
        if (isset($data['receiptAttachmentId'])) {
            if ($data['receiptAttachmentId'] === null || $data['receiptAttachmentId'] === '') {
                $mot->setReceiptAttachment(null);
            } else {
                $att = $this->entityManager->getRepository(\App\Entity\Attachment::class)->find($data['receiptAttachmentId']);
                if ($att) {
                    $mot->setReceiptAttachment($att);
                } else {
                    $mot->setReceiptAttachment(null);
                }
            }
        }
        if (isset($data['advisories'])) {
            if (is_array($data['advisories'])) {
                $mot->setAdvisoryItems($data['advisories']);
            } else {
                $mot->setAdvisories($data['advisories']);
            }
        }
        if (isset($data['failures'])) {
            if (is_array($data['failures'])) {
                $mot->setFailureItems($data['failures']);
            } else {
                $mot->setFailures($data['failures']);
            }
        }
        if (isset($data['repairDetails'])) {
            $mot->setRepairDetails($data['repairDetails']);
        }
        if (isset($data['notes'])) {
            $mot->setNotes($data['notes']);
        }
    }

    private function isAdminForUser(?\App\Entity\User $user): bool
    {
        if (!$user) {
            return false;
        }
        $roles = $user->getRoles() ?: [];
        return in_array('ROLE_ADMIN', $roles, true);
    }
}
