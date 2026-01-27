<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Attachment;

#[ORM\Entity]
#[ORM\Table(name: 'mot_records')]
class MotRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Vehicle::class, inversedBy: 'motRecords')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Vehicle $vehicle = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $testDate = null;

    #[ORM\Column(type: 'string', length: 20)]
    private ?string $result = null; // Pass, Fail, Advisory

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $testCost = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $repairCost = '0.00';

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $mileage = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $testCenter = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $expiryDate = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $motTestNumber = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $testerName = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isRetest = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $advisories = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $failures = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $repairDetails = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: Attachment::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Attachment $receiptAttachment = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

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

    public function getTestDate(): ?\DateTimeInterface
    {
        return $this->testDate;
    }

    public function setTestDate(\DateTimeInterface $testDate): self
    {
        $this->testDate = $testDate;
        return $this;
    }

    public function getResult(): ?string
    {
        return $this->result;
    }

    public function setResult(string $result): self
    {
        $this->result = $result;
        return $this;
    }

    public function getTestCost(): ?string
    {
        return $this->testCost;
    }

    public function setTestCost(string $testCost): self
    {
        $this->testCost = $testCost;
        return $this;
    }

    public function getRepairCost(): string
    {
        return $this->repairCost;
    }

    public function setRepairCost(string $repairCost): self
    {
        $this->repairCost = $repairCost;
        return $this;
    }

    public function getTotalCost(): string
    {
        return bcadd($this->testCost, $this->repairCost, 2);
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

    public function getTestCenter(): ?string
    {
        return $this->testCenter;
    }

    public function setTestCenter(?string $testCenter): self
    {
        $this->testCenter = $testCenter;
        return $this;
    }

    public function getAdvisories(): ?string
    {
        return $this->advisories;
    }

    public function setAdvisories(?string $advisories): self
    {
        $this->advisories = $advisories;
        return $this;
    }

    public function getFailures(): ?string
    {
        return $this->failures;
    }

    public function setFailures(?string $failures): self
    {
        $this->failures = $failures;
        return $this;
    }

    public function getRepairDetails(): ?string
    {
        return $this->repairDetails;
    }

    public function setRepairDetails(?string $repairDetails): self
    {
        $this->repairDetails = $repairDetails;
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



    public function getReceiptAttachment(): ?Attachment
    {
        return $this->receiptAttachment;
    }

    public function setReceiptAttachment(?Attachment $receiptAttachment): self
    {
        $this->receiptAttachment = $receiptAttachment;
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

    /**
     * Alias for setResult()
     */
    public function setTestResult(string $result): self
    {
        return $this->setResult($result);
    }

    public function getExpiryDate(): ?\DateTimeInterface
    {
        return $this->expiryDate;
    }

    public function setExpiryDate(?\DateTimeInterface $expiryDate): self
    {
        $this->expiryDate = $expiryDate;
        return $this;
    }

    /**
     * Alias for setTestCost() - accepts float or string
     * @param string|float $cost
     */
    public function setCost($cost): self
    {
        return $this->setTestCost((string) $cost);
    }

    public function getCost(): ?string
    {
        return $this->testCost;
    }

    public function getMotTestNumber(): ?string
    {
        return $this->motTestNumber;
    }

    public function setMotTestNumber(?string $motTestNumber): self
    {
        $this->motTestNumber = $motTestNumber;
        return $this;
    }

    public function getTesterName(): ?string
    {
        return $this->testerName;
    }

    public function setTesterName(?string $testerName): self
    {
        $this->testerName = $testerName;
        return $this;
    }

    public function getIsRetest(): bool
    {
        return $this->isRetest;
    }

    public function setIsRetest(bool $isRetest): self
    {
        $this->isRetest = $isRetest;
        return $this;
    }

    /**
     * Set advisory items (expects JSON array or string)
     */
    public function setAdvisoryItems($items): self
    {
        if (is_array($items)) {
            $this->advisories = json_encode($items);
        } else {
            $this->advisories = $items;
        }
        return $this;
    }

    /**
     * Set failure items (expects JSON array or string)
     */
    public function setFailureItems($items): self
    {
        if (is_array($items)) {
            $this->failures = json_encode($items);
        } else {
            $this->failures = $items;
        }
        return $this;
    }

    /**
     * Check if there are advisory items
     */
    public function hasAdvisoryItems(): bool
    {
        return !empty($this->advisories);
    }

    /**
     * Check if there are failure items
     */
    public function hasFailureItems(): bool
    {
        return !empty($this->failures);
    }

    public function getAdvisoryItems(): ?array
    {
        if (empty($this->advisories)) {
            return null;
        }
        $decoded = json_decode($this->advisories, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function getFailureItems(): ?array
    {
        if (empty($this->failures)) {
            return null;
        }
        $decoded = json_decode($this->failures, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function getTestResult(): ?string
    {
        return $this->result;
    }

    public function isPassed(): bool
    {
        return strtolower($this->result ?? '') === 'pass';
    }

    public function isFailed(): bool
    {
        return strtolower($this->result ?? '') === 'fail';
    }

    public function isValid(): bool
    {
        return $this->isPassed();
    }

    public function isRetest(): bool
    {
        return $this->isRetest;
    }

    public function isExpired(): bool
    {
        if (!$this->expiryDate) {
            return false;
        }
        $now = new \DateTime();
        return $this->expiryDate < $now;
    }

    public function isDueSoon(int $days = 30): bool
    {
        if (!$this->expiryDate) {
            return false;
        }
        $now = new \DateTime();
        $threshold = (clone $now)->modify("+{$days} days");
        return $this->expiryDate <= $threshold && $this->expiryDate >= $now;
    }

    public function getDaysUntilExpiry(): ?int
    {
        if (!$this->expiryDate) {
            return null;
        }
        $now = new \DateTime();
        $diff = $now->diff($this->expiryDate);
        return $diff->invert ? -$diff->days : $diff->days;
    }
}
