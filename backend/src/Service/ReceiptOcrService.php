<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use thiagoalessio\TesseractOCR\TesseractOCR;

class ReceiptOcrService
{
    public function __construct(
        private LoggerInterface $logger,
        private string $uploadDirectory
    ) {
    }

    /**
     * Extract fuel receipt data (date, cost, litres, station, fuel type)
     */
    public function extractReceiptData(string $filePath): array
    {
        $result = [
            'date' => null,
            'cost' => null,
            'litres' => null,
            'station' => null,
            'fuelType' => null,
        ];

        try {
            $ocr = new TesseractOCR($filePath);
            $text = $ocr->run();

            $this->logger->info('OCR extracted text', ['text' => $text]);

            // Extract date (various formats)
            if (preg_match('/(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/', $text, $matches)) {
                $result['date'] = $this->normalizeDate($matches[1]);
            }

            // Extract cost/total amount (£, $, €, or just numbers)
            if (preg_match('/(?:total|amount|price)[:\s]*[£$€]?\s*([\d,]+\.?\d{0,2})/i', $text, $matches)) {
                $result['cost'] = str_replace(',', '', $matches[1]);
            } elseif (preg_match('/[£$€]\s*([\d,]+\.?\d{2})/', $text, $matches)) {
                $result['cost'] = str_replace(',', '', $matches[1]);
            }

            // Extract litres/gallons
            if (preg_match('/([\d,]+\.?\d{0,2})\s*(?:l|ltr|litre|litres)/i', $text, $matches)) {
                $result['litres'] = str_replace(',', '', $matches[1]);
            } elseif (preg_match('/([\d,]+\.?\d{0,2})\s*(?:gal|gallon|gallons)/i', $text, $matches)) {
                // Convert gallons to litres
                $gallons = str_replace(',', '', $matches[1]);
                $result['litres'] = number_format((float)$gallons * 3.78541, 2, '.', '');
            }

            // Extract station name (common UK stations)
            $stations = [
                'Shell', 'BP', 'Esso', 'Texaco', 'Total', 'Tesco', 'Asda', 'Sainsbury',
                'Morrisons', 'Gulf', 'Jet', 'Murco', 'Applegreen', 'Circle K', 'MFG'
            ];
            foreach ($stations as $station) {
                if (stripos($text, $station) !== false) {
                    $result['station'] = $station;
                    break;
                }
            }

            // Extract fuel type
            $fuelTypes = [
                'E5' => ['petrol', 'unleaded', 'e5'],
                'E10' => ['e10'],
                'Super Unleaded' => ['super unleaded'],
                'Diesel' => ['diesel'],
                'Premium Diesel' => ['premium diesel'],
                'LPG' => ['lpg', 'autogas'],
                'Electric' => ['electric', 'charging'],
            ];

            foreach ($fuelTypes as $type => $patterns) {
                foreach ($patterns as $pattern) {
                    if (stripos($text, $pattern) !== false) {
                        $result['fuelType'] = $type;
                        break 2;
                    }
                }
            }

            $this->logger->info('OCR parsed receipt data', $result);
        } catch (\Exception $e) {
            $this->logger->error('OCR processing failed', [
                'error' => $e->getMessage(),
                'file' => $filePath
            ]);
        }

        return $result;
    }

    private function normalizeDate(string $dateStr): ?string
    {
        // Try to parse various date formats and return Y-m-d format
        $formats = ['d/m/Y', 'd-m-Y', 'd.m.Y', 'd/m/y', 'd-m-y', 'd.m.y'];

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $dateStr);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * Extract parts/consumables receipt data
     * (part name, price, quantity, supplier, part number)
     */
    public function extractPartReceiptData(string $filePath): array
    {
        $result = [
            'name' => null,
            'partNumber' => null,
            'manufacturer' => null,
            'price' => null,
            'quantity' => null,
            'supplier' => null,
            'date' => null,
        ];

        try {
            $ocr = new TesseractOCR($filePath);
            $text = $ocr->run();

            $this->logger->info('Part OCR extracted text', ['text' => $text]);

            // Extract date
            if (preg_match('/(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/', $text, $matches)) {
                $result['date'] = $this->normalizeDate($matches[1]);
            }

            // Extract price/cost
            if (preg_match('/(?:price|cost|total)[:\s]*[£$€]?\s*([\d,]+\.?\d{0,2})/i', $text, $matches)) {
                $result['price'] = str_replace(',', '', $matches[1]);
            } elseif (preg_match('/[£$€]\s*([\d,]+\.?\d{2})/', $text, $matches)) {
                $result['price'] = str_replace(',', '', $matches[1]);
            }

            // Extract quantity
            if (preg_match('/(?:qty|quantity)[:\s]*(\d+)/i', $text, $matches)) {
                $result['quantity'] = $matches[1];
            } elseif (preg_match('/(\d+)\s*(?:x|×|pcs|pieces)/i', $text, $matches)) {
                $result['quantity'] = $matches[1];
            }

            // Extract part number (various formats)
            if (preg_match('/(?:part|item|sku|p\/n)[#:\s]*([A-Z0-9\-]+)/i', $text, $matches)) {
                $result['partNumber'] = $matches[1];
            }

            // Extract common auto parts suppliers
            $suppliers = [
                'Halfords', 'Euro Car Parts', 'GSF', 'Autodoc', 'CarParts4Less',
                'AutoZone', 'Advance Auto', 'O\'Reilly', 'NAPA', 'Amazon',
                'eBay', 'Screwfix'
            ];
            foreach ($suppliers as $supplier) {
                if (stripos($text, $supplier) !== false) {
                    $result['supplier'] = $supplier;
                    break;
                }
            }

            // Try to extract product name (usually first or near top)
            $lines = explode("\n", $text);
            foreach ($lines as $line) {
                $line = trim($line);
                if (strlen($line) > 10 && strlen($line) < 200 && !preg_match('/receipt|invoice|date|total/i', $line)) {
                    if (!$result['name']) {
                        $result['name'] = $line;
                        break;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Part OCR processing failed', ['error' => $e->getMessage()]);
        }

        return $result;
    }

    /**
     * Extract service record receipt data
     * (service description, labor cost, parts cost, date, provider)
     */
    public function extractServiceReceiptData(string $filePath): array
    {
        $result = [
            'serviceType' => null,
            'laborCost' => null,
            'partsCost' => null,
            'serviceProvider' => null,
            'date' => null,
            'mileage' => null,
            'workPerformed' => null,
        ];

        try {
            $ocr = new TesseractOCR($filePath);
            $text = $ocr->run();

            $this->logger->info('Service OCR extracted text', ['text' => $text]);

            // Extract date
            if (preg_match('/(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/', $text, $matches)) {
                $result['date'] = $this->normalizeDate($matches[1]);
            }

            // Extract labor cost
            if (preg_match('/labor|labour[:\s]*[£$€]?\s*([\d,]+\.?\d{0,2})/i', $text, $matches)) {
                $result['laborCost'] = str_replace(',', '', $matches[1]);
            }

            // Extract parts cost
            if (preg_match('/parts[:\s]*[£$€]?\s*([\d,]+\.?\d{0,2})/i', $text, $matches)) {
                $result['partsCost'] = str_replace(',', '', $matches[1]);
            }

            // Extract mileage
            if (preg_match('/(\d{1,6})\s*(?:miles|km|mileage)/i', $text, $matches)) {
                $result['mileage'] = $matches[1];
            }

            // Extract service type keywords
            $serviceTypes = [
                'Full Service', 'Interim Service', 'Oil Change', 'MOT', 'Brake Service',
                'Tyre Change', 'Battery', 'Clutch', 'Timing Belt', 'Air Conditioning'
            ];
            foreach ($serviceTypes as $type) {
                if (stripos($text, $type) !== false) {
                    $result['serviceType'] = $type;
                    break;
                }
            }

            // Extract service provider (garages, chains)
            $providers = [
                'Kwik Fit', 'Halfords', 'ATS Euromaster', 'National Tyres',
                'Kwik-Fit', 'Midas', 'Mr Tyre', 'Formula One', 'Protyre'
            ];
            foreach ($providers as $provider) {
                if (stripos($text, $provider) !== false) {
                    $result['serviceProvider'] = $provider;
                    break;
                }
            }

            // Try to extract work performed (look for detailed lines)
            $lines = explode("\n", $text);
            $workLines = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if (strlen($line) > 15 && strlen($line) < 100) {
                    if (preg_match('/(?:oil|filter|brake|tyre|replace|check|inspect)/i', $line)) {
                        $workLines[] = $line;
                    }
                }
            }
            if (!empty($workLines)) {
                $result['workPerformed'] = implode("\n", array_slice($workLines, 0, 5));
            }
        } catch (\Exception $e) {
            $this->logger->error('Service OCR processing failed', ['error' => $e->getMessage()]);
        }

        return $result;
    }
}
