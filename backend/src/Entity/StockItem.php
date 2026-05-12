<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'stock_items')]
class StockItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: VehicleType::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?VehicleType $vehicleType = null;

    #[ORM\Column(type: 'string', length: 32)]
    private ?string $itemType = null;

    #[ORM\Column(type: 'string', length: 200)]
    private ?string $category = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $quantity = '0.00';

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $supplier = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $price = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $purchaseDate = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $partNumber = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $manufacturer = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $warranty = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getVehicleType(): ?VehicleType
    {
        return $this->vehicleType;
    }

    public function setVehicleType(?VehicleType $vehicleType): self
    {
        $this->vehicleType = $vehicleType;
        return $this;
    }

    public function getItemType(): ?string
    {
        return $this->itemType;
    }

    public function setItemType(string $itemType): self
    {
        $this->itemType = $itemType;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function setQuantity(string $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getSupplier(): ?string
    {
        return $this->supplier;
    }

    public function setSupplier(?string $supplier): self
    {
        $this->supplier = $supplier;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(?string $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function getPurchaseDate(): ?\DateTimeInterface
    {
        return $this->purchaseDate;
    }

    public function setPurchaseDate(?\DateTimeInterface $purchaseDate): self
    {
        $this->purchaseDate = $purchaseDate;
        return $this;
    }

    public function getPartNumber(): ?string
    {
        return $this->partNumber;
    }

    public function setPartNumber(?string $partNumber): self
    {
        $this->partNumber = $partNumber;
        return $this;
    }

    public function getManufacturer(): ?string
    {
        return $this->manufacturer;
    }

    public function setManufacturer(?string $manufacturer): self
    {
        $this->manufacturer = $manufacturer;
        return $this;
    }

    public function getWarranty(): ?string
    {
        return $this->warranty;
    }

    public function setWarranty(?string $warranty): self
    {
        $this->warranty = $warranty;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function touch(): self
    {
        $this->updatedAt = new \DateTime();
        return $this;
    }
}
