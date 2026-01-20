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

#[Route('/api/vehicle-makes')]
class VehicleMakeController extends AbstractController
{
    #[Route('', name: 'api_vehicle_makes_list', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $typeId = $request->query->get('vehicleTypeId');

        $qb = $em->getRepository(VehicleMake::class)->createQueryBuilder('m');

        if ($typeId) {
            $qb->where('m.vehicleType = :typeId')
               ->setParameter('typeId', $typeId);
        }

        $makes = $qb->orderBy('m.name', 'ASC')->getQuery()->getResult();

        return $this->json(array_map(function ($make) {
            return [
                'id' => $make->getId(),
                'name' => $make->getName(),
                'vehicleTypeId' => $make->getVehicleType()->getId()
            ];
        }, $makes));
    }

    #[Route('', name: 'api_vehicle_makes_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], 400);
        }

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

        return $this->json(
            [
                'id' => $make->getId(),
                'name' => $make->getName(),
                'vehicleTypeId' => $make->getVehicleType()->getId(),
            ]
        );
    }
}
