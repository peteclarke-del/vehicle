<?php

declare(strict_types=1);

namespace App\Tests\Service\Adapter;

use App\Service\Adapter\EbayAdapter;
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
        $this->adapter = new EbayAdapter($this->httpClient, new NullLogger());
    }

    public function testSupportsEbayUrl(): void
    {
        $this->assertTrue($this->adapter->supports('https://www.ebay.com/itm/1234567890'));
        $this->assertTrue($this->adapter->supports('https://www.ebay.co.uk/itm/1234567890'));
        $this->assertTrue($this->adapter->supports('https://ebay.com/itm/1234567890'));
        $this->assertFalse($this->adapter->supports('https://amazon.com/dp/B001234567'));
    }

    public function testExtractsProductName(): void
    {
        $html = '<html><body><h1 class="x-item-title__mainTitle">Oil Filter</h1></body></html>';
        $mockResponse = new MockResponse($html);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->adapter = new EbayAdapter($this->httpClient, new NullLogger());

        $result = $this->adapter->parse($html, 'https://www.ebay.com/itm/1234567890');

        $this->assertArrayHasKey('name', $result);
        $this->assertSame('Oil Filter', $result['name']);
    }

    public function testExtractsPrice(): void
    {
        $html = '<html><body><div class="x-price-primary"><span class="ux-textspans">£12.99</span></div></body></html>';
        $mockResponse = new MockResponse($html);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->adapter = new EbayAdapter($this->httpClient, new NullLogger());

        $result = $this->adapter->parse($html, 'https://www.ebay.com/itm/1234567890');

        $this->assertArrayHasKey('price', $result);
        $this->assertSame(12.99, $result['price']);
    }

    public function testExtractsImage(): void
    {
        $html = '<html><body><img class="ux-image-carousel-item" src="https://i.ebayimg.com/images/g/item.jpg" /></body></html>';
        $mockResponse = new MockResponse($html);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->adapter = new EbayAdapter($this->httpClient, new NullLogger());

        $result = $this->adapter->parse($html, 'https://www.ebay.com/itm/1234567890');

        $this->assertArrayHasKey('image', $result);
        $this->assertStringContainsString('ebayimg.com', $result['image']);
    }

    public function testExtractsItemNumber(): void
    {
        $html = '<html><body><div class="ux-labels-values__values-content">Item number: 1234567890</div></body></html>';
        $mockResponse = new MockResponse($html);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->adapter = new EbayAdapter($this->httpClient, new NullLogger());

        $result = $this->adapter->parse($html, 'https://www.ebay.com/itm/1234567890');

        $this->assertArrayHasKey('sku', $result);
        $this->assertSame('1234567890', $result['sku']);
    }

    public function testExtractsBrand(): void
    {
        $html = '<html><body><span>Brand:</span><span>Bosch</span></body></html>';
        $mockResponse = new MockResponse($html);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->adapter = new EbayAdapter($this->httpClient, new NullLogger());

        $result = $this->adapter->parse($html, 'https://www.ebay.com/itm/1234567890');

        $this->assertArrayHasKey('brand', $result);
    }

    public function testExtractsCondition(): void
    {
        $html = '<html><body><div class="x-item-condition-value">New</div></body></html>';
        $mockResponse = new MockResponse($html);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->adapter = new EbayAdapter($this->httpClient, new NullLogger());

        $result = $this->adapter->parse($html, 'https://www.ebay.com/itm/1234567890');

        $this->assertArrayHasKey('condition', $result);
        $this->assertSame('New', $result['condition']);
    }

    public function testExtractsQuantity(): void
    {
        $html = '<html><body><span id="qtyTextBox" value="5"></span></body></html>';
        $mockResponse = new MockResponse($html);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->adapter = new EbayAdapter($this->httpClient, new NullLogger());

        $result = $this->adapter->parse($html, 'https://www.ebay.com/itm/1234567890');

        $this->assertArrayHasKey('quantity', $result);
    }

    public function testExtractsShippingCost(): void
    {
        $html = '<html><body><span class="ux-textspans--BOLD">Shipping: £3.99</span></body></html>';
        $mockResponse = new MockResponse($html);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->adapter = new EbayAdapter($this->httpClient, new NullLogger());

        $result = $this->adapter->parse($html, 'https://www.ebay.com/itm/1234567890');

        $this->assertArrayHasKey('shippingCost', $result);
    }

    public function testHandlesFreeShipping(): void
    {
        $html = '<html><body><span class="ux-textspans">Free shipping</span></body></html>';
        $mockResponse = new MockResponse($html);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->adapter = new EbayAdapter($this->httpClient, new NullLogger());

        $result = $this->adapter->parse($html, 'https://www.ebay.com/itm/1234567890');

        $this->assertArrayHasKey('shippingCost', $result);
        $this->assertSame(0.0, $result['shippingCost']);
    }

    public function testParsesItemIdFromUrl(): void
    {
        $url = 'https://www.ebay.com/itm/1234567890';
        $itemId = $this->adapter->extractItemIdFromUrl($url);

        $this->assertSame('1234567890', $itemId);
    }

    public function testHandlesUkEbayUrl(): void
    {
        $url = 'https://www.ebay.co.uk/itm/1234567890';
        
        $this->assertTrue($this->adapter->supports($url));
    }

    public function testHandlesDeEbayUrl(): void
    {
        $url = 'https://www.ebay.de/itm/1234567890';
        
        $this->assertTrue($this->adapter->supports($url));
    }

    public function testDetectsCurrency(): void
    {
        $html = '<html><body><div class="x-price-primary"><span>£12.99</span></div></body></html>';
        $mockResponse = new MockResponse($html);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->adapter = new EbayAdapter($this->httpClient, new NullLogger());

        $result = $this->adapter->parse($html, 'https://www.ebay.co.uk/itm/1234567890');

        $this->assertArrayHasKey('currency', $result);
        $this->assertSame('GBP', $result['currency']);
    }

    public function testHandlesMissingElements(): void
    {
        $html = '<html><body></body></html>';
        $mockResponse = new MockResponse($html);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->adapter = new EbayAdapter($this->httpClient, new NullLogger());

        $result = $this->adapter->parse($html, 'https://www.ebay.com/itm/1234567890');

        $this->assertIsArray($result);
    }
}
