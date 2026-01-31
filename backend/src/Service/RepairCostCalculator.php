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
            // Use single aggregate query to calculate total from all sources
            $dql = 'SELECT 
                        (SELECT COALESCE(SUM(p.cost * CASE WHEN p.quantity IS NULL THEN 1 ELSE p.quantity END), 0) 
                         FROM App\Entity\Part p 
                         WHERE p.motRecord = :mot) +
                        (SELECT COALESCE(SUM(c.cost * CASE WHEN c.quantity IS NULL THEN 1 ELSE c.quantity END), 0) 
                         FROM App\Entity\Consumable c 
                         WHERE c.motRecord = :mot) +
                        (SELECT COALESCE(SUM(s.laborCost + s.partsCost + s.consumablesCost + s.additionalCosts), 0) 
                         FROM App\Entity\ServiceRecord s 
                         WHERE s.motRecord = :mot)
                    AS total';

            $total = (float) $this->entityManager->createQuery($dql)
                ->setParameter('mot', $mot)
                ->getSingleScalarResult();

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
