<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Kernel;
use App\Entity\Vehicle;
use App\Service\CostCalculator;

$kernel = new Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

$em = $container->get('doctrine')->getManager();

// Accept an optional vehicle ID as first CLI argument (default: 1)
$id = isset($argv[1]) && is_numeric($argv[1]) ? (int) $argv[1] : 1;

$vehicle = $em->getRepository(Vehicle::class)->find($id);
if (!$vehicle) {
    echo "Vehicle #{$id} not found." . PHP_EOL;
    exit(1);
}

/** @var CostCalculator $calc */
$calc = $container->get(CostCalculator::class);

$stats = $calc->getVehicleStats($vehicle);

$output = [
    'id' => $vehicle->getId(),
    'displayName' => method_exists($vehicle, 'getDisplayName') ? $vehicle->getDisplayName() : null,
    'currentMileage' => $stats['currentMileage'] ?? $vehicle->getCurrentMileage(),
    'purchaseMileage' => method_exists($vehicle, 'getPurchaseMileage') ? $vehicle->getPurchaseMileage() : null,
    'milesSincePurchase' => $stats['milesSincePurchase'],
    'totalFuelCost' => $stats['totalFuelCost'],
    'totalPartsCost' => $stats['totalPartsCost'],
    'totalConsumablesCost' => $stats['totalConsumablesCost'],
    'totalRunningCost' => $stats['totalRunningCost'],
    'costPerMile' => $stats['costPerMile']
];

echo json_encode($output, JSON_PRETTY_PRINT) . PHP_EOL;

$kernel->shutdown();
