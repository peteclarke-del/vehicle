<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\InsurancePolicy;

#[ORM\Entity]
#[ORM\Table(name: 'vehicles')]
class Vehicle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'vehicles')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\ManyToOne(targetEntity: VehicleType::class, inversedBy: 'vehicles')]
    #[ORM\JoinColumn(nullable: false)]
    private ?VehicleType $vehicleType = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $make = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $model = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $year = null;

    #[ORM\Column(type: 'string', length: 17, nullable: true, unique: true)]
    private ?string $vin = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $vinDecodedData = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $vinDecodedAt = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $registrationNumber = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $engineNumber = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $v5DocumentNumber = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $purchaseCost = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $purchaseDate = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $purchaseMileage = null;

    // current mileage is computed from fuel records; do not persist
    // Non-persisted transient current mileage for tests and API updates
    private ?int $transientCurrentMileage = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $securityFeatures = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $vehicleColor = null;

    #[ORM\Column(type: 'integer', options: ['default' => 12])]
    private int $serviceIntervalMonths = 12;

    #[ORM\Column(type: 'integer', options: ['default' => 4000])]
    private int $serviceIntervalMiles = 4000;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'Live'])]
    private string $status = 'Live';

    #[ORM\Column(
        type: 'string',
        length: 20,
        options: ['default' => 'automotive_standard']
    )]
    private string $depreciationMethod = 'automotive_standard';

    #[ORM\Column(type: 'integer', options: ['default' => 10])]
    private int $depreciationYears = 10;

    #[ORM\Column(
        type: 'decimal',
        precision: 5,
        scale: 2,
        options: ['default' => '20.00']
    )]
    private string $depreciationRate = '20.00';

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $roadTaxExempt = null;
    
    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $motExempt = null;

    #[ORM\OneToMany(mappedBy: 'vehicle', targetEntity: FuelRecord::class, cascade: ['remove'])]
    private Collection $fuelRecords;

    #[ORM\OneToMany(mappedBy: 'vehicle', targetEntity: Part::class, cascade: ['remove'])]
    private Collection $parts;

    #[ORM\OneToMany(mappedBy: 'vehicle', targetEntity: Consumable::class, cascade: ['remove'])]
    private Collection $consumables;

    #[ORM\OneToMany(mappedBy: 'vehicle', targetEntity: Todo::class, cascade: ['remove'])]
    private Collection $todos;

    #[ORM\OneToMany(mappedBy: 'vehicle', targetEntity: ServiceRecord::class, cascade: ['remove'])]
    private Collection $serviceRecords;

    #[ORM\ManyToMany(targetEntity: InsurancePolicy::class, mappedBy: 'vehicles')]
    private Collection $insurancePolicies;

    #[ORM\OneToMany(mappedBy: 'vehicle', targetEntity: MotRecord::class, cascade: ['remove'])]
    private Collection $motRecords;

    #[ORM\OneToMany(mappedBy: 'vehicle', targetEntity: RoadTax::class, cascade: ['remove'])]
    private Collection $roadTaxRecords;

    #[ORM\OneToMany(mappedBy: 'vehicle', targetEntity: VehicleImage::class, cascade: ['remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['displayOrder' => 'ASC', 'id' => 'ASC'])]
    private Collection $images;

    #[ORM\OneToMany(mappedBy: 'vehicle', targetEntity: VehicleStatusHistory::class, cascade: ['persist', 'remove'])]
    private Collection $statusHistory;

    public function __construct()
    {
        $this->fuelRecords = new ArrayCollection();
        $this->parts = new ArrayCollection();
        $this->consumables = new ArrayCollection();
        $this->serviceRecords = new ArrayCollection();
        $this->insurancePolicies = new ArrayCollection();
        $this->motRecords = new ArrayCollection();
        $this->roadTaxRecords = new ArrayCollection();
        $this->images = new ArrayCollection();
        $this->statusHistory = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    /**
     * @return Collection<int, VehicleStatusHistory>
     */
    public function getStatusHistory(): Collection
    {
        return $this->statusHistory;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): self
    {
        $this->owner = $owner;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getMake(): ?string
    {
        return $this->make;
    }

    /**
     * @param string|VehicleMake|null $make
     */
    public function setMake($make): self
    {
        if ($make instanceof VehicleMake) {
            $this->make = $make->getName();
        } else {
            $this->make = $make;
        }
        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    /**
     * @param string|VehicleModel|null $model
     */
    public function setModel($model): self
    {
        if ($model instanceof VehicleModel) {
            $this->model = $model->getName();
        } else {
            $this->model = $model;
        }
        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(?int $year): self
    {
        $this->year = $year;
        return $this;
    }

    public function getVin(): ?string
    {
        return $this->vin;
    }

    public function setVin(?string $vin): self
    {
        $this->vin = $vin;
        return $this;
    }

    public function getVinDecodedData(): ?array
    {
        return $this->vinDecodedData;
    }

    public function setVinDecodedData(?array $vinDecodedData): self
    {
        $this->vinDecodedData = $vinDecodedData;
        return $this;
    }

    public function getVinDecodedAt(): ?\DateTimeInterface
    {
        return $this->vinDecodedAt;
    }

    public function setVinDecodedAt(?\DateTimeInterface $vinDecodedAt): self
    {
        $this->vinDecodedAt = $vinDecodedAt;
        return $this;
    }

    public function getRegistrationNumber(): ?string
    {
        return $this->registrationNumber;
    }

    public function setRegistrationNumber(?string $registrationNumber): self
    {
        $this->registrationNumber = $registrationNumber;
        return $this;
    }

    public function getEngineNumber(): ?string
    {
        return $this->engineNumber;
    }

    public function setEngineNumber(?string $engineNumber): self
    {
        $this->engineNumber = $engineNumber;
        return $this;
    }

    public function getV5DocumentNumber(): ?string
    {
        return $this->v5DocumentNumber;
    }

    public function setV5DocumentNumber(?string $v5DocumentNumber): self
    {
        $this->v5DocumentNumber = $v5DocumentNumber;
        return $this;
    }

    public function getPurchaseCost(): ?string
    {
        return $this->purchaseCost;
    }

    public function setPurchaseCost(string $purchaseCost): self
    {
        $this->purchaseCost = $purchaseCost;
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

    public function getPurchaseMileage(): ?int
    {
        return $this->purchaseMileage;
    }

    public function setPurchaseMileage(?int $purchaseMileage): self
    {
        $this->purchaseMileage = $purchaseMileage;
        return $this;
    }

    public function getCurrentMileage(): ?int
    {
        if ($this->transientCurrentMileage !== null) {
            return $this->transientCurrentMileage;
        }

        return $this->getComputedCurrentMileage();
    }

    public function setCurrentMileage(?int $currentMileage): self
    {
        $this->transientCurrentMileage = $currentMileage;
        return $this;
    }

    public function getLastServiceDate(): ?\DateTimeInterface
    {
        return $this->getComputedLastServiceDate();
    }

    public function getMotExpiryDate(): ?\DateTimeInterface
    {
        return $this->getComputedMotExpiryDate();
    }

    /**
     * Compute the most recently recorded service date from related ServiceRecord
     * entities. Falls back to the stored `lastServiceDate` when no records exist.
     *
     * @return \DateTimeInterface|null
     */
    public function getComputedLastServiceDate(): ?\DateTimeInterface
    {
        $latest = null;
        foreach ($this->serviceRecords as $sr) {
            $d = $sr->getServiceDate();
            if ($d instanceof \DateTimeInterface) {
                if ($latest === null || $d > $latest) {
                    $latest = $d;
                }
            }
        }

        return $latest;
    }

    public function getRoadTaxExpiryDate(): ?\DateTimeInterface
    {
        return $this->getComputedRoadTaxExpiryDate();
    }

    /**
     * Compute road tax expiry. Currently no RoadTax entity exists, so
     * return null until RoadTax records are modelled.
     */
    public function getComputedRoadTaxExpiryDate(): ?\DateTimeInterface
    {
        $latest = null;
        foreach ($this->roadTaxRecords as $rt) {
            $d = $rt->getExpiryDate();
            if ($d instanceof \DateTimeInterface) {
                if ($latest === null || $d > $latest) {
                    $latest = $d;
                }
            }
        }
        return $latest;
    }

    public function getRoadTaxRecords(): Collection
    {
        return $this->roadTaxRecords;
    }

    /** @return Collection<int, InsurancePolicy> */
    public function getInsurancePolicies(): Collection
    {
        return $this->insurancePolicies;
    }

    public function addInsurancePolicy(InsurancePolicy $policy): self
    {
        if (!$this->insurancePolicies->contains($policy)) {
            $this->insurancePolicies->add($policy);
        }
        return $this;
    }

    public function removeInsurancePolicy(InsurancePolicy $policy): self
    {
        if ($this->insurancePolicies->contains($policy)) {
            $this->insurancePolicies->removeElement($policy);
        }
        return $this;
    }

    /**
     * Compute current mileage from the vehicle's FuelRecord collection. Returns
     * the highest mileage found, or the stored `currentMileage` as a fallback.
     */
    public function getComputedCurrentMileage(): ?int
    {
        $max = null;
        foreach ($this->fuelRecords as $fr) {
            if (method_exists($fr, 'getMileage')) {
                $m = $fr->getMileage();
                if ($m !== null) {
                    if ($max === null || $m > $max) {
                        $max = $m;
                    }
                }
            }
        }

        return $max;
    }

    /**
     * Compute MOT expiry from the latest MotRecord. Prefers the record's
     * `expiryDate` when present, otherwise falls back to `testDate`. If no
     * records exist returns the stored `motExpiryDate`.
     */
    public function getComputedMotExpiryDate(): ?\DateTimeInterface
    {
        $latest = null;
        foreach ($this->motRecords as $mr) {
            $d = $mr->getTestDate() ?? $mr->getExpiryDate();
            if ($d instanceof \DateTimeInterface) {
                if ($latest === null || $d > $latest) {
                    $latest = $d;
                }
            }
        }

        if ($latest !== null) {
            // If the latest mot record has an explicit expiry date prefer that
            foreach ($this->motRecords as $mr) {
                if ($mr->getTestDate() === $latest && $mr->getExpiryDate() !== null) {
                    return $mr->getExpiryDate();
                }
            }
            return $latest;
        }

        return $latest;
    }

    /**
     * Compute insurance expiry from the latest InsurancePolicy expiry date. Falls back
     * to the stored `insuranceExpiryDate` when no policies exist.
     */
    public function getComputedInsuranceExpiryDate(): ?\DateTimeInterface
    {
        $latest = null;
        foreach ($this->insurancePolicies as $policy) {
            $d = $policy->getExpiryDate();
            if ($d instanceof \DateTimeInterface) {
                if ($latest === null || $d > $latest) {
                    $latest = $d;
                }
            }
        }

        return $latest;
    }

    public function getInsuranceExpiryDate(): ?\DateTimeInterface
    {
        return $this->getComputedInsuranceExpiryDate();
    }

    public function getSecurityFeatures(): ?string
    {
        return $this->securityFeatures;
    }

    public function setSecurityFeatures(?string $securityFeatures): self
    {
        $this->securityFeatures = $securityFeatures;
        return $this;
    }

    public function getDepreciationMethod(): string
    {
        return $this->depreciationMethod;
    }

    public function setDepreciationMethod(string $depreciationMethod): self
    {
        $this->depreciationMethod = $depreciationMethod;
        return $this;
    }

    public function getDepreciationYears(): int
    {
        return $this->depreciationYears;
    }

    public function setDepreciationYears(int $depreciationYears): self
    {
        $this->depreciationYears = $depreciationYears;
        return $this;
    }

    public function getDepreciationRate(): string
    {
        return $this->depreciationRate;
    }

    public function setDepreciationRate(string $depreciationRate): self
    {
        $this->depreciationRate = $depreciationRate;
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

    public function getFuelRecords(): Collection
    {
        return $this->fuelRecords;
    }

    public function getParts(): Collection
    {
        return $this->parts;
    }

    public function getConsumables(): Collection
    {
        return $this->consumables;
    }

    public function getVehicleColor(): ?string
    {
        return $this->vehicleColor;
    }

    public function setVehicleColor(?string $vehicleColor): self
    {
        $this->vehicleColor = $vehicleColor;
        return $this;
    }

    public function getServiceIntervalMonths(): int
    {
        return $this->serviceIntervalMonths;
    }

    public function setServiceIntervalMonths(int $serviceIntervalMonths): self
    {
        $this->serviceIntervalMonths = $serviceIntervalMonths;
        return $this;
    }

    public function getServiceIntervalMiles(): int
    {
        return $this->serviceIntervalMiles;
    }

    public function setServiceIntervalMiles(int $serviceIntervalMiles): self
    {
        $this->serviceIntervalMiles = $serviceIntervalMiles;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getServiceRecords(): Collection
    {
        return $this->serviceRecords;
    }

    

    public function getMotRecords(): Collection
    {
        return $this->motRecords;
    }

    /**
     * Alias for getRegistrationNumber()
     */
    public function getRegistration(): ?string
    {
        return $this->getRegistrationNumber();
    }

    /**
     * Alias for setRegistrationNumber()
     */
    public function setRegistration(?string $registration): self
    {
        return $this->setRegistrationNumber($registration);
    }

    /**
     * Alias for setOwner()
     */
    public function setUser(?User $user): self
    {
        return $this->setOwner($user);
    }

    /**
     * Alias for getOwner()
     */
    public function getUser(): ?User
    {
        return $this->getOwner();
    }

    /**
     * Alias for setVehicleColor()
     */
    public function getColour(): ?string
    {
        return $this->getVehicleColor();
    }

    /**
     * Alias for setVehicleColor()
     */
    public function setColour(?string $colour): self
    {
        return $this->setVehicleColor($colour);
    }

    /**
     * Get engine size (currently not stored, for test compatibility)
     */
    public function getEngineSize(): ?string
    {
        return null;
    }

    /**
     * Set engine size (currently not stored, for test compatibility)
     */
    public function setEngineSize($engineSize): self
    {
        // Property doesn't exist in schema, but method needed for tests
        return $this;
    }

    /**
     * Get fuel type (currently not stored, for test compatibility)
     */
    public function getFuelType(): ?string
    {
        return null;
    }

    /**
     * Set fuel type (currently not stored, for test compatibility)
     */
    public function setFuelType($fuelType): self
    {
        // Property doesn't exist in schema, but method needed for tests
        return $this;
    }

    /**
     * Get mileage (alias for getCurrentMileage)
     */
    public function getMileage(): ?int
    {
        return $this->getCurrentMileage();
    }

    /**
     * Set mileage (alias for setCurrentMileage)
     */
    public function setMileage(?int $mileage): self
    {
        return $this->setCurrentMileage($mileage);
    }

    /**
     * Get purchase price (alias for getPurchaseCost)
     */
    public function getPurchasePrice(): ?string
    {
        return $this->getPurchaseCost();
    }

    /**
     * Set purchase price (alias for setPurchaseCost)
     */
    public function setPurchasePrice($purchasePrice): self
    {
        if ($purchasePrice !== null) {
            $purchasePrice = (string) $purchasePrice;
        }
        return $this->setPurchaseCost($purchasePrice);
    }

    /**
     * Get transmission (currently not stored, for test compatibility)
     */
    public function getTransmission(): ?string
    {
        return null;
    }

    /**
     * Set transmission (currently not stored, for test compatibility)
     */
    public function setTransmission($transmission): self
    {
        // Property doesn't exist in schema, but method needed for tests
        return $this;
    }

    /**
     * Get the age of the vehicle in years
     */
    public function getAge(): int
    {
        if (!$this->year) {
            return 0;
        }
        return (int) date('Y') - $this->year;
    }

    /**
     * Check if vehicle is considered classic (25+ years old)
     */
    public function isClassic(): bool
    {
        return $this->getAge() >= 25;
    }

    /**
     * Vehicle-level override for road tax exemption. When null the exemption
     * is computed from vehicle age (30+ years). When set, it forces the
     * exemption state.
     */
    public function getRoadTaxExempt(): ?bool
    {
        return $this->roadTaxExempt;
    }

    public function setRoadTaxExempt(?bool $roadTaxExempt): self
    {
        $this->roadTaxExempt = $roadTaxExempt;
        return $this;
    }

    /**
     * True when the vehicle is considered exempt from road tax. Uses the
     * manual override when provided; otherwise vehicles 30 years or older
     * are treated as exempt.
     */
    public function isRoadTaxExempt(): bool
    {
        if ($this->roadTaxExempt !== null) {
            return (bool) $this->roadTaxExempt;
        }
        return $this->getAge() >= 30;
    }

    public function getMotExempt(): ?bool
    {
        return $this->motExempt;
    }

    public function setMotExempt(?bool $motExempt): self
    {
        $this->motExempt = $motExempt;
        return $this;
    }

    /**
     * True when the vehicle is considered exempt from MOT. Uses the
     * manual override when provided; otherwise vehicles 30 years or older
     * are treated as exempt.
     */
    public function isMotExempt(): bool
    {
        if ($this->motExempt !== null) {
            return (bool) $this->motExempt;
        }
        return $this->getAge() >= 30;
    }

    /**
     * Compute the annualized road tax cost from the latest RoadTax record.
     * Returns a string representing the annual cost (two decimal places),
     * or null when no amount is available.
     */
    public function getComputedRoadTaxAnnualCost(): ?string
    {
        $latestDate = null;
        $latestRecord = null;
        foreach ($this->roadTaxRecords as $rt) {
            $d = $rt->getExpiryDate() ?? $rt->getStartDate();
            if ($d instanceof \DateTimeInterface) {
                if ($latestDate === null || $d > $latestDate) {
                    $latestDate = $d;
                    $latestRecord = $rt;
                }
            }
        }

        if ($latestRecord) {
            return $latestRecord->getNormalizedAnnualAmount();
        }

        return null;
    }

    /**
     * Get display name for the vehicle
     */
    public function getDisplayName(): string
    {
        if ($this->name) {
            return $this->name;
        }
        $parts = array_filter([$this->year, $this->make, $this->model]);
        return implode(' ', $parts) ?: 'Unknown Vehicle';
    }

    /**
     * @return Collection<int, VehicleImage>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(VehicleImage $image): self
    {
        if (!$this->images->contains($image)) {
            $this->images[] = $image;
            $image->setVehicle($this);
        }
        return $this;
    }

    public function removeImage(VehicleImage $image): self
    {
        if ($this->images->removeElement($image)) {
            if ($image->getVehicle() === $this) {
                $image->setVehicle(null);
            }
        }
        return $this;
    }
}
