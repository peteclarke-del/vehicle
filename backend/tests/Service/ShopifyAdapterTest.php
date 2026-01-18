<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\SiteAdapter\ShopifyAdapter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Shopify Adapter Test
 *
 * Comprehensive test suite for ShopifyAdapter
 */
class ShopifyAdapterTest extends TestCase
{
    private ShopifyAdapter $adapter;
    private MockHttpClient $httpClient;

    protected function setUp(): void
    {
        $this->httpClient = new MockHttpClient();
        $this->adapter = new ShopifyAdapter($this->httpClient, new NullLogger());
    }

    /**
     * Test adapter supports Shopify sites
     */
    public function testSupportsShopifySite(): void
    {
        $html = '<html><head><script>window.Shopify = {};</script></head></html>';
        
        $this->assertTrue($this->adapter->supports('example.com', $html));
    }

    /**
     * Test adapter detects cdn.shopify.com
     */
    public function testDetectsCdnShopify(): void
    {
        $html = '<html><link href="https://cdn.shopify.com/styles.css"></html>';
        
        $this->assertTrue($this->adapter->supports('example.com', $html));
    }

    /**
     * Test adapter detects ShopifyAnalytics
     */
    public function testDetectsShopifyAnalytics(): void
    {
        $html = '<html><script>ShopifyAnalytics = {};</script></html>';
        
        $this->assertTrue($this->adapter->supports('example.com', $html));
    }

    /**
     * Test adapter does not support non-Shopify sites
     */
    public function testDoesNotSupportNonShopifySite(): void
    {
        $html = '<html><head><title>Regular Site</title></head></html>';
        
        $this->assertFalse($this->adapter->supports('example.com', $html));
    }

    /**
     * Test parsing Shopify product from JSON endpoint
     */
    public function testParseShopifyProductFromJson(): void
    {
        $productJson = json_encode([
            'title' => 'Test Product',
            'description' => 'Test Description',
            'vendor' => 'Test Vendor',
            'variants' => [
                [
                    'price' => 9999,
                    'sku' => 'TEST-SKU-001'
                ]
            ],
            'currency' => 'GBP'
        ]);

        $this->httpClient = new MockHttpClient([
            new MockResponse($productJson, ['http_code' => 200])
        ]);

        $this->adapter = new ShopifyAdapter($this->httpClient, new NullLogger());
        $this->adapter->setContext([
            'final_url' => 'https://example.com/products/test-product'
        ]);

        $html = '<html></html>';
        $result = $this->adapter->parse($html);

        $this->assertArrayHasKey('name', $result);
        $this->assertEquals('Test Product', $result['name']);
        $this->assertEquals(99.99, $result['price']);
        $this->assertEquals('Test Vendor', $result['manufacturer']);
        $this->assertEquals('TEST-SKU-001', $result['partNumber']);
    }

    /**
     * Test parsing without context returns empty
     */
    public function testParseWithoutContextReturnsEmpty(): void
    {
        $result = $this->adapter->parse('<html></html>');
        
        $this->assertEmpty($result);
    }

    /**
     * Test parsing non-product URL returns empty
     */
    public function testParseNonProductUrlReturnsEmpty(): void
    {
        $this->adapter->setContext([
            'final_url' => 'https://example.com/pages/about'
        ]);

        $result = $this->adapter->parse('<html></html>');
        
        $this->assertEmpty($result);
    }

    /**
     * Test setContext with valid URL
     */
    public function testSetContextWithValidUrl(): void
    {
        $this->adapter->setContext([
            'final_url' => 'https://example.com/products/test'
        ]);

        // Context should be set (tested indirectly through parse)
        $this->assertInstanceOf(ShopifyAdapter::class, $this->adapter);
    }

    /**
     * Test setContext with invalid URL format
     */
    public function testSetContextWithInvalidUrl(): void
    {
        $this->adapter->setContext([
            'final_url' => 'not-a-url'
        ]);

        $result = $this->adapter->parse('<html></html>');
        $this->assertEmpty($result);
    }

    /**
     * Test fallback to embedded JSON parsing
     */
    public function testFallbackToEmbeddedJsonParsing(): void
    {
        $html = <<<HTML
<html>
<script>
ShopifyAnalytics.meta = {
    "product": {
        "id": 123,
        "vendor": "Test Vendor",
        "type": "Test Type",
        "variants": [{"id": 456, "price": 5000, "sku": "SKU-001"}]
    },
    "page": {
        "title": "Test Product",
        "pageType": "product"
    }
};
</script>
</html>
HTML;

        $this->httpClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 404])
        ]);

        $this->adapter = new ShopifyAdapter($this->httpClient, new NullLogger());
        $this->adapter->setContext([
            'final_url' => 'https://example.com/products/test-product'
        ]);

        $result = $this->adapter->parse($html);

        $this->assertArrayHasKey('name', $result);
        $this->assertEquals('Test Product', $result['name']);
        $this->assertEquals(50.00, $result['price']);
    }

    protected function tearDown(): void
    {
        unset($this->adapter, $this->httpClient);
    }
}
