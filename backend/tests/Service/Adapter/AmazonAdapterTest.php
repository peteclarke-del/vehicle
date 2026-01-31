<?php

declare(strict_types=1);

namespace App\Tests\Service\Adapter;

use App\Service\SiteAdapter\AmazonAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Psr\Log\NullLogger;

/**
 * Amazon Adapter Test
 * 
 * Unit tests for Amazon product scraping adapter
 */
class AmazonAdapterTest extends TestCase
{
    private AmazonAdapter $adapter;
    private MockHttpClient $httpClient;

    protected function setUp(): void
    {
        $this->httpClient = new MockHttpClient();
        $this->adapter = new AmazonAdapter(new NullLogger(), $this->httpClient);
    }

    public function testSupportsAmazonUrl(): void
    {
        $this->assertTrue($this->adapter->supports('https://www.amazon.com/dp/B001234567'));
        $this->assertTrue($this->adapter->supports('https://www.amazon.co.uk/dp/B001234567'));
        $this->assertTrue($this->adapter->supports('https://amazon.com/gp/product/B001234567'));
        $this->assertFalse($this->adapter->supports('https://ebay.com/itm/123'));
    }

    public function testExtractsProductName(): void
    {
        $html = '<html><body><span id="productTitle">Brake Pads Set</span></body></html>';
        $mockResponse = new MockResponse($html);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->adapter = new AmazonAdapter($this->httpClient, new NullLogger());

        $result = $this->adapter->parse($html, 'https://www.amazon.com/dp/B001234567');

        $this->assertArrayHasKey('name', $result);
        $this->assertSame('Brake Pads Set', $result['name']);
    }

    public function testExtractsPrice(): void
    {
        $html = '<html><body><span class="a-price-whole">25.</span><span class="a-price-fraction">99</span></body></html>';
        $mockResponse = new MockResponse($html);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->adapter = new AmazonAdapter($this->httpClient, new NullLogger());

        $result = $this->adapter->parse($html, 'https://www.amazon.com/dp/B001234567');

        $this->assertArrayHasKey('price', $result);
        $this->assertSame(25.99, $result['price']);
    }

    public function testExtractsImage(): void
    {
        $html = '<html><body><img id="landingImage" src="https://m.media-amazon.com/images/I/image.jpg" /></body></html>';
        $mockResponse = new MockResponse($html);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->adapter = new AmazonAdapter($this->httpClient, new NullLogger());

        $result = $this->adapter->parse($html, 'https://www.amazon.com/dp/B001234567');

        $this->assertArrayHasKey('image', $result);
        $this->assertSame('https://m.media-amazon.com/images/I/image.jpg', $result['image']);
    }

    public function testExtractsAsin(): void
    {
        $html = '<html><body><input type="hidden" name="ASIN" value="B001234567" /></body></html>';
        $mockResponse = new MockResponse($html);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->adapter = new AmazonAdapter($this->httpClient, new NullLogger());

        $result = $this->adapter->parse($html, 'https://www.amazon.com/dp/B001234567');

        $this->assertArrayHasKey('sku', $result);
        $this->assertSame('B001234567', $result['sku']);
    }

    public function testExtractsBrand(): void
    {
        $html = '<html><body><a id="bylineInfo">Bosch</a></body></html>';
        $mockResponse = new MockResponse($html);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->adapter = new AmazonAdapter($this->httpClient, new NullLogger());

        $result = $this->adapter->parse($html, 'https://www.amazon.com/dp/B001234567');

        $this->assertArrayHasKey('brand', $result);
        $this->assertSame('Bosch', $result['brand']);
    }

    public function testExtractsRating(): void
    {
        $html = '<html><body><span class="a-icon-alt">4.5 out of 5 stars</span></body></html>';
        $mockResponse = new MockResponse($html);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->adapter = new AmazonAdapter($this->httpClient, new NullLogger());

        $result = $this->adapter->parse($html, 'https://www.amazon.com/dp/B001234567');

        $this->assertArrayHasKey('rating', $result);
        $this->assertSame(4.5, $result['rating']);
    }

    public function testExtractsReviewCount(): void
    {
        $html = '<html><body><span id="acrCustomerReviewText">1,234 ratings</span></body></html>';
        $mockResponse = new MockResponse($html);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->adapter = new AmazonAdapter($this->httpClient, new NullLogger());

        $result = $this->adapter->parse($html, 'https://www.amazon.com/dp/B001234567');

        $this->assertArrayHasKey('reviewCount', $result);
        $this->assertSame(1234, $result['reviewCount']);
    }

    public function testExtractsAvailability(): void
    {
        $html = '<html><body><span id="availability">In Stock</span></body></html>';
        $mockResponse = new MockResponse($html);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->adapter = new AmazonAdapter($this->httpClient, new NullLogger());

        $result = $this->adapter->parse($html, 'https://www.amazon.com/dp/B001234567');

        $this->assertArrayHasKey('availability', $result);
        $this->assertSame('In Stock', $result['availability']);
    }

    public function testHandlesOutOfStock(): void
    {
        $html = '<html><body><span id="availability">Currently unavailable</span></body></html>';
        $mockResponse = new MockResponse($html);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->adapter = new AmazonAdapter($this->httpClient, new NullLogger());

        $result = $this->adapter->parse($html, 'https://www.amazon.com/dp/B001234567');

        $this->assertSame('Currently unavailable', $result['availability']);
    }

    public function testHandlesMissingElements(): void
    {
        $html = '<html><body></body></html>';
        $mockResponse = new MockResponse($html);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->adapter = new AmazonAdapter($this->httpClient, new NullLogger());

        $result = $this->adapter->parse($html, 'https://www.amazon.com/dp/B001234567');

        $this->assertIsArray($result);
    }

    public function testParsesAsinFromUrl(): void
    {
        $url = 'https://www.amazon.com/dp/B001234567';
        $asin = $this->adapter->extractAsinFromUrl($url);

        $this->assertSame('B001234567', $asin);
    }

    public function testParsesAsinFromProductUrl(): void
    {
        $url = 'https://www.amazon.com/Product-Name/dp/B001234567/ref=xyz';
        $asin = $this->adapter->extractAsinFromUrl($url);

        $this->assertSame('B001234567', $asin);
    }

    public function testHandlesUkAmazonUrl(): void
    {
        $url = 'https://www.amazon.co.uk/dp/B001234567';
        
        $this->assertTrue($this->adapter->supports($url));
    }

    public function testHandlesDeAmazonUrl(): void
    {
        $url = 'https://www.amazon.de/dp/B001234567';
        
        $this->assertTrue($this->adapter->supports($url));
    }

    public function testDetectsCurrency(): void
    {
        $html = '<html><body><span class="a-price-symbol">£</span><span class="a-price-whole">25.</span></body></html>';
        $mockResponse = new MockResponse($html);

        $this->httpClient = new MockHttpClient([$mockResponse]);
        $this->adapter = new AmazonAdapter($this->httpClient, new NullLogger());

        $result = $this->adapter->parse($html, 'https://www.amazon.co.uk/dp/B001234567');

        $this->assertArrayHasKey('currency', $result);
        $this->assertSame('GBP', $result['currency']);
    }
}
