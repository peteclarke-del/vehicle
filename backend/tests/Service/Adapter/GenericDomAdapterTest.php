<?php

declare(strict_types=1);

namespace App\Tests\Service\Adapter;

use App\Service\SiteAdapter\GenericDomAdapter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Generic DOM Adapter Test
 * 
 * Unit tests for generic HTML scraping adapter.
 * This adapter only takes LoggerInterface, no HttpClient.
 * It acts as a fallback adapter that supports all hosts.
 */
class GenericDomAdapterTest extends TestCase
{
    private GenericDomAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new GenericDomAdapter(new NullLogger());
    }

    public function testSupportsAnyHost(): void
    {
        // Generic adapter should support any host as fallback
        $this->assertTrue($this->adapter->supports('any-site.com', ''));
        $this->assertTrue($this->adapter->supports('another-site.com', ''));
        $this->assertTrue($this->adapter->supports('example.org', ''));
    }

    public function testExtractsProductNameFromH1(): void
    {
        $html = '<html><body><h1>Brake Pads Set</h1></body></html>';
        
        $result = $this->adapter->parse($html);

        $this->assertArrayHasKey('name', $result);
        $this->assertSame('Brake Pads Set', $result['name']);
    }

    public function testExtractsProductNameFromOgTitle(): void
    {
        $html = '<html><head><meta property="og:title" content="Product from OG" /></head><body></body></html>';
        
        $result = $this->adapter->parse($html);

        $this->assertArrayHasKey('name', $result);
        $this->assertSame('Product from OG', $result['name']);
    }

    public function testExtractsProductNameFromTitleTag(): void
    {
        $html = '<html><head><title>Product Title | Site Name</title></head><body></body></html>';
        
        $result = $this->adapter->parse($html);

        $this->assertArrayHasKey('name', $result);
        $this->assertSame('Product Title', $result['name']);
    }

    public function testExtractsPriceFromCommonClasses(): void
    {
        $html = '<html><body><span class="price">£25.99</span></body></html>';
        
        $result = $this->adapter->parse($html);

        $this->assertArrayHasKey('price', $result);
        $this->assertEquals('25.99', $result['price']);
    }

    public function testExtractsDescriptionFromMetaTag(): void
    {
        $html = '<html><head><meta name="description" content="High quality brake pads" /></head><body></body></html>';
        
        $result = $this->adapter->parse($html);

        $this->assertArrayHasKey('description', $result);
        $this->assertSame('High quality brake pads', $result['description']);
    }

    public function testExtractsDescriptionFromOgDescription(): void
    {
        $html = '<html><head><meta property="og:description" content="OG Description" /></head><body></body></html>';
        
        $result = $this->adapter->parse($html);

        $this->assertArrayHasKey('description', $result);
        $this->assertSame('OG Description', $result['description']);
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
            ],
        ];

        $html = '<html><head><script type="application/ld+json">' . json_encode($jsonLd) . '</script></head><body></body></html>';
        
        $result = $this->adapter->parse($html);

        $this->assertSame('Oil Filter', $result['name']);
        $this->assertEquals('12.99', $result['price']);
    }

    public function testExtractsJsonLdWithOffersArray(): void
    {
        $jsonLd = [
            '@type' => 'Product',
            'name' => 'Test Product',
            'offers' => [
                ['price' => '19.99'],
            ],
        ];

        $html = '<html><head><script type="application/ld+json">' . json_encode($jsonLd) . '</script></head><body></body></html>';
        
        $result = $this->adapter->parse($html);

        $this->assertSame('Test Product', $result['name']);
        $this->assertEquals('19.99', $result['price']);
    }

    public function testExtractsBrandFromJsonLd(): void
    {
        $jsonLd = [
            '@type' => 'Product',
            'name' => 'Product',
            'brand' => ['name' => 'Bosch'],
        ];

        $html = '<html><head><script type="application/ld+json">' . json_encode($jsonLd) . '</script></head><body></body></html>';
        
        $result = $this->adapter->parse($html);

        $this->assertArrayHasKey('manufacturer', $result);
        $this->assertSame('Bosch', $result['manufacturer']);
    }

    public function testExtractsBrandAsStringFromJsonLd(): void
    {
        $jsonLd = [
            '@type' => 'Product',
            'name' => 'Product',
            'brand' => 'SimpleBrand',
        ];

        $html = '<html><head><script type="application/ld+json">' . json_encode($jsonLd) . '</script></head><body></body></html>';
        
        $result = $this->adapter->parse($html);

        $this->assertArrayHasKey('manufacturer', $result);
        $this->assertSame('SimpleBrand', $result['manufacturer']);
    }

    public function testHandlesInvalidHtml(): void
    {
        $html = '<html><body>Incomplete HTML';
        
        $result = $this->adapter->parse($html);

        $this->assertIsArray($result);
    }

    public function testHandlesEmptyHtml(): void
    {
        $html = '';
        
        $result = $this->adapter->parse($html);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testHandlesInvalidJsonLd(): void
    {
        $html = '<html><head><script type="application/ld+json">not valid json</script></head><body></body></html>';
        
        $result = $this->adapter->parse($html);

        $this->assertIsArray($result);
    }

    public function testHandlesMultipleJsonLdScripts(): void
    {
        $jsonLd1 = ['@type' => 'WebSite'];
        $jsonLd2 = [
            '@type' => 'Product',
            'name' => 'Test Product',
            'offers' => ['price' => '29.99'],
        ];

        $html = '<html><head>'
            . '<script type="application/ld+json">' . json_encode($jsonLd1) . '</script>'
            . '<script type="application/ld+json">' . json_encode($jsonLd2) . '</script>'
            . '</head><body></body></html>';
        
        $result = $this->adapter->parse($html);

        $this->assertSame('Test Product', $result['name']);
    }

    public function testFiltersOutEmptyResults(): void
    {
        // The parse method uses array_filter, so empty values should be removed
        $html = '<html><body></body></html>';
        
        $result = $this->adapter->parse($html);

        $this->assertIsArray($result);
        // Should not contain any null or empty string values
        foreach ($result as $value) {
            $this->assertNotNull($value);
            $this->assertNotSame('', $value);
        }
    }

    public function testPriceValidation(): void
    {
        // Price too low should be filtered
        $html = '<html><body><span class="price">£0.01</span></body></html>';
        $result = $this->adapter->parse($html);
        $this->assertArrayNotHasKey('price', $result);

        // Valid price
        $html2 = '<html><body><span class="price">£15.99</span></body></html>';
        $result2 = $this->adapter->parse($html2);
        $this->assertArrayHasKey('price', $result2);
    }
}
