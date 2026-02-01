<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Trait\UserSecurityTrait;
use App\Controller\Trait\JsonValidationTrait;
use App\Entity\RoadTax;
use App\Entity\Vehicle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/road-tax')]
class RoadTaxController extends AbstractController
{
    use UserSecurityTrait;
    use JsonValidationTrait;

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $vehicleId = $request->query->get('vehicleId');
        $user = $this->getUserEntity();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        if ($vehicleId) {
            $vehicle = $this->entityManager->getRepository(Vehicle::class)->find((int)$vehicleId);
            if (!$vehicle || (!$this->isAdminForUser($user) && $vehicle->getOwner()->getId() !== $user->getId())) {
                return new JsonResponse(['error' => 'Vehicle not found'], 404);
            }

            $records = $this->entityManager->getRepository(RoadTax::class)
                ->findBy(['vehicle' => $vehicle], ['startDate' => 'DESC']);
        } else {
            // Fetch records for all vehicles the user can see
            $vehicleRepo = $this->entityManager->getRepository(Vehicle::class);
            $vehicles = $this->isAdminForUser($user) ? $vehicleRepo->findAll() : $vehicleRepo->findBy(['owner' => $user]);
            if (empty($vehicles)) {
                return new JsonResponse([]);
            }

            $qb = $this->entityManager->createQueryBuilder()
                ->select('r')
                ->from(RoadTax::class, 'r')
                ->where('r.vehicle IN (:vehicles)')
                ->setParameter('vehicles', $vehicles)
                ->orderBy('r.startDate', 'DESC');

            $records = $qb->getQuery()->getResult();
        }

        return new JsonResponse(array_map(fn($r) => $this->serializeRoadTax($r), $records));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $validation = $this->validateJsonRequest($request);
        if ($validation['error']) {
            return $validation['error'];
        }
        $data = $validation['data'];

        $vehicle = $this->entityManager->getRepository(Vehicle::class)->find($data['vehicleId'] ?? null);
        $user = $this->getUserEntity();
        if (!$vehicle || !$user || (!$this->isAdminForUser($user) && $vehicle->getOwner()->getId() !== $user->getId())) {
            return new JsonResponse(['error' => 'Vehicle not found'], 404);
        }

        if ($vehicle->isRoadTaxExempt()) {
            return new JsonResponse(['error' => 'Vehicle is road tax exempt'], 400);
        }

        $rt = new RoadTax();
        $rt->setVehicle($vehicle);
        $this->updateRoadTaxFromData($rt, $data);

        $this->entityManager->persist($rt);
        $this->entityManager->flush();

        return new JsonResponse($this->serializeRoadTax($rt), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $rt = $this->entityManager->getRepository(RoadTax::class)->find($id);
        $user = $this->getUserEntity();
        if (!$rt || !$user || (!$this->isAdminForUser($user) && $rt->getVehicle()->getOwner()->getId() !== $user->getId())) {
            return new JsonResponse(['error' => 'Road tax record not found'], 404);
        }

        if ($rt->getVehicle()->isRoadTaxExempt()) {
            return new JsonResponse(['error' => 'Vehicle is road tax exempt'], 400);
        }

        $validation = $this->validateJsonRequest($request);
        if ($validation['error']) {
            return $validation['error'];
        }
        $data = $validation['data'];
        $this->updateRoadTaxFromData($rt, $data);

        $this->entityManager->flush();

        return new JsonResponse($this->serializeRoadTax($rt));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $rt = $this->entityManager->getRepository(RoadTax::class)->find($id);
        $user = $this->getUserEntity();
        if (!$rt || !$user || (!$this->isAdminForUser($user) && $rt->getVehicle()->getOwner()->getId() !== $user->getId())) {
            return new JsonResponse(['error' => 'Road tax record not found'], 404);
        }
        // Allow deletion even if the vehicle is marked road-tax-exempt.
        $this->entityManager->remove($rt);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Road tax record deleted']);
    }

    private function serializeRoadTax(RoadTax $rt): array
    {
        return [
            'id' => $rt->getId(),
            'vehicleId' => $rt->getVehicle()->getId(),
            'startDate' => $rt->getStartDate()?->format('Y-m-d'),
            'expiryDate' => $rt->getExpiryDate()?->format('Y-m-d'),
            'amount' => $rt->getAmount(),
            'frequency' => $rt->getFrequency(),
            'sorn' => $rt->getSorn(),
            'notes' => $rt->getNotes(),
            'createdAt' => $rt->getCreatedAt()?->format('c'),
        ];
    }

    private function updateRoadTaxFromData(RoadTax $rt, array $data): void
    {
        if (isset($data['startDate']) && $data['startDate']) {
            $rt->setStartDate(new \DateTime($data['startDate']));
        }
        if (isset($data['expiryDate']) && $data['expiryDate']) {
            $rt->setExpiryDate(new \DateTime($data['expiryDate']));
        }
        if (isset($data['amount'])) {
            $rt->setAmount($data['amount'] === null ? null : (string)$data['amount']);
        }
        if (isset($data['sorn'])) {
            $rt->setSorn((bool)$data['sorn']);
        }
        if (isset($data['notes'])) {
            $rt->setNotes($data['notes']);
        }
        if (isset($data['frequency'])) {
            $rt->setFrequency($data['frequency']);
        }
    }
}
