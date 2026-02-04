<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Exception thrown during vehicle import operations
 */
class ImportException extends \RuntimeException
{
    public function __construct(
        string $message,
        private ?array $validationErrors = null,
        private ?string $context = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getValidationErrors(): ?array
    {
        return $this->validationErrors;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function hasValidationErrors(): bool
    {
        return !empty($this->validationErrors);
    }
}
