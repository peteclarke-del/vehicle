<?php

declare(strict_types=1);

namespace App\Service\SiteAdapter;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AmazonAdapter implements SiteAdapterInterface
{
    private LoggerInterface $logger;
    private HttpClientInterface $httpClient;
    private ?string $accessKey;
    private ?string $secretKey;
    private ?string $associateTag;
    private ?string $region;

    public function __construct(
        LoggerInterface $logger,
        HttpClientInterface $httpClient,
        ?string $amazonAccessKey = null,
        ?string $amazonSecretKey = null,
        ?string $amazonAssociateTag = null,
        string $amazonRegion = 'eu-west-1'
    ) {
        $this->logger = $logger;
        $this->httpClient = $httpClient;
        $this->accessKey = $amazonAccessKey;
        $this->secretKey = $amazonSecretKey;
        $this->associateTag = $amazonAssociateTag;
        $this->region = $amazonRegion;
    }

    public function setContext(array $context): void
    {
        // Amazon adapter doesn't need context for scraping
    }

    public function supports(string $host, string $html): bool
    {
        return str_contains($host, 'amazon.');
    }

    public function parse(string $html): array
    {
        // Fallback scraping method
        $xp = $this->createXPath($html);
        $data = [];

        // Title
        $title = trim($xp->evaluate('string(//*[@id="productTitle"])'));
        if ($title) {
            $data['name'] = $title;
        }

        // Price (whole + fraction)
        $priceWhole = $xp->evaluate('string(//span[@class="a-price-whole"])');
        $priceFraction = $xp->evaluate('string(//span[@class="a-price-fraction"])');
        
        if ($priceWhole) {
            $price = $priceWhole . $priceFraction;
            $data['price'] = str_replace(',', '', $price);
        }

        // Alternative price location
        if (!isset($data['price'])) {
            $altPrice = trim($xp->evaluate('string(//span[@class="a-offscreen"])'));
            if ($altPrice && preg_match('/[\d\.,]+/', $altPrice, $m)) {
                $data['price'] = str_replace(',', '', $m[0]);
            }
        }

        // Description
        $desc = trim($xp->evaluate('string(//*[@id="feature-bullets"])'));
        if ($desc) {
            $data['description'] = $desc;
        }

        // Manufacturer/Brand
        $brand = trim($xp->evaluate('string(//*[@id="bylineInfo"])'));
        if ($brand) {
            $data['manufacturer'] = preg_replace('/^(Visit the |Brand: )/i', '', $brand);
        }

        $this->logger->info('Amazon: Extracted data', $data);
        return array_filter($data);
    }

    /**
     * Fetch product details using Amazon PA-API
     */
    public function fetchFromApi(string $asin): array
    {
        if (!$this->accessKey || !$this->secretKey || !$this->associateTag) {
            $this->logger->warning('Amazon: API credentials not configured');
            return [];
        }

        try {
            $this->logger->info('Amazon: Fetching item via PA-API', ['asin' => $asin]);

            $host = $this->getHost();
            $endpoint = "https://{$host}/paapi5/getitems";
            
            $payload = [
                'ItemIds' => [$asin],
                'Resources' => [
                    'ItemInfo.Title',
                    'ItemInfo.Features',
                    'ItemInfo.ByLineInfo',
                    'ItemInfo.ManufactureInfo',
                    'Offers.Listings.Price',
                ],
                'PartnerTag' => $this->associateTag,
                'PartnerType' => 'Associates',
                'Marketplace' => $this->getMarketplace(),
            ];

            $payloadJson = json_encode($payload);
            $headers = $this->signRequest('POST', $endpoint, $payloadJson);

            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => $headers,
                'body' => $payloadJson,
            ]);

            $data = $response->toArray(false);

            if (isset($data['Errors'])) {
                $this->logger->error('Amazon API error', ['error' => $data['Errors']]);
                return [];
            }

            $item = $data['ItemsResult']['Items'][0] ?? null;
            if (!$item) {
                return [];
            }

            $result = [
                'name' => $item['ItemInfo']['Title']['DisplayValue'] ?? null,
                'price' => $item['Offers']['Listings'][0]['Price']['Amount'] ?? null,
                'description' => isset($item['ItemInfo']['Features']['DisplayValues'])
                    ? implode('. ', $item['ItemInfo']['Features']['DisplayValues'])
                    : null,
                'manufacturer' => $item['ItemInfo']['ByLineInfo']['Manufacturer']['DisplayValue']
                    ?? $item['ItemInfo']['ByLineInfo']['Brand']['DisplayValue']
                    ?? null,
            ];

            $this->logger->info('Amazon: API fetch successful', $result);
            return array_filter($result);
        } catch (\Exception $e) {
            $this->logger->error('Amazon API error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Extract ASIN from Amazon URL
     */
    public function extractAsin(string $url): ?string
    {
        // Match patterns like /dp/B08N5WRWNW or /gp/product/B08N5WRWNW
        if (preg_match('/\/(dp|gp\/product)\/([A-Z0-9]{10})/', $url, $matches)) {
            return $matches[2];
        }
        return null;
    }

    private function signRequest(string $method, string $url, string $payload): array
    {
        $timestamp = gmdate('Ymd\THis\Z');
        $date = substr($timestamp, 0, 8);

        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'];
        $path = $parsedUrl['path'];

        // Create canonical request
        $hashedPayload = hash('sha256', $payload);
        $canonicalHeaders = "content-type:application/json; charset=utf-8\nhost:{$host}\nx-amz-date:{$timestamp}\n";
        $signedHeaders = 'content-type;host;x-amz-date';
        $canonicalRequest = "{$method}\n{$path}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$hashedPayload}";

        // Create string to sign
        $hashedCanonicalRequest = hash('sha256', $canonicalRequest);
        $credentialScope = "{$date}/{$this->region}/ProductAdvertisingAPI/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n{$timestamp}\n{$credentialScope}\n{$hashedCanonicalRequest}";

        // Calculate signature
        $kDate = hash_hmac('sha256', $date, 'AWS4' . $this->secretKey, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 'ProductAdvertisingAPI', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        // Create authorization header
        $authorization = "AWS4-HMAC-SHA256 Credential={$this->accessKey}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        return [
            'Content-Type' => 'application/json; charset=utf-8',
            'Host' => $host,
            'X-Amz-Date' => $timestamp,
            'X-Amz-Target' => 'com.amazon.paapi5.v1.ProductAdvertisingAPIv1.GetItems',
            'Authorization' => $authorization,
        ];
    }

    private function getHost(): string
    {
        $hosts = [
            'us-east-1' => 'webservices.amazon.com',
            'eu-west-1' => 'webservices.amazon.co.uk',
            'us-west-2' => 'webservices.amazon.com',
            'ap-northeast-1' => 'webservices.amazon.co.jp',
        ];
        return $hosts[$this->region] ?? 'webservices.amazon.co.uk';
    }

    private function getMarketplace(): string
    {
        $marketplaces = [
            'us-east-1' => 'www.amazon.com',
            'eu-west-1' => 'www.amazon.co.uk',
            'us-west-2' => 'www.amazon.com',
            'ap-northeast-1' => 'www.amazon.co.jp',
        ];
        return $marketplaces[$this->region] ?? 'www.amazon.co.uk';
    }

    private function createXPath(string $html): \DOMXPath
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        return new \DOMXPath($dom);
    }
}
