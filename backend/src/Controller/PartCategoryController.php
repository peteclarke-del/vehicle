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
use App\Controller\Trait\JsonValidationTrait;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Route('/api/part-categories')]
class PartCategoryController extends AbstractController
{
    use JsonValidationTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private CacheInterface $lookupsCache
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
        $cacheKey = $vehicleTypeId ? "part_categories_type_{$vehicleTypeId}" : 'part_categories_all';
        
        $data = $this->lookupsCache->get($cacheKey, function (ItemInterface $item) use ($vehicleTypeId) {
            $item->expiresAfter(3600); // 1 hour cache
            $tags = ['part_categories'];
            if ($vehicleTypeId) {
                $tags[] = "vehicle_type_{$vehicleTypeId}";
            }
            $item->tag($tags);
            
            $repo = $this->entityManager->getRepository(PartCategory::class);

            if ($vehicleTypeId) {
                $vt = $this->entityManager->getRepository(VehicleType::class)->find((int)$vehicleTypeId);
                if (!$vt) {
                    return [];
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

            return array_map(fn(PartCategory $c) => [
                'id' => $c->getId(), 
                'name' => $c->getName(), 
                'vehicleType' => $c->getVehicleType()?->getId(), 
                'description' => $c->getDescription()
            ], $unique);
        });

        return $this->json($data);
    }

    #[Route('', name: 'api_part_categories_create', methods: ['POST'])]
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
// Invalidate caches
        $tags = ['part_categories'];
        if ($vehicleType) {
            $tags[] = "vehicle_type_{$vehicleType->getId()}";
        }
        $this->lookupsCache->invalidateTags($tags);

        
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
