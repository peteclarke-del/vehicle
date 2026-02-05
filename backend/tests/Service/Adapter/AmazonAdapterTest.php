<?php

declare(strict_types=1);

namespace App\Tests\Service\Adapter;

use App\Service\SiteAdapter\AmazonAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Psr\Log\NullLogger;

/**
 * Amazon Adapter Test
 * 
 * Unit tests for Amazon product scraping adapter.
 * Constructor: (LoggerInterface $logger, HttpClientInterface $httpClient, ...)
 * supports(): (string $host, string $html) - checks if host contains 'amazon.'
 * parse(): (string $html) - returns name, price, description, manufacturer
 */
class AmazonAdapterTest extends TestCase
{
    private AmazonAdapter $adapter;
    private MockHttpClient $httpClient;

    protected function setUp(): void
    {
        $this->httpClient = new MockHttpClient();
        // Constructor order: LoggerInterface $logger, HttpClientInterface $httpClient
        $this->adapter = new AmazonAdapter(new NullLogger(), $this->httpClient);
    }

    public function testSupportsAmazonDomains(): void
    {
        // supports() takes (host, html) not a full URL
        $this->assertTrue($this->adapter->supports('www.amazon.com', ''));
        $this->assertTrue($this->adapter->supports('www.amazon.co.uk', ''));
        $this->assertTrue($this->adapter->supports('amazon.de', ''));
        $this->assertFalse($this->adapter->supports('ebay.com', ''));
        $this->assertFalse($this->adapter->supports('www.ebay.com', ''));
    }

    public function testExtractsProductName(): void
    {
        $html = '<html><body><span id="productTitle">Brake Pads Set</span></body></html>';

        $result = $this->adapter->parse($html);

        $this->assertArrayHasKey('name', $result);
        $this->assertSame('Brake Pads Set', $result['name']);
    }

    public function testExtractsPrice(): void
    {
        $html = '<html><body><span class="a-price-whole">25.</span><span class="a-price-fraction">99</span></body></html>';

        $result = $this->adapter->parse($html);

        $this->assertArrayHasKey('price', $result);
        $this->assertEquals('25.99', $result['price']);
    }

    public function testExtractsPriceWithCommas(): void
    {
        $html = '<html><body><span class="a-price-whole">1,299.</span><span class="a-price-fraction">99</span></body></html>';

        $result = $this->adapter->parse($html);

        $this->assertArrayHasKey('price', $result);
        $this->assertEquals('1299.99', $result['price']);
    }

    public function testExtractsAlternativePrice(): void
    {
        $html = '<html><body><span class="a-offscreen">£45.99</span></body></html>';

        $result = $this->adapter->parse($html);

        $this->assertArrayHasKey('price', $result);
        $this->assertEquals('45.99', $result['price']);
    }

    public function testExtractsDescription(): void
    {
        $html = '<html><body><div id="feature-bullets">High quality brake pads for your vehicle</div></body></html>';

        $result = $this->adapter->parse($html);

        $this->assertArrayHasKey('description', $result);
        $this->assertSame('High quality brake pads for your vehicle', $result['description']);
    }

    public function testExtractsManufacturer(): void
    {
        $html = '<html><body><a id="bylineInfo">Bosch Automotive</a></body></html>';

        $result = $this->adapter->parse($html);

        $this->assertArrayHasKey('manufacturer', $result);
        $this->assertSame('Bosch Automotive', $result['manufacturer']);
    }

    public function testExtractsManufacturerRemovesVisitThePrefix(): void
    {
        $html = '<html><body><a id="bylineInfo">Visit the Bosch Store</a></body></html>';

        $result = $this->adapter->parse($html);

        $this->assertArrayHasKey('manufacturer', $result);
        $this->assertSame('Bosch Store', $result['manufacturer']);
    }

    public function testExtractsManufacturerRemovesBrandPrefix(): void
    {
        $html = '<html><body><a id="bylineInfo">Brand: Bosch</a></body></html>';

        $result = $this->adapter->parse($html);

        $this->assertArrayHasKey('manufacturer', $result);
        $this->assertSame('Bosch', $result['manufacturer']);
    }

    public function testHandlesMissingElements(): void
    {
        $html = '<html><body></body></html>';

        $result = $this->adapter->parse($html);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testHandlesPartialData(): void
    {
        $html = '<html><body><span id="productTitle">Test Product</span></body></html>';

        $result = $this->adapter->parse($html);

        $this->assertArrayHasKey('name', $result);
        $this->assertArrayNotHasKey('price', $result);
        $this->assertArrayNotHasKey('description', $result);
    }

    public function testFiltersEmptyValues(): void
    {
        // The parse method uses array_filter, so empty values should be removed
        $html = '<html><body><span id="productTitle">  </span></body></html>';

        $result = $this->adapter->parse($html);

        // Empty string after trim should be filtered out
        $this->assertArrayNotHasKey('name', $result);
    }

    public function testExtractsCompleteProduct(): void
    {
        $html = '<html><body>
            <span id="productTitle">Oil Filter</span>
            <span class="a-price-whole">12.</span><span class="a-price-fraction">99</span>
            <div id="feature-bullets">Premium oil filter for all vehicles</div>
            <a id="bylineInfo">Mann Filter</a>
        </body></html>';

        $result = $this->adapter->parse($html);

        $this->assertSame('Oil Filter', $result['name']);
        $this->assertEquals('12.99', $result['price']);
        $this->assertSame('Premium oil filter for all vehicles', $result['description']);
        $this->assertSame('Mann Filter', $result['manufacturer']);
    }
}
