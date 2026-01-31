<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\CostCalculator;
use App\Service\DepreciationCalculator;
use App\Entity\Vehicle;
use App\Entity\ServiceRecord;
use App\Entity\Part;
use App\Entity\Consumable;
use App\Entity\FuelRecord;
use App\Entity\InsurancePolicy;
use PHPUnit\Framework\TestCase;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\AbstractQuery;

/**
 * Cost Calculator Test
 * 
 * Unit tests for vehicle cost calculations
 */
class CostCalculatorTest extends TestCase
{
    private CostCalculator $calculator;
    private EntityManagerInterface $entityManager;
    private DepreciationCalculator $depreciationCalculator;

    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->depreciationCalculator = $this->createMock(DepreciationCalculator::class);
        $this->calculator = new CostCalculator($this->entityManager, $this->depreciationCalculator);
    }

    /**
     * Test calculating total vehicle costs
     */
    public function testCalculateTotalCosts(): void
    {
        $vehicle = $this->createMockVehicle();

        $result = $this->calculator->calculateTotalCosts($vehicle);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('breakdown', $result);
        $this->assertIsFloat($result['total']);
    }

    /**
     * Test cost breakdown includes all categories
     */
    public function testCostBreakdownIncludesAllCategories(): void
    {
        $vehicle = $this->createMockVehicle();

        $result = $this->calculator->calculateTotalCosts($vehicle);
        $breakdown = $result['breakdown'];

        $this->assertArrayHasKey('service', $breakdown);
        $this->assertArrayHasKey('parts', $breakdown);
        $this->assertArrayHasKey('consumables', $breakdown);
        $this->assertArrayHasKey('fuel', $breakdown);
        $this->assertArrayHasKey('insurance', $breakdown);
        $this->assertArrayHasKey('purchase', $breakdown);
    }

    /**
     * Test calculating service costs
     */
    public function testCalculateServiceCosts(): void
    {
        $vehicle = $this->createMockVehicle();

        // Add service records
        $service1 = new ServiceRecord();
        $service1->setLabourCost(100.00);
        $service1->setPartsCost(50.00);

        $service2 = new ServiceRecord();
        $service2->setLabourCost(200.00);
        $service2->setPartsCost(75.00);

        $vehicle->addServiceRecord($service1);
        $vehicle->addServiceRecord($service2);

        $result = $this->calculator->calculateTotalCosts($vehicle);

        $this->assertSame(425.00, $result['breakdown']['service']);
    }

    /**
     * Test calculating parts costs
     */
    public function testCalculatePartsCosts(): void
    {
        $vehicle = $this->createMockVehicle();

        // Add parts
        $part1 = new Part();
        $part1->setPrice(150.00);
        $part1->setQuantity(2);

        $part2 = new Part();
        $part2->setPrice(75.50);
        $part2->setQuantity(1);

        $vehicle->addPart($part1);
        $vehicle->addPart($part2);

        $result = $this->calculator->calculateTotalCosts($vehicle);

        $this->assertSame(375.50, $result['breakdown']['parts']);
    }

    /**
     * Test calculating consumables costs
     */
    public function testCalculateConsumablesCosts(): void
    {
        $vehicle = $this->createMockVehicle();

        // Add consumables
        $consumable1 = new Consumable();
        $consumable1->setCost(45.00);

        $consumable2 = new Consumable();
        $consumable2->setCost(32.50);

        $vehicle->addConsumable($consumable1);
        $vehicle->addConsumable($consumable2);

        $result = $this->calculator->calculateTotalCosts($vehicle);

        $this->assertSame(77.50, $result['breakdown']['consumables']);
    }

    /**
     * Test calculating fuel costs
     */
    public function testCalculateFuelCosts(): void
    {
        $vehicle = $this->createMockVehicle();

        // Add fuel records
        $fuel1 = new FuelRecord();
        $fuel1->setCost(60.00);

        $fuel2 = new FuelRecord();
        $fuel2->setCost(55.00);

        $fuel3 = new FuelRecord();
        $fuel3->setCost(58.50);

        $vehicle->addFuelRecord($fuel1);
        $vehicle->addFuelRecord($fuel2);
        $vehicle->addFuelRecord($fuel3);

        $result = $this->calculator->calculateTotalCosts($vehicle);

        $this->assertSame(173.50, $result['breakdown']['fuel']);
    }

    /**
     * Test calculating insurance costs
     */
    public function testCalculateInsuranceCosts(): void
    {
        $vehicle = $this->createMockVehicle();

        // Add insurance policy records
        $policy1 = new InsurancePolicy();
        $policy1->setAnnualCost(650.00);

        $policy2 = new InsurancePolicy();
        $policy2->setAnnualCost(700.00);

        $vehicle->addInsurancePolicy($policy1);
        $vehicle->addInsurancePolicy($policy2);

        $result = $this->calculator->calculateTotalCosts($vehicle);

        $this->assertSame(1350.00, $result['breakdown']['insurance']);
    }

    /**
     * Test purchase cost is included
     */
    public function testPurchaseCostIsIncluded(): void
    {
        $vehicle = $this->createMockVehicle();
        $vehicle->setPurchasePrice(15000.00);

        $result = $this->calculator->calculateTotalCosts($vehicle);

        $this->assertSame(15000.00, $result['breakdown']['purchase']);
    }

    /**
     * Test total is sum of all costs
     */
    public function testTotalIsSumOfAllCosts(): void
    {
        $vehicle = $this->createMockVehicle();
        $vehicle->setPurchasePrice(10000.00);

        // Add various costs
        $service = new ServiceRecord();
        $service->setLabourCost(100.00);
        $vehicle->addServiceRecord($service);

        $part = new Part();
        $part->setPrice(50.00);
        $part->setQuantity(1);
        $vehicle->addPart($part);

        $fuel = new FuelRecord();
        $fuel->setCost(60.00);
        $vehicle->addFuelRecord($fuel);

        $result = $this->calculator->calculateTotalCosts($vehicle);

        $expectedTotal = 10000.00 + 100.00 + 50.00 + 60.00;
        $this->assertSame($expectedTotal, $result['total']);
    }

    /**
     * Test handling vehicle with no costs
     */
    public function testHandleVehicleWithNoCosts(): void
    {
        $vehicle = $this->createMockVehicle();

        $result = $this->calculator->calculateTotalCosts($vehicle);

        $this->assertSame(0.0, $result['total']);
        $this->assertSame(0.0, $result['breakdown']['service']);
        $this->assertSame(0.0, $result['breakdown']['parts']);
    }

    /**
     * Test cost per mile calculation
     */
    public function testCostPerMileCalculation(): void
    {
        $vehicle = $this->createMockVehicle();
        $vehicle->setPurchasePrice(10000.00);
        $vehicle->setCurrentMileage(50000);
        $vehicle->setPurchaseMileage(20000);

        $service = new ServiceRecord();
        $service->setLabourCost(500.00);
        $vehicle->addServiceRecord($service);

        $costPerMile = $this->calculator->calculateCostPerMile($vehicle);

        // Total costs: 10500.00
        // Miles driven: 30000
        // Cost per mile: 0.35
        $this->assertEqualsWithDelta(0.35, $costPerMile, 0.01);
    }

    /**
     * Test cost per mile with zero miles returns zero
     */
    public function testCostPerMileWithZeroMiles(): void
    {
        $vehicle = $this->createMockVehicle();
        $vehicle->setCurrentMileage(10000);
        $vehicle->setPurchaseMileage(10000); // No miles driven

        $costPerMile = $this->calculator->calculateCostPerMile($vehicle);

        $this->assertSame(0.0, $costPerMile);
    }

    /**
     * Test monthly cost calculation
     */
    public function testMonthlyCostCalculation(): void
    {
        $vehicle = $this->createMockVehicle();
        $vehicle->setPurchaseDate(new \DateTime('-12 months'));

        $service = new ServiceRecord();
        $service->setLabourCost(1200.00);
        $vehicle->addServiceRecord($service);

        $monthlyCost = $this->calculator->calculateMonthlyCost($vehicle);

        // Total: 1200.00 over 12 months = 100.00/month
        $this->assertSame(100.00, $monthlyCost);
    }

    /**
     * Test annual cost projection
     */
    public function testAnnualCostProjection(): void
    {
        $vehicle = $this->createMockVehicle();
        $vehicle->setPurchaseDate(new \DateTime('-6 months'));

        $service = new ServiceRecord();
        $service->setLabourCost(600.00);
        $vehicle->addServiceRecord($service);

        $annualProjection = $this->calculator->calculateAnnualProjection($vehicle);

        // 600.00 over 6 months = 1200.00 annually
        $this->assertSame(1200.00, $annualProjection);
    }

    /**
     * Create a mock vehicle for testing
     * 
     * @return Vehicle
     */
    private function createMockVehicle(): Vehicle
    {
        $vehicle = new Vehicle();
        $vehicle->setRegistration('TEST123');
        $vehicle->setMake('Test Make');
        $vehicle->setModel('Test Model');
        $vehicle->setYear(2020);
        $vehicle->setCurrentMileage(50000);
        $vehicle->setPurchaseMileage(20000);
        $vehicle->setPurchaseDate(new \DateTime('-24 months'));

        return $vehicle;
    }
}
