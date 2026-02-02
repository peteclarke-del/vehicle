<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Vehicle;
use Doctrine\ORM\EntityManagerInterface;

class CostCalculator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DepreciationCalculator $depreciationCalculator
    ) {
    }

    public function calculateTotalFuelCost(Vehicle $vehicle): float
    {
        $dql = 'SELECT COALESCE(SUM(fr.cost), 0) FROM App\Entity\FuelRecord fr WHERE fr.vehicle = :vehicle';

        return (float) $this->entityManager->createQuery($dql)
            ->setParameter('vehicle', $vehicle)
            ->getSingleScalarResult();
    }

    public function calculateTotalPartsCost(Vehicle $vehicle): float
    {
        // Count standalone parts (not in service/MOT records) OR parts in service/MOT records but not included in their cost totals
        $dql = 'SELECT COALESCE(SUM(p.cost), 0) FROM App\Entity\Part p 
                WHERE p.vehicle = :vehicle 
                AND (
                    (p.serviceRecord IS NULL AND p.motRecord IS NULL)
                    OR (p.includedInServiceCost = false)
                )';

        return (float) $this->entityManager->createQuery($dql)
            ->setParameter('vehicle', $vehicle)
            ->getSingleScalarResult();
    }

    public function calculateTotalConsumablesCost(Vehicle $vehicle): float
    {
        // Count standalone consumables (not in service/MOT records) OR consumables in service/MOT records but not included in their cost totals
        $dql = 'SELECT COALESCE(SUM(c.cost), 0) FROM App\Entity\Consumable c 
                WHERE c.vehicle = :vehicle 
                AND (
                    (c.serviceRecord IS NULL AND c.motRecord IS NULL)
                    OR (c.includedInServiceCost = false)
                )';

        return (float) $this->entityManager->createQuery($dql)
            ->setParameter('vehicle', $vehicle)
            ->getSingleScalarResult();
    }

    public function calculateTotalRunningCost(Vehicle $vehicle): float
    {
        return $this->calculateTotalFuelCost($vehicle)
            + $this->calculateTotalPartsCost($vehicle)
            + $this->calculateTotalConsumablesCost($vehicle)
            + $this->calculateTotalServiceCost($vehicle);
    }

    public function calculateTotalCostToDate(Vehicle $vehicle): float
    {
        $purchaseCost = (float) $vehicle->getPurchaseCost();
        $runningCost = $this->calculateTotalRunningCost($vehicle);

        return $purchaseCost + $runningCost;
    }

    public function calculateCostPerMile(Vehicle $vehicle): ?float
    {
        $currentMileage = $vehicle->getCurrentMileage();

        if (!$currentMileage || $currentMileage <= 0) {
            return null;
        }
        $purchaseMileage = $this->determinePurchaseMileageFallback($vehicle);

        if ($purchaseMileage !== null) {
            $milesDriven = $currentMileage - $purchaseMileage;
            if ($milesDriven <= 0) {
                // If no miles have been driven since purchase, report the total cost-to-date
                // as the per-mile figure (treating the effective denominator as 1).
                return $this->calculateTotalCostToDate($vehicle);
            }
        } else {
            // No purchase mileage and no fuel-record fallback available - treat miles
            // since purchase as unknown rather than using the vehicle odometer. This
            // keeps the per-mile figure meaningful for recently purchased vehicles.
            $milesDriven = null;
        }

        // Include purchase cost when calculating cost-per-mile (total cost to date)
        $totalCost = $this->calculateTotalCostToDate($vehicle);

        // If miles-driven is unknown or zero, return total cost (purchase + running)
        // as the effective per-mile number for a newly acquired vehicle.
        if ($milesDriven === null || $milesDriven <= 0) {
            return $totalCost;
        }

        return $totalCost / $milesDriven;
    }

    private function determinePurchaseMileageFallback(Vehicle $vehicle): ?int
    {
        if (method_exists($vehicle, 'getPurchaseMileage') && $vehicle->getPurchaseMileage() !== null) {
            return $vehicle->getPurchaseMileage();
        }

        // Fallback to the earliest (minimum) mileage from fuel records, if available
        $fuelRecords = $vehicle->getFuelRecords()->toArray();
        $min = null;
        foreach ($fuelRecords as $fr) {
            $m = $fr->getMileage();
            if ($m === null) {
                continue;
            }
            if ($min === null || $m < $min) {
                $min = $m;
            }
        }
        return $min;
    }

    public function calculateAverageFuelConsumption(Vehicle $vehicle): ?float
    {
        $fuelRecords = $vehicle->getFuelRecords()->toArray();

        if (count($fuelRecords) < 2) {
            return null;
        }

        usort($fuelRecords, fn($a, $b) => $a->getMileage() <=> $b->getMileage());

        $totalLitres = 0;
        $totalDistance = 0;

        for ($i = 1; $i < count($fuelRecords); $i++) {
            $distance = $fuelRecords[$i]->getMileage() - $fuelRecords[$i - 1]->getMileage();
            $litres = (float) $fuelRecords[$i]->getLitres();

            if ($distance > 0) {
                $totalDistance += $distance;
                $totalLitres += $litres;
            }
        }

        if ($totalDistance <= 0) {
            return null;
        }

        return ($totalLitres / $totalDistance) * 100;
    }

    /**
     * Calculate total fuel cost for the last 12 months for a vehicle using a single query.
     * This avoids loading all fuel record entities into memory.
     */
    public function calculateTotalFuelCostLast12Months(Vehicle $vehicle): float
    {
        $cutoff = new \DateTimeImmutable('-1 year');

        $dql = 'SELECT SUM(fr.cost) FROM App\\Entity\\FuelRecord fr WHERE fr.vehicle = :vehicle AND fr.date >= :cutoff';
        $query = $this->entityManager->createQuery($dql)
            ->setParameter('vehicle', $vehicle)
            ->setParameter('cutoff', $cutoff);

        $result = $query->getSingleScalarResult();

        return $result !== null ? (float) $result : 0.0;
    }

    public function getVehicleStats(Vehicle $vehicle): array
    {
        $currentValue = $this->depreciationCalculator->calculateCurrentValue($vehicle);
        $totalDepreciation = $this->depreciationCalculator->calculateTotalDepreciation($vehicle);

        return [
            'purchaseCost' => (float) $vehicle->getPurchaseCost(),
            'currentValue' => round($currentValue, 2),
            'totalDepreciation' => round($totalDepreciation, 2),
            'totalFuelCost' => round($this->calculateTotalFuelCost($vehicle), 2),
            'totalPartsCost' => round($this->calculateTotalPartsCost($vehicle), 2),
            'totalServiceCost' => round($this->calculateTotalServiceCost($vehicle), 2),
            'totalConsumablesCost' => round($this->calculateTotalConsumablesCost($vehicle), 2),
            'totalRunningCost' => round($this->calculateTotalRunningCost($vehicle), 2),
            'totalCostToDate' => round($this->calculateTotalCostToDate($vehicle), 2),
            'costPerMile' => (
                ($cpm = $this->calculateCostPerMile($vehicle)) !== null
            ) ? round($cpm, 2) : null,
            'averageFuelConsumption' => $this->calculateAverageFuelConsumption($vehicle)
                ? round($this->calculateAverageFuelConsumption($vehicle), 2)
                : null,
            'currentMileage' => $vehicle->getCurrentMileage(),
            'milesSincePurchase' => $this->determineMilesSincePurchase($vehicle)
        ];
    }

    public function calculateTotalServiceCost(Vehicle $vehicle): float
    {
        // Sum of service record costs
        $serviceDql = 'SELECT COALESCE(SUM(sr.laborCost + sr.partsCost + sr.consumablesCost + sr.additionalCosts), 0) 
                       FROM App\Entity\ServiceRecord sr 
                       WHERE sr.vehicle = :vehicle';

        $serviceTotal = (float) $this->entityManager->createQuery($serviceDql)
            ->setParameter('vehicle', $vehicle)
            ->getSingleScalarResult();

        // Get IDs of MOT records linked to service records
        $linkedMotIdsDql = 'SELECT IDENTITY(sr.motRecord) 
                            FROM App\Entity\ServiceRecord sr 
                            WHERE sr.vehicle = :vehicle 
                            AND sr.motRecord IS NOT NULL';

        $linkedMotIds = $this->entityManager->createQuery($linkedMotIdsDql)
            ->setParameter('vehicle', $vehicle)
            ->getResult();

        $linkedMotIdsArray = array_column($linkedMotIds, 1);

        // Sum of MOT costs not linked to service records
        $motDql = 'SELECT COALESCE(SUM(m.testCost + m.repairCost), 0) 
                   FROM App\Entity\MotRecord m 
                   WHERE m.vehicle = :vehicle';

        if (!empty($linkedMotIdsArray)) {
            $motDql .= ' AND m.id NOT IN (:linkedIds)';
            $motTotal = (float) $this->entityManager->createQuery($motDql)
                ->setParameter('vehicle', $vehicle)
                ->setParameter('linkedIds', $linkedMotIdsArray)
                ->getSingleScalarResult();
        } else {
            $motTotal = (float) $this->entityManager->createQuery($motDql)
                ->setParameter('vehicle', $vehicle)
                ->getSingleScalarResult();
        }

        return $serviceTotal + $motTotal;
    }

    private function determineMilesSincePurchase(Vehicle $vehicle): ?int
    {
        $current = $vehicle->getCurrentMileage();
        if ($current === null) {
            return null;
        }

        $purchase = $this->determinePurchaseMileageFallback($vehicle);
        if ($purchase === null) {
            return null;
        }

        $diff = $current - $purchase;
        return $diff >= 0 ? $diff : 0;
    }
}
