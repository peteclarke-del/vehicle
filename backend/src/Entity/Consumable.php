<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Attachment;

#[ORM\Entity]
#[ORM\Table(name: 'consumables')]

/**
 * class Consumable
 */
class Consumable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]

    /**
     * @var int
     */
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Vehicle::class, inversedBy: 'consumables')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]

    /**
     * @var Vehicle
     */
    private ?Vehicle $vehicle = null;

    #[ORM\ManyToOne(targetEntity: ConsumableType::class, inversedBy: 'consumables')]
    #[ORM\JoinColumn(nullable: false)]

    /**
     * @var ConsumableType
     */
    private ?ConsumableType $consumableType = null;

    #[ORM\Column(type: 'string', length: 200, nullable: true)]

    /**
     * @var string
     */
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]

    /**
     * @var string
     */
    private ?string $brand = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]

    /**
     * @var string
     */
    private ?string $partNumber = null;

    #[ORM\Column(type: 'integer', nullable: true)]

    /**
     * @var int
     */
    private ?int $replacementInterval = null;

    #[ORM\Column(type: 'integer', nullable: true)]

    /**
     * @var int
     */
    private ?int $nextReplacement = null;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, nullable: true)]

    /**
     * @var string
     */
    private ?string $quantity = null;

    #[ORM\Column(type: 'date', nullable: true)]

    /**
     * @var \DateTimeInterface
     */
    private ?\DateTimeInterface $lastChanged = null;

    #[ORM\Column(type: 'integer', nullable: true)]

    /**
     * @var int
     */
    private ?int $mileageAtChange = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]

    /**
     * @var string
     */
    private ?string $cost = null;

    #[ORM\Column(type: 'text', nullable: true)]

    /**
     * @var string
     */
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: ServiceRecord::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]

    /**
     * @var ServiceRecord
     */
    private ?ServiceRecord $serviceRecord = null;

    #[ORM\ManyToOne(targetEntity: \App\Entity\Todo::class, inversedBy: 'consumables')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]

    /**
     * @var \App\Entity\Todo
     */
    private ?\App\Entity\Todo $todo = null;

    #[ORM\ManyToOne(targetEntity: MotRecord::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]

    /**
     * @var MotRecord
     */
    private ?MotRecord $motRecord = null;

    #[ORM\Column(type: 'datetime')]

    /**
     * @var \DateTimeInterface
     */
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime')]

    /**
     * @var \DateTimeInterface
     */
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: Attachment::class, cascade: ['remove'])]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]

    /**
     * @var Attachment
     */
    private ?Attachment $receiptAttachment = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]

    /**
     * @var string
     */
    private ?string $productUrl = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]

    /**
     * @var string
     */
    private ?string $supplier = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]

    /**
     * @var bool
     */
    private bool $includedInServiceCost = false;

    /**
     * function __construct
     *
     * @return void
     */
    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    /**
     * function getId
     *
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * function getVehicle
     *
     * @return Vehicle
     */
    public function getVehicle(): ?Vehicle
    {
        return $this->vehicle;
    }

    /**
     * function setVehicle
     *
     * @param Vehicle $vehicle
     *
     * @return self
     */
    public function setVehicle(?Vehicle $vehicle): self
    {
        $this->vehicle = $vehicle;
        return $this;
    }

    /**
     * function getConsumableType
     *
     * @return ConsumableType
     */
    public function getConsumableType(): ?ConsumableType
    {
        return $this->consumableType;
    }

    /**
     * function setConsumableType
     *
     * @param ConsumableType $consumableType
     *
     * @return self
     */
    public function setConsumableType(?ConsumableType $consumableType): self
    {
        $this->consumableType = $consumableType;
        return $this;
    }

    /**
     * function getQuantity
     *
     * @return string
     */
    public function getQuantity(): ?string
    {
        return $this->quantity;
    }

    /**
     * function setQuantity
     *
     * @param mixed $quantity
     *
     * @return self
     */
    public function setQuantity($quantity): self
    {
        if ($quantity !== null) {
            $this->quantity = (string) $quantity;
        } else {
            $this->quantity = null;
        }
        return $this;
    }

    /**
     * function getLastChanged
     *
     * @return \DateTimeInterface
     */
    public function getLastChanged(): ?\DateTimeInterface
    {
        return $this->lastChanged;
    }

    /**
     * function setLastChanged
     *
     * @param \DateTimeInterface $lastChanged
     *
     * @return self
     */
    public function setLastChanged(?\DateTimeInterface $lastChanged): self
    {
        $this->lastChanged = $lastChanged;
        return $this;
    }

    /**
     * function getMileageAtChange
     *
     * @return int
     */
    public function getMileageAtChange(): ?int
    {
        return $this->mileageAtChange;
    }

    /**
     * function setMileageAtChange
     *
     * @param int $mileageAtChange
     *
     * @return self
     */
    public function setMileageAtChange(?int $mileageAtChange): self
    {
        $this->mileageAtChange = $mileageAtChange;
        return $this;
    }

    /**
     * function getCost
     *
     * @return string
     */
    public function getCost(): ?string
    {
        return $this->cost;
    }

    /**
     * function setCost
     *
     * @param mixed $cost
     *
     * @return self
     */
    public function setCost($cost): self
    {
        if ($cost !== null) {
            $this->cost = (string) $cost;
        } else {
            $this->cost = null;
        }
        return $this;
    }

    /**
     * function getServiceRecord
     *
     * @return ServiceRecord
     */
    public function getServiceRecord(): ?ServiceRecord
    {
        return $this->serviceRecord;
    }

    /**
     * function setServiceRecord
     *
     * @param ServiceRecord $serviceRecord
     *
     * @return self
     */
    public function setServiceRecord(?ServiceRecord $serviceRecord): self
    {
        $this->serviceRecord = $serviceRecord;
        return $this;
    }

    /**
     * function getTodo
     *
     * @return \App\Entity\Todo
     */
    public function getTodo(): ?\App\Entity\Todo
    {
        return $this->todo;
    }

    /**
     * function setTodo
     *
     * @param \App\Entity\Todo $todo
     *
     * @return self
     */
    public function setTodo(?\App\Entity\Todo $todo): self
    {
        $this->todo = $todo;
        return $this;
    }

    /**
     * function getMotRecord
     *
     * @return MotRecord
     */
    public function getMotRecord(): ?MotRecord
    {
        return $this->motRecord;
    }

    /**
     * function setMotRecord
     *
     * @param MotRecord $motRecord
     *
     * @return self
     */
    public function setMotRecord(?MotRecord $motRecord): self
    {
        $this->motRecord = $motRecord;
        return $this;
    }

    /**
     * function getNotes
     *
     * @return string
     */
    public function getNotes(): ?string
    {
        return $this->notes;
    }

    /**
     * function setNotes
     *
     * @param string $notes
     *
     * @return self
     */
    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    /**
     * function getCreatedAt
     *
     * @return \DateTimeInterface
     */
    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * function setCreatedAt
     *
     * @param \DateTimeInterface $createdAt
     *
     * @return self
     */
    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * function getUpdatedAt
     *
     * @return \DateTimeInterface
     */
    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * function setUpdatedAt
     *
     * @param \DateTimeInterface $updatedAt
     *
     * @return self
     */
    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * function getReceiptAttachment
     *
     * @return Attachment
     */
    public function getReceiptAttachment(): ?Attachment
    {
        return $this->receiptAttachment;
    }

    /**
     * function setReceiptAttachment
     *
     * @param Attachment $receiptAttachment
     *
     * @return self
     */
    public function setReceiptAttachment(?Attachment $receiptAttachment): self
    {
        $this->receiptAttachment = $receiptAttachment;
        return $this;
    }

    /**
     * function getProductUrl
     *
     * @return string
     */
    public function getProductUrl(): ?string
    {
        return $this->productUrl;
    }

    /**
     * function setProductUrl
     *
     * @param string $productUrl
     *
     * @return self
     */
    public function setProductUrl(?string $productUrl): self
    {
        $this->productUrl = $productUrl;
        return $this;
    }

    /**
     * function getType
     *
     * @return ConsumableType
     */
    public function getType(): ?ConsumableType
    {
        return $this->consumableType;
    }

    /**
     * function setType
     *
     * Alias for setConsumableType() - accepts ConsumableType object or string
     *
     * @param mixed $type
     *
     * @return self
     */
    public function setType($type): self
    {
        if (is_string($type)) {
            // For now, when a string is provided, set to null
            // In a real app, you'd look up the ConsumableType by description
            return $this->setConsumableType(null);
        }
        return $this->setConsumableType($type);
    }

    /**
     * function getDescription
     *
     * @return string
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * function setDescription
     *
     * @param string $description
     *
     * @return self
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * function getBrand
     *
     * @return string
     */
    public function getBrand(): ?string
    {
        return $this->brand;
    }

    /**
     * function setBrand
     *
     * @param string $brand
     *
     * @return self
     */
    public function setBrand(?string $brand): self
    {
        $this->brand = $brand;
        return $this;
    }

    /**
     * function getPartNumber
     *
     * @return string
     */
    public function getPartNumber(): ?string
    {
        return $this->partNumber;
    }

    /**
     * function setPartNumber
     *
     * @param string $partNumber
     *
     * @return self
     */
    public function setPartNumber(?string $partNumber): self
    {
        $this->partNumber = $partNumber;
        return $this;
    }

    /**
     * function setLastReplacementDate
     *
     * Alias for setLastChanged()
     *
     * @param \DateTimeInterface $date
     *
     * @return self
     */
    public function setLastReplacementDate(\DateTimeInterface $date): self
    {
        return $this->setLastChanged($date);
    }

    /**
     * function setLastReplacementMileage
     *
     * Alias for setMileageAtChange()
     *
     * @param int $mileage
     *
     * @return self
     */
    public function setLastReplacementMileage(?int $mileage): self
    {
        return $this->setMileageAtChange($mileage);
    }

    /**
     * function getReplacementInterval
     *
     * @return int
     */
    public function getReplacementInterval(): ?int
    {
        return $this->replacementInterval;
    }

    /**
     * function setReplacementInterval
     *
     * @param int $interval
     *
     * @return self
     */
    public function setReplacementInterval(?int $interval): self
    {
        $this->replacementInterval = $interval;
        return $this;
    }

    /**
     * function getNextReplacement
     *
     * @return int
     */
    public function getNextReplacement(): ?int
    {
        return $this->nextReplacement;
    }

    /**
     * function setNextReplacement
     *
     * @param int $distance
     *
     * @return self
     */
    public function setNextReplacement(?int $distance): self
    {
        $this->nextReplacement = $distance;
        return $this;
    }

    /**
     * function getReplacementHistory
     *
     * Get replacement history (simplified - returns array with current record)
     *
     * @return array
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

    /**
     * function getLastReplacementDate
     *
     * @return \DateTimeInterface
     */
    public function getLastReplacementDate(): ?\DateTimeInterface
    {
        return $this->lastChanged;
    }

    /**
     * function getLastReplacementMileage
     *
     * @return int
     */
    public function getLastReplacementMileage(): ?int
    {
        return $this->mileageAtChange;
    }

    /**
     * function getReplacementIntervalMiles
     *
     * @return int
     */
    public function getReplacementIntervalMiles(): ?int
    {
        return $this->replacementInterval;
    }

    /**
     * function calculateNextReplacementMileage
     *
     * @return int
     */
    public function calculateNextReplacementMileage(): ?int
    {
        if ($this->mileageAtChange && $this->replacementInterval) {
            return $this->mileageAtChange + $this->replacementInterval;
        }
        return $this->nextReplacement;
    }

    /**
     * function calculateNextReplacement
     *
     * @return int
     */
    public function calculateNextReplacement(): ?int
    {
        return $this->calculateNextReplacementMileage();
    }

    /**
     * function isDueForReplacement
     *
     * @param int $currentMileage
     *
     * @return bool
     */
    public function isDueForReplacement(?int $currentMileage = null): bool
    {
        if (!$currentMileage || !$this->nextReplacement) {
            return false;
        }
        return $currentMileage >= $this->nextReplacement;
    }

    /**
     * function getMilesUntilReplacement
     *
     * @param int $currentMileage
     *
     * @return int
     */
    public function getMilesUntilReplacement(?int $currentMileage = null): ?int
    {
        if (!$currentMileage || !$this->nextReplacement) {
            return null;
        }
        return max(0, $this->nextReplacement - $currentMileage);
    }

    /**
     * function isOverdue
     *
     * @param int $currentMileage
     *
     * @return bool
     */
    public function isOverdue(?int $currentMileage = null): bool
    {
        if (!$currentMileage || !$this->nextReplacement) {
            return false;
        }
        return $currentMileage > $this->nextReplacement;
    }

    /**
     * function getOverdueMiles
     *
     * @param int $currentMileage
     *
     * @return int
     */
    public function getOverdueMiles(?int $currentMileage = null): int
    {
        if (!$currentMileage || !$this->nextReplacement) {
            return 0;
        }
        return max(0, $currentMileage - $this->nextReplacement);
    }

    /**
     * function calculateAnnualCost
     *
     * @param int $annualMileage
     *
     * @return float
     */
    public function calculateAnnualCost(?int $annualMileage = null): ?float
    {
        if (!$this->cost || !$this->replacementInterval) {
            return null;
        }
        // Use provided annual distance or default to 19,312 km (12,000 miles)
        $distance = $annualMileage ?? 19312;
        $replacementsPerYear = $distance / $this->replacementInterval;
        return (float)$this->cost * $replacementsPerYear;
    }

    /**
     * function getSupplier
     *
     * @return string
     */
    public function getSupplier(): ?string
    {
        return $this->supplier;
    }

    /**
     * function setSupplier
     *
     * @param string $supplier
     *
     * @return self
     */
    public function setSupplier(?string $supplier): self
    {
        $this->supplier = $supplier;
        return $this;
    }

    /**
     * function isIncludedInServiceCost
     *
     * @return bool
     */
    public function isIncludedInServiceCost(): bool
    {
        return $this->includedInServiceCost;
    }

    /**
     * function setIncludedInServiceCost
     *
     * @param bool $includedInServiceCost
     *
     * @return self
     */
    public function setIncludedInServiceCost(bool $includedInServiceCost): self
    {
        $this->includedInServiceCost = $includedInServiceCost;
        return $this;
    }

    // Alias methods for backward compatibility

    /**
     * function getNextReplacementMileage
     *
     * @return int
     */
    public function getNextReplacementMileage(): ?int
    {
        return $this->nextReplacement;
    }

    /**
     * function setNextReplacementMileage
     *
     * @param int $distance
     *
     * @return self
     */
    public function setNextReplacementMileage(?int $distance): self
    {
        return $this->setNextReplacement($distance);
    }

    /**
     * function setReplacementIntervalMiles
     *
     * @param int $interval
     *
     * @return self
     */
    public function setReplacementIntervalMiles(?int $interval): self
    {
        return $this->setReplacementInterval($interval);
    }
}
