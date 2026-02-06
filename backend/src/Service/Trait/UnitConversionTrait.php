<?php

declare(strict_types=1);

namespace App\Service\Trait;

/**
 * trait UnitConversionTrait
 *
 * Trait for unit conversions (distance, fuel economy, currency formatting).
 * Provides standardized conversion methods that match frontend distanceUtils.js
 */
trait UnitConversionTrait
{
    // Conversion constants matching frontend distanceUtils.js
    protected const KM_TO_MILES = 0.621371;
    protected const MILES_TO_KM = 1.60934;
    protected const LITRES_TO_GALLONS = 0.219969;
    protected const GALLONS_TO_LITRES = 4.54609;

    /**
     * @var string
     */
    protected string $distanceUnit = 'miles';

    /**
     * function setDistanceUnit
     *
     * Set the distance unit preference.
     *
     * @param string $unit
     *
     * @return void
     */
    protected function setDistanceUnit(string $unit): void
    {
        // Normalize: accept 'mi', 'miles', 'km'
        if ($unit === 'mi' || $unit === 'miles') {
            $this->distanceUnit = 'miles';
        } else {
            $this->distanceUnit = 'km';
        }
    }

    /**
     * function getDistanceUnitPreference
     *
     * Get the current distance unit preference.
     *
     * @return string
     */
    protected function getDistanceUnitPreference(): string
    {
        return $this->distanceUnit;
    }

    /**
     * function usesMiles
     *
     * Check if using miles.
     *
     * @return bool
     */
    protected function usesMiles(): bool
    {
        return $this->distanceUnit === 'miles';
    }

    /**
     * function kmToMiles
     *
     * Convert kilometers to miles.
     *
     * @param float $km
     * @param int $decimals
     *
     * @return float
     */
    protected function kmToMiles(float $km, int $decimals = 0): float
    {
        $result = $km * self::KM_TO_MILES;
        return $decimals > 0 ? round($result, $decimals) : round($result);
    }

    /**
     * function milesToKm
     *
     * Convert miles to kilometers.
     *
     * @param float $miles
     * @param int $decimals
     *
     * @return float
     */
    protected function milesToKm(float $miles, int $decimals = 0): float
    {
        $result = $miles * self::MILES_TO_KM;
        return $decimals > 0 ? round($result, $decimals) : round($result);
    }

    /**
     * function convertDistanceFromKm
     *
     * Convert kilometers to the user's preferred distance unit.
     *
     * @param float $km
     * @param int $decimals
     *
     * @return float
     */
    protected function convertDistanceFromKm(float $km, int $decimals = 0): float
    {
        if ($this->usesMiles()) {
            return $this->kmToMiles($km, $decimals);
        }
        return $decimals > 0 ? round($km, $decimals) : round($km);
    }

    /**
     * function litresToGallons
     *
     * Convert litres to gallons (Imperial).
     *
     * @param float $litres
     * @param int $decimals
     *
     * @return float
     */
    protected function litresToGallons(float $litres, int $decimals = 2): float
    {
        return round($litres * self::LITRES_TO_GALLONS, $decimals);
    }

    /**
     * function gallonsToLitres
     *
     * Convert gallons (Imperial) to litres.
     *
     * @param float $gallons
     * @param int $decimals
     *
     * @return float
     */
    protected function gallonsToLitres(float $gallons, int $decimals = 2): float
    {
        return round($gallons * self::GALLONS_TO_LITRES, $decimals);
    }

    /**
     * function calculateFuelEconomy
     *
     * Calculate fuel economy based on user's distance unit.
     * Returns MPG for miles, km/l for km.
     *
     * @param float $distanceKm
     * @param float $litres
     *
     * @return float
     */
    protected function calculateFuelEconomy(float $distanceKm, float $litres): float
    {
        if ($litres <= 0) {
            return 0;
        }

        if ($this->usesMiles()) {
            // Convert km to miles, litres to gallons, return MPG
            $miles = $distanceKm * self::KM_TO_MILES;
            $gallons = $litres * self::LITRES_TO_GALLONS;
            return $gallons > 0 ? round($miles / $gallons, 2) : 0;
        }

        // Return km/l
        return round($distanceKm / $litres, 2);
    }

    /**
     * function getFuelEconomyLabel
     *
     * Get the label for the fuel economy column based on user's unit preference.
     *
     * @return string
     */
    protected function getFuelEconomyLabel(): string
    {
        return $this->usesMiles() ? 'MPG' : 'km/l';
    }

    /**
     * function getDistanceLabel
     *
     * Get the label for distance columns based on user's unit preference.
     *
     * @param bool $plural
     *
     * @return string
     */
    protected function getDistanceLabel(bool $plural = true): string
    {
        if ($this->usesMiles()) {
            return $plural ? 'Miles' : 'Mile';
        }
        return 'km';
    }

    /**
     * function formatCurrency
     *
     * Format currency for display (GBP).
     * Uses ASCII-safe pound sign for PDF compatibility.
     *
     * @param mixed $value
     * @param bool $forPdf
     *
     * @return string
     */
    protected function formatCurrency(float|int|string $value, bool $forPdf = false): string
    {
        $numValue = is_numeric($value) ? (float)$value : 0.0;
        // Use GBP symbol - for PDF we'll use the direct character which FPDF handles
        $symbol = "\xA3"; // £ in ISO-8859-1 (Latin-1)
        return $symbol . number_format($numValue, 2);
    }

    /**
     * function formatCurrencyForPdf
     *
     * Format currency for PDF output specifically.
     * FPDF uses ISO-8859-1 encoding by default.
     *
     * @param mixed $value
     *
     * @return string
     */
    protected function formatCurrencyForPdf(float|int|string $value): string
    {
        $numValue = is_numeric($value) ? (float)$value : 0.0;
        // Use the ISO-8859-1 pound sign which FPDF handles correctly
        return chr(163) . number_format($numValue, 2);
    }

    /**
     * function formatDistance
     *
     * Format distance with unit suffix.
     *
     * @param float $km
     * @param bool $includeSuffix
     *
     * @return string
     */
    protected function formatDistance(float $km, bool $includeSuffix = true): string
    {
        $converted = $this->convertDistanceFromKm($km);
        $formatted = number_format($converted, 0);
        
        if ($includeSuffix) {
            $suffix = $this->usesMiles() ? ' mi' : ' km';
            return $formatted . $suffix;
        }
        
        return $formatted;
    }

    /**
     * function formatFuelEconomy
     *
     * Format fuel economy with unit suffix.
     *
     * @param float $distanceKm
     * @param float $litres
     * @param bool $includeSuffix
     *
     * @return string
     */
    protected function formatFuelEconomy(float $distanceKm, float $litres, bool $includeSuffix = true): string
    {
        $economy = $this->calculateFuelEconomy($distanceKm, $litres);
        $formatted = number_format($economy, 2);
        
        if ($includeSuffix) {
            $suffix = $this->usesMiles() ? ' mpg' : ' km/l';
            return $formatted . $suffix;
        }
        
        return $formatted;
    }
}
