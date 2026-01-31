<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'vehicle_makes')]
class VehicleMake
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name;

    #[ORM\ManyToOne(targetEntity: VehicleType::class)]
    #[ORM\JoinColumn(nullable: false)]
    private VehicleType $vehicleType;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\OneToMany(mappedBy: 'make', targetEntity: VehicleModel::class)]
    private Collection $models;

    public function __construct()
    {
        $this->models = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getVehicleType(): VehicleType
    {
        return $this->vehicleType;
    }

    public function setVehicleType(VehicleType $vehicleType): self
    {
        $this->vehicleType = $vehicleType;
        return $this;
    }

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getModels(): Collection
    {
        return $this->models;
    }

    public function addModel(VehicleModel $model): self
    {
        if (!$this->models->contains($model)) {
            $this->models[] = $model;
            $model->setMake($this);
        }
        return $this;
    }

    public function removeModel(VehicleModel $model): self
    {
        $this->models->removeElement($model);
        return $this;
    }

    /**
     * Get model by name
     */
    public function getModelByName(string $name): ?VehicleModel
    {
        foreach ($this->models as $model) {
            if ($model->getName() === $name) {
                return $model;
            }
        }
        return null;
    }

    /**
     * String representation
     */
    public function __toString(): string
    {
        return $this->name;
    }

    public function getModelCount(): int
    {
        return $this->models->count();
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }
}
