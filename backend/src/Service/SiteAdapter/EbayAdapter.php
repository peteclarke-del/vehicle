<?php

declare(strict_types=1);

namespace App\Service\SiteAdapter;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EbayAdapter implements SiteAdapterInterface
{
    private LoggerInterface $logger;
    private HttpClientInterface $httpClient;
    private ?string $clientId;
    private ?string $clientSecret;
    private ?string $marketplace;
    private ?string $accessToken;
    private ?string $cacheDir;

    // Token cache keys
    private const TOKEN_CACHE_FILE = 'ebay_oauth_token.json';

    public function __construct(
        LoggerInterface $logger,
        HttpClientInterface $httpClient,
        ?string $ebayClientId = null,
        ?string $ebayClientSecret = null,
        string $ebayMarketplace = 'EBAY_GB',
        ?string $ebayAccessToken = null,
        ?string $cacheDir = null
    ) {
        $this->logger = $logger;
        $this->httpClient = $httpClient;
        $this->clientId = $ebayClientId;
        $this->clientSecret = $ebayClientSecret;
        $this->marketplace = $ebayMarketplace;
        $this->accessToken = $ebayAccessToken;
        $this->cacheDir = $cacheDir ?? sys_get_temp_dir();
    }

    public function setContext(array $context): void
    {
        // eBay adapter doesn't need context for scraping
    }

    public function supports(string $host, string $html): bool
    {
        return str_contains($host, 'ebay.');
    }

    public function parse(string $html): array
    {
        // This method is now used only as fallback
        // Try JSON-LD first (most reliable)
        $data = $this->extractJsonLd($html);
        if (!empty($data)) {
            $this->logger->info('eBay: Extracted from JSON-LD', $data);
            return $data;
        }

        // Fallback to DOM parsing
        $xp = $this->createXPath($html);
        $data = [];

        // Title
        $title = trim($xp->evaluate('string(//h1[contains(@class, "x-item-title")])'));
        if (!$title) {
            $title = trim($xp->evaluate('string(//h1)'));
        }
        if ($title) {
            $data['name'] = $title;
        }

        // Price
        $priceText = trim($xp->evaluate('string(//*[contains(@class, "x-price-primary")])'));
        if (!$priceText) {
            $priceText = trim($xp->evaluate('string(//*[@itemprop="price"])'));
        }
        if ($priceText && preg_match('/[\d\.,]+/', $priceText, $m)) {
            $data['price'] = str_replace(',', '', $m[0]);
        }

        // Description from meta
        $desc = trim($xp->evaluate('string(//meta[@property="og:description"]/@content)'));
        if ($desc) {
            $data['description'] = $desc;
        }

        $this->logger->info('eBay: Extracted from DOM', $data);
        return array_filter($data);
    }

    /**
     * Fetch product details using eBay Browse API
     */
    public function fetchFromApi(string $itemId): array
    {
        return $this->doFetchFromApi($itemId, false);
    }

    /**
     * Internal method to fetch from API with retry logic for expired tokens
     */
    private function doFetchFromApi(string $itemId, bool $isRetry): array
    {
        try {
            // On retry, force a fresh token by skipping cached/configured token
            $token = $isRetry ? $this->generateFreshToken() : ($this->accessToken ?? $this->getOAuthToken());
            if (!$token) {
                $this->logger->warning('eBay: No access token available');
                return [];
            }

            // Call Browse API
            $this->logger->info('eBay: Fetching item via API', ['item_id' => $itemId, 'is_retry' => $isRetry]);

            $response = $this->httpClient->request('GET', "https://api.ebay.com/buy/browse/v1/item/v1|{$itemId}|0", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'X-EBAY-C-MARKETPLACE-ID' => $this->marketplace,
                    'X-EBAY-C-ENDUSERCTX' => 'affiliateCampaignId=<ePNCampaignId>,affiliateReferenceId=<referenceId>',
                ],
            ]);

            $status = $response->getStatusCode();
            $data = $response->toArray(false);

            // On 401 (invalid/expired token), retry once with a fresh token
            if ($status === 401 && !$isRetry) {
                $this->logger->warning('eBay: Token expired/invalid, retrying with fresh token');
                $this->clearCachedToken();
                return $this->doFetchFromApi($itemId, true);
            }

            // Explicitly surface 404 (item not found) so callers do not fall back to fragile HTML scraping
            if ($status === 404) {
                $this->logger->info('eBay API: item not found via Browse API', ['item_id' => $itemId, 'status' => $status, 'body' => $data]);
                throw new \RuntimeException('eBay Browse API: item not found');
            }

            // Any other HTTP error -> surface as exception with helpful log
            if ($status >= 400) {
                $this->logger->error('eBay API HTTP error', ['status' => $status, 'body' => $data]);
                $message = $data['errors'][0]['message'] ?? 'HTTP ' . $status;
                throw new \RuntimeException('eBay Browse API error: ' . $message);
            }

            // Some responses include an `errors` array even with a 200-ish status — treat as error
            if (!empty($data['errors'])) {
                $this->logger->error('eBay API returned errors', ['errors' => $data['errors']]);
                foreach ($data['errors'] as $err) {
                    if (isset($err['errorId']) && (int)$err['errorId'] === 11001) {
                        throw new \RuntimeException('eBay Browse API: item not found');
                    }
                }
                $first = $data['errors'][0]['message'] ?? json_encode($data['errors']);
                throw new \RuntimeException('eBay Browse API error: ' . $first);
            }

            // Calculate total price (item price + shipping cost)
            $itemPrice = isset($data['price']['value']) ? (float)$data['price']['value'] : 0;
            $shippingCost = 0;

            // Get the cheapest shipping option
            if (!empty($data['shippingOptions'])) {
                $shippingCost = (float)($data['shippingOptions'][0]['shippingCost']['value'] ?? 0);
            }

            $totalPrice = $itemPrice + $shippingCost;

            // Try to get manufacturer/brand from multiple sources
            $manufacturer = $data['brand'] ?? null;
            if (!$manufacturer && !empty($data['localizedAspects'])) {
                foreach ($data['localizedAspects'] as $aspect) {
                    if (($aspect['name'] ?? '') === 'Brand') {
                        $manufacturer = $aspect['value'] ?? null;
                        break;
                    }
                }
            }

            // Part number: use MPN if available, otherwise use eBay item ID
            $partNumber = $data['mpn'] ?? $data['legacyItemId'] ?? null;

            // Supplier: always use seller username
            $supplier = $data['seller']['username'] ?? null;

            $result = [
                'name' => $data['title'] ?? null,
                'price' => $totalPrice > 0 ? $totalPrice : null,
                'description' => $data['shortDescription'] ?? $data['description'] ?? null,
                'manufacturer' => $manufacturer,
                'partNumber' => $partNumber,
                'supplier' => $supplier,
            ];

            $this->logger->info('eBay: API fetch successful', array_merge($result, [
                'item_price' => $itemPrice,
                'shipping_cost' => $shippingCost,
            ]));

            // Filter out only null values, keep empty strings and 0
            return array_filter($result, fn($value) => $value !== null);
        } catch (\Exception $e) {
            $this->logger->error('eBay API error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Extract item ID from eBay URL
     */
    public function extractItemId(string $url): ?string
    {
        // Match patterns like /itm/123456789 or /itm/product-name/123456789
        if (preg_match('/\/itm\/(?:[^\/]+\/)?(\d+)/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function getOAuthToken(): ?string
    {
        // First, check if we have a pre-configured token
        if ($this->accessToken) {
            return $this->accessToken;
        }

        if (!$this->clientId || !$this->clientSecret) {
            $this->logger->info('eBay: Client credentials not configured, cannot generate token');
            return null;
        }

        // Check for cached token
        $cachedToken = $this->getCachedToken();
        if ($cachedToken) {
            $this->logger->info('eBay: Using cached OAuth token');
            return $cachedToken;
        }

        try {
            $this->logger->info('eBay: Generating new OAuth token');
            $credentials = base64_encode($this->clientId . ':' . $this->clientSecret);

            $response = $this->httpClient->request('POST', 'https://api.ebay.com/identity/v1/oauth2/token', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Basic ' . $credentials,
                ],
                'body' => [
                    'grant_type' => 'client_credentials',
                    'scope' => 'https://api.ebay.com/oauth/api_scope',
                ],
            ]);

            $data = $response->toArray(false);
            $token = $data['access_token'] ?? null;
            $expiresIn = $data['expires_in'] ?? 7200; // Default 2 hours

            if ($token) {
                $this->cacheToken($token, (int)$expiresIn);
            }

            return $token;
        } catch (\Exception $e) {
            $this->logger->error('eBay OAuth error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get cached OAuth token if still valid
     */
    private function getCachedToken(): ?string
    {
        $cacheFile = $this->cacheDir . '/' . self::TOKEN_CACHE_FILE;

        if (!file_exists($cacheFile)) {
            return null;
        }

        try {
            $data = json_decode(file_get_contents($cacheFile), true);
            if (!$data || !isset($data['token'], $data['expires_at'])) {
                return null;
            }

            // Check if token is still valid (with 5-minute buffer)
            if ($data['expires_at'] > (time() + 300)) {
                return $data['token'];
            }

            $this->logger->info('eBay: Cached token expired');
            return null;
        } catch (\Exception $e) {
            $this->logger->warning('eBay: Failed to read cached token: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Clear the cached OAuth token
     */
    private function clearCachedToken(): void
    {
        $cacheFile = $this->cacheDir . '/' . self::TOKEN_CACHE_FILE;

        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
            $this->logger->info('eBay: Cleared cached token');
        }
    }

    /**
     * Generate a fresh OAuth token, bypassing any cached or configured token
     */
    private function generateFreshToken(): ?string
    {
        if (!$this->clientId || !$this->clientSecret) {
            $this->logger->info('eBay: Client credentials not configured, cannot generate token');
            return null;
        }

        try {
            $this->logger->info('eBay: Generating fresh OAuth token');
            $credentials = base64_encode($this->clientId . ':' . $this->clientSecret);

            $response = $this->httpClient->request('POST', 'https://api.ebay.com/identity/v1/oauth2/token', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Basic ' . $credentials,
                ],
                'body' => [
                    'grant_type' => 'client_credentials',
                    'scope' => 'https://api.ebay.com/oauth/api_scope',
                ],
            ]);

            $data = $response->toArray(false);
            $token = $data['access_token'] ?? null;
            $expiresIn = $data['expires_in'] ?? 7200;

            if ($token) {
                $this->cacheToken($token, (int)$expiresIn);
            }

            return $token;
        } catch (\Exception $e) {
            $this->logger->error('eBay OAuth error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Cache OAuth token to file
     */
    private function cacheToken(string $token, int $expiresIn): void
    {
        $cacheFile = $this->cacheDir . '/' . self::TOKEN_CACHE_FILE;

        try {
            $data = [
                'token' => $token,
                'expires_at' => time() + $expiresIn,
                'created_at' => time(),
            ];

            file_put_contents($cacheFile, json_encode($data), LOCK_EX);
            $this->logger->info('eBay: Cached OAuth token', ['expires_in' => $expiresIn]);
        } catch (\Exception $e) {
            $this->logger->warning('eBay: Failed to cache token: ' . $e->getMessage());
        }
    }

    private function extractJsonLd(string $html): array
    {
        if (!preg_match('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/s', $html, $m)) {
            return [];
        }

        $json = json_decode($m[1], true);
        if (!$json || !isset($json['name'])) {
            return [];
        }

        $data = [
            'name' => $json['name'] ?? null,
            'description' => $json['description'] ?? null,
        ];

        // Handle offers
        if (isset($json['offers']['price'])) {
            $data['price'] = $json['offers']['price'];
        } elseif (isset($json['offers'][0]['price'])) {
            $data['price'] = $json['offers'][0]['price'];
        }

        // Handle brand
        if (isset($json['brand']['name'])) {
            $data['manufacturer'] = $json['brand']['name'];
        } elseif (isset($json['brand']) && is_string($json['brand'])) {
            $data['manufacturer'] = $json['brand'];
        }

        return array_filter($data);
    }

    private function createXPath(string $html): \DOMXPath
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        return new \DOMXPath($dom);
    }
}
