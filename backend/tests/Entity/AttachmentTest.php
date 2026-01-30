<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Attachment;
use App\Entity\Vehicle;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Attachment Entity Test
 * 
 * Unit tests for Attachment entity
 * 
 * @coversDefaultClass \App\Entity\Attachment
 */
class AttachmentTest extends TestCase
{
    private Attachment $attachment;

    protected function setUp(): void
    {
        $this->attachment = new Attachment();
    }

    public function testGetSetFilename(): void
    {
        $this->attachment->setFilename('receipt.pdf');
        
        $this->assertSame('receipt.pdf', $this->attachment->getFilename());
    }

    public function testGetSetOriginalFilename(): void
    {
        $this->attachment->setOriginalFilename('Service Receipt 2024.pdf');
        
        $this->assertSame('Service Receipt 2024.pdf', $this->attachment->getOriginalFilename());
    }

    public function testGetSetMimeType(): void
    {
        $this->attachment->setMimeType('application/pdf');
        
        $this->assertSame('application/pdf', $this->attachment->getMimeType());
    }

    public function testGetSetFileSize(): void
    {
        $this->attachment->setFileSize(1024000);
        
        $this->assertSame(1024000, $this->attachment->getFileSize());
    }

    public function testGetSetStoragePath(): void
    {
        $this->attachment->setStoragePath('/var/uploads/attachments/2024/03/receipt.pdf');
        
        $this->assertSame('/var/uploads/attachments/2024/03/receipt.pdf', $this->attachment->getStoragePath());
    }

    public function testGetSetVehicle(): void
    {
        $vehicle = new Vehicle();
        $vehicle->setRegistration('AB12 CDE');

        $this->attachment->setVehicle($vehicle);

        $this->assertSame($vehicle, $this->attachment->getVehicle());
    }


    public function testGetSetUploadedBy(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');

        $this->attachment->setUploadedBy($user);

        $this->assertSame($user, $this->attachment->getUploadedBy());
    }

    public function testIsImage(): void
    {
        $this->attachment->setMimeType('image/jpeg');
        
        $this->assertTrue($this->attachment->isImage());

        $this->attachment->setMimeType('application/pdf');
        
        $this->assertFalse($this->attachment->isImage());
    }

    public function testIsPdf(): void
    {
        $this->attachment->setMimeType('application/pdf');
        
        $this->assertTrue($this->attachment->isPdf());

        $this->attachment->setMimeType('image/jpeg');
        
        $this->assertFalse($this->attachment->isPdf());
    }

    public function testGetFileSizeFormatted(): void
    {
        $this->attachment->setFileSize(1024);
        
        $this->assertSame('1.00 KB', $this->attachment->getFileSizeFormatted());

        $this->attachment->setFileSize(1048576);
        
        $this->assertSame('1.00 MB', $this->attachment->getFileSizeFormatted());

        $this->attachment->setFileSize(500);
        
        $this->assertSame('500 bytes', $this->attachment->getFileSizeFormatted());
    }


    public function testGetSetDescription(): void
    {
        $this->attachment->setDescription('MOT certificate');
        
        $this->assertSame('MOT certificate', $this->attachment->getDescription());
    }

    public function testGetSetCategory(): void
    {
        $this->attachment->setCategory('receipt');
        
        $this->assertSame('receipt', $this->attachment->getCategory());
    }

    public function testGetExtension(): void
    {
        $this->attachment->setOriginalName('receipt.pdf');
        
        $this->assertSame('pdf', $this->attachment->getExtension());

        $this->attachment->setOriginalName('photo.jpg');
        
        $this->assertSame('jpg', $this->attachment->getExtension());
    }

    public function testIsVirusFree(): void
    {
        // virus-scan removed: no-op test removed
    }

    public function testGetSetVirusScanDate(): void
    {
        // virus-scan removed: no-op test removed
    }
}
