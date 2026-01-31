<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\SecurityFeature;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api/security-features", name="security_features_")
 */
class SecurityFeatureController extends AbstractController
{
    /**
     * @Route("", name="list", methods={"GET"})
     */
    public function list(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $vehicleTypeId = $request->query->get('vehicleTypeId');

        $qb = $entityManager->createQueryBuilder();
        $qb->select('sf.id', 'sf.name', 'sf.description', 'vt.id as vehicleTypeId', 'vt.name as vehicleTypeName')
            ->from(SecurityFeature::class, 'sf')
            ->innerJoin('sf.vehicleType', 'vt')
            ->orderBy('sf.name', 'ASC');

        if ($vehicleTypeId) {
            $qb->where('vt.id = :vehicleTypeId')
                ->setParameter('vehicleTypeId', $vehicleTypeId);
        }

        $results = $qb->getQuery()->getArrayResult();

        return new JsonResponse($results);
    }
}
