<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\VehicleType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/vehicle-types')]
class VehicleTypeController extends AbstractController
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[Route('', name: 'api_vehicle_types_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $types = $this->entityManager->getRepository(VehicleType::class)->findAll();

        $data = array_map(function ($type) {
            return [
                'id' => $type->getId(),
                'name' => $type->getName()
            ];
        }, $types);

        return $this->json($data);
    }

    #[Route('/{id}/consumable-types', name: 'api_vehicle_types_consumables', methods: ['GET'])]
    public function consumableTypes(int $id): JsonResponse
    {
        $type = $this->entityManager->getRepository(VehicleType::class)->find($id);

        if (!$type) {
            return $this->json(['error' => 'Vehicle type not found'], 404);
        }

        $consumableTypes = $type->getConsumableTypes();

        $data = array_map(function ($ct) {
            return [
                'id' => $ct->getId(),
                'name' => $ct->getName(),
                'unit' => $ct->getUnit(),
                'description' => $ct->getDescription()
            ];
        }, $consumableTypes->toArray());

        return $this->json($data);
    }
}
