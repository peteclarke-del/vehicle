<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Exception thrown during vehicle export operations
 */
class ExportException extends \RuntimeException
{
    public function __construct(
        string $message,
        private ?string $context = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getContext(): ?string
    {
        return $this->context;
    }
}
