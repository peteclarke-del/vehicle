#!/usr/bin/env php
<?php

/**
 * Test script for API Ninjas vehicle specification scraping
 * 
 * Usage: php tests/test_api_ninjas_scraping.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// Load environment variables
$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/../.env');

$apiKey = $_ENV['API_NINJAS_KEY'] ?? null;

if (!$apiKey) {
    echo "ERROR: API_NINJAS_KEY not found in environment variables\n";
    exit(1);
}

echo "=== API Ninjas Scraping Test ===\n\n";
echo "API Key: " . substr($apiKey, 0, 8) . "...\n\n";

// Test cases
$testCases = [
    [
        'type' => 'Motorcycle',
        'endpoint' => 'motorcycles',
        'params' => [
            'make' => 'Yamaha',
            'model' => 'YZF-R1',
            'year' => 2020
        ]
    ],
    [
        'type' => 'Car',
        'endpoint' => 'cars',
        'params' => [
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2020
        ]
    ],
    [
        'type' => 'Car',
        'endpoint' => 'cars',
        'params' => [
            'make' => 'Honda',
            'model' => 'Civic',
            'year' => 2019
        ]
    ]
];

function testApiNinjas($endpoint, $params, $apiKey) {
    $url = 'https://api.api-ninjas.com/v1/' . $endpoint . '?' . http_build_query($params);
    
    echo "Testing: $endpoint with " . json_encode($params) . "\n";
    echo "URL: $url\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Api-Key: ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    
    $startTime = microtime(true);
    $response = curl_exec($ch);
    $duration = microtime(true) - $startTime;
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    fclose($verbose);
    
    curl_close($ch);
    
    echo "HTTP Status: $httpCode\n";
    echo "Response Time: " . round($duration * 1000, 2) . "ms\n";
    
    if ($curlError) {
        echo "CURL Error: $curlError\n";
    }
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        echo "Response Data:\n";
        echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
        echo "Result Count: " . (is_array($data) ? count($data) : 0) . "\n";
        
        if (is_array($data) && count($data) > 0) {
            echo "✓ SUCCESS: Found " . count($data) . " result(s)\n";
            echo "First result keys: " . implode(', ', array_keys($data[0])) . "\n";
        } else {
            echo "⚠ WARNING: Request succeeded but returned empty results\n";
        }
    } else {
        echo "✗ FAILED: HTTP $httpCode\n";
        echo "Response Body: $response\n";
        echo "\nVerbose CURL Log:\n$verboseLog\n";
    }
    
    echo "\n" . str_repeat('-', 80) . "\n\n";
    
    return $httpCode === 200;
}

// Run tests
$results = [];
foreach ($testCases as $test) {
    $success = testApiNinjas($test['endpoint'], $test['params'], $apiKey);
    $results[] = [
        'type' => $test['type'],
        'params' => $test['params'],
        'success' => $success
    ];
    
    // Small delay between requests
    sleep(1);
}

// Summary
echo "\n=== Test Summary ===\n";
$successCount = 0;
foreach ($results as $result) {
    $status = $result['success'] ? '✓' : '✗';
    echo "$status {$result['type']}: {$result['params']['make']} {$result['params']['model']} ({$result['params']['year']})\n";
    if ($result['success']) {
        $successCount++;
    }
}

echo "\nTotal: $successCount/" . count($results) . " tests passed\n";

exit($successCount === count($results) ? 0 : 1);
