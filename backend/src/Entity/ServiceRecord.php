<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Attachment;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Entity\ServiceItem;

#[ORM\Entity]
#[ORM\Table(name: 'service_records')]
class ServiceRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Vehicle::class, inversedBy: 'serviceRecords')]
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

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $consumablesCost = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $mileage = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $serviceProvider = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $workPerformed = null;

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

    #[ORM\ManyToOne(targetEntity: Attachment::class, cascade: ['remove'])]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Attachment $receiptAttachment = null;

    #[ORM\ManyToOne(targetEntity: \App\Entity\MotRecord::class)]
    private ?\App\Entity\MotRecord $motRecord = null;

    /**
     * @var Collection<int, ServiceItem>
     */
    #[ORM\OneToMany(mappedBy: 'serviceRecord', targetEntity: ServiceItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->items = new ArrayCollection();
    }

    /**
     * @return ServiceItem[]
     */
    public function getItems(): array
    {
        return $this->items->toArray();
    }

    public function addItem(ServiceItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setServiceRecord($this);
        }
        return $this;
    }

    public function removeItem(ServiceItem $item): self
    {
        if ($this->items->contains($item)) {
            $this->items->removeElement($item);
            $item->setServiceRecord(null);
        }
        return $this;
    }

    public function sumItemsByType(string $type): string
    {
        $total = '0.00';
        foreach ($this->items as $it) {
            if ($it->getType() === $type) {
                $amount = $it->getTotal();
                $total = bcadd($total, $amount, 2);
            }
        }
        return $total;
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

    public function getConsumablesCost(): ?string
    {
        return $this->consumablesCost;
    }

    public function setConsumablesCost($consumablesCost): self
    {
        if ($consumablesCost !== null) {
            $this->consumablesCost = (string) $consumablesCost;
        } else {
            $this->consumablesCost = null;
        }
        return $this;
    }

    public function setLaborCost($laborCost): self
    {
        if ($laborCost !== null) {
            $this->laborCost = (string) $laborCost;
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
        $labor = $this->laborCost ?? '0.00';
        $parts = $this->partsCost ?? '0.00';
        $consumables = $this->consumablesCost ?? $this->sumItemsByType('consumable');
        $additional = $this->additionalCosts ?? '0.00';
        $baseTotal = bcadd(bcadd($labor, $parts, 2), $consumables, 2);
        return bcadd($baseTotal, $additional, 2);
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

    public function getMotRecord(): ?\App\Entity\MotRecord
    {
        return $this->motRecord;
    }

    public function setMotRecord(?\App\Entity\MotRecord $motRecord): self
    {
        $this->motRecord = $motRecord;
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
