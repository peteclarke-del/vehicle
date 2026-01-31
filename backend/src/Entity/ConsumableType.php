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

    public function __toString(): string
    {
        return $this->name ?? '';
    }

    public function getConsumableCount(): int
    {
        return $this->consumables->count();
    }
}
