<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'vehicle_images')]
class VehicleImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Vehicle::class, inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Vehicle $vehicle = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $path = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $caption = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isPrimary = false;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $displayOrder = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isScraped = false;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $sourceUrl = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $uploadedAt = null;

    public function __construct()
    {
        $this->uploadedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVehicle(): ?Vehicle
    {
        return $this->vehicle;
    }

    public function setVehicle(?Vehicle $vehicle): self
    {
        $this->vehicle = $vehicle;
        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function setCaption(?string $caption): self
    {
        $this->caption = $caption;
        return $this;
    }

    public function getIsPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function setIsPrimary(bool $isPrimary): self
    {
        $this->isPrimary = $isPrimary;
        return $this;
    }

    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(int $displayOrder): self
    {
        $this->displayOrder = $displayOrder;
        return $this;
    }

    public function getIsScraped(): bool
    {
        return $this->isScraped;
    }

    public function setIsScraped(bool $isScraped): self
    {
        $this->isScraped = $isScraped;
        return $this;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(?string $sourceUrl): self
    {
        $this->sourceUrl = $sourceUrl;
        return $this;
    }

    public function getUploadedAt(): ?\DateTimeInterface
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(\DateTimeInterface $uploadedAt): self
    {
        $this->uploadedAt = $uploadedAt;
        return $this;
    }
}
