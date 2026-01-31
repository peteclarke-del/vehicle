<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\SiteAdapter\SiteAdapterInterface;
use App\Service\SiteAdapter\ShopifyAdapter;
use App\Service\SiteAdapter\AmazonAdapter;
use App\Service\SiteAdapter\EbayAdapter;
use App\Service\SiteAdapter\GenericDomAdapter;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class UrlScraperService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private ShopifyAdapter $shopifyAdapter,
        private AmazonAdapter $amazonAdapter,
        private EbayAdapter $ebayAdapter,
        private GenericDomAdapter $genericAdapter
    ) {
    }

    /**
     * Scrape product details from a URL
     *
     * @param string $url The URL to scrape
     *
     * @return array<string, mixed> Product data
     *
     * @throws \RuntimeException If scraping fails
     */
    public function scrapeProductDetails(string $url): array
    {
        $this->logger->info('Scraping URL', ['url' => $url]);

        /* ---------------------------
         * 0. eBay API Fast Path (BEFORE fetching HTML)
         * --------------------------- */
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';

        if (str_contains($host, 'ebay.')) {
            $itemId = $this->ebayAdapter->extractItemId($url);
            if ($itemId) {
                $this->logger->info('eBay URL detected, using API', ['item_id' => $itemId]);
                $data = $this->ebayAdapter->fetchFromApi($itemId);
                if (!empty($data)) {
                    return $data;
                }
                $this->logger->warning('eBay API failed, falling back to HTML');
            }
        }

        /* ---------------------------
         * 1. Fetch page (follow redirects)
         * --------------------------- */
        $response = $this->httpClient->request('GET', $url, [
            'max_redirects' => 10,
            'timeout' => 20,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0',
                'Accept' => 'text/html',
            ],
        ]);

        $html = $response->getContent(false);
        $finalUrlInfo = $response->getInfo('url');
        $finalUrl = is_string($finalUrlInfo) ? $finalUrlInfo : $url;

        // Detect eBay anti-bot/challenge redirects — surface a clear error instead of attempting fragile HTML parsing.
        if (
            str_contains($finalUrl, 'splashui/challenge') ||
            str_contains($finalUrl, 'pages.ebay.com/messages') ||
            str_contains($finalUrl, 'page_not_responding') ||
            str_contains($finalUrl, '/n/error')
        ) {
            $this->logger->warning('Blocked by eBay anti-bot challenge', ['final_url' => $finalUrl]);
            throw new \RuntimeException('Blocked by eBay anti-bot challenge. Browse API returned no result and HTML fetch was blocked by eBay.');
        }
        $parsedFinal = parse_url($finalUrl);
        $host = is_array($parsedFinal) && isset($parsedFinal['host']) ? $parsedFinal['host'] : '';

        $this->logger->info('HTTP response', [
            'final_url' => $finalUrl,
            'status' => $response->getStatusCode(),
            'html_length' => strlen($html),
        ]);

        /* ---------------------------
         * 2. Shopify FAST PATH (before bot checks)
         * --------------------------- */
        if (
            str_contains($html, 'cdn.shopify.com') ||
            str_contains($html, 'ShopifyAnalytics') ||
            str_contains($html, 'window.Shopify')
        ) {
            $this->logger->info('Detected Shopify site');

            $this->shopifyAdapter->setContext([
                'final_url' => $finalUrl,
            ]);

            $data = $this->shopifyAdapter->parse($html);
            if (!empty($data)) {
                return $data;
            }
        }

        /* ---------------------------
         * 3. Bot detection (SAFE version)
         * --------------------------- */
        $lower = strtolower($html);
        if (
            str_contains($lower, 'pardon our interruption') ||
            str_contains($lower, 'checking your browser') ||
            str_contains($lower, 'captcha')
        ) {
            throw new \RuntimeException('Bot protection detected.');
        }

        /* ---------------------------
         * 4. Adapter pipeline (fallthrough allowed)
         * --------------------------- */
        $adapters = [
            $this->amazonAdapter,
            $this->ebayAdapter,
            $this->genericAdapter,
        ];

        foreach ($adapters as $adapter) {
            if (!$adapter->supports($host, $html)) {
                continue;
            }

            $adapter->setContext([
                'url' => $url,
                'final_url' => $finalUrl,
                'host' => $host,
            ]);

            $data = $adapter->parse($html);
            if (!empty($data)) {
                return $data;
            }
        }

        throw new \RuntimeException('Could not extract product information.');
    }
}
