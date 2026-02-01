<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\SecurityFeature;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @Route("/api/security-features", name="security_features_")
 */
class SecurityFeatureController extends AbstractController
{
    public function __construct(private CacheInterface $lookupsCache)
    {
    }

    /**
     * @Route("", name="list", methods={"GET"})
     */
    public function list(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $vehicleTypeId = $request->query->get('vehicleTypeId');
        $cacheKey = $vehicleTypeId ? "security_features_type_{$vehicleTypeId}" : 'security_features_all';
        
        $data = $this->lookupsCache->get($cacheKey, function (ItemInterface $item) use ($entityManager, $vehicleTypeId) {
            $item->expiresAfter(3600); // 1 hour cache
            $tags = ['security_features'];
            if ($vehicleTypeId) {
                $tags[] = "vehicle_type_{$vehicleTypeId}";
            }
            $item->tag($tags);
            
            $qb = $entityManager->createQueryBuilder();
            $qb->select('sf.id', 'sf.name', 'sf.description', 'vt.id as vehicleTypeId', 'vt.name as vehicleTypeName')
                ->from(SecurityFeature::class, 'sf')
                ->innerJoin('sf.vehicleType', 'vt')
                ->orderBy('sf.name', 'ASC');
            
            if ($vehicleTypeId) {
                $qb->where('vt.id = :vehicleTypeId')
                    ->setParameter('vehicleTypeId', $vehicleTypeId);
            }
            
            return $qb->getQuery()->getArrayResult();
        });

        return new JsonResponse($data);
    }
}
