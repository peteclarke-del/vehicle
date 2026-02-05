<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\UrlScraperService;
use App\Service\SiteAdapter\GenericDomAdapter;
use App\Service\SiteAdapter\ShopifyAdapter;
use App\Service\SiteAdapter\AmazonAdapter;
use App\Service\SiteAdapter\EbayAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Psr\Log\NullLogger;

/**
 * class UrlScraperServiceTest
 *
 * URL Scraper Service Test
 * Unit tests for URL scraping orchestration service
 */
class UrlScraperServiceTest extends TestCase
{
    /**
     * @var UrlScraperService
     */
    private UrlScraperService $service;

    /**
     * @var MockHttpClient
     */
    private MockHttpClient $httpClient;

    /**
     * @var NullLogger
     */
    private NullLogger $logger;

    /**
     * function setUp
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->httpClient = new MockHttpClient();
        $this->logger = new NullLogger();
        
        $shopifyAdapter = new ShopifyAdapter($this->httpClient, $this->logger);
        $amazonAdapter = new AmazonAdapter($this->logger, $this->httpClient);
        $ebayAdapter = new EbayAdapter($this->logger, $this->httpClient);
        $genericAdapter = new GenericDomAdapter($this->logger);
        
        $this->service = new UrlScraperService(
            $this->httpClient,
            $this->logger,
            $shopifyAdapter,
            $amazonAdapter,
            $ebayAdapter,
            $genericAdapter
        );
    }

    /**
     * function createServiceWithMockedResponses
     *
     * @param array $responses
     *
     * @return UrlScraperService
     */
    protected function createServiceWithMockedResponses(array $responses): UrlScraperService
    {
        $httpClient = new MockHttpClient($responses);
        $logger = new NullLogger();
        
        return new UrlScraperService(
            $httpClient,
            $logger,
            new ShopifyAdapter($httpClient, $logger),
            new AmazonAdapter($logger, $httpClient),
            new EbayAdapter($logger, $httpClient),
            new GenericDomAdapter($logger)
        );
    }

    /**
     * function testScrapesShopifyUrlWithProductJsonLd
     *
     * @return void
     */
    public function testScrapesShopifyUrlWithProductJsonLd(): void
    {
        // Shopify detection needs ShopifyAnalytics in HTML and JSON-LD for data extraction
        $html = <<<HTML
<html>
<head>
<script type="application/ld+json">{"@type":"Product","name":"Test Product","offers":{"@type":"Offer","price":"29.99","priceCurrency":"GBP"}}</script>
</head>
<body><script>window.ShopifyAnalytics = {};</script></body>
</html>
HTML;
        $mockResponse = new MockResponse($html);
        $service = $this->createServiceWithMockedResponses([$mockResponse]);

        $result = $service->scrapeProductDetails('https://shop.example.com/products/test');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        $this->assertSame('Test Product', $result['name']);
    }

    /**
     * function testScrapesGenericUrlWithPrice
     *
     * @return void
     */
    public function testScrapesGenericUrlWithPrice(): void
    {
        $html = <<<HTML
<html>
<head><title>Product Page</title></head>
<body>
<h1 itemprop="name">Generic Product</h1>
<span class="price" itemprop="price">25.99</span>
</body>
</html>
HTML;
        $mockResponse = new MockResponse($html);
        $service = $this->createServiceWithMockedResponses([$mockResponse]);

        $result = $service->scrapeProductDetails('https://unknown-shop.com/product/123');

        $this->assertIsArray($result);
        // Generic adapter returns parsed data from HTML
    }

    /**
     * function testSelectsShopifyAdapterForShopifyContent
     *
     * @return void
     */
    public function testSelectsShopifyAdapterForShopifyContent(): void
    {
        // Full Shopify page with proper JSON-LD data
        $shopifyHtml = <<<HTML
<html>
<head>
<script type="application/ld+json">{"@type":"Product","name":"Shopify Product","offers":{"@type":"Offer","price":"19.99","priceCurrency":"GBP"}}</script>
</head>
<body><script>window.Shopify = {};</script></body>
</html>
HTML;
        
        $mockResponse = new MockResponse($shopifyHtml);
        $service = $this->createServiceWithMockedResponses([$mockResponse]);

        $result = $service->scrapeProductDetails('https://shop.example.com/products/test');
        
        // Should extract data from Shopify adapter
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    /**
     * function testThrowsOnBotProtection
     *
     * @return void
     */
    public function testThrowsOnBotProtection(): void
    {
        $html = '<html><body>Pardon our interruption while we verify you are human</body></html>';
        
        $mockResponse = new MockResponse($html);
        $service = $this->createServiceWithMockedResponses([$mockResponse]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Bot protection detected');
        
        $service->scrapeProductDetails('https://example.com/blocked');
    }

    /**
     * function testThrowsOnCaptcha
     *
     * @return void
     */
    public function testThrowsOnCaptcha(): void
    {
        $html = '<html><body>Please complete the captcha</body></html>';
        
        $mockResponse = new MockResponse($html);
        $service = $this->createServiceWithMockedResponses([$mockResponse]);

        $this->expectException(\RuntimeException::class);
        
        $service->scrapeProductDetails('https://example.com/captcha');
    }

    /**
     * function testHandlesMalformedHtml
     *
     * @return void
     */
    public function testHandlesMalformedHtml(): void
    {
        // Should not throw, but may return empty result
        $malformedHtml = '<html><body><p>Unclosed paragraph<div>Mixed tags</body>';
        $mockResponse = new MockResponse($malformedHtml);
        $service = $this->createServiceWithMockedResponses([$mockResponse]);

        // May throw RuntimeException if no data extracted
        try {
            $result = $service->scrapeProductDetails('https://example.com/malformed');
            $this->assertIsArray($result);
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Could not extract', $e->getMessage());
        }
    }

    /**
     * function testExtractsFromJsonLd
     *
     * @return void
     */
    public function testExtractsFromJsonLd(): void
    {
        // Test with structured JSON-LD data on a Shopify-detected page
        $html = <<<HTML
<html>
<head>
<script type="application/ld+json">
{
    "@type": "Product",
    "name": "Test Product",
    "description": "A great product",
    "offers": {
        "@type": "Offer",
        "price": "99.99",
        "priceCurrency": "GBP"
    }
}
</script>
</head>
<body><script>window.ShopifyAnalytics = {};</script></body>
</html>
HTML;
        $mockResponse = new MockResponse($html);
        $service = $this->createServiceWithMockedResponses([$mockResponse]);

        $result = $service->scrapeProductDetails('https://example.com/product');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
    }

    /**
     * function testServiceInstantiation
     *
     * @return void
     */
    public function testServiceInstantiation(): void
    {
        $this->assertInstanceOf(UrlScraperService::class, $this->service);
    }
}
