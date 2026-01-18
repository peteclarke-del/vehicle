<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Vehicle;

class DepreciationCalculator
{
    public function calculateCurrentValue(Vehicle $vehicle): float
    {
        $purchaseCost = (float) $vehicle->getPurchaseCost();
        $method = $vehicle->getDepreciationMethod();

        return match ($method) {
            'straight_line' => $this->calculateStraightLine($vehicle),
            'declining_balance' => $this->calculateDecliningBalance($vehicle),
            'double_declining' => $this->calculateDoubleDeclining($vehicle),
            default => $purchaseCost,
        };
    }

    private function calculateStraightLine(Vehicle $vehicle): float
    {
        $purchaseCost = (float) $vehicle->getPurchaseCost();
        $years = $vehicle->getDepreciationYears();
        $purchaseDate = $vehicle->getPurchaseDate();
        $now = new \DateTime();

        $monthsOwned = $purchaseDate->diff($now)->m + ($purchaseDate->diff($now)->y * 12);
        $totalMonths = $years * 12;

        if ($monthsOwned >= $totalMonths) {
            return 0;
        }

        $depreciationPerMonth = $purchaseCost / $totalMonths;
        $totalDepreciation = $depreciationPerMonth * $monthsOwned;

        return max(0, $purchaseCost - $totalDepreciation);
    }

    private function calculateDecliningBalance(Vehicle $vehicle): float
    {
        $purchaseCost = (float) $vehicle->getPurchaseCost();
        $rate = (float) $vehicle->getDepreciationRate() / 100;
        $purchaseDate = $vehicle->getPurchaseDate();
        $now = new \DateTime();

        $yearsOwned = $purchaseDate->diff($now)->y + ($purchaseDate->diff($now)->m / 12);

        return $purchaseCost * pow(1 - $rate, $yearsOwned);
    }

    private function calculateDoubleDeclining(Vehicle $vehicle): float
    {
        $purchaseCost = (float) $vehicle->getPurchaseCost();
        $years = $vehicle->getDepreciationYears();
        $purchaseDate = $vehicle->getPurchaseDate();
        $now = new \DateTime();

        $rate = (2 / $years);
        $yearsOwned = $purchaseDate->diff($now)->y + ($purchaseDate->diff($now)->m / 12);

        return $purchaseCost * pow(1 - $rate, $yearsOwned);
    }

    public function calculateTotalDepreciation(Vehicle $vehicle): float
    {
        $purchaseCost = (float) $vehicle->getPurchaseCost();
        $currentValue = $this->calculateCurrentValue($vehicle);

        return $purchaseCost - $currentValue;
    }

    public function getDepreciationSchedule(Vehicle $vehicle, ?int $years = null): array
    {
        $years = $years ?? $vehicle->getDepreciationYears();
        $schedule = [];
        $purchaseCost = (float) $vehicle->getPurchaseCost();
        $method = $vehicle->getDepreciationMethod();
        $rate = (float) $vehicle->getDepreciationRate() / 100;

        for ($year = 0; $year <= $years; $year++) {
            $value = match ($method) {
                'straight_line' => max(0, $purchaseCost - (($purchaseCost / $years) * $year)),
                'declining_balance' => $purchaseCost * pow(1 - $rate, $year),
                'double_declining' => $purchaseCost * pow(1 - (2 / $years), $year),
                default => $purchaseCost,
            };

            $schedule[] = [
                'year' => $year,
                'value' => round($value, 2),
                'depreciation' => round($purchaseCost - $value, 2)
            ];
        }

        return $schedule;
    }
}
