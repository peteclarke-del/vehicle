<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ReceiptOcrService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Receipt OCR Service Test
 * 
 * Unit tests for receipt text extraction and parsing
 */
class ReceiptOcrServiceTest extends TestCase
{
    private ReceiptOcrService $service;

    protected function setUp(): void
    {
        $this->service = new ReceiptOcrService(new NullLogger(), sys_get_temp_dir());
    }

    public function testExtractsDateFromReceipt(): void
    {
        $ocrText = "Receipt\nDate: 15/03/2024\nTotal: £45.99";
        
        $result = $this->service->parseReceipt($ocrText);

        $this->assertArrayHasKey('date', $result);
        $this->assertSame('2024-03-15', $result['date']->format('Y-m-d'));
    }

    public function testExtractsTotalAmount(): void
    {
        $ocrText = "Receipt\nDate: 15/03/2024\nTotal: £45.99";
        
        $result = $this->service->parseReceipt($ocrText);

        $this->assertArrayHasKey('total', $result);
        $this->assertSame(45.99, $result['total']);
    }

    public function testExtractsSupplierName(): void
    {
        $ocrText = "AutoZone\n123 Main St\nDate: 15/03/2024\nTotal: £45.99";
        
        $result = $this->service->parseReceipt($ocrText);

        $this->assertArrayHasKey('supplier', $result);
        $this->assertSame('AutoZone', $result['supplier']);
    }

    public function testExtractsItemsFromReceipt(): void
    {
        $ocrText = "Receipt\nOil Filter £12.99\nBrake Pads £45.99\nTotal: £58.98";
        
        $result = $this->service->parseReceipt($ocrText);

        $this->assertArrayHasKey('items', $result);
        $this->assertCount(2, $result['items']);
    }

    public function testExtractsMileageFromReceipt(): void
    {
        $ocrText = "Service Receipt\nMileage: 45,000\nTotal: £150.00";
        
        $result = $this->service->parseReceipt($ocrText);

        $this->assertArrayHasKey('mileage', $result);
        $this->assertSame(45000, $result['mileage']);
    }

    public function testExtractsVatAmount(): void
    {
        $ocrText = "Receipt\nSubtotal: £100.00\nVAT (20%): £20.00\nTotal: £120.00";
        
        $result = $this->service->parseReceipt($ocrText);

        $this->assertArrayHasKey('vat', $result);
        $this->assertSame(20.00, $result['vat']);
    }

    public function testExtractsPaymentMethod(): void
    {
        $ocrText = "Receipt\nTotal: £45.99\nPaid: Card";
        
        $result = $this->service->parseReceipt($ocrText);

        $this->assertArrayHasKey('paymentMethod', $result);
        $this->assertSame('Card', $result['paymentMethod']);
    }

    public function testHandlesMultipleDateFormats(): void
    {
        $formats = [
            "Date: 15/03/2024" => '2024-03-15',
            "Date: 15-03-2024" => '2024-03-15',
            "Date: 2024-03-15" => '2024-03-15',
            "Date: 03/15/2024" => '2024-03-15',
        ];

        foreach ($formats as $dateText => $expected) {
            $result = $this->service->parseReceipt($dateText);
            $this->assertSame($expected, $result['date']->format('Y-m-d'));
        }
    }

    public function testHandlesMultipleCurrencyFormats(): void
    {
        $amounts = [
            "Total: £45.99" => 45.99,
            "Total: 45.99" => 45.99,
            "Total: £45,99" => 45.99,
            "Total: GBP 45.99" => 45.99,
        ];

        foreach ($amounts as $amountText => $expected) {
            $result = $this->service->parseReceipt($amountText);
            $this->assertSame($expected, $result['total']);
        }
    }

    public function testExtractsPhoneNumber(): void
    {
        $ocrText = "AutoZone\nTel: 01234 567890\nTotal: £45.99";
        
        $result = $this->service->parseReceipt($ocrText);

        $this->assertArrayHasKey('phone', $result);
        $this->assertSame('01234 567890', $result['phone']);
    }

    public function testExtractsPostcode(): void
    {
        $ocrText = "AutoZone\n123 Main St\nLondon SW1A 1AA\nTotal: £45.99";
        
        $result = $this->service->parseReceipt($ocrText);

        $this->assertArrayHasKey('postcode', $result);
        $this->assertSame('SW1A 1AA', $result['postcode']);
    }

    public function testExtractsVatNumber(): void
    {
        $ocrText = "Receipt\nVAT No: GB 123456789\nTotal: £45.99";
        
        $result = $this->service->parseReceipt($ocrText);

        $this->assertArrayHasKey('vatNumber', $result);
        $this->assertSame('GB 123456789', $result['vatNumber']);
    }

    public function testHandlesLowConfidenceOcr(): void
    {
        $ocrText = "R3c31pt\nD4t3: 1S/03/2024\nT0t4l: £4S.99";
        
        $result = $this->service->parseReceipt($ocrText);

        // Should still extract recognizable patterns
        $this->assertIsArray($result);
    }

    public function testHandlesEmptyReceipt(): void
    {
        $result = $this->service->parseReceipt('');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testExtractsServiceType(): void
    {
        $ocrText = "MOT Test\nDate: 15/03/2024\nTotal: £54.85";
        
        $result = $this->service->parseReceipt($ocrText);

        $this->assertArrayHasKey('serviceType', $result);
        $this->assertStringContainsString('MOT', $result['serviceType']);
    }

    public function testExtractsVehicleRegistration(): void
    {
        $ocrText = "Receipt\nVehicle: AB12 CDE\nTotal: £45.99";
        
        $result = $this->service->parseReceipt($ocrText);

        $this->assertArrayHasKey('registration', $result);
        $this->assertSame('AB12 CDE', $result['registration']);
    }

    public function testCalculatesConfidenceScore(): void
    {
        $ocrText = "Receipt\nDate: 15/03/2024\nTotal: £45.99\nSupplier: AutoZone";
        
        $result = $this->service->parseReceipt($ocrText);

        $this->assertArrayHasKey('confidence', $result);
        $this->assertGreaterThan(0.5, $result['confidence']);
    }
}
