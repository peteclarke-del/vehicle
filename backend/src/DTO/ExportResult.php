<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Result object for export operations
 */
class ExportResult
{
    public function __construct(
        private bool $success,
        private array $data,
        private array $statistics = [],
        private ?string $message = null
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getStatistics(): array
    {
        return $this->statistics;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'statistics' => $this->statistics,
            'data' => $this->data,
        ];
    }

    public static function createSuccess(array $data, array $statistics, ?string $message = null): self
    {
        return new self(true, $data, $statistics, $message);
    }

    public static function createFailure(?string $message = null): self
    {
        return new self(false, [], [], $message);
    }
}
