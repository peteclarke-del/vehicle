<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * VIN Decoder Service using API Ninjas VIN Lookup API
 * 
 * Decodes Vehicle Identification Numbers (VIN) to extract:
 * - Manufacturer/Make
 * - Model
 * - Year
 * - Country of origin
 * - Vehicle class
 * - WMI, VDS, VIS identifiers
 */
class VinDecoderService
{
    private string $apiKey;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        string $apiNinjasKey = ''
    ) {
        $this->apiKey = $apiNinjasKey ?: ($_ENV['API_NINJAS_KEY'] ?? '');
    }

    /**
     * Decode a VIN and return vehicle information
     *
     * @param string $vin 17-character VIN
     * @return array|null Decoded VIN data or null if invalid/not found
     */
    public function decodeVin(string $vin): ?array
    {
        // Validate VIN format (must be exactly 17 characters)
        $vin = strtoupper(trim($vin));
        if (strlen($vin) !== 17) {
            $this->logger->warning('Invalid VIN length', ['vin' => $vin, 'length' => strlen($vin)]);
            return null;
        }

        // VINs should not contain I, O, or Q to avoid confusion with 1, 0
        if (preg_match('/[IOQ]/', $vin)) {
            $this->logger->warning('VIN contains invalid characters (I, O, Q)', ['vin' => $vin]);
            return null;
        }

        if (empty($this->apiKey)) {
            $this->logger->error('API Ninjas API key not configured');
            return null;
        }

        try {
            $apiUrl = 'https://api.api-ninjas.com/v1/vinlookup?vin=' . urlencode($vin);
            
            $this->logger->info('Decoding VIN', ['vin' => $vin]);

            $response = $this->httpClient->request('GET', $apiUrl, [
                'headers' => [
                    'X-Api-Key' => $this->apiKey
                ],
                'timeout' => 10
            ]);

            $data = $response->toArray();
            
            if (empty($data) || !isset($data['vin'])) {
                $this->logger->warning('No data returned for VIN', ['vin' => $vin]);
                return null;
            }

            $this->logger->info('Successfully decoded VIN', [
                'vin' => $vin,
                'make' => $data['manufacturer'] ?? $data['make'] ?? 'Unknown',
                'year' => $data['year'] ?? 'Unknown'
            ]);

            return $this->formatVinData($data);
        } catch (\Exception $e) {
            $this->logger->error('Failed to decode VIN', [
                'error' => $e->getMessage(),
                'vin' => $vin
            ]);
            return null;
        }
    }

    /**
     * Format VIN data for consistent output
     */
    private function formatVinData(array $data): array
    {
        return [
            'vin' => $data['vin'] ?? null,
            'make' => $data['manufacturer'] ?? $data['make'] ?? null,
            'model' => $data['model'] ?? null,
            'year' => $data['year'] ?? null,
            'country' => $data['country'] ?? null,
            'region' => $data['region'] ?? null,
            'class' => $data['class'] ?? null,
            'wmi' => $data['wmi'] ?? null,
            'vds' => $data['vds'] ?? null,
            'vis' => $data['vis'] ?? null,
        ];
    }

    /**
     * Check if VIN is valid format (17 characters, no I/O/Q)
     */
    public function isValidVinFormat(string $vin): bool
    {
        $vin = strtoupper(trim($vin));
        return strlen($vin) === 17 && !preg_match('/[IOQ]/', $vin);
    }

    /**
     * Extract WMI (World Manufacturer Identifier) from VIN
     * First 3 characters identify the manufacturer
     */
    public function extractWmi(string $vin): ?string
    {
        $vin = strtoupper(trim($vin));
        if (strlen($vin) < 3) {
            return null;
        }
        return substr($vin, 0, 3);
    }

    /**
     * Extract VDS (Vehicle Descriptor Section) from VIN
     * Characters 4-9 describe the vehicle attributes
     */
    public function extractVds(string $vin): ?string
    {
        $vin = strtoupper(trim($vin));
        if (strlen($vin) < 9) {
            return null;
        }
        return substr($vin, 3, 6);
    }

    /**
     * Extract VIS (Vehicle Identifier Section) from VIN
     * Characters 10-17 are the unique serial number
     */
    public function extractVis(string $vin): ?string
    {
        $vin = strtoupper(trim($vin));
        if (strlen($vin) !== 17) {
            return null;
        }
        return substr($vin, 9, 8);
    }
}
