<?php

require __DIR__ . '/backend/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpClient\HttpClient;

// Load .env
$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/backend/.env');

$token = $_ENV['EBAY_ACCESS_TOKEN'] ?? null;
$marketplace = $_ENV['EBAY_MARKETPLACE'] ?? 'EBAY_GB';
$itemId = '326304900639';

if (!$token) {
    echo "❌ EBAY_ACCESS_TOKEN not found in .env\n";
    exit(1);
}

echo "🔍 Testing eBay Browse API\n";
echo "Item ID: {$itemId}\n";
echo "Marketplace: {$marketplace}\n";
echo "Token: " . substr($token, 0, 20) . "...\n\n";

$client = HttpClient::create();

try {
    $response = $client->request('GET', "https://api.ebay.com/buy/browse/v1/item/v1|{$itemId}|0", [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'X-EBAY-C-MARKETPLACE-ID' => $marketplace,
            'Content-Type' => 'application/json',
        ],
    ]);

    $statusCode = $response->getStatusCode();
    echo "📡 HTTP Status: {$statusCode}\n\n";

    if ($statusCode === 200) {
        $data = $response->toArray(false);
        
        if (isset($data['errors'])) {
            echo "❌ eBay API Error:\n";
            print_r($data['errors']);
        } else {
            echo "✅ Success! Product Data:\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "Title: " . ($data['title'] ?? 'N/A') . "\n";
            echo "Price: " . ($data['price']['value'] ?? 'N/A') . " " . ($data['price']['currency'] ?? '') . "\n";
            echo "Condition: " . ($data['condition'] ?? 'N/A') . "\n";
            echo "Brand: " . ($data['brand'] ?? 'N/A') . "\n";
            echo "MPN: " . ($data['mpn'] ?? 'N/A') . "\n";
            echo "Category: " . ($data['categoryPath'] ?? 'N/A') . "\n";
            
            if (!empty($data['shortDescription'])) {
                echo "\nDescription:\n" . substr(strip_tags($data['shortDescription']), 0, 200) . "...\n";
            }
            
            echo "\n📦 Full response saved to ebay_response.json\n";
            file_put_contents(__DIR__ . '/ebay_response.json', json_encode($data, JSON_PRETTY_PRINT));
        }
    } else {
        echo "❌ Unexpected status code\n";
        echo $response->getContent(false);
    }

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    if (method_exists($e, 'getResponse')) {
        $response = $e->getResponse();
        echo "\n🔍 Response body:\n";
        echo $response->getContent(false) . "\n";
    }
}
