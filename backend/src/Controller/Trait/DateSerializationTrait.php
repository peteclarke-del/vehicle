<?php

namespace App\Controller\Trait;

/**
 * Provides common date serialization methods for API responses
 */
trait DateSerializationTrait
{
    /**
     * Format a date for API output (Y-m-d format)
     *
     * @param \DateTimeInterface|null $date
     * @return string|null
     */
    protected function formatDate(?\DateTimeInterface $date): ?string
    {
        return $date?->format('Y-m-d');
    }

    /**
     * Format a datetime for API output (ISO 8601 format)
     *
     * @param \DateTimeInterface|null $date
     * @return string|null
     */
    protected function formatDateTime(?\DateTimeInterface $date): ?string
    {
        return $date?->format('c');
    }

    /**
     * Format a date with time for display (Y-m-d H:i:s format)
     *
     * @param \DateTimeInterface|null $date
     * @return string|null
     */
    protected function formatDateTimeDisplay(?\DateTimeInterface $date): ?string
    {
        return $date?->format('Y-m-d H:i:s');
    }
}
