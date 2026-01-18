<?php

declare(strict_types=1);

namespace App\Service\SiteAdapter;

interface SiteAdapterInterface
{
    /**
     * Check if this adapter can handle the given host and HTML
     *
     * @param string $host The hostname
     * @param string $html The HTML content
     *
     * @return bool
     */
    public function supports(string $host, string $html): bool;

    /**
     * Parse the HTML and extract product data
     *
     * @param string $html The HTML content
     *
     * @return array<string, mixed> Product data
     */
    public function parse(string $html): array;

    /**
     * Set contextual information for the adapter
     *
     * @param array<string, mixed> $context Context data
     *
     * @return void
     */
    public function setContext(array $context): void;
}
