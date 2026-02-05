<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\MotRecord;
use App\Entity\Part;
use App\Entity\Consumable;
use App\Entity\ServiceRecord;
use Doctrine\ORM\EntityManagerInterface;

class RepairCostCalculator
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function recalculateAndPersist(MotRecord $mot): float
    {
        $this->entityManager->beginTransaction();

        try {
            // Calculate parts cost (include items created for MOT, exclude existing items that were linked)
            $partsDql = 'SELECT COALESCE(SUM(p.cost * CASE WHEN p.quantity IS NULL THEN 1 ELSE p.quantity END), 0) 
                         FROM App\Entity\Part p 
                         WHERE p.motRecord = :mot 
                         AND (p.includedInServiceCost = true OR p.includedInServiceCost IS NULL)';
            $partsTotal = (float) $this->entityManager->createQuery($partsDql)
                ->setParameter('mot', $mot)
                ->getSingleScalarResult();

            // Calculate consumables cost (include items created for MOT, exclude existing items that were linked)
            $consumablesDql = 'SELECT COALESCE(SUM(c.cost * CASE WHEN c.quantity IS NULL THEN 1 ELSE c.quantity END), 0) 
                               FROM App\Entity\Consumable c 
                               WHERE c.motRecord = :mot 
                               AND (c.includedInServiceCost = true OR c.includedInServiceCost IS NULL)';
            $consumablesTotal = (float) $this->entityManager->createQuery($consumablesDql)
                ->setParameter('mot', $mot)
                ->getSingleScalarResult();

            // Calculate service records total only if includedInMotCost is true (checked)
            $servicesDql = 'SELECT COALESCE(SUM(s.laborCost + s.partsCost + s.consumablesCost + s.additionalCosts), 0) 
                            FROM App\Entity\ServiceRecord s 
                            WHERE s.motRecord = :mot
                            AND s.includedInMotCost = true';
            $servicesTotal = (float) $this->entityManager->createQuery($servicesDql)
                ->setParameter('mot', $mot)
                ->getSingleScalarResult();

            // Sum all components
            $total = $partsTotal + $consumablesTotal + $servicesTotal;

            $mot->setRepairCost(number_format($total, 2, '.', ''));
            $this->entityManager->persist($mot);
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $total;
        } catch (\Exception $e) {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }
            throw $e;
        }
    }
}
