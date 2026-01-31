<?php

declare(strict_types=1);

namespace App\Tests\Service\Adapter;

use App\Service\SiteAdapter\GenericDomAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Psr\Log\NullLogger;

/**
 * Generic DOM Adapter Test
 * 
 * Unit tests for generic HTML scraping adapter
 */
class GenericDomAdapterTest extends TestCase
{
    private GenericDomAdapter $adapter;
    private MockHttpClient $httpClient;

    protected function setUp(): void
    {
        $this->httpClient = new MockHttpClient();
        $this->adapter = new GenericDomAdapter(new NullLogger(), $this->httpClient);
    }

    public function testExtractsProductNameFromH1(): void
    {
        $html = '<html><body><h1>Brake Pads Set</h1></body></html>';
        
        $result = $this->adapter->parse($html, 'https://example.com/product');

        $this->assertArrayHasKey('name', $result);
        $this->assertSame('Brake Pads Set', $result['name']);
    }

    public function testExtractsPriceFromCommonClasses(): void
    {
        $html = '<html><body><span class="price">£25.99</span></body></html>';
        
        $result = $this->adapter->parse($html, 'https://example.com/product');

        $this->assertArrayHasKey('price', $result);
        $this->assertSame(25.99, $result['price']);
    }

    public function testExtractsPriceWithCurrency(): void
    {
        $html = '<html><body><div class="product-price">$45.00</div></body></html>';
        
        $result = $this->adapter->parse($html, 'https://example.com/product');

        $this->assertSame(45.00, $result['price']);
        $this->assertSame('USD', $result['currency']);
    }

    public function testExtractsImageFromOgMeta(): void
    {
        $html = '<html><head><meta property="og:image" content="https://example.com/image.jpg" /></head><body></body></html>';
        
        $result = $this->adapter->parse($html, 'https://example.com/product');

        $this->assertArrayHasKey('image', $result);
        $this->assertSame('https://example.com/image.jpg', $result['image']);
    }

    public function testExtractsDescriptionFromMetaTag(): void
    {
        $html = '<html><head><meta name="description" content="High quality brake pads" /></head><body></body></html>';
        
        $result = $this->adapter->parse($html, 'https://example.com/product');

        $this->assertArrayHasKey('description', $result);
        $this->assertSame('High quality brake pads', $result['description']);
    }

    public function testExtractsJsonLdData(): void
    {
        $jsonLd = [
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            'name' => 'Oil Filter',
            'offers' => [
                '@type' => 'Offer',
                'price' => '12.99',
                'priceCurrency' => 'GBP',
            ],
        ];

        $html = '<html><head><script type="application/ld+json">' . json_encode($jsonLd) . '</script></head><body></body></html>';
        
        $result = $this->adapter->parse($html, 'https://example.com/product');

        $this->assertSame('Oil Filter', $result['name']);
        $this->assertSame(12.99, $result['price']);
        $this->assertSame('GBP', $result['currency']);
    }

    public function testHandlesMultipleJsonLdScripts(): void
    {
        $jsonLd1 = ['@type' => 'WebSite'];
        $jsonLd2 = [
            '@type' => 'Product',
            'name' => 'Test Product',
            'offers' => ['price' => '19.99'],
        ];

        $html = '<html><head>'
            . '<script type="application/ld+json">' . json_encode($jsonLd1) . '</script>'
            . '<script type="application/ld+json">' . json_encode($jsonLd2) . '</script>'
            . '</head><body></body></html>';
        
        $result = $this->adapter->parse($html, 'https://example.com/product');

        $this->assertSame('Test Product', $result['name']);
    }

    public function testExtractsSku(): void
    {
        $html = '<html><body><span class="sku">ABC-12345</span></body></html>';
        
        $result = $this->adapter->parse($html, 'https://example.com/product');

        $this->assertArrayHasKey('sku', $result);
        $this->assertSame('ABC-12345', $result['sku']);
    }

    public function testExtractsBrand(): void
    {
        $html = '<html><body><span class="brand">Bosch</span></body></html>';
        
        $result = $this->adapter->parse($html, 'https://example.com/product');

        $this->assertArrayHasKey('brand', $result);
        $this->assertSame('Bosch', $result['brand']);
    }

    public function testExtractsAvailability(): void
    {
        $html = '<html><body><span class="availability">In Stock</span></body></html>';
        
        $result = $this->adapter->parse($html, 'https://example.com/product');

        $this->assertArrayHasKey('availability', $result);
        $this->assertSame('In Stock', $result['availability']);
    }

    public function testHandlesInvalidHtml(): void
    {
        $html = '<html><body>Incomplete HTML';
        
        $result = $this->adapter->parse($html, 'https://example.com/product');

        $this->assertIsArray($result);
    }

    public function testHandlesEmptyHtml(): void
    {
        $html = '';
        
        $result = $this->adapter->parse($html, 'https://example.com/product');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testNormalizesPrice(): void
    {
        $price = $this->adapter->normalizePrice('£1,234.56');

        $this->assertSame(1234.56, $price);
    }

    public function testNormalizesPriceWithComma(): void
    {
        $price = $this->adapter->normalizePrice('€1.234,56');

        $this->assertSame(1234.56, $price);
    }

    public function testDetectsCurrencyFromSymbol(): void
    {
        $currency = $this->adapter->detectCurrency('£25.99');
        $this->assertSame('GBP', $currency);

        $currency = $this->adapter->detectCurrency('$45.00');
        $this->assertSame('USD', $currency);

        $currency = $this->adapter->detectCurrency('€15.50');
        $this->assertSame('EUR', $currency);
    }

    public function testExtractsMultipleImages(): void
    {
        $html = '<html><body>'
            . '<img src="https://example.com/img1.jpg" alt="Product" />'
            . '<img src="https://example.com/img2.jpg" alt="Product" />'
            . '</body></html>';
        
        $result = $this->adapter->parse($html, 'https://example.com/product');

        $this->assertArrayHasKey('images', $result);
        $this->assertCount(2, $result['images']);
    }

    public function testSupportsUrl(): void
    {
        // Generic adapter should support any URL
        $this->assertTrue($this->adapter->supports('https://any-site.com/product'));
        $this->assertTrue($this->adapter->supports('https://another-site.com/item'));
    }

    public function testExtractsRating(): void
    {
        $html = '<html><body><span class="rating">4.5</span></body></html>';
        
        $result = $this->adapter->parse($html, 'https://example.com/product');

        $this->assertArrayHasKey('rating', $result);
        $this->assertSame(4.5, $result['rating']);
    }

    public function testExtractsReviewCount(): void
    {
        $html = '<html><body><span class="reviews">(123 reviews)</span></body></html>';
        
        $result = $this->adapter->parse($html, 'https://example.com/product');

        $this->assertArrayHasKey('reviewCount', $result);
        $this->assertSame(123, $result['reviewCount']);
    }

    public function testHandlesRelativeImageUrls(): void
    {
        $html = '<html><body><img src="/images/product.jpg" alt="Product" /></body></html>';
        
        $result = $this->adapter->parse($html, 'https://example.com/product');

        $this->assertStringStartsWith('https://', $result['image']);
    }

    public function testExtractsCanonicalUrl(): void
    {
        $html = '<html><head><link rel="canonical" href="https://example.com/product-canonical" /></head><body></body></html>';
        
        $result = $this->adapter->parse($html, 'https://example.com/product');

        $this->assertArrayHasKey('url', $result);
        $this->assertSame('https://example.com/product-canonical', $result['url']);
    }
}
