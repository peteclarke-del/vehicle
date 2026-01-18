<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'vehicle_types')]
class VehicleType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $typicalSeatingCapacity = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $typicalDoors = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $iconName = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isPopular = false;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $avgInsuranceGroup = null;

    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    private ?string $fuelEfficiencyRating = null;

    #[ORM\OneToMany(mappedBy: 'vehicleType', targetEntity: Vehicle::class)]
    private Collection $vehicles;

    #[ORM\OneToMany(mappedBy: 'vehicleType', targetEntity: ConsumableType::class)]
    private Collection $consumableTypes;

    public function __construct()
    {
        $this->vehicles = new ArrayCollection();
        $this->consumableTypes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getVehicles(): Collection
    {
        return $this->vehicles;
    }

    public function getConsumableTypes(): Collection
    {
        return $this->consumableTypes;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getTypicalSeatingCapacity(): ?int
    {
        return $this->typicalSeatingCapacity;
    }

    public function setTypicalSeatingCapacity(?int $typicalSeatingCapacity): self
    {
        $this->typicalSeatingCapacity = $typicalSeatingCapacity;
        return $this;
    }

    public function getTypicalDoors(): ?int
    {
        return $this->typicalDoors;
    }

    public function setTypicalDoors(?int $typicalDoors): self
    {
        $this->typicalDoors = $typicalDoors;
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

    public function getIsPopular(): bool
    {
        return $this->isPopular;
    }

    public function setIsPopular(bool $isPopular): self
    {
        $this->isPopular = $isPopular;
        return $this;
    }

    public function getAvgInsuranceGroup(): ?int
    {
        return $this->avgInsuranceGroup;
    }

    public function setAvgInsuranceGroup(?int $avgInsuranceGroup): self
    {
        $this->avgInsuranceGroup = $avgInsuranceGroup;
        return $this;
    }

    public function getFuelEfficiencyRating(): ?string
    {
        return $this->fuelEfficiencyRating;
    }

    public function setFuelEfficiencyRating($fuelEfficiencyRating): self
    {
        if ($fuelEfficiencyRating !== null) {
            $this->fuelEfficiencyRating = (string) $fuelEfficiencyRating;
        } else {
            $this->fuelEfficiencyRating = null;
        }
        return $this;
    }

    public function addVehicle(Vehicle $vehicle): self
    {
        if (!$this->vehicles->contains($vehicle)) {
            $this->vehicles[] = $vehicle;
            $vehicle->setVehicleType($this);
        }
        return $this;
    }

    public function removeVehicle(Vehicle $vehicle): self
    {
        $this->vehicles->removeElement($vehicle);
        return $this;
    }

    /**
     * Get vehicle count
     */
    public function getVehicleCount(): int
    {
        return $this->vehicles->count();
    }

    /**
     * String representation
     */
    public function __toString(): string
    {
        return $this->name ?? '';
    }

    public function isPopular(): bool
    {
        return $this->isPopular;
    }
}
