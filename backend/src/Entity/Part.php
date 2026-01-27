<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Attachment;

#[ORM\Entity]
#[ORM\Table(name: 'parts')]
class Part
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Vehicle::class, inversedBy: 'parts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Vehicle $vehicle = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $purchaseDate = null;

    #[ORM\Column(type: 'string', length: 200)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 200, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $price = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $partNumber = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $sku = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $manufacturer = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $supplier = null;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $quantity = 1;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $warrantyMonths = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $cost = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $category = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $installationDate = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $mileageAtInstallation = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: ServiceRecord::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ServiceRecord $serviceRecord = null;

    #[ORM\ManyToOne(targetEntity: \App\Entity\Todo::class, inversedBy: 'parts')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?\App\Entity\Todo $todo = null;

    #[ORM\ManyToOne(targetEntity: MotRecord::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?MotRecord $motRecord = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(targetEntity: Attachment::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Attachment $receiptAttachment = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $productUrl = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
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

    public function getPurchaseDate(): ?\DateTimeInterface
    {
        return $this->purchaseDate;
    }

    public function setPurchaseDate(\DateTimeInterface $purchaseDate): self
    {
        $this->purchaseDate = $purchaseDate;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
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

    public function getCost(): ?string
    {
        return $this->cost;
    }

    public function setCost(string $cost): self
    {
        $this->cost = $cost;
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

    public function getInstallationDate(): ?\DateTimeInterface
    {
        return $this->installationDate;
    }

    public function setInstallationDate(?\DateTimeInterface $installationDate): self
    {
        $this->installationDate = $installationDate;
        return $this;
    }

    public function getMileageAtInstallation(): ?int
    {
        return $this->mileageAtInstallation;
    }

    public function setMileageAtInstallation(?int $mileageAtInstallation): self
    {
        $this->mileageAtInstallation = $mileageAtInstallation;
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

    public function getServiceRecord(): ?ServiceRecord
    {
        return $this->serviceRecord;
    }

    public function setServiceRecord(?ServiceRecord $serviceRecord): self
    {
        $this->serviceRecord = $serviceRecord;
        return $this;
    }

    public function getTodo(): ?\App\Entity\Todo
    {
        return $this->todo;
    }

    public function setTodo(?\App\Entity\Todo $todo): self
    {
        $this->todo = $todo;
        return $this;
    }

    public function getMotRecord(): ?MotRecord
    {
        return $this->motRecord;
    }

    public function setMotRecord(?MotRecord $motRecord): self
    {
        $this->motRecord = $motRecord;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getReceiptAttachment(): ?Attachment
    {
        return $this->receiptAttachment;
    }

    public function setReceiptAttachment(?Attachment $receiptAttachment): self
    {
        $this->receiptAttachment = $receiptAttachment;
        return $this;
    }


    public function getProductUrl(): ?string
    {
        return $this->productUrl;
    }

    public function setProductUrl(?string $productUrl): self
    {
        $this->productUrl = $productUrl;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name ?? $this->description;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price ?? $this->cost;
    }

    public function setPrice($price): self
    {
        if ($price !== null) {
            $this->price = (string) $price;
        } else {
            $this->price = null;
        }
        return $this;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(?string $sku): self
    {
        $this->sku = $sku;
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

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getWarrantyMonths(): ?int
    {
        return $this->warrantyMonths;
    }

    public function setWarranty($warrantyMonths): self
    {
        if ($warrantyMonths !== null) {
            $this->warrantyMonths = (int) $warrantyMonths;
        } else {
            $this->warrantyMonths = null;
        }
        return $this;
    }

    public function getWarranty(): ?int
    {
        return $this->warrantyMonths;
    }

    public function getTotalCost(): ?float
    {
        if (!$this->price) {
            return null;
        }
        return (float)$this->price * $this->quantity;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): self
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    /**
     * Check if part is installed
     */
    public function isInstalled(): bool
    {
        return $this->installationDate !== null;
    }

    /**
     * Get age of part in days since installation or purchase
     */
    public function getAge(): int
    {
        $referenceDate = $this->installationDate ?? $this->purchaseDate;
        if (!$referenceDate) {
            return 0;
        }
        $now = new \DateTime();
        return $now->diff($referenceDate)->days;
    }
}
