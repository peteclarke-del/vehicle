<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\FuelRecord;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\VehicleAssignment;
use App\Entity\Attachment;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Controller\Trait\UserSecurityTrait;
use App\Controller\Trait\JsonValidationTrait;
use App\Service\AttachmentLinkingService;

#[Route('/api/fuel-records')]
class FuelRecordController extends AbstractController
{
    use UserSecurityTrait;
    use JsonValidationTrait;

    private const FUEL_TYPES = [
        'Biodiesel',
        'Diesel',
        'E5',
        'E10',
        'Electric',
        'Hybrid',
        'Hydrogen',
        'LPG',
        'Premium Diesel',
        'Super Unleaded',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private AttachmentLinkingService $attachmentLinkingService
    ) {
    }

    #[Route('/fuel-types', name: 'api_fuel_types', methods: ['GET'])]
    public function fuelTypes(): JsonResponse
    {
        $response = $this->json(self::FUEL_TYPES);
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        return $response;
    }

    #[Route('', name: 'api_fuel_records_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $vehicleId = $request->query->get('vehicleId');

        if ($vehicleId) {
            $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($vehicleId);
            if (!$vehicle || (!$this->isAdminForUser($user) && $vehicle->getOwner()->getId() !== $user->getId()
                && !$this->entityManager->getRepository(VehicleAssignment::class)->findOneBy(['assignedTo' => $user, 'vehicle' => $vehicle]))) {
                return $this->json(['error' => 'Vehicle not found'], 404);
            }
            $records = $this->entityManager->getRepository(FuelRecord::class)
                ->findBy(['vehicle' => $vehicle], ['date' => 'DESC']);
        } else {
            // Fetch fuel records for all vehicles the user can see
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
            if (empty($vehicles)) {
                $records = [];
            } else {
                $qb = $this->entityManager->createQueryBuilder()
                    ->select('f')
                    ->from(FuelRecord::class, 'f')
                    ->where('f.vehicle IN (:vehicles)')
                    ->setParameter('vehicles', $vehicles)
                    ->orderBy('f.date', 'DESC');

                $records = $qb->getQuery()->getResult();
            }
        }

        $data = array_map(fn($r) => $this->serializeFuelRecord($r), $records);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'api_fuel_records_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $record = $this->entityManager->getRepository(FuelRecord::class)->find($id);

        if (!$record || (!$this->isAdminForUser($user) && $record->getVehicle()->getOwner()->getId() !== $user->getId())) {
            return $this->json(['error' => 'Fuel record not found'], 404);
        }

        return $this->json($this->serializeFuelRecord($record));
    }

    #[Route('', name: 'api_fuel_records_create', methods: ['POST'])]
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

        $vehicle = $this->entityManager->getRepository(Vehicle::class)
            ->find($data['vehicleId']);

        if (!$vehicle || (!$this->isAdminForUser($user) && $vehicle->getOwner()->getId() !== $user->getId())) {
            return $this->json(['error' => 'Vehicle not found'], 404);
        }

        $record = new FuelRecord();
        $record->setVehicle($vehicle);
        $this->updateRecordFromData($record, $data);

        $this->entityManager->persist($record);
        $this->entityManager->flush();

        // Finalize attachment link after flush (entity now has ID)
        $this->attachmentLinkingService->finalizeAttachmentLink($record);
        $this->entityManager->flush();

        if (isset($data['mileage']) && $data['mileage'] > ($vehicle->getCurrentMileage() ?? 0)) {
            $vehicle->setCurrentMileage($data['mileage']);
            $this->entityManager->flush();
        }

        return $this->json($this->serializeFuelRecord($record), 201);
    }

    #[Route('/{id}', name: 'api_fuel_records_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $record = $this->entityManager->getRepository(FuelRecord::class)->find($id);

        if (!$record || (!$this->isAdminForUser($user) && $record->getVehicle()->getOwner()->getId() !== $user->getId())) {
            return $this->json(['error' => 'Fuel record not found'], 404);
        }

        $validation = $this->validateJsonRequest($request);
        if ($validation['error']) {
            return $validation['error'];
        }
        $data = $validation['data'];
        $this->updateRecordFromData($record, $data);

        $this->entityManager->flush();

        return $this->json($this->serializeFuelRecord($record));
    }

    #[Route('/{id}', name: 'api_fuel_records_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $record = $this->entityManager->getRepository(FuelRecord::class)->find($id);

        if (!$record || (!$this->isAdminForUser($user) && $record->getVehicle()->getOwner()->getId() !== $user->getId())) {
            return $this->json(['error' => 'Fuel record not found'], 404);
        }

        $this->entityManager->remove($record);
        $this->entityManager->flush();

        return $this->json(['message' => 'Fuel record deleted successfully']);
    }

    private function serializeFuelRecord(FuelRecord $record): array
    {
        return [
            'id' => $record->getId(),
            'vehicleId' => $record->getVehicle()->getId(),
            'date' => $record->getDate()?->format('Y-m-d'),
            'litres' => $record->getLitres(),
            'cost' => $record->getCost(),
            'mileage' => $record->getMileage(),
            'fuelType' => $record->getFuelType(),
            'station' => $record->getStation(),
            'notes' => $record->getNotes(),
            'receiptAttachmentId' => $record->getReceiptAttachment()?->getId(),
            'createdAt' => $record->getCreatedAt()?->format('c')
        ];
    }

    private function updateRecordFromData(FuelRecord $record, array $data): void
    {
        if (isset($data['date'])) {
            $record->setDate(new \DateTime($data['date']));
        }
        if (isset($data['litres'])) {
            $record->setLitres($data['litres']);
        }
        if (isset($data['cost'])) {
            $record->setCost($data['cost']);
        }
        if (isset($data['mileage'])) {
            $record->setMileage($data['mileage']);
        }
        if (isset($data['fuelType'])) {
            $record->setFuelType($data['fuelType']);
        }
        if (isset($data['station'])) {
            $record->setStation($data['station']);
        }
        if (isset($data['notes'])) {
            $record->setNotes($data['notes']);
        }
        if (array_key_exists('receiptAttachmentId', $data)) {
            $attachmentId = $data['receiptAttachmentId'];
            if ($attachmentId === null || $attachmentId === '' || $attachmentId === 0) {
                if ($record->getReceiptAttachment()) {
                    $this->attachmentLinkingService->unlinkAttachment($record->getReceiptAttachment(), $record);
                }
                $record->setReceiptAttachment(null);
            } else {
                $this->attachmentLinkingService->processReceiptAttachmentId(
                    (int) $attachmentId,
                    $record,
                    'fuel_record'
                );
            }
        }
    }
}
