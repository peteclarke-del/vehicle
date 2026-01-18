#!/usr/bin/env php
<?php

/**
 * Test script to debug vehicle specification scraping issues
 * This script checks a specific vehicle and attempts to scrape its specifications
 */

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

// Load environment variables
$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/../.env');

// Boot the Symfony kernel
$kernel = new Kernel($_ENV['APP_ENV'] ?? 'dev', (bool) ($_ENV['APP_DEBUG'] ?? true));
$kernel->boot();
$container = $kernel->getContainer();

// Get required services
$entityManager = $container->get('doctrine.orm.entity_manager');

// Create HTTP client and logger manually since services might not be public
$httpClient = Symfony\Component\HttpClient\HttpClient::create();
$logger = new Symfony\Component\HttpKernel\Log\Logger();
$apiKey = $_ENV['API_NINJAS_KEY'] ?? '';

// Create adapters manually
$motorcycleAdapter = new App\Service\VehicleSpecAdapter\ApiNinjasMotorcycleAdapter(
    $httpClient,
    $logger,
    $apiKey
);
$carAdapter = new App\Service\VehicleSpecAdapter\ApiNinjasCarAdapter(
    $httpClient,
    $logger,
    $apiKey
);

// Create scraper service with adapters
$scraperService = new App\Service\VehicleSpecificationScraperService($logger);
$scraperService->registerAdapter($motorcycleAdapter);
$scraperService->registerAdapter($carAdapter);

echo "=== Vehicle Specification Scraping Debug ===\n\n";

// Get vehicle ID from command line or use default
$vehicleId = $argv[1] ?? null;

if (!$vehicleId) {
    // List all vehicles
    $vehicles = $entityManager->getRepository(App\Entity\Vehicle::class)->findAll();
    
    if (empty($vehicles)) {
        echo "No vehicles found in database\n";
        exit(1);
    }
    
    echo "Available vehicles:\n";
    foreach ($vehicles as $vehicle) {
        echo sprintf(
            "  ID: %d - %s %s %s (%s) - VIN: %s - Reg: %s\n",
            $vehicle->getId(),
            $vehicle->getYear() ?: 'N/A',
            $vehicle->getMake() ?: 'Unknown',
            $vehicle->getModel() ?: 'Unknown',
            $vehicle->getVehicleType() ?: 'Unknown Type',
            $vehicle->getVin() ?: 'N/A',
            $vehicle->getRegistrationNumber() ?: 'N/A'
        );
    }
    
    echo "\nUsage: php " . $argv[0] . " <vehicle_id>\n";
    exit(0);
}

// Fetch the specific vehicle
$vehicle = $entityManager->getRepository(App\Entity\Vehicle::class)->find($vehicleId);

if (!$vehicle) {
    echo "ERROR: Vehicle with ID $vehicleId not found\n";
    exit(1);
}

// Display vehicle information
echo "Testing vehicle:\n";
echo "  ID: " . $vehicle->getId() . "\n";
echo "  Make: " . ($vehicle->getMake() ?: 'NOT SET') . "\n";
echo "  Model: " . ($vehicle->getModel() ?: 'NOT SET') . "\n";
echo "  Year: " . ($vehicle->getYear() ?: 'NOT SET') . "\n";
echo "  Vehicle Type: " . ($vehicle->getVehicleType() ?: 'NOT SET') . "\n";
echo "  VIN: " . ($vehicle->getVin() ?: 'NOT SET') . "\n";
echo "  Registration: " . ($vehicle->getRegistrationNumber() ?: 'NOT SET') . "\n";
echo "\n";

// Check for existing specifications
$existingSpec = $entityManager->getRepository(App\Entity\Specification::class)->findOneBy(['vehicle' => $vehicle]);
if ($existingSpec) {
    echo "⚠ Vehicle already has specifications (scraped at: " . $existingSpec->getScrapedAt()?->format('Y-m-d H:i:s') . ")\n";
    echo "Source: " . ($existingSpec->getSourceUrl() ?: 'N/A') . "\n";
    echo "\n";
}

// Validate required fields
$errors = [];
if (!$vehicle->getMake()) {
    $errors[] = "Make is not set";
}
if (!$vehicle->getModel()) {
    $errors[] = "Model is not set";
}
if (!$vehicle->getVehicleType()) {
    $errors[] = "Vehicle type is not set";
}

if (!empty($errors)) {
    echo "✗ VALIDATION ERRORS:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
    echo "\nScraping cannot proceed without make, model, and vehicle type.\n";
    exit(1);
}

echo "✓ Vehicle has required fields\n\n";

// Attempt to scrape
echo "Attempting to scrape specifications...\n";
echo str_repeat('-', 80) . "\n";

try {
    $specification = $scraperService->scrapeSpecifications($vehicle);
    
    if ($specification) {
        echo "\n✓ SUCCESS! Specifications found:\n\n";
        
        // Display some key specifications
        if ($specification->getEngineType()) {
            echo "  Engine Type: " . $specification->getEngineType() . "\n";
        }
        if ($specification->getDisplacement()) {
            echo "  Displacement: " . $specification->getDisplacement() . "\n";
        }
        if ($specification->getPower()) {
            echo "  Power: " . $specification->getPower() . "\n";
        }
        if ($specification->getTorque()) {
            echo "  Torque: " . $specification->getTorque() . "\n";
        }
        if ($specification->getGearbox()) {
            echo "  Gearbox: " . $specification->getGearbox() . "\n";
        }
        if ($specification->getFuelCapacity()) {
            echo "  Fuel Capacity: " . $specification->getFuelCapacity() . "\n";
        }
        if ($specification->getDryWeight()) {
            echo "  Dry Weight: " . $specification->getDryWeight() . "\n";
        }
        if ($specification->getWetWeight()) {
            echo "  Wet Weight: " . $specification->getWetWeight() . "\n";
        }
        if ($specification->getTopSpeed()) {
            echo "  Top Speed: " . $specification->getTopSpeed() . "\n";
        }
        
        echo "\n  Source URL: " . ($specification->getSourceUrl() ?: 'N/A') . "\n";
        echo "  Scraped At: " . ($specification->getScrapedAt() ? $specification->getScrapedAt()->format('Y-m-d H:i:s') : 'N/A') . "\n";
        
        // Ask if user wants to save
        echo "\nWould you like to save these specifications? (y/n): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        if (trim($line) === 'y') {
            if ($existingSpec) {
                // Update existing - copy all fields from new specification
                $existingSpec->setEngineType($specification->getEngineType());
                $existingSpec->setDisplacement($specification->getDisplacement());
                $existingSpec->setPower($specification->getPower());
                $existingSpec->setTorque($specification->getTorque());
                $existingSpec->setCompression($specification->getCompression());
                $existingSpec->setBore($specification->getBore());
                $existingSpec->setStroke($specification->getStroke());
                $existingSpec->setFuelSystem($specification->getFuelSystem());
                $existingSpec->setCooling($specification->getCooling());
                $existingSpec->setGearbox($specification->getGearbox());
                $existingSpec->setTransmission($specification->getTransmission());
                $existingSpec->setClutch($specification->getClutch());
                $existingSpec->setFrame($specification->getFrame());
                $existingSpec->setFrontSuspension($specification->getFrontSuspension());
                $existingSpec->setRearSuspension($specification->getRearSuspension());
                $existingSpec->setFrontBrakes($specification->getFrontBrakes());
                $existingSpec->setRearBrakes($specification->getRearBrakes());
                $existingSpec->setFrontTyre($specification->getFrontTyre());
                $existingSpec->setRearTyre($specification->getRearTyre());
                $existingSpec->setFrontWheelTravel($specification->getFrontWheelTravel());
                $existingSpec->setRearWheelTravel($specification->getRearWheelTravel());
                $existingSpec->setWheelbase($specification->getWheelbase());
                $existingSpec->setSeatHeight($specification->getSeatHeight());
                $existingSpec->setGroundClearance($specification->getGroundClearance());
                $existingSpec->setDryWeight($specification->getDryWeight());
                $existingSpec->setWetWeight($specification->getWetWeight());
                $existingSpec->setFuelCapacity($specification->getFuelCapacity());
                $existingSpec->setTopSpeed($specification->getTopSpeed());
                $existingSpec->setAdditionalInfo($specification->getAdditionalInfo());
                $existingSpec->setScrapedAt(new DateTime());
                $existingSpec->setSourceUrl($specification->getSourceUrl());
            } else {
                $specification->setVehicle($vehicle);
                $entityManager->persist($specification);
            }
            $entityManager->flush();
            echo "✓ Specifications saved to database\n";
        } else {
            echo "Specifications not saved\n";
        }
        
        exit(0);
    } else {
        echo "\n✗ FAILED: No specifications found\n";
        echo "\nPossible reasons:\n";
        echo "  1. Make/Model combination not found in API database\n";
        echo "  2. Year might not match available data\n";
        echo "  3. Model name might need to be more specific\n";
        echo "  4. API adapter doesn't support this vehicle type\n";
        echo "\nCheck the logs above for more details.\n";
        exit(1);
    }
} catch (\Exception $e) {
    echo "\n✗ EXCEPTION: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
