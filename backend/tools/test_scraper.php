<?php

// Simple test to see what the scraper actually returns

$url = "https://www.ebay.co.uk/itm/326304900639";

// Simulate the API call
$ch = curl_init('http://localhost:8000/api/parts/scrape-url');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['url' => $url]));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: {$httpCode}\n";
echo "Response:\n";
echo json_encode(json_decode($response, true), JSON_PRETTY_PRINT);
echo "\n";

// Check specific fields
$data = json_decode($response, true);
echo "\nField Check:\n";
echo "- supplier: " . (isset($data['supplier']) ? "'{$data['supplier']}'" : "NOT SET") . "\n";
echo "- partNumber: " . (isset($data['partNumber']) ? "'{$data['partNumber']}'" : "NOT SET") . "\n";
echo "- manufacturer: " . (isset($data['manufacturer']) ? "'{$data['manufacturer']}'" : "NOT SET") . "\n";
