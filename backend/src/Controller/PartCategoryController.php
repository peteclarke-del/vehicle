<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\PartCategory;
use App\Entity\VehicleType;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/part-categories')]
class PartCategoryController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    #[Route('', name: 'api_part_categories_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $vehicleTypeId = $request->query->get('vehicleTypeId');
        $repo = $this->entityManager->getRepository(PartCategory::class);

        if ($vehicleTypeId) {
            $vt = $this->entityManager->getRepository(VehicleType::class)->find((int)$vehicleTypeId);
            if (!$vt) {
                return $this->json([], 200);
            }
            // Find categories for this vehicle type OR categories with no specific type (generic)
            $qb = $repo->createQueryBuilder('pc')
                ->where('pc.vehicleType = :vt')
                ->orWhere('pc.vehicleType IS NULL')
                ->setParameter('vt', $vt)
                ->orderBy('pc.name', 'ASC')
                ->getQuery();
            $items = $qb->getResult();
        } else {
            $items = $repo->findBy([], ['name' => 'ASC']);
        }

        // Remove duplicates by name (keep first occurrence)
        $seen = [];
        $unique = [];
        foreach ($items as $item) {
            $name = $item->getName();
            if (!isset($seen[$name])) {
                $seen[$name] = true;
                $unique[] = $item;
            }
        }

        $data = array_map(fn(PartCategory $c) => ['id' => $c->getId(), 'name' => $c->getName(), 'vehicleType' => $c->getVehicleType()?->getId(), 'description' => $c->getDescription()], $unique);

        return $this->json($data);
    }

    #[Route('', name: 'api_part_categories_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            return $this->json(['error' => 'Name required'], 400);
        }

        $vehicleType = null;
        if (!empty($data['vehicleTypeId'])) {
            $vehicleType = $this->entityManager->getRepository(VehicleType::class)->find((int)$data['vehicleTypeId']);
            if (!$vehicleType) {
                return $this->json(['error' => 'Vehicle type not found'], 404);
            }
        }

        // Avoid duplicates (simple exact-name check for now)
        $repo = $this->entityManager->getRepository(PartCategory::class);
        $existing = $repo->findOneBy(['name' => $name, 'vehicleType' => $vehicleType]);
        if ($existing instanceof PartCategory) {
            return $this->json(['id' => $existing->getId(), 'name' => $existing->getName()], 200);
        }

        $pc = new PartCategory();
        $pc->setName($name);
        $pc->setVehicleType($vehicleType);
        $pc->setDescription($data['description'] ?? null);

        $this->entityManager->persist($pc);
        $this->entityManager->flush();

        return $this->json(['id' => $pc->getId(), 'name' => $pc->getName(), 'vehicleType' => $pc->getVehicleType()?->getId(), 'description' => $pc->getDescription()], 201);
    }
}
