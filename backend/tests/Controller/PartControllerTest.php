<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\PartController;
use App\Entity\Part;
use App\Service\ReceiptOcrService;
use App\Service\UrlScraperService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class PartControllerTest extends TestCase
{
    public function testSerializeIncludesSupplierAndWarranty(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $ocr = $this->createMock(ReceiptOcrService::class);
        $scraper = $this->createMock(UrlScraperService::class);

        $controller = new PartController($em, $ocr, $scraper);

        $part = new Part();
        $part->setDescription('Test part');
        $part->setSupplier('ACME Supplies');
        $part->setWarranty(12);

        // Provide a minimal vehicle mock so serializePart can read vehicle id
        $vehicle = $this->createMock(\App\Entity\Vehicle::class);
        $vehicle->method('getId')->willReturn(1);
        $part->setVehicle($vehicle);

        $ref = new \ReflectionClass(PartController::class);
        $method = $ref->getMethod('serializePart');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $part);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('supplier', $result);
        $this->assertArrayHasKey('warranty', $result);
        $this->assertSame('ACME Supplies', $result['supplier']);
        $this->assertSame(12, $result['warranty']);
    }
}
