<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\VehicleType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Route('/api/vehicle-types')]
class VehicleTypeController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CacheInterface $lookupsCache
    ) {
    }

    #[Route('', name: 'api_vehicle_types_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $data = $this->lookupsCache->get('vehicle_types_list', function (ItemInterface $item) {
            $item->expiresAfter(3600); // 1 hour cache
            $item->tag(['vehicle_types']);
            
            $types = $this->entityManager->getRepository(VehicleType::class)->findAll();
            
            return array_map(function ($type) {
                return [
                    'id' => $type->getId(),
                    'name' => $type->getName()
                ];
            }, $types);
        });

        return $this->json($data);
    }

    #[Route('/{id}/consumable-types', name: 'api_vehicle_types_consumables', methods: ['GET'])]
    public function consumableTypes(int $id): JsonResponse
    {
        $data = $this->lookupsCache->get("vehicle_type_{$id}_consumables", function (ItemInterface $item) use ($id) {
            $item->expiresAfter(3600); // 1 hour cache
            $item->tag(['vehicle_types', "vehicle_type_{$id}"]);
            
            $type = $this->entityManager->getRepository(VehicleType::class)->find($id);
            
            if (!$type) {
                return null;
            }
            
            $consumableTypes = $type->getConsumableTypes();
            
            return array_map(function ($ct) {
                return [
                    'id' => $ct->getId(),
                    'name' => $ct->getName(),
                    'unit' => $ct->getUnit(),
                    'description' => $ct->getDescription()
                ];
            }, $consumableTypes->toArray());
        });

        if ($data === null) {
            return $this->json(['error' => 'Vehicle type not found'], 404);
        }

        return $this->json($data);
    }
}
