<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\VehicleModel;
use App\Entity\VehicleMake;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/vehicle-models')]
class VehicleModelController extends AbstractController
{
    #[Route('', name: 'api_vehicle_models_list', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $makeId = $request->query->get('makeId');
        $year = $request->query->get('year');

        $qb = $em->getRepository(VehicleModel::class)->createQueryBuilder('m');

        if ($makeId) {
            $qb->where('m.make = :makeId')
               ->setParameter('makeId', $makeId);
        }

        if ($year) {
            $qb->andWhere('(m.startYear IS NULL OR m.startYear <= :year)')
               ->andWhere('(m.endYear IS NULL OR m.endYear >= :year)')
               ->setParameter('year', $year);
        }

        $models = $qb->orderBy('m.name', 'ASC')->getQuery()->getResult();

        return $this->json(array_map(function ($model) {
            return [
                'id' => $model->getId(),
                'name' => $model->getName(),
                'makeId' => $model->getMake()->getId(),
                'startYear' => $model->getStartYear(),
                'endYear' => $model->getEndYear()
            ];
        }, $models));
    }

    #[Route('', name: 'api_vehicle_models_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $make = $em->getRepository(VehicleMake::class)->find($data['makeId']);
        if (!$make) {
            return $this->json(['error' => 'Make not found'], 404);
        }

        $model = new VehicleModel();
        $model->setName($data['name']);
        $model->setMake($make);
        if (isset($data['startYear'])) {
            $model->setStartYear($data['startYear']);
        }
        if (isset($data['endYear'])) {
            $model->setEndYear($data['endYear']);
        }

        $em->persist($model);
        $em->flush();

        return $this->json([
            'id' => $model->getId(),
            'name' => $model->getName(),
            'makeId' => $model->getMake()->getId(),
            'startYear' => $model->getStartYear(),
            'endYear' => $model->getEndYear()
        ]);
    }
}
