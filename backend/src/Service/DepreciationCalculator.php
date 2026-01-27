<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Vehicle;

class DepreciationCalculator
{
    /**
     * Backwards-compatible calculateCurrentValue. An optional second
     * parameter enables mileage-based adjustment used by tests.
     */
    public function calculateCurrentValue(Vehicle $vehicle, bool $adjustForMileage = false): float
    {
        $purchaseCost = (float) $vehicle->getPurchaseCost();
        $method = $vehicle->getDepreciationMethod();

        // If there's no purchase cost, nothing to depreciate.
        if ($purchaseCost <= 0.0) {
            return 0.0;
        }

        // If purchase date is not set, assume no depreciation has occurred.
        if (!$vehicle->getPurchaseDate()) {
            return (float) round($purchaseCost, 2);
        }

        $value = match ($method) {
            'straight_line' => $this->calculateStraightLine($vehicle),
            'declining_balance' => $this->calculateDecliningBalance($vehicle),
            'double_declining' => $this->calculateDoubleDeclining($vehicle),
            'automotive_standard' => $this->calculateAutomotiveStandardCurrentValue($vehicle),
            default => $purchaseCost,
        };

        if ($adjustForMileage) {
            $mileage = (int) $vehicle->getCurrentMileage();
            // Apply a modest mileage-related penalty: more mileage => lower value.
            // This is intentionally simple and deterministic for tests.
            $penaltyFactor = min(0.5, max(0.0, ($mileage - 10000) / 200000));
            $value = $value * (1 - $penaltyFactor);
        }

        return (float) round(max(0.0, $value), 2);
    }

    private function calculateAutomotiveStandardCurrentValue(Vehicle $vehicle): float
    {
        $purchaseCost = (float) $vehicle->getPurchaseCost();
        $purchaseDate = $vehicle->getPurchaseDate();
        if (!$purchaseDate) {
            return $purchaseCost;
        }

        $now = new \DateTime();
        $yearsOwned = $purchaseDate->diff($now)->y + ($purchaseDate->diff($now)->m / 12);

        // Use whole-year steps from the automotive standard table (year 0,1,2...)
        $yearIndex = (int) floor($yearsOwned);
        return $this->automotiveStandardValue($purchaseCost, max(0, $yearIndex));
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
        // Keep original behaviour but expose a compatibility wrapper
        // through `generateSchedule()` below.
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

            $schedule[$year] = (float) round($value, 2);
        }

        return $schedule;
    }

    /* Compatibility API expected by tests */
    public function generateSchedule(Vehicle $vehicle, int $years, string $method = null, float $rate = null, float $minValue = 0.0): array
    {
        // Determine method/rate from args or vehicle
        $method = $method ?? $vehicle->getDepreciationMethod();
        $rate = $rate ?? ((float) $vehicle->getDepreciationRate() / 100);

        $purchaseCost = (float) $vehicle->getPurchaseCost();

        $schedule = [];
        for ($year = 0; $year <= $years; $year++) {
            $value = match ($method) {
                'straight_line' => max(0, $purchaseCost - (($purchaseCost / $years) * $year)),
                'declining_balance' => $purchaseCost * pow(1 - $rate, $year),
                'double_declining' => $purchaseCost * pow(1 - (2 / $years), $year),
                'automotive_standard' => $this->automotiveStandardValue($purchaseCost, $year),
                default => $purchaseCost,
            };

            $value = max($minValue, (float) round($value, 2));
            $schedule[$year] = $value;
        }

        return $schedule;
    }

    private function automotiveStandardValue(float $purchaseCost, int $year): float
    {
        if ($year === 0) {
            return $purchaseCost;
        }

        if ($year === 1) {
            return $purchaseCost * 0.8; // 20% first year
        }

        // years 2+ : 15% per year from previous
        $value = $purchaseCost * 0.8;
        for ($y = 2; $y <= $year; $y++) {
            $value *= 0.85;
        }

        return $value;
    }

    public function exportSchedule(array $schedule, string $format = 'array'): array
    {
        // Tests expect an array with 'years' and 'values' keys
        $years = array_keys($schedule);
        $values = array_values($schedule);

        return [
            'years' => $years,
            'values' => $values,
        ];
    }

    public function calculateDepreciationPercentage(Vehicle $vehicle): float
    {
        $purchaseCost = (float) $vehicle->getPurchaseCost();
        if ($purchaseCost <= 0.0) {
            return 0.0;
        }

        $totalDepreciation = $this->calculateTotalDepreciation($vehicle);

        return (float) round(($totalDepreciation / $purchaseCost) * 100, 2);
    }

    public function calculateValueAtDate(Vehicle $vehicle, \DateTimeInterface $date): float
    {
        $years = (int) $vehicle->getDepreciationYears();
        $diff = $vehicle->getPurchaseDate() ? $vehicle->getPurchaseDate()->diff($date)->y : 0;
        $schedule = $this->generateSchedule($vehicle, max($years, $diff));
        return $schedule[min($diff, count($schedule) - 1)];
    }

    public function calculateAnnualDepreciationRate(Vehicle $vehicle): float
    {
        return (float) $vehicle->getDepreciationRate();
    }
}
