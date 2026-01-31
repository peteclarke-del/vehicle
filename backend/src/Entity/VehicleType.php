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
}
