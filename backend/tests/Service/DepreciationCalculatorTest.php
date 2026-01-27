<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\DepreciationCalculator;
use App\Entity\Vehicle;
use PHPUnit\Framework\TestCase;

/**
 * Depreciation Calculator Test
 * 
 * Unit tests for vehicle depreciation calculations
 */
class DepreciationCalculatorTest extends TestCase
{
    private DepreciationCalculator $calculator;

    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        $this->calculator = new DepreciationCalculator();
    }

    /**
     * Test generating depreciation schedule
     */
    public function testGenerateDepreciationSchedule(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setPurchasePrice(20000.00);
        $vehicle->setPurchaseDate(new \DateTime('2020-01-01'));

        $schedule = $this->calculator->generateSchedule($vehicle, 5);

        $this->assertIsArray($schedule);
        $this->assertCount(6, $schedule);
        
        foreach ($schedule as $year => $value) {
            $this->assertIsInt($year);
            $this->assertIsFloat($value);
            $this->assertGreaterThanOrEqual(0, $value);
        }
    }

    /**
     * Test straight line depreciation method
     */
    public function testStraightLineDepreciation(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setPurchasePrice(20000.00);
        $vehicle->setPurchaseDate(new \DateTime('2020-01-01'));

        $schedule = $this->calculator->generateSchedule($vehicle, 5, 'straight_line');

        // Year 0: 20000, Year 1: 16000, Year 2: 12000, Year 3: 8000, Year 4: 4000, Year 5: 0
        $this->assertSame(20000.00, $schedule[0]);
        $this->assertSame(16000.00, $schedule[1]);
        $this->assertSame(12000.00, $schedule[2]);
        $this->assertSame(8000.00, $schedule[3]);
        $this->assertSame(4000.00, $schedule[4]);
        $this->assertSame(0.00, $schedule[5]);
    }

    /**
     * Test declining balance depreciation method
     */
    public function testDecliningBalanceDepreciation(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setPurchasePrice(20000.00);
        $vehicle->setPurchaseDate(new \DateTime('2020-01-01'));

        $schedule = $this->calculator->generateSchedule($vehicle, 5, 'declining_balance', 0.2);

        // 20% depreciation per year
        $this->assertSame(20000.00, $schedule[0]);
        $this->assertSame(16000.00, $schedule[1]); // 20000 * 0.8
        $this->assertSame(12800.00, $schedule[2]); // 16000 * 0.8
        $this->assertSame(10240.00, $schedule[3]); // 12800 * 0.8
        $this->assertSame(8192.00, $schedule[4]); // 10240 * 0.8
        $this->assertSame(6553.60, $schedule[5]); // 8192 * 0.8
    }

    /**
     * Test automotive standard depreciation (year 1: 20%, years 2-5: 15%)
     */
    public function testAutomotiveStandardDepreciation(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setPurchasePrice(20000.00);
        $vehicle->setPurchaseDate(new \DateTime('2020-01-01'));

        $schedule = $this->calculator->generateSchedule($vehicle, 5, 'automotive_standard');

        // Year 0: 20000
        $this->assertSame(20000.00, $schedule[0]);
        
        // Year 1: 20% depreciation = 16000
        $this->assertSame(16000.00, $schedule[1]);
        
        // Year 2: 15% depreciation = 13600
        $this->assertSame(13600.00, $schedule[2]);
        
        // Year 3: 15% depreciation = 11560
        $this->assertSame(11560.00, $schedule[3]);
    }

    /**
     * Test current value calculation
     */
    public function testCalculateCurrentValue(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setPurchasePrice(20000.00);
        $vehicle->setPurchaseDate(new \DateTime('-2 years'));

        $currentValue = $this->calculator->calculateCurrentValue($vehicle);

        $this->assertIsFloat($currentValue);
        $this->assertLessThan(20000.00, $currentValue);
        $this->assertGreaterThan(0.00, $currentValue);
    }

    /**
     * Test depreciation percentage calculation
     */
    public function testCalculateDepreciationPercentage(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setPurchasePrice(20000.00);
        $vehicle->setPurchaseDate(new \DateTime('-1 year'));

        // Assuming 20% first year depreciation
        $percentage = $this->calculator->calculateDepreciationPercentage($vehicle);

        $this->assertEqualsWithDelta(20.0, $percentage, 1.0);
    }

    /**
     * Test total depreciation amount calculation
     */
    public function testCalculateTotalDepreciation(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setPurchasePrice(20000.00);
        $vehicle->setPurchaseDate(new \DateTime('-1 year'));

        $depreciation = $this->calculator->calculateTotalDepreciation($vehicle);

        $this->assertIsFloat($depreciation);
        $this->assertGreaterThan(0.00, $depreciation);
        $this->assertLessThan(20000.00, $depreciation);
    }

    /**
     * Test depreciation for brand new vehicle is zero
     */
    public function testBrandNewVehicleHasZeroDepreciation(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setPurchasePrice(20000.00);
        $vehicle->setPurchaseDate(new \DateTime('today'));

        $depreciation = $this->calculator->calculateTotalDepreciation($vehicle);

        $this->assertSame(0.00, $depreciation);
    }

    /**
     * Test handling vehicle without purchase price
     */
    public function testHandleVehicleWithoutPurchasePrice(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setPurchaseDate(new \DateTime('-1 year'));
        // No purchase price set

        $currentValue = $this->calculator->calculateCurrentValue($vehicle);

        $this->assertSame(0.00, $currentValue);
    }

    /**
     * Test handling vehicle without purchase date
     */
    public function testHandleVehicleWithoutPurchaseDate(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setPurchasePrice(20000.00);
        // No purchase date set

        $currentValue = $this->calculator->calculateCurrentValue($vehicle);

        $this->assertSame(20000.00, $currentValue); // No depreciation
    }

    /**
     * Test projected value at specific future date
     */
    public function testProjectedValueAtFutureDate(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setPurchasePrice(20000.00);
        $vehicle->setPurchaseDate(new \DateTime('2020-01-01'));

        $futureDate = new \DateTime('2025-01-01'); // 5 years later
        $projectedValue = $this->calculator->calculateValueAtDate($vehicle, $futureDate);

        $this->assertIsFloat($projectedValue);
        $this->assertLessThan(20000.00, $projectedValue);
        $this->assertGreaterThanOrEqual(0.00, $projectedValue);
    }

    /**
     * Test annual depreciation rate calculation
     */
    public function testCalculateAnnualDepreciationRate(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setPurchasePrice(20000.00);
        $vehicle->setPurchaseDate(new \DateTime('-3 years'));

        $annualRate = $this->calculator->calculateAnnualDepreciationRate($vehicle);

        $this->assertIsFloat($annualRate);
        $this->assertGreaterThan(0.0, $annualRate);
        $this->assertLessThan(100.0, $annualRate);
    }

    /**
     * Test depreciation schedule respects minimum value
     */
    public function testDepreciationScheduleRespectsMinimumValue(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setPurchasePrice(20000.00);
        $vehicle->setPurchaseDate(new \DateTime('2020-01-01'));

        $minValue = 5000.00;
        $schedule = $this->calculator->generateSchedule($vehicle, 10, 'straight_line', 0.2, $minValue);

        // Check that no value in schedule goes below minimum
        foreach ($schedule as $value) {
            $this->assertGreaterThanOrEqual($minValue, $value);
        }
    }

    /**
     * Test custom depreciation rate
     */
    public function testCustomDepreciationRate(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setPurchasePrice(10000.00);
        $vehicle->setPurchaseDate(new \DateTime('2020-01-01'));

        // 30% annual depreciation rate
        $schedule = $this->calculator->generateSchedule($vehicle, 3, 'declining_balance', 0.3);

        $this->assertSame(10000.00, $schedule[0]);
        $this->assertSame(7000.00, $schedule[1]); // 10000 * 0.7
        $this->assertSame(4900.00, $schedule[2]); // 7000 * 0.7
        $this->assertSame(3430.00, $schedule[3]); // 4900 * 0.7
    }

    /**
     * Test mileage-based depreciation adjustment
     */
    public function testMileageBasedDepreciationAdjustment(): void
    {
        $vehicle1 = new Vehicle();
        $vehicle1->setPurchasePrice(20000.00);
        $vehicle1->setPurchaseDate(new \DateTime('-1 year'));
        $vehicle1->setCurrentMileage(30000); // High mileage

        $vehicle2 = new Vehicle();
        $vehicle2->setPurchasePrice(20000.00);
        $vehicle2->setPurchaseDate(new \DateTime('-1 year'));
        $vehicle2->setCurrentMileage(10000); // Low mileage

        $value1 = $this->calculator->calculateCurrentValue($vehicle1, true); // With mileage adjustment
        $value2 = $this->calculator->calculateCurrentValue($vehicle2, true);

        // Higher mileage should result in lower value
        $this->assertLessThan($value2, $value1);
    }

    /**
     * Test export depreciation schedule to array
     */
    public function testExportScheduleToArray(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setPurchasePrice(20000.00);
        $vehicle->setPurchaseDate(new \DateTime('2020-01-01'));

        $schedule = $this->calculator->generateSchedule($vehicle, 3);
        $export = $this->calculator->exportSchedule($schedule, 'array');

        $this->assertIsArray($export);
        $this->assertArrayHasKey('years', $export);
        $this->assertArrayHasKey('values', $export);
        $this->assertCount(4, $export['years']); // 0, 1, 2, 3
        $this->assertCount(4, $export['values']);
    }
}
