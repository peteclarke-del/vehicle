<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'insurance')]
class Insurance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Vehicle::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Vehicle $vehicle = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $provider = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $policyNumber = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $coverageType = null; // Comprehensive, Third Party, etc.

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $coverType = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $annualCost = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $excess = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $ncdYears = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $mileageLimit = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $autoRenewal = false;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $expiryDate = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

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

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    public function getPolicyNumber(): ?string
    {
        return $this->policyNumber;
    }

    public function setPolicyNumber(?string $policyNumber): self
    {
        $this->policyNumber = $policyNumber;
        return $this;
    }

    public function getCoverageType(): ?string
    {
        return $this->coverageType;
    }

    public function setCoverageType(string $coverageType): self
    {
        $this->coverageType = $coverageType;
        return $this;
    }

    public function getAnnualCost(): ?string
    {
        return $this->annualCost;
    }

    public function setAnnualCost($annualCost): self
    {
        if ($annualCost !== null) {
            $this->annualCost = (string) $annualCost;
        }
        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getExpiryDate(): ?\DateTimeInterface
    {
        return $this->expiryDate;
    }

    public function setExpiryDate(\DateTimeInterface $expiryDate): self
    {
        $this->expiryDate = $expiryDate;
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

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getCoverType(): ?string
    {
        return $this->coverType ?? $this->coverageType;
    }

    public function setCoverType(string $coverType): self
    {
        $this->coverType = $coverType;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate ?? $this->expiryDate;
    }

    public function setEndDate(\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getExcess(): ?string
    {
        return $this->excess;
    }

    public function setExcess($excess): self
    {
        if ($excess !== null) {
            $this->excess = (string) $excess;
        } else {
            $this->excess = null;
        }
        return $this;
    }

    public function getNcdYears(): ?int
    {
        return $this->ncdYears;
    }

    public function setNcdYears(?int $ncdYears): self
    {
        $this->ncdYears = $ncdYears;
        return $this;
    }

    public function getMileageLimit(): ?int
    {
        return $this->mileageLimit;
    }

    public function setMileageLimit(?int $mileageLimit): self
    {
        $this->mileageLimit = $mileageLimit;
        return $this;
    }

    public function getAutoRenewal(): bool
    {
        return $this->autoRenewal;
    }

    public function setAutoRenewal(bool $autoRenewal): self
    {
        $this->autoRenewal = $autoRenewal;
        return $this;
    }

    public function hasAutoRenewal(): bool
    {
        return $this->autoRenewal;
    }

    public function isActive(): bool
    {
        $now = new \DateTime();
        return $this->startDate <= $now && $this->expiryDate >= $now;
    }

    public function isExpired(): bool
    {
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

    public function getMonthlyCost(): ?float
    {
        if (!$this->annualCost) {
            return null;
        }
        return (float)$this->annualCost / 12;
    }

    public function getDailyRate(): ?float
    {
        if (!$this->annualCost) {
            return null;
        }
        return (float)$this->annualCost / 365;
    }
}
