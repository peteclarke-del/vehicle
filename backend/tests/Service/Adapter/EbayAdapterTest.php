<?php

declare(strict_types=1);

namespace App\Tests\Service\Adapter;

use App\Service\SiteAdapter\EbayAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Psr\Log\NullLogger;

/**
 * eBay Adapter Test
 * 
 * Unit tests for eBay item scraping adapter
 */
class EbayAdapterTest extends TestCase
{
    private EbayAdapter $adapter;
    private MockHttpClient $httpClient;

    protected function setUp(): void
    {
        $this->httpClient = new MockHttpClient();
        $this->adapter = new EbayAdapter(new NullLogger(), $this->httpClient);
    }

    public function testSupportsEbayUrl(): void
    {
        $this->assertTrue($this->adapter->supports('www.ebay.com', ''));
        $this->assertTrue($this->adapter->supports('www.ebay.co.uk', ''));
        $this->assertTrue($this->adapter->supports('ebay.com', ''));
        $this->assertFalse($this->adapter->supports('amazon.com', ''));
    }

    public function testExtractsProductName(): void
    {
        $html = '<html><body><h1 class="x-item-title__mainTitle">Oil Filter</h1></body></html>';

        $this->adapter = new EbayAdapter(new NullLogger(), $this->httpClient);

        $result = $this->adapter->parse($html);

        $this->assertArrayHasKey('name', $result);
        $this->assertSame('Oil Filter', $result['name']);
    }

    public function testExtractsPrice(): void
    {
        $html = '<html><body><div class="x-price-primary"><span class="ux-textspans">£12.99</span></div></body></html>';

        $this->adapter = new EbayAdapter(new NullLogger(), $this->httpClient);

        $result = $this->adapter->parse($html);

        $this->assertArrayHasKey('price', $result);
        $this->assertEquals('12.99', $result['price']);
    }

    public function testExtractsItemId(): void
    {
        $url = 'https://www.ebay.com/itm/1234567890';
        $itemId = $this->adapter->extractItemId($url);

        $this->assertSame('1234567890', $itemId);
    }

    public function testExtractsItemIdWithProductName(): void
    {
        $url = 'https://www.ebay.com/itm/some-product-name/1234567890';
        $itemId = $this->adapter->extractItemId($url);

        $this->assertSame('1234567890', $itemId);
    }

    public function testHandlesUkEbayUrl(): void
    {
        $this->assertTrue($this->adapter->supports('www.ebay.co.uk', ''));
    }

    public function testHandlesDeEbayUrl(): void
    {
        $this->assertTrue($this->adapter->supports('www.ebay.de', ''));
    }

    public function testHandlesMissingElements(): void
    {
        $html = '<html><body></body></html>';

        $this->adapter = new EbayAdapter(new NullLogger(), $this->httpClient);

        $result = $this->adapter->parse($html);

        $this->assertIsArray($result);
    }

    public function testExtractsFromJsonLd(): void
    {
        $html = '<html><head>
            <script type="application/ld+json">
            {
                "name": "Test Product",
                "description": "A test product description",
                "offers": {"price": "29.99"},
                "brand": {"name": "TestBrand"}
            }
            </script>
        </head><body></body></html>';

        $this->adapter = new EbayAdapter(new NullLogger(), $this->httpClient);

        $result = $this->adapter->parse($html);

        $this->assertArrayHasKey('name', $result);
        $this->assertSame('Test Product', $result['name']);
        $this->assertArrayHasKey('price', $result);
        $this->assertEquals('29.99', $result['price']);
        $this->assertArrayHasKey('manufacturer', $result);
        $this->assertSame('TestBrand', $result['manufacturer']);
    }
}
