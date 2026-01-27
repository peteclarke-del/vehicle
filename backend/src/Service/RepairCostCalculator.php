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
        $total = 0.0;

        $parts = $this->entityManager->getRepository(Part::class)->findBy(['motRecord' => $mot]);
        foreach ($parts as $p) {
            $cost = (float) ($p->getCost() ?? 0);
            $qty = (float) ($p->getQuantity() ?? 1);
            $total += $cost * max(1, $qty);
        }

        $consumables = $this->entityManager->getRepository(Consumable::class)->findBy(['motRecord' => $mot]);
        foreach ($consumables as $c) {
            $cost = (float) ($c->getCost() ?? 0);
            $qty = (float) ($c->getQuantity() ?? 1);
            $total += $cost * max(1, $qty);
        }

        $services = $this->entityManager->getRepository(ServiceRecord::class)->findBy(['motRecord' => $mot]);
        foreach ($services as $s) {
            $serviceTotal = (float) ($s->getTotalCost() ?? 0);
            $total += $serviceTotal;
        }

        $mot->setRepairCost(number_format($total, 2, '.', ''));
        $this->entityManager->persist($mot);
        $this->entityManager->flush();

        return $total;
    }
}
