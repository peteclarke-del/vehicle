<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\VehicleMake;
use App\Entity\VehicleType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Controller\Trait\JsonValidationTrait;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Route('/api/vehicle-makes')]
class VehicleMakeController extends AbstractController
{
    use JsonValidationTrait;

    public function __construct(private CacheInterface $lookupsCache)
    {
    }

    #[Route('', name: 'api_vehicle_makes_list', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $typeId = $request->query->get('vehicleTypeId');
        $cacheKey = $typeId ? "vehicle_makes_type_{$typeId}" : 'vehicle_makes_all';
        
        $data = $this->lookupsCache->get($cacheKey, function (ItemInterface $item) use ($em, $typeId) {
            $item->expiresAfter(3600); // 1 hour cache
            $tags = ['vehicle_makes'];
            if ($typeId) {
                $tags[] = "vehicle_type_{$typeId}";
            }
            $item->tag($tags);
            
            $qb = $em->getRepository(VehicleMake::class)->createQueryBuilder('m');
            
            if ($typeId) {
                $qb->where('m.vehicleType = :typeId')
                   ->setParameter('typeId', $typeId);
            }
            
            $makes = $qb->orderBy('m.name', 'ASC')->getQuery()->getResult();
            
            return array_map(function ($make) {
                return [
                    'id' => $make->getId(),
                    'name' => $make->getName(),
                    'vehicleTypeId' => $make->getVehicleType()->getId()
                ];
            }, $makes);
        });

        return $this->json($data);
    }

    #[Route('', name: 'api_vehicle_makes_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $validation = $this->validateJsonRequest($request);
        if ($validation['error']) {
            return $validation['error'];
        }
        $data = $validation['data'];

        if (empty($data['vehicleTypeId'])) {
            return $this->json(['error' => 'Missing vehicleTypeId'], 400);
        }

        if (empty($data['name'])) {
            return $this->json(['error' => 'Missing name'], 400);
        }

        $vehicleTypeRepo = $em->getRepository(VehicleType::class);
        $vehicleType = $vehicleTypeRepo->find($data['vehicleTypeId']);
        if (!$vehicleType) {
            return $this->json(['error' => 'Vehicle type not found'], 404);
        }

        $make = new VehicleMake();
        $make->setName((string) $data['name']);
        $make->setVehicleType($vehicleType);

        $em->persist($make);
        $em->flush();

        // Invalidate caches for this vehicle type
        $this->lookupsCache->invalidateTags(['vehicle_makes', "vehicle_type_{$vehicleType->getId()}"]);

        return $this->json(
            [
                'id' => $make->getId(),
                'name' => $make->getName(),
                'vehicleTypeId' => $make->getVehicleType()->getId(),
            ]
        );
    }
}
