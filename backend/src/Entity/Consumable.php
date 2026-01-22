<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'consumables')]
class Consumable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Vehicle::class, inversedBy: 'consumables')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Vehicle $vehicle = null;

    #[ORM\ManyToOne(targetEntity: ConsumableType::class, inversedBy: 'consumables')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ConsumableType $consumableType = null;

    #[ORM\Column(type: 'string', length: 200)]
    private ?string $specification = null;

    #[ORM\Column(type: 'string', length: 200, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $brand = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $partNumber = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $replacementIntervalMiles = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $nextReplacementMileage = null;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, nullable: true)]
    private ?string $quantity = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $lastChanged = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $mileageAtChange = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $cost = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: ServiceRecord::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ServiceRecord $serviceRecord = null;

    #[ORM\ManyToOne(targetEntity: MotRecord::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?MotRecord $motRecord = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $receiptAttachmentId = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $productUrl = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $supplier = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
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

    public function getConsumableType(): ?ConsumableType
    {
        return $this->consumableType;
    }

    public function setConsumableType(?ConsumableType $consumableType): self
    {
        $this->consumableType = $consumableType;
        return $this;
    }

    public function getSpecification(): ?string
    {
        return $this->specification;
    }

    public function setSpecification(string $specification): self
    {
        $this->specification = $specification;
        return $this;
    }

    public function getQuantity(): ?string
    {
        return $this->quantity;
    }

    public function setQuantity($quantity): self
    {
        if ($quantity !== null) {
            $this->quantity = (string) $quantity;
        } else {
            $this->quantity = null;
        }
        return $this;
    }

    public function getLastChanged(): ?\DateTimeInterface
    {
        return $this->lastChanged;
    }

    public function setLastChanged(\DateTimeInterface $lastChanged): self
    {
        $this->lastChanged = $lastChanged;
        return $this;
    }

    public function getMileageAtChange(): ?int
    {
        return $this->mileageAtChange;
    }

    public function setMileageAtChange(?int $mileageAtChange): self
    {
        $this->mileageAtChange = $mileageAtChange;
        return $this;
    }

    public function getCost(): ?string
    {
        return $this->cost;
    }

    public function setCost($cost): self
    {
        if ($cost !== null) {
            $this->cost = (string) $cost;
        } else {
            $this->cost = null;
        }
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

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getReceiptAttachmentId(): ?int
    {
        return $this->receiptAttachmentId;
    }

    public function setReceiptAttachmentId(?int $receiptAttachmentId): self
    {
        $this->receiptAttachmentId = $receiptAttachmentId;
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

    public function getType(): ?ConsumableType
    {
        return $this->consumableType;
    }

    /**
     * Alias for setConsumableType() - accepts ConsumableType object or string
     * @param ConsumableType|string|null $type
     */
    public function setType($type): self
    {
        if (is_string($type)) {
            // For now, when a string is provided, set to null
            // In a real app, you'd look up the ConsumableType by name
            return $this->setConsumableType(null);
        }
        return $this->setConsumableType($type);
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(?string $brand): self
    {
        $this->brand = $brand;
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

    /**
     * Alias for setLastChanged()
     */
    public function setLastReplacementDate(\DateTimeInterface $date): self
    {
        return $this->setLastChanged($date);
    }

    /**
     * Alias for setMileageAtChange()
     */
    public function setLastReplacementMileage(?int $mileage): self
    {
        return $this->setMileageAtChange($mileage);
    }

    public function getReplacementIntervalMiles(): ?int
    {
        return $this->replacementIntervalMiles;
    }

    public function setReplacementInterval(?int $intervalMiles): self
    {
        $this->replacementIntervalMiles = $intervalMiles;
        return $this;
    }

    public function getNextReplacementMileage(): ?int
    {
        return $this->nextReplacementMileage;
    }

    public function setNextReplacementMileage(?int $mileage): self
    {
        $this->nextReplacementMileage = $mileage;
        return $this;
    }

    /**
     * Get replacement history (simplified - returns array with current record)
     */
    public function getReplacementHistory(): array
    {
        return [
            [
                'date' => $this->lastChanged,
                'mileage' => $this->mileageAtChange,
                'cost' => $this->cost,
            ]
        ];
    }

    public function getLastReplacementDate(): ?\DateTimeInterface
    {
        return $this->lastChanged;
    }

    public function getLastReplacementMileage(): ?int
    {
        return $this->mileageAtChange;
    }

    public function getReplacementInterval(): ?int
    {
        return $this->replacementIntervalMiles;
    }

    public function calculateNextReplacementMileage(): ?int
    {
        if ($this->mileageAtChange && $this->replacementIntervalMiles) {
            return $this->mileageAtChange + $this->replacementIntervalMiles;
        }
        return $this->nextReplacementMileage;
    }

    public function isDueForReplacement(?int $currentMileage = null): bool
    {
        if (!$currentMileage || !$this->nextReplacementMileage) {
            return false;
        }
        return $currentMileage >= $this->nextReplacementMileage;
    }

    public function getMilesUntilReplacement(?int $currentMileage = null): ?int
    {
        if (!$currentMileage || !$this->nextReplacementMileage) {
            return null;
        }
        return max(0, $this->nextReplacementMileage - $currentMileage);
    }

    public function isOverdue(?int $currentMileage = null): bool
    {
        if (!$currentMileage || !$this->nextReplacementMileage) {
            return false;
        }
        return $currentMileage > $this->nextReplacementMileage;
    }

    public function getOverdueMiles(?int $currentMileage = null): int
    {
        if (!$currentMileage || !$this->nextReplacementMileage) {
            return 0;
        }
        return max(0, $currentMileage - $this->nextReplacementMileage);
    }

    public function calculateAnnualCost(?int $annualMileage = null): ?float
    {
        if (!$this->cost || !$this->replacementIntervalMiles) {
            return null;
        }
        // Use provided annual mileage or default to 12,000 miles
        $mileage = $annualMileage ?? 12000;
        $replacementsPerYear = $mileage / $this->replacementIntervalMiles;
        return (float)$this->cost * $replacementsPerYear;
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
}
