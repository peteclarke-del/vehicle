<?php

declare(strict_types=1);

namespace App\Service\SiteAdapter;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class ShopifyAdapter implements SiteAdapterInterface
{
    private ?string $finalUrl = null;
    private ?string $baseUrl = null;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return void
     */
    public function setContext(array $context): void
    {
        $finalUrl = $context['final_url'] ?? null;
        $this->finalUrl = is_string($finalUrl) ? $finalUrl : null;

        if ($this->finalUrl) {
            $parts = parse_url($this->finalUrl);
            if (
                is_array($parts)
                && isset($parts['scheme'])
                && isset($parts['host'])
            ) {
                $this->baseUrl = $parts['scheme'] . '://' . $parts['host'];
            }
        }
    }

    public function supports(string $host, string $html): bool
    {
        return str_contains($html, 'cdn.shopify.com')
            || str_contains($html, 'ShopifyAnalytics')
            || str_contains($html, 'window.Shopify');
    }

    /**
     * @param string $html
     *
     * @return array<string, mixed>
     */
    public function parse(string $html): array
    {
        if (!$this->finalUrl || !$this->baseUrl) {
            return [];
        }

        // ✅ Shopify ALWAYS resolves products to /products/{handle}
        if (!preg_match('#/products/([^/?]+)#', $this->finalUrl, $m)) {
            return [];
        }

        $handle = $m[1];

        return $this->fetchProductJson($handle)
            ?: $this->parseEmbeddedJson($html);
    }

    private function fetchProductJson(string $handle): array
    {
        $url = "{$this->baseUrl}/products/{$handle}.js";

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'Mozilla/5.0',
                ],
                'timeout' => 10,
            ]);

            $json = $response->getContent(false);
            $data = json_decode($json, true);

            if (!isset($data['title'])) {
                return [];
            }

            $variant = $data['variants'][0] ?? [];

            return array_filter([
                'name' => $data['title'],
                'description' => trim(strip_tags($data['description'] ?? '')),
                'price' => isset($variant['price'])
                    ? ((float)$variant['price'] / 100)
                    : null,
                'manufacturer' => $data['vendor'] ?? null,
                'partNumber' => $variant['sku'] ?? null,
                'currency' => $data['currency'] ?? 'GBP',
            ]);
        } catch (\Throwable) {
            return [];
        }
    }

    private function parseEmbeddedJson(string $html): array
    {
        if (!preg_match(
            '/ShopifyAnalytics\.meta\.product\s*=\s*(\{.*?\});/s',
            $html,
            $m
        )) {
            return [];
        }

        $data = json_decode($m[1], true);
        if (!$data) return [];

        $variant = $data['variants'][0] ?? [];

        return array_filter([
            'name' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'price' => isset($variant['price'])
                ? ((float)$variant['price'] / 100)
                : null,
            'manufacturer' => $data['vendor'] ?? null,
            'partNumber' => $variant['sku'] ?? null,
        ]);
    }
}
