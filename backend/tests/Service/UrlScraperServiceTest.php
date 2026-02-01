<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\UrlScraperService;
use App\Service\SiteAdapter\GenericDomAdapter;
use App\Service\SiteAdapter\ShopifyAdapter;
use App\Service\SiteAdapter\AmazonAdapter;
use App\Service\SiteAdapter\EbayAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Psr\Log\NullLogger;

/**
 * URL Scraper Service Test
 * 
 * Unit tests for URL scraping orchestration service
 */
class UrlScraperServiceTest extends TestCase
{
    private UrlScraperService $service;
    private MockHttpClient $httpClient;

    protected function setUp(): void
    {
        $this->httpClient = new MockHttpClient();
        
        $adapters = [
            new ShopifyAdapter($this->httpClient, new NullLogger()),
            new AmazonAdapter($this->httpClient, new NullLogger()),
            new EbayAdapter($this->httpClient, new NullLogger()),
            new GenericDomAdapter($this->httpClient, new NullLogger()),
        ];
        
        $this->service = new UrlScraperService($adapters, new NullLogger());
    }

    public function testScrapesShopifyUrl(): void
    {
        $html = '<html><body><script>window.ShopifyAnalytics = {};</script></body></html>';
        $mockResponse = new MockResponse($html);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->setUp();

        $result = $this->service->scrape('https://shop.example.com/products/test');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('adapter', $result);
        $this->assertSame('shopify', $result['adapter']);
    }

    public function testScrapesAmazonUrl(): void
    {
        $html = '<html><body><span id="productTitle">Test Product</span></body></html>';
        $mockResponse = new MockResponse($html);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->setUp();

        $result = $this->service->scrape('https://www.amazon.com/dp/B001234567');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('adapter', $result);
        $this->assertSame('amazon', $result['adapter']);
    }

    public function testScrapesEbayUrl(): void
    {
        $html = '<html><body><h1 class="x-item-title__mainTitle">Test Item</h1></body></html>';
        $mockResponse = new MockResponse($html);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->setUp();

        $result = $this->service->scrape('https://www.ebay.com/itm/1234567890');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('adapter', $result);
        $this->assertSame('ebay', $result['adapter']);
    }

    public function testFallsBackToGenericAdapter(): void
    {
        $html = '<html><body><h1>Product</h1><div class="price">£25.99</div></body></html>';
        $mockResponse = new MockResponse($html);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->setUp();

        $result = $this->service->scrape('https://unknown-shop.com/product/123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('adapter', $result);
        $this->assertSame('generic', $result['adapter']);
    }

    public function testHandlesGzipCompressedContent(): void
    {
        $content = gzencode('<html><body>Test</body></html>');
        $mockResponse = new MockResponse($content, [
            'response_headers' => ['Content-Encoding' => 'gzip'],
        ]);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->setUp();

        $result = $this->service->scrape('https://example.com/product');

        $this->assertIsArray($result);
    }

    public function testHandlesInvalidUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->scrape('not-a-valid-url');
    }

    public function testHandlesHttpError(): void
    {
        $mockResponse = new MockResponse('', ['http_code' => 404]);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->setUp();

        $result = $this->service->scrape('https://example.com/not-found');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testSelectsCorrectAdapter(): void
    {
        $shopifyUrl = 'https://shop.example.com/products/test';
        $amazonUrl = 'https://www.amazon.com/dp/B001234567';
        $ebayUrl = 'https://www.ebay.com/itm/1234567890';

        $adapter = $this->service->selectAdapter($shopifyUrl);
        $this->assertInstanceOf(ShopifyAdapter::class, $adapter);

        $adapter = $this->service->selectAdapter($amazonUrl);
        $this->assertInstanceOf(AmazonAdapter::class, $adapter);

        $adapter = $this->service->selectAdapter($ebayUrl);
        $this->assertInstanceOf(EbayAdapter::class, $adapter);
    }

    public function testReturnsGenericAdapterForUnknownSites(): void
    {
        $url = 'https://unknown-site.com/product';

        $adapter = $this->service->selectAdapter($url);

        $this->assertInstanceOf(GenericDomAdapter::class, $adapter);
    }

    public function testExtractsProductData(): void
    {
        $html = '<html><body><h1>Test Product</h1><span class="price">£99.99</span></body></html>';
        $mockResponse = new MockResponse($html);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->setUp();

        $result = $this->service->scrape('https://example.com/product');

        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('price', $result);
    }

    public function testHandlesTimeout(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('timeout');

        $mockResponse = new MockResponse('', ['timeout' => true]);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->setUp();

        $this->service->scrape('https://example.com/slow');
    }

    public function testHandlesRedirects(): void
    {
        $mockResponse1 = new MockResponse('', [
            'http_code' => 301,
            'response_headers' => ['Location' => 'https://example.com/new-location'],
        ]);

        $mockResponse2 = new MockResponse('<html><body>Final Content</body></html>');

        $this->httpClient = new MockHttpClient([$mockResponse1, $mockResponse2]);
        $this->setUp();

        $result = $this->service->scrape('https://example.com/old-location');

        $this->assertIsArray($result);
    }

    public function testRespectRobotsTxt(): void
    {
        $result = $this->service->canScrape('https://example.com/disallowed', true);

        $this->assertIsBool($result);
    }

    public function testSetCustomHeaders(): void
    {
        $headers = [
            'User-Agent' => 'Custom Bot/1.0',
            'Accept-Language' => 'en-GB',
        ];

        $this->service->setHeaders($headers);

        $this->assertSame($headers, $this->service->getHeaders());
    }

    public function testSetTimeout(): void
    {
        $this->service->setTimeout(30);

        $this->assertSame(30, $this->service->getTimeout());
    }

    public function testHandlesMultipleRequests(): void
    {
        $urls = [
            'https://example1.com/product',
            'https://example2.com/product',
            'https://example3.com/product',
        ];

        $mockResponse = new MockResponse('<html><body>Product</body></html>');
        $this->httpClient = new MockHttpClient([$mockResponse, $mockResponse, $mockResponse]);
        $this->setUp();

        $results = $this->service->scrapeMultiple($urls);

        $this->assertIsArray($results);
        $this->assertCount(3, $results);
    }

    public function testExtractsMetadata(): void
    {
        $html = '<html><head><meta property="og:title" content="Product Title" /></head><body></body></html>';
        $mockResponse = new MockResponse($html);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->setUp();

        $result = $this->service->scrape('https://example.com/product');

        $this->assertArrayHasKey('metadata', $result);
    }
}
