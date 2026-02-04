<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Result object for import operations
 */
class ImportResult
{
    public function __construct(
        private bool $success,
        private array $statistics = [],
        private array $errors = [],
        private ?string $message = null,
        private ?array $vehicleMap = null
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getStatistics(): array
    {
        return $this->statistics;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getVehicleMap(): ?array
    {
        return $this->vehicleMap;
    }

    public function getImportedCount(): int
    {
        $imported = (int)($this->statistics['vehiclesImported'] ?? 0);
        if ($imported === 0 && is_array($this->vehicleMap)) {
            return count($this->vehicleMap);
        }
        return $imported;
    }

    public function getFailedCount(): int
    {
        return (int)($this->statistics['errors'] ?? 0);
    }

    public function getTotalCount(): int
    {
        $imported = $this->getImportedCount();
        $failed = $this->getFailedCount();
        return (int)($this->statistics['total'] ?? ($imported + $failed));
    }

    public function getExecutionTime(): ?float
    {
        $time = $this->statistics['processingTimeSeconds'] ?? null;
        return $time !== null ? (float)$time : null;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'statistics' => $this->statistics,
            'errors' => $this->errors,
        ];
    }

    public static function createSuccess(array $statistics, ?string $message = null, ?array $vehicleMap = null): self
    {
        return new self(true, $statistics, [], $message, $vehicleMap);
    }

    public static function createFailure(array $errors, ?string $message = null): self
    {
        return new self(false, [], $errors, $message);
    }
}
