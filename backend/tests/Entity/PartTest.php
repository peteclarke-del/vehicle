<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Part;
use App\Entity\Vehicle;
use PHPUnit\Framework\TestCase;

/**
 * Part Entity Test
 * 
 * Unit tests for Part entity
 * 
 * @coversDefaultClass \App\Entity\Part
 */
class PartTest extends TestCase
{
    public function testPartCreation(): void
    {
        $part = new Part();
        
        $this->assertInstanceOf(Part::class, $part);
        $this->assertNull($part->getId());
    }

    public function testSetAndGetName(): void
    {
        $part = new Part();
        $part->setName('Brake Pads');
        
        $this->assertSame('Brake Pads', $part->getName());
    }

    public function testSetAndGetCategory(): void
    {
        $part = new Part();
        $part->setCategory('Brakes');
        
        $this->assertSame('Brakes', $part->getCategory());
    }

    public function testSetAndGetPrice(): void
    {
        $part = new Part();
        $part->setPrice(45.99);
        
        $this->assertSame('45.99', $part->getPrice());
    }

    public function testSetAndGetQuantity(): void
    {
        $part = new Part();
        $part->setQuantity(2);
        
        $this->assertSame(2, $part->getQuantity());
    }

    public function testCalculateTotalCost(): void
    {
        $part = new Part();
        $part->setPrice(45.99);
        $part->setQuantity(2);
        
        $this->assertSame(91.98, $part->getTotalCost());
    }

    public function testSetAndGetSupplier(): void
    {
        $part = new Part();
        $part->setSupplier('AutoParts Co');
        
        $this->assertSame('AutoParts Co', $part->getSupplier());
    }

    public function testSetAndGetSku(): void
    {
        $part = new Part();
        $part->setSku('BP-12345');
        
        $this->assertSame('BP-12345', $part->getSku());
    }

    public function testSetAndGetPurchaseDate(): void
    {
        $part = new Part();
        $date = new \DateTime('2024-01-15');
        $part->setPurchaseDate($date);
        
        $this->assertSame($date, $part->getPurchaseDate());
    }

    public function testSetAndGetInstallationDate(): void
    {
        $part = new Part();
        $date = new \DateTime('2024-01-20');
        $part->setInstallationDate($date);
        
        $this->assertSame($date, $part->getInstallationDate());
    }

    public function testSetAndGetWarranty(): void
    {
        $part = new Part();
        $part->setWarranty(24);
        
        $this->assertSame(24, $part->getWarranty());
        $this->assertSame(24, $part->getWarrantyMonths());
    }

    public function testSetAndGetNotes(): void
    {
        $part = new Part();
        $part->setNotes('OEM part, genuine replacement');
        
        $this->assertSame('OEM part, genuine replacement', $part->getNotes());
    }

    public function testSetAndGetImageUrl(): void
    {
        $part = new Part();
        $part->setImageUrl('https://example.com/image.jpg');
        
        $this->assertSame('https://example.com/image.jpg', $part->getImageUrl());
    }

    public function testSetAndGetProductUrl(): void
    {
        $part = new Part();
        $part->setProductUrl('https://amazon.com/dp/B001234567');
        
        $this->assertSame('https://amazon.com/dp/B001234567', $part->getProductUrl());
    }

    public function testVehicleRelationship(): void
    {
        $part = new Part();
        $vehicle = new Vehicle();
        
        $part->setVehicle($vehicle);
        
        $this->assertSame($vehicle, $part->getVehicle());
    }

    public function testIsInstalled(): void
    {
        $part = new Part();
        
        $this->assertFalse($part->isInstalled());
        
        $part->setInstallationDate(new \DateTime());
        $this->assertTrue($part->isInstalled());
    }

    public function testGetAge(): void
    {
        $part = new Part();
        $installDate = new \DateTime('-30 days');
        $part->setInstallationDate($installDate);
        
        $age = $part->getAge();
        
        $this->assertEqualsWithDelta(30, $age, 1);
    }

    public function testCreatedAtTimestamp(): void
    {
        $part = new Part();
        $part->setCreatedAt(new \DateTime());
        
        $this->assertInstanceOf(\DateTime::class, $part->getCreatedAt());
    }
}
