<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'insurance_policies')]
class InsurancePolicy
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $provider = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $policyNumber = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $annualCost = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $ncdYears = null;

    // NCD percentage removed — stored as years only

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $expiryDate = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $coverageType = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $excess = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $mileageLimit = null;

    /**
     * Owner/holder id — now stored as the User id of the logged-in user.
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $holderId = null;

    public function getHolderId(): ?int
    {
        return $this->holderId;
    }

    public function setHolderId(?int $holderId): self
    {
        $this->holderId = $holderId;
        return $this;
    }

    #[ORM\ManyToMany(targetEntity: Vehicle::class, inversedBy: 'insurancePolicies')]
    #[ORM\JoinTable(name: 'insurance_policy_vehicles')]
    private Collection $vehicles;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $autoRenewal = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->vehicles = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getAnnualCost(): ?string
    {
        return $this->annualCost;
    }

    public function setAnnualCost($annualCost): self
    {
        $this->annualCost = $annualCost !== null ? (string)$annualCost : null;
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

    // ncdPercentage removed — use ncdYears only

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getExpiryDate(): ?\DateTimeInterface
    {
        return $this->expiryDate;
    }

    public function getCoverageType(): ?string
    {
        return $this->coverageType;
    }

    public function setCoverageType(?string $coverageType): self
    {
        $this->coverageType = $coverageType;
        return $this;
    }

    public function getExcess(): ?string
    {
        return $this->excess;
    }

    public function setExcess($excess): self
    {
        $this->excess = $excess !== null ? (string)$excess : null;
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

    public function setExpiryDate(?\DateTimeInterface $expiryDate): self
    {
        $this->expiryDate = $expiryDate;
        return $this;
    }

    /** @return Collection<int, Vehicle> */
    public function getVehicles(): Collection
    {
        return $this->vehicles;
    }

    public function addVehicle(Vehicle $vehicle): self
    {
        if (!$this->vehicles->contains($vehicle)) {
            $this->vehicles->add($vehicle);
            $vehicle->addInsurancePolicy($this);
        }
        return $this;
    }

    public function removeVehicle(Vehicle $vehicle): self
    {
        if ($this->vehicles->contains($vehicle)) {
            $this->vehicles->removeElement($vehicle);
            $vehicle->removeInsurancePolicy($this);
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt ?? new \DateTime();
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
}
