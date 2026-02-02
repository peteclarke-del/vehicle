<?php

declare(strict_types=1);

namespace App\Config;

/**
 * class ImportExportConfig
 *
 * Configuration for import/export operations
 */
class ImportExportConfig
{
    /**
     * function __construct
     *
     * @param int $batchSize
     * @param int $memoryLimitMB
     * @param int $maxExecutionTime
     * @param array $allowedMimeTypes
     * @param int $maxFileSizeMB
     * @param bool $enableMemoryCleanup
     * @param int $cleanupInterval
     *
     * @return void
     */
    public function __construct(
        private readonly int $batchSize = 25,
        private readonly int $memoryLimitMB = 1024,
        private readonly int $maxExecutionTime = 0,
        private readonly array $allowedMimeTypes = ['application/zip', 'application/json'],
        private readonly int $maxFileSizeMB = 100,
        private readonly bool $enableMemoryCleanup = true,
        private readonly int $cleanupInterval = 25,
    ) {}

    /**
     * function getBatchSize
     *
     * @return int
     */
    public function getBatchSize(): int
    {
        return $this->batchSize;
    }

    /**
     * function getMemoryLimitMB
     *
     * @return int
     */
    public function getMemoryLimitMB(): int
    {
        return $this->memoryLimitMB;
    }

    /**
     * function getMaxExecutionTime
     *
     * @return int
     */
    public function getMaxExecutionTime(): int
    {
        return $this->maxExecutionTime;
    }

    /**
     * function getAllowedMimeTypes
     *
     * @return array
     */
    public function getAllowedMimeTypes(): array
    {
        return $this->allowedMimeTypes;
    }

    /**
     * function getMaxFileSizeMB
     *
     * @return int
     */
    public function getMaxFileSizeMB(): int
    {
        return $this->maxFileSizeMB;
    }

    /**
     * function getMaxFileSizeBytes
     *
     * @return int
     */
    public function getMaxFileSizeBytes(): int
    {
        return $this->maxFileSizeMB * 1024 * 1024;
    }

    /**
     * function isMemoryCleanupEnabled
     *
     * @return bool
     */
    public function isMemoryCleanupEnabled(): bool
    {
        return $this->enableMemoryCleanup;
    }

    /**
     * function getCleanupInterval
     *
     * @return int
     */
    public function getCleanupInterval(): int
    {
        return $this->cleanupInterval;
    }
}
