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
            $total += (float) $part->getCost();
        }
        return $total;
    }

    public function calculateTotalConsumablesCost(Vehicle $vehicle): float
    {
        $total = 0;
        foreach ($vehicle->getConsumables() as $consumable) {
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
            + $this->calculateTotalConsumablesCost($vehicle);
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

        $totalCost = $this->calculateTotalRunningCost($vehicle);

        return $totalCost / $currentMileage;
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
            'totalConsumablesCost' => round($this->calculateTotalConsumablesCost($vehicle), 2),
            'totalRunningCost' => round($this->calculateTotalRunningCost($vehicle), 2),
            'totalCostToDate' => round($this->calculateTotalCostToDate($vehicle), 2),
            'costPerMile' => $this->calculateCostPerMile($vehicle)
                ? round($this->calculateCostPerMile($vehicle), 2)
                : null,
            'averageFuelConsumption' => $this->calculateAverageFuelConsumption($vehicle)
                ? round($this->calculateAverageFuelConsumption($vehicle), 2)
                : null,
            'currentMileage' => $vehicle->getCurrentMileage()
        ];
    }
}
