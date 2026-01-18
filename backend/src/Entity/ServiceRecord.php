<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'service_records')]
class ServiceRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Vehicle::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Vehicle $vehicle = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $serviceDate = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $serviceType = null; // Full Service, Interim Service, Oil Change, etc.

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $laborCost = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $partsCost = '0.00';

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $mileage = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $serviceProvider = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $workshop = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $workPerformed = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $additionalCosts = '0.00';

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $nextServiceDate = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $nextServiceMileage = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $receiptAttachmentId = null;

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

    public function getServiceDate(): ?\DateTimeInterface
    {
        return $this->serviceDate;
    }

    public function setServiceDate(\DateTimeInterface $serviceDate): self
    {
        $this->serviceDate = $serviceDate;
        return $this;
    }

    public function getServiceType(): ?string
    {
        return $this->serviceType;
    }

    public function setServiceType(string $serviceType): self
    {
        $this->serviceType = $serviceType;
        return $this;
    }

    public function getLaborCost(): ?string
    {
        return $this->laborCost;
    }

    public function getLabourCost(): ?string
    {
        return $this->laborCost;
    }

    /**
     * Get labor cost
     * @param string|float|int|null $laborCost
     * @return self
     */
    public function setLaborCost($laborCost): self
    {
        if ($laborCost !== null) {
            $this->laborCost = (string) $laborCost;
        } else {
            $this->laborCost = null;
        }
        return $this;
    }

    public function getPartsCost(): string
    {
        return $this->partsCost;
    }

    public function setPartsCost($partsCost): self
    {
        if ($partsCost !== null) {
            $this->partsCost = (string) $partsCost;
        }
        return $this;
    }

    public function getTotalCost(): string
    {
        return bcadd($this->laborCost, $this->partsCost, 2);
    }

    public function getMileage(): ?int
    {
        return $this->mileage;
    }

    public function setMileage(?int $mileage): self
    {
        $this->mileage = $mileage;
        return $this;
    }

    public function getServiceProvider(): ?string
    {
        return $this->serviceProvider;
    }

    public function setServiceProvider(?string $serviceProvider): self
    {
        $this->serviceProvider = $serviceProvider;
        return $this;
    }

    public function getWorkPerformed(): ?string
    {
        return $this->workPerformed;
    }

    public function setWorkPerformed(?string $workPerformed): self
    {
        $this->workPerformed = $workPerformed;
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
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

    public function getWorkshop(): ?string
    {
        return $this->workshop ?? $this->serviceProvider;
    }

    public function setWorkshop(?string $workshop): self
    {
        $this->workshop = $workshop;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description ?? $this->workPerformed;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Alias for setLaborCost() - British spelling - accepts float or string
     * @param string|float|int|null $labourCost
     */
    public function setLabourCost($labourCost): self
    {
        return $this->setLaborCost($labourCost);
    }

    public function getAdditionalCosts(): string
    {
        return $this->additionalCosts;
    }

    public function setAdditionalCosts($additionalCosts): self
    {
        if ($additionalCosts !== null) {
            $this->additionalCosts = (string) $additionalCosts;
        }
        return $this;
    }

    public function getNextServiceDate(): ?\DateTimeInterface
    {
        return $this->nextServiceDate;
    }

    public function setNextServiceDate(?\DateTimeInterface $nextServiceDate): self
    {
        $this->nextServiceDate = $nextServiceDate;
        return $this;
    }

    public function getNextServiceMileage(): ?int
    {
        return $this->nextServiceMileage;
    }

    public function setNextServiceMileage(?int $nextServiceMileage): self
    {
        $this->nextServiceMileage = $nextServiceMileage;
        return $this;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * Get attachments (returns empty collection for now)
     */
    public function getAttachments(): array
    {
        return [];
    }

    /**
     * Get parts used (returns empty collection for now)
     */
    public function getPartsUsed(): array
    {
        return [];
    }

    public function isOverdue(?\DateTimeInterface $currentDate = null): bool
    {
        if (!$this->nextServiceDate) {
            return false;
        }
        $date = $currentDate ?? new \DateTime();
        return $this->nextServiceDate < $date;
    }

    public function isDueSoon(?\DateTimeInterface $currentDate = null, int $days = 30): bool
    {
        if (!$this->nextServiceDate) {
            return false;
        }
        $date = $currentDate ?? new \DateTime();
        $threshold = (clone $date)->modify("+{$days} days");
        return $this->nextServiceDate <= $threshold && $this->nextServiceDate >= $date;
    }
}
