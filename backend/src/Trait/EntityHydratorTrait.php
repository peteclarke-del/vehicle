<?php

declare(strict_types=1);

namespace App\Trait;

/**
 * Trait for common entity hydration patterns
 */
trait EntityHydratorTrait
{
    /**
     * Safely trim a string value, returning null if empty or not a string
     */
    private function trimString($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Hydrate date fields from string values
     */
    private function hydrateDates(array $data, array $dateFields): array
    {
        foreach ($dateFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                try {
                    $data[$field] = new \DateTime($data[$field]);
                } catch (\Exception $e) {
                    $data[$field] = null;
                }
            }
        }
        return $data;
    }

    /**
     * Trim string values in array
     */
    private function trimArrayValues(array $data, array $stringFields): array
    {
        foreach ($stringFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = $this->trimString($data[$field]);
            }
        }
        return $data;
    }

    /**
     * Safely extract numeric value
     */
    private function extractNumeric(array $data, string $field, bool $asInt = false): int|float|null
    {
        if (!isset($data[$field])) {
            return null;
        }

        $value = $data[$field];
        if ($asInt) {
            return is_numeric($value) ? (int)$value : null;
        }

        return is_numeric($value) ? (float)$value : null;
    }

    /**
     * Safely extract boolean value
     */
    private function extractBoolean(array $data, string $field): ?bool
    {
        if (!isset($data[$field])) {
            return null;
        }

        return (bool)$data[$field];
    }
}
