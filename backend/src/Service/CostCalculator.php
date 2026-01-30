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
        $total = 0;
        foreach ($vehicle->getFuelRecords() as $record) {
            $total += (float) $record->getCost();
        }
        return $total;
    }

    public function calculateTotalPartsCost(Vehicle $vehicle): float
    {
        $total = 0;
        foreach ($vehicle->getParts() as $part) {
            if ($part->getServiceRecord() || $part->getMotRecord()) {
                continue;
            }
            $total += (float) $part->getCost();
        }
        return $total;
    }

    public function calculateTotalConsumablesCost(Vehicle $vehicle): float
    {
        $total = 0;
        foreach ($vehicle->getConsumables() as $consumable) {
            if ($consumable->getServiceRecord() || $consumable->getMotRecord()) {
                continue;
            }
            if ($consumable->getCost()) {
                $total += (float) $consumable->getCost();
            }
        }
        return $total;
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
        $total = 0.0;
        $linkedMotIds = [];
        foreach ($vehicle->getServiceRecords() as $sr) {
            $total += (float) $sr->getTotalCost();
            $mot = $sr->getMotRecord();
            if ($mot && $mot->getId()) {
                $linkedMotIds[$mot->getId()] = true;
            }
        }

        foreach ($vehicle->getMotRecords() as $mot) {
            $motId = $mot->getId();
            if ($motId && isset($linkedMotIds[$motId])) {
                continue;
            }
            $total += (float) $mot->getTotalCost();
        }
        return $total;
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
