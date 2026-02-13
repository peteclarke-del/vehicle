<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * class ReceiptTemplateService
 *
 * Smart receipt template engine that detects vendor/format and applies
 * structured extraction rules to OCR text.
 * Templates define vendor-specific patterns for extracting data fields.
 * The engine tries to match a vendor first, then applies the best-fit
 * template rules for data extraction.
 */
class ReceiptTemplateService
{
    /**
     * @var array
     */
    private array $templates;

    /**
     * function __construct
     *
     * @param LoggerInterface $logger
     *
     * @return void
     */
    public function __construct(
        private LoggerInterface $logger
    ) {
        $this->templates = $this->buildTemplates();
    }

    /**
     * function detectVendor
     *
     * Detect which vendor template matches the OCR text.
     * Returns the template key or 'generic' if no specific match.
     *
     * @param string $text
     *
     * @return string
     */
    public function detectVendor(string $text): string
    {
        $textLower = strtolower($text);
        $scores = [];

        foreach ($this->templates as $key => $template) {
            if ($key === 'generic') {
                continue;
            }

            $score = 0;
            foreach ($template['identifiers'] as $identifier) {
                if (stripos($textLower, strtolower($identifier)) !== false) {
                    $score += $template['identifierWeight'] ?? 10;
                }
            }

            // Bonus for structural markers (layout-specific patterns)
            foreach (($template['structuralMarkers'] ?? []) as $marker) {
                if (preg_match($marker, $text)) {
                    $score += 5;
                }
            }

            if ($score > 0) {
                $scores[$key] = $score;
            }
        }

        if (empty($scores)) {
            return 'generic';
        }

        arsort($scores);
        $best = array_key_first($scores);

        $this->logger->info('Receipt vendor detected', [
            'vendor' => $best,
            'score' => $scores[$best],
            'allScores' => $scores
        ]);

        return $best;
    }

    /**
     * function extract
     *
     * Extract structured data from OCR text using the appropriate template.
     *
     * @param string $text
     * @param string $category
     *
     * @return array
     */
    public function extract(string $text, string $category): array
    {
        $vendor = $this->detectVendor($text);
        $template = $this->templates[$vendor] ?? $this->templates['generic'];

        $this->logger->info('Extracting receipt data', [
            'vendor' => $vendor,
            'category' => $category,
            'textLength' => strlen($text)
        ]);

        // Start with category-specific extraction
        $result = match ($category) {
            'fuel' => $this->extractFuelFields($text, $template),
            'part', 'consumable' => $this->extractPartFields($text, $template),
            'service' => $this->extractServiceFields($text, $template),
            'mot' => $this->extractMotFields($text, $template),
            default => $this->extractGenericFields($text, $template),
        };

        // Add metadata
        $result['_meta'] = [
            'vendor' => $vendor,
            'vendorName' => $template['name'] ?? $vendor,
            'confidence' => $this->calculateConfidence($result),
            'category' => $category,
        ];

        return $result;
    }

    /**
     * function extractFromMultiplePages
     *
     * Extract data using multiple pages of OCR text.
     * Merges information found across pages, preferring data from earlier pages
     * when conflicts arise (first page usually has header/summary info).
     *
     * @param array $pageTexts
     * @param string $category
     *
     * @return array
     */
    public function extractFromMultiplePages(array $pageTexts, string $category): array
    {
        if (count($pageTexts) === 1) {
            return $this->extract($pageTexts[0], $category);
        }

        // Merge all text to detect vendor from the combined corpus
        $fullText = implode("\n--- PAGE BREAK ---\n", $pageTexts);
        $vendor = $this->detectVendor($fullText);
        $template = $this->templates[$vendor] ?? $this->templates['generic'];

        // Extract from each page independently
        $pageResults = [];
        foreach ($pageTexts as $i => $pageText) {
            $pageResult = match ($category) {
                'fuel' => $this->extractFuelFields($pageText, $template),
                'part', 'consumable' => $this->extractPartFields($pageText, $template),
                'service' => $this->extractServiceFields($pageText, $template),
                'mot' => $this->extractMotFields($pageText, $template),
                default => $this->extractGenericFields($pageText, $template),
            };
            $pageResults[] = $pageResult;
        }

        // Also extract from the combined text (catches cross-page patterns)
        $combinedResult = match ($category) {
            'fuel' => $this->extractFuelFields($fullText, $template),
            'part', 'consumable' => $this->extractPartFields($fullText, $template),
            'service' => $this->extractServiceFields($fullText, $template),
            'mot' => $this->extractMotFields($fullText, $template),
            default => $this->extractGenericFields($fullText, $template),
        };

        // Merge: prefer page-specific results, fall back to combined
        $merged = $this->mergePageResults($pageResults, $combinedResult);

        $merged['_meta'] = [
            'vendor' => $vendor,
            'vendorName' => $template['name'] ?? $vendor,
            'confidence' => $this->calculateConfidence($merged),
            'category' => $category,
            'pageCount' => count($pageTexts),
        ];

        return $merged;
    }

    // ─── Field Extraction by Category ────────────────────────────────────

    /**
     * function extractFuelFields
     *
     * @param string $text
     * @param array $template
     *
     * @return array
     */
    private function extractFuelFields(string $text, array $template): array
    {
        return [
            'date' => $this->extractDate($text, $template),
            'cost' => $this->extractAmount($text, $template, ['total', 'amount', 'price', 'paid']),
            'litres' => $this->extractLitres($text),
            'station' => $this->extractStation($text, $template),
            'fuelType' => $this->extractFuelType($text),
        ];
    }

    /**
     * function extractPartFields
     *
     * @param string $text
     * @param array $template
     *
     * @return array
     */
    private function extractPartFields(string $text, array $template): array
    {
        return [
            'date' => $this->extractDate($text, $template),
            'name' => $this->extractItemName($text, $template),
            'partNumber' => $this->extractPartNumber($text, $template),
            'manufacturer' => $this->extractManufacturer($text, $template),
            'price' => $this->extractAmount($text, $template, ['total', 'price', 'subtotal', 'item total', 'order total']),
            'quantity' => $this->extractQuantity($text, $template),
            'supplier' => $this->extractSupplier($text, $template),
            'sku' => $this->extractSku($text, $template),
        ];
    }

    /**
     * function extractServiceFields
     *
     * @param string $text
     * @param array $template
     *
     * @return array
     */
    private function extractServiceFields(string $text, array $template): array
    {
        return [
            'date' => $this->extractDate($text, $template),
            'serviceType' => $this->extractServiceType($text),
            'laborCost' => $this->extractAmount($text, $template, ['labo[u]?r', 'workmanship', 'fitting']),
            'partsCost' => $this->extractAmount($text, $template, ['parts', 'materials', 'components']),
            'totalCost' => $this->extractAmount($text, $template, ['total', 'amount due', 'balance', 'grand total']),
            'mileage' => $this->extractMileage($text),
            'serviceProvider' => $this->extractServiceProvider($text, $template),
            'workPerformed' => $this->extractWorkPerformed($text),
        ];
    }

    /**
     * function extractMotFields
     *
     * @param string $text
     * @param array $template
     *
     * @return array
     */
    private function extractMotFields(string $text, array $template): array
    {
        return [
            'testDate' => $this->extractDate($text, $template),
            'result' => $this->extractMotResult($text),
            'testCost' => $this->extractAmount($text, $template, ['test fee', 'mot fee', 'test cost', 'total']),
            'mileage' => $this->extractMileage($text),
            'testCenter' => $this->extractServiceProvider($text, $template),
            'expiryDate' => $this->extractExpiryDate($text),
            'motTestNumber' => $this->extractMotTestNumber($text),
            'advisories' => $this->extractMotAdvisories($text),
            'failures' => $this->extractMotFailures($text),
        ];
    }

    /**
     * function extractGenericFields
     *
     * @param string $text
     * @param array $template
     *
     * @return array
     */
    private function extractGenericFields(string $text, array $template): array
    {
        return [
            'date' => $this->extractDate($text, $template),
            'cost' => $this->extractAmount($text, $template, ['total', 'amount', 'price', 'paid', 'balance']),
            'supplier' => $this->extractSupplier($text, $template),
            'description' => $this->extractItemName($text, $template),
        ];
    }

    // ─── Individual Field Extractors ─────────────────────────────────────

    /**
     * function extractDate
     *
     * @param string $text
     * @param array $template
     *
     * @return string
     */
    private function extractDate(string $text, array $template): ?string
    {
        // Try template-specific date patterns first
        foreach (($template['datePatterns'] ?? []) as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $normalized = $this->normalizeDate($matches[1] ?? $matches[0]);
                if ($normalized) {
                    return $normalized;
                }
            }
        }

        // Generic date patterns
        $patterns = [
            // "Date: 13 Feb 2026" / "Date: 13 February 2026"
            '/(?:date|dated|invoice date|order date|transaction date)[:\s]*(\d{1,2}\s+\w+\s+\d{4})/i',
            // "Date: 13/02/2026" or "13-02-2026"
            '/(?:date|dated|invoice date|order date|transaction date)[:\s]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i',
            // "13 Feb 2026" / "13 February 2026" standalone
            '/(\d{1,2}\s+(?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:t(?:ember)?)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)\s+\d{4})/i',
            // dd/mm/yyyy or dd-mm-yyyy
            '/(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $normalized = $this->normalizeDate($matches[1]);
                if ($normalized) {
                    return $normalized;
                }
            }
        }

        return null;
    }

    /**
     * function extractAmount
     *
     * @param string $text
     * @param array $template
     * @param array $labels
     *
     * @return string
     */
    private function extractAmount(string $text, array $template, array $labels): ?string
    {
        // Try template-specific amount patterns first
        foreach (($template['amountPatterns'] ?? []) as $patternDef) {
            if (isset($patternDef['pattern']) && isset($patternDef['field'])) {
                if (in_array($patternDef['field'], $labels, true) || $patternDef['field'] === 'total') {
                    if (preg_match($patternDef['pattern'], $text, $matches)) {
                        return $this->cleanAmount($matches[1] ?? $matches[0]);
                    }
                }
            }
        }

        // Build label pattern from given labels
        $labelPattern = implode('|', $labels);

        // Try labeled amounts first
        if (preg_match('/(?:' . $labelPattern . ')[:\s]*[£$€]?\s*([\d,]+\.?\d{0,2})/i', $text, $matches)) {
            return $this->cleanAmount($matches[1]);
        }

        // Currency-prefixed standalone amounts (last resort for 'total' type)
        if (in_array('total', $labels, true) || in_array('price', $labels, true)) {
            // Find all currency amounts, prefer the largest (likely total)
            if (preg_match_all('/[£$€]\s*([\d,]+\.\d{2})/', $text, $allMatches)) {
                $amounts = array_map(fn($a) => (float)str_replace(',', '', $a), $allMatches[1]);
                $maxAmount = max($amounts);
                return number_format($maxAmount, 2, '.', '');
            }
        }

        return null;
    }

    /**
     * function extractLitres
     *
     * @param string $text
     *
     * @return string
     */
    private function extractLitres(string $text): ?string
    {
        if (preg_match('/([\d,]+\.?\d{0,3})\s*(?:l(?:tr)?(?:es?)?|litres?)/i', $text, $matches)) {
            return $this->cleanAmount($matches[1]);
        }
        if (preg_match('/([\d,]+\.?\d{0,3})\s*(?:gal(?:lon)?s?)/i', $text, $matches)) {
            $gallons = (float)str_replace(',', '', $matches[1]);
            return number_format($gallons * 3.78541, 2, '.', '');
        }
        return null;
    }

    /**
     * function extractStation
     *
     * @param string $text
     * @param array $template
     *
     * @return string
     */
    private function extractStation(string $text, array $template): ?string
    {
        // Check template-specific name first
        if (!empty($template['vendorDisplayName'])) {
            return $template['vendorDisplayName'];
        }

        $stations = [
            'Shell', 'BP', 'Esso', 'Texaco', 'Total Energies', 'Total',
            'Tesco', 'Asda', 'Sainsbury\'s', 'Sainsburys', 'Morrisons',
            'Gulf', 'Jet', 'Murco', 'Applegreen', 'Circle K', 'MFG',
            'Costco', 'Co-op', 'Harvest Energy', 'Pace',
        ];

        foreach ($stations as $station) {
            if (stripos($text, $station) !== false) {
                return $station;
            }
        }

        return null;
    }

    /**
     * function extractFuelType
     *
     * @param string $text
     *
     * @return string
     */
    private function extractFuelType(string $text): ?string
    {
        $fuelTypes = [
            'Super Unleaded' => ['super unleaded', 'super', 'v-power', 'momentum 99', 'synergy supreme'],
            'Premium Diesel' => ['premium diesel', 'v-power diesel', 'ultimate diesel', 'supreme diesel'],
            'E10' => ['e10', 'unleaded e10'],
            'E5' => ['e5', 'petrol', 'unleaded', 'regular unleaded'],
            'Diesel' => ['diesel', 'derv', 'gas oil'],
            'LPG' => ['lpg', 'autogas'],
            'Electric' => ['electric', 'charging', 'ev charge', 'kwh'],
            'AdBlue' => ['adblue'],
        ];

        $textLower = strtolower($text);
        foreach ($fuelTypes as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($textLower, $pattern)) {
                    return $type;
                }
            }
        }

        return null;
    }

    /**
     * function extractItemName
     *
     * @param string $text
     * @param array $template
     *
     * @return string
     */
    private function extractItemName(string $text, array $template): ?string
    {
        // Try template-specific item name patterns
        foreach (($template['itemNamePatterns'] ?? []) as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[1]);
            }
        }

        // Generic: look for "Item:", "Description:", "Product:" lines
        $labels = ['item', 'description', 'product', 'product name', 'item title', 'article'];
        foreach ($labels as $label) {
            if (preg_match('/(?:' . preg_quote($label, '/') . ')[:\s]+(.{10,120})/i', $text, $matches)) {
                return trim($matches[1]);
            }
        }

        // Fall back: first substantive line that looks like a product name
        $lines = explode("\n", $text);
        foreach ($lines as $line) {
            $line = trim($line);
            if (strlen($line) > 10 && strlen($line) < 150
                && !preg_match('/^(receipt|invoice|order|date|total|subtotal|tax|vat|qty|payment|thank|delivery|dispatch|post|ship)/i', $line)
                && !preg_match('/[£$€]/', $line)
                && !preg_match('/^\d+[\/\-\.]/', $line)
            ) {
                return $line;
            }
        }

        return null;
    }

    /**
     * function extractPartNumber
     *
     * @param string $text
     * @param array $template
     *
     * @return string
     */
    private function extractPartNumber(string $text, array $template): ?string
    {
        // Template-specific patterns
        foreach (($template['partNumberPatterns'] ?? []) as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[1]);
            }
        }

        // Generic part number patterns
        $patterns = [
            '/(?:part\s*(?:no|number|#|num)?|item\s*(?:no|number|#)?|sku|p\/n|mpn|oem)[:\s#]*([A-Z0-9][\w\-\.]{3,30})/i',
            '/(?:catalogue|cat\.?\s*no)[:\s#]*([A-Z0-9][\w\-\.]{3,30})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * function extractSku
     *
     * @param string $text
     * @param array $template
     *
     * @return string
     */
    private function extractSku(string $text, array $template): ?string
    {
        foreach (($template['skuPatterns'] ?? []) as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[1]);
            }
        }

        if (preg_match('/\b(?:sku|stock\s*code)[:\s#]*([A-Z0-9][\w\-]{3,20})/i', $text, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * function extractManufacturer
     *
     * @param string $text
     * @param array $template
     *
     * @return string
     */
    private function extractManufacturer(string $text, array $template): ?string
    {
        // Template-specific
        foreach (($template['manufacturerPatterns'] ?? []) as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[1]);
            }
        }

        // Common auto parts manufacturers
        $manufacturers = [
            'Bosch', 'Denso', 'NGK', 'Mann', 'Mahle', 'Continental', 'Gates',
            'SKF', 'TRW', 'Brembo', 'Sachs', 'Valeo', 'Hella', 'Delphi',
            'Castrol', 'Mobil 1', 'Total', 'Shell', 'Liqui Moly', 'Comma',
            'Mintex', 'EBC', 'Pagid', 'Ferodo', 'Lucas', 'ATE', 'Textar',
            'KYB', 'Monroe', 'Bilstein', 'Meyle', 'Febi', 'Lemforder',
            'Dayco', 'INA', 'FAG', 'NTN', 'Timken', 'Corteco', 'Elring',
            'OEM', 'Genuine',
        ];

        foreach ($manufacturers as $mfr) {
            if (stripos($text, $mfr) !== false) {
                return $mfr;
            }
        }

        return null;
    }

    /**
     * function extractSupplier
     *
     * @param string $text
     * @param array $template
     *
     * @return string
     */
    private function extractSupplier(string $text, array $template): ?string
    {
        // Template vendor is the supplier
        if (!empty($template['vendorDisplayName'])) {
            return $template['vendorDisplayName'];
        }

        $suppliers = [
            'Halfords', 'Euro Car Parts', 'GSF Car Parts', 'Autodoc', 'CarParts4Less',
            'AutoZone', 'Advance Auto Parts', "O'Reilly", 'NAPA', 'Amazon',
            'eBay', 'Screwfix', 'Toolstation', 'Motor Parts Direct',
            'Parts Gateway', 'Andrew Page', 'LKQ Euro Car Parts',
        ];

        foreach ($suppliers as $supplier) {
            if (stripos($text, $supplier) !== false) {
                return $supplier;
            }
        }

        return null;
    }

    /**
     * function extractQuantity
     *
     * @param string $text
     * @param array $template
     *
     * @return string
     */
    private function extractQuantity(string $text, array $template): ?string
    {
        foreach (($template['quantityPatterns'] ?? []) as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return $matches[1];
            }
        }

        if (preg_match('/(?:qty|quantity|qnty)[:\s]*(\d+)/i', $text, $matches)) {
            return $matches[1];
        }
        if (preg_match('/(\d+)\s*(?:x|×|pcs|pieces|units|pack)/i', $text, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * function extractMileage
     *
     * @param string $text
     *
     * @return string
     */
    private function extractMileage(string $text): ?string
    {
        if (preg_match('/(?:mileage|odometer|miles|mileometer)[:\s]*([\d,]+)\s*(?:miles|mi|km)?/i', $text, $matches)) {
            return str_replace(',', '', $matches[1]);
        }
        return null;
    }

    /**
     * function extractServiceType
     *
     * @param string $text
     *
     * @return string
     */
    private function extractServiceType(string $text): ?string
    {
        $serviceTypes = [
            'Full Service' => ['full service', 'major service'],
            'Interim Service' => ['interim service', 'minor service', 'oil service'],
            'Oil Change' => ['oil change', 'oil & filter', 'oil and filter'],
            'Brake Service' => ['brake service', 'brake pad', 'brake disc', 'brake repair'],
            'Tyre Change' => ['tyre fitting', 'tyre change', 'wheel alignment', 'tyre replacement', 'tire'],
            'Battery' => ['battery replacement', 'battery fitting', 'new battery'],
            'Clutch' => ['clutch replacement', 'clutch repair'],
            'Timing Belt' => ['timing belt', 'cambelt', 'timing chain'],
            'Air Conditioning' => ['air conditioning', 'aircon', 'a/c service', 'climate control'],
            'Exhaust' => ['exhaust repair', 'exhaust replacement', 'catalytic converter'],
            'Suspension' => ['suspension', 'shock absorber', 'spring replacement'],
            'Diagnostic' => ['diagnostic', 'fault code', 'engine management'],
            'Bodywork' => ['bodywork', 'panel repair', 'dent repair', 'paint', 'respray'],
            'Windscreen' => ['windscreen', 'windshield', 'glass repair', 'chip repair'],
        ];

        $textLower = strtolower($text);
        foreach ($serviceTypes as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($textLower, $pattern)) {
                    return $type;
                }
            }
        }

        return null;
    }

    /**
     * function extractServiceProvider
     *
     * @param string $text
     * @param array $template
     *
     * @return string
     */
    private function extractServiceProvider(string $text, array $template): ?string
    {
        if (!empty($template['vendorDisplayName'])) {
            return $template['vendorDisplayName'];
        }

        $providers = [
            'Kwik Fit', 'Halfords Autocentre', 'ATS Euromaster', 'National Tyres',
            'Midas', 'Mr Tyre', 'Formula One Autocentres', 'Protyre',
            'Arnold Clark', 'Evans Halshaw', 'Lookers', 'Pendragon',
        ];

        foreach ($providers as $provider) {
            if (stripos($text, $provider) !== false) {
                return $provider;
            }
        }

        // Try to find a company name near the top of the receipt
        $lines = explode("\n", $text);
        foreach (array_slice($lines, 0, 5) as $line) {
            $line = trim($line);
            if (strlen($line) > 3 && strlen($line) < 60
                && preg_match('/^[A-Z]/', $line)
                && !preg_match('/^(receipt|invoice|tax|date|tel|phone|address|email|www|http)/i', $line)
            ) {
                return $line;
            }
        }

        return null;
    }

    /**
     * function extractWorkPerformed
     *
     * @param string $text
     *
     * @return string
     */
    private function extractWorkPerformed(string $text): ?string
    {
        $workKeywords = [
            'replace', 'replaced', 'change', 'changed', 'repair', 'repaired',
            'check', 'checked', 'inspect', 'inspected', 'fit', 'fitted',
            'adjust', 'adjusted', 'top.?up', 'lubricate', 'service', 'clean',
            'flush', 'bleed', 'align', 'balance', 'torque', 'reset',
        ];

        $pattern = '/(' . implode('|', $workKeywords) . ')/i';
        $lines = explode("\n", $text);
        $workLines = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (strlen($line) > 10 && strlen($line) < 150 && preg_match($pattern, $line)) {
                $workLines[] = $line;
            }
        }

        return !empty($workLines) ? implode("\n", array_slice($workLines, 0, 10)) : null;
    }

    /**
     * function extractMotResult
     *
     * @param string $text
     *
     * @return string
     */
    private function extractMotResult(string $text): ?string
    {
        $textLower = strtolower($text);
        if (str_contains($textLower, 'pass')) {
            return 'Pass';
        }
        if (str_contains($textLower, 'fail')) {
            return 'Fail';
        }
        return null;
    }

    /**
     * function extractExpiryDate
     *
     * @param string $text
     *
     * @return string
     */
    private function extractExpiryDate(string $text): ?string
    {
        if (preg_match('/(?:expiry|expires|valid until|exp(?:iry)? date)[:\s]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i', $text, $matches)) {
            return $this->normalizeDate($matches[1]);
        }
        if (preg_match('/(?:expiry|expires|valid until)[:\s]*(\d{1,2}\s+\w+\s+\d{4})/i', $text, $matches)) {
            return $this->normalizeDate($matches[1]);
        }
        return null;
    }

    /**
     * function extractMotTestNumber
     *
     * @param string $text
     *
     * @return string
     */
    private function extractMotTestNumber(string $text): ?string
    {
        if (preg_match('/(?:test\s*(?:number|no|#)|mot\s*(?:number|no|#)|certificate\s*(?:number|no|#))[:\s]*(\d{10,15})/i', $text, $matches)) {
            return $matches[1];
        }
        // MOT test numbers are typically 12-13 digits
        if (preg_match('/\b(\d{12,13})\b/', $text, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * function extractMotAdvisories
     *
     * @param string $text
     *
     * @return array
     */
    private function extractMotAdvisories(string $text): ?array
    {
        $advisories = [];
        // "Advisory" or "Advise:" followed by text
        if (preg_match_all('/(?:advisory|advise|monitor)[:\s]*(.{10,200})/i', $text, $matches)) {
            $advisories = array_map('trim', $matches[1]);
        }
        return !empty($advisories) ? $advisories : null;
    }

    /**
     * function extractMotFailures
     *
     * @param string $text
     *
     * @return array
     */
    private function extractMotFailures(string $text): ?array
    {
        $failures = [];
        if (preg_match_all('/(?:fail(?:ure)?|dangerous|major defect)[:\s]*(.{10,200})/i', $text, $matches)) {
            $failures = array_map('trim', $matches[1]);
        }
        return !empty($failures) ? $failures : null;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    /**
     * function normalizeDate
     *
     * @param string $dateStr
     *
     * @return string
     */
    private function normalizeDate(string $dateStr): ?string
    {
        $dateStr = trim($dateStr);

        // Named month formats: "13 Feb 2026", "13 February 2026"
        $date = \DateTime::createFromFormat('j M Y', $dateStr)
             ?: \DateTime::createFromFormat('j F Y', $dateStr)
             ?: \DateTime::createFromFormat('d M Y', $dateStr)
             ?: \DateTime::createFromFormat('d F Y', $dateStr);

        if ($date) {
            return $date->format('Y-m-d');
        }

        // Numeric formats
        $formats = ['d/m/Y', 'd-m-Y', 'd.m.Y', 'd/m/y', 'd-m-y', 'd.m.y', 'Y-m-d', 'Y/m/d'];
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $dateStr);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * function cleanAmount
     *
     * @param string $amount
     *
     * @return string
     */
    private function cleanAmount(string $amount): string
    {
        return number_format((float)str_replace(',', '', $amount), 2, '.', '');
    }

    /**
     * function calculateConfidence
     *
     * @param array $result
     *
     * @return float
     */
    private function calculateConfidence(array $result): float
    {
        $total = 0;
        $filled = 0;

        foreach ($result as $key => $value) {
            if ($key === '_meta') {
                continue;
            }
            $total++;
            if ($value !== null && $value !== '' && $value !== []) {
                $filled++;
            }
        }

        return $total > 0 ? round($filled / $total, 2) : 0.0;
    }

    /**
     * function mergePageResults
     *
     * @param array $pageResults
     * @param array $combinedResult
     *
     * @return array
     */
    private function mergePageResults(array $pageResults, array $combinedResult): array
    {
        $merged = [];

        // Get all keys from all results
        $allKeys = [];
        foreach ($pageResults as $pr) {
            $allKeys = array_merge($allKeys, array_keys($pr));
        }
        $allKeys = array_merge($allKeys, array_keys($combinedResult));
        $allKeys = array_unique($allKeys);

        foreach ($allKeys as $key) {
            if ($key === '_meta') {
                continue;
            }

            // Try page results first (prefer earlier pages)
            $found = false;
            foreach ($pageResults as $pr) {
                if (isset($pr[$key]) && $pr[$key] !== null && $pr[$key] !== '' && $pr[$key] !== []) {
                    $merged[$key] = $pr[$key];
                    $found = true;
                    break;
                }
            }

            // Fall back to combined text extraction
            if (!$found && isset($combinedResult[$key]) && $combinedResult[$key] !== null) {
                $merged[$key] = $combinedResult[$key];
            } elseif (!$found) {
                $merged[$key] = null;
            }
        }

        return $merged;
    }

    // ─── Template Definitions ────────────────────────────────────────────

    /**
     * function buildTemplates
     *
     * @return array
     */
    private function buildTemplates(): array
    {
        return [
            'ebay' => [
                'name' => 'eBay',
                'vendorDisplayName' => 'eBay',
                'identifiers' => ['ebay', 'ebay.co.uk', 'ebay.com', 'eBay Inc'],
                'identifierWeight' => 15,
                'structuralMarkers' => [
                    '/item\s*(?:number|#|no)[:\s]*\d{10,14}/i',
                    '/ebay\s*item\s*id/i',
                    '/seller[:\s]/i',
                ],
                'datePatterns' => [
                    '/(?:order|purchase|payment|transaction)\s*date[:\s]*(\d{1,2}\s+\w+\s+\d{4})/i',
                    '/(?:order|purchase|payment|transaction)\s*date[:\s]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i',
                ],
                'amountPatterns' => [
                    ['field' => 'total', 'pattern' => '/(?:order\s*total|total\s*price|you\s*paid)[:\s]*[£$€]?\s*([\d,]+\.?\d{0,2})/i'],
                    ['field' => 'total', 'pattern' => '/(?:item\s*subtotal|item\s*price)[:\s]*[£$€]?\s*([\d,]+\.?\d{0,2})/i'],
                ],
                'itemNamePatterns' => [
                    '/(?:item\s*title|item\s*name|product)[:\s]+(.{10,150})/i',
                ],
                'partNumberPatterns' => [
                    '/(?:manufacturer\s*part\s*(?:number|no)|mpn)[:\s]*([A-Z0-9][\w\-\.]{3,30})/i',
                    '/(?:item\s*(?:number|no|#))[:\s]*(\d{10,14})/i',
                ],
                'skuPatterns' => [
                    '/(?:custom\s*label|sku)[:\s]*([A-Z0-9][\w\-]{3,20})/i',
                ],
                'quantityPatterns' => [
                    '/(?:qty|quantity)[:\s]*(\d+)/i',
                ],
            ],

            'amazon' => [
                'name' => 'Amazon',
                'vendorDisplayName' => 'Amazon',
                'identifiers' => ['amazon', 'amazon.co.uk', 'amazon.com', 'amzn'],
                'identifierWeight' => 15,
                'structuralMarkers' => [
                    '/order\s*(?:number|#)[:\s]*\d{3}-\d{7}-\d{7}/i',
                    '/(?:sold|fulfilled)\s*by/i',
                ],
                'datePatterns' => [
                    '/(?:order\s*(?:placed|date)|delivery\s*date)[:\s]*(\d{1,2}\s+\w+\s+\d{4})/i',
                    '/(?:order\s*(?:placed|date)|delivery\s*date)[:\s]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i',
                ],
                'amountPatterns' => [
                    ['field' => 'total', 'pattern' => '/(?:grand\s*total|order\s*total)[:\s]*[£$€]?\s*([\d,]+\.?\d{0,2})/i'],
                    ['field' => 'total', 'pattern' => '/(?:item[s]?\s*subtotal)[:\s]*[£$€]?\s*([\d,]+\.?\d{0,2})/i'],
                ],
                'itemNamePatterns' => [
                    '/(?:item|product)[:\s]+(.{10,150})/i',
                ],
                'partNumberPatterns' => [
                    '/(?:ASIN|model\s*(?:number|no))[:\s]*([A-Z0-9]{10})/i',
                ],
                'quantityPatterns' => [
                    '/(?:qty|quantity)[:\s]*(\d+)/i',
                ],
            ],

            'halfords' => [
                'name' => 'Halfords',
                'vendorDisplayName' => 'Halfords',
                'identifiers' => ['halfords', 'halfords.com', 'halfords autocentre'],
                'identifierWeight' => 15,
                'structuralMarkers' => [
                    '/halfords\s*(?:group|autocentre|store)/i',
                ],
                'datePatterns' => [
                    '/(?:date|transaction\s*date)[:\s]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i',
                ],
                'amountPatterns' => [
                    ['field' => 'total', 'pattern' => '/(?:total|amount\s*due|balance)[:\s]*[£$€]?\s*([\d,]+\.?\d{0,2})/i'],
                ],
                'itemNamePatterns' => [
                    '/(?:description|item)[:\s]+(.{10,120})/i',
                ],
                'partNumberPatterns' => [
                    '/(?:cat\s*(?:no|number|#)|halfords\s*(?:no|number|#))[:\s]*([A-Z0-9][\w\-]{3,20})/i',
                ],
            ],

            'eurocarparts' => [
                'name' => 'Euro Car Parts',
                'vendorDisplayName' => 'Euro Car Parts',
                'identifiers' => ['euro car parts', 'eurocarparts', 'ecp', 'lkq euro'],
                'identifierWeight' => 15,
                'structuralMarkers' => [
                    '/invoice\s*(?:number|no)/i',
                    '/branch/i',
                ],
                'datePatterns' => [
                    '/(?:invoice\s*date|date)[:\s]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i',
                ],
                'amountPatterns' => [
                    ['field' => 'total', 'pattern' => '/(?:total\s*(?:inc(?:l?)\s*vat|incl)|invoice\s*total|balance\s*due)[:\s]*[£$€]?\s*([\d,]+\.?\d{0,2})/i'],
                ],
                'partNumberPatterns' => [
                    '/(?:oem\s*(?:ref|number|no)|part\s*(?:no|number))[:\s]*([A-Z0-9][\w\-\.]{3,30})/i',
                ],
            ],

            'kwikfit' => [
                'name' => 'Kwik Fit',
                'vendorDisplayName' => 'Kwik Fit',
                'identifiers' => ['kwik fit', 'kwikfit', 'kwik-fit'],
                'identifierWeight' => 15,
                'structuralMarkers' => [
                    '/(?:registration|reg)[:\s]*[A-Z]{2}\d{2}\s*[A-Z]{3}/i',
                    '/centre[:\s]/i',
                ],
                'datePatterns' => [
                    '/(?:date|invoice\s*date)[:\s]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i',
                ],
                'amountPatterns' => [
                    ['field' => 'total', 'pattern' => '/(?:total|amount|balance)[:\s]*[£$€]?\s*([\d,]+\.?\d{0,2})/i'],
                    ['field' => 'labo[u]?r', 'pattern' => '/(?:labour|labor|fitting)[:\s]*[£$€]?\s*([\d,]+\.?\d{0,2})/i'],
                ],
            ],

            'mot_certificate' => [
                'name' => 'MOT Certificate',
                'vendorDisplayName' => null,
                'identifiers' => ['mot test certificate', 'mot testing', 'vehicle inspectorate', 'dvsa', 'vosa'],
                'identifierWeight' => 20,
                'structuralMarkers' => [
                    '/test\s*(?:number|no)[:\s]*\d{10,15}/i',
                    '/(?:advisory|advisories)/i',
                    '/(?:pass|fail)/i',
                ],
                'datePatterns' => [
                    '/(?:test\s*date|date\s*of\s*test|date\s*tested)[:\s]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i',
                    '/(?:test\s*date|date\s*of\s*test)[:\s]*(\d{1,2}\s+\w+\s+\d{4})/i',
                ],
                'amountPatterns' => [
                    ['field' => 'total', 'pattern' => '/(?:test\s*fee|mot\s*fee|fee)[:\s]*[£$€]?\s*([\d,]+\.?\d{0,2})/i'],
                ],
            ],

            'fuel_station' => [
                'name' => 'Fuel Station',
                'vendorDisplayName' => null,
                'identifiers' => ['pump', 'nozzle', 'forecourt', 'fuel receipt', 'petrol', 'diesel', 'unleaded'],
                'identifierWeight' => 8,
                'structuralMarkers' => [
                    '/(?:pump|nozzle)\s*(?:no|number|#)[:\s]*\d/i',
                    '/(?:price\s*per\s*(?:litre|ltr)|ppl)/i',
                    '/(?:litres|ltrs|gallons)/i',
                ],
                'datePatterns' => [
                    '/(?:date|transaction)[:\s]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i',
                ],
                'amountPatterns' => [
                    ['field' => 'total', 'pattern' => '/(?:total|amount|sale)[:\s]*[£$€]?\s*([\d,]+\.?\d{0,2})/i'],
                ],
            ],

            'generic' => [
                'name' => 'Generic Receipt',
                'vendorDisplayName' => null,
                'identifiers' => [],
                'datePatterns' => [],
                'amountPatterns' => [],
                'itemNamePatterns' => [],
                'partNumberPatterns' => [],
            ],
        ];
    }
}
