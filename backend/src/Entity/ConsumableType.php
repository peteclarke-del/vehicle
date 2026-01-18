<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'consumable_types')]
class ConsumableType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: VehicleType::class, inversedBy: 'consumableTypes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?VehicleType $vehicleType = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $unit = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $defaultIntervalMiles = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $defaultIntervalMonths = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $typicalCost = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $iconName = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isCommon = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $requiresSpecialization = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $manufacturerRecommendation = null;

    #[ORM\OneToMany(mappedBy: 'consumableType', targetEntity: Consumable::class)]
    private Collection $consumables;

    public function __construct()
    {
        $this->consumables = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(?string $unit): self
    {
        $this->unit = $unit;
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

    public function getConsumables(): Collection
    {
        return $this->consumables;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getDefaultIntervalMiles(): ?int
    {
        return $this->defaultIntervalMiles;
    }

    public function setDefaultIntervalMiles(?int $defaultIntervalMiles): self
    {
        $this->defaultIntervalMiles = $defaultIntervalMiles;
        return $this;
    }

    public function getDefaultIntervalMonths(): ?int
    {
        return $this->defaultIntervalMonths;
    }

    public function setDefaultIntervalMonths(?int $defaultIntervalMonths): self
    {
        $this->defaultIntervalMonths = $defaultIntervalMonths;
        return $this;
    }

    public function getTypicalCost(): ?string
    {
        return $this->typicalCost;
    }

    public function setTypicalCost($typicalCost): self
    {
        if ($typicalCost !== null) {
            $this->typicalCost = (string) $typicalCost;
        } else {
            $this->typicalCost = null;
        }
        return $this;
    }

    public function getIconName(): ?string
    {
        return $this->iconName;
    }

    public function setIconName(?string $iconName): self
    {
        $this->iconName = $iconName;
        return $this;
    }

    public function getIsCommon(): bool
    {
        return $this->isCommon;
    }

    public function setIsCommon(bool $isCommon): self
    {
        $this->isCommon = $isCommon;
        return $this;
    }

    public function getRequiresSpecialization(): bool
    {
        return $this->requiresSpecialization;
    }

    public function setRequiresSpecialization(bool $requiresSpecialization): self
    {
        $this->requiresSpecialization = $requiresSpecialization;
        return $this;
    }

    public function getManufacturerRecommendation(): ?string
    {
        return $this->manufacturerRecommendation;
    }

    public function setManufacturerRecommendation(?string $manufacturerRecommendation): self
    {
        $this->manufacturerRecommendation = $manufacturerRecommendation;
        return $this;
    }

    public function addConsumable(Consumable $consumable): self
    {
        if (!$this->consumables->contains($consumable)) {
            $this->consumables[] = $consumable;
            $consumable->setConsumableType($this);
        }
        return $this;
    }

    public function removeConsumable(Consumable $consumable): self
    {
        $this->consumables->removeElement($consumable);
        return $this;
    }

    public function isCommon(): bool
    {
        return $this->isCommon;
    }

    public function requiresSpecialization(): bool
    {
        return $this->requiresSpecialization;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }

    public function getConsumableCount(): int
    {
        return $this->consumables->count();
    }
}
