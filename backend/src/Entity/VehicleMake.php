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

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $logoUrl = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $countryOfOrigin = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $foundedYear = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $headquarters = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $popularity = 0;

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

    public function getLogoUrl(): ?string
    {
        return $this->logoUrl;
    }

    public function setLogoUrl(?string $logoUrl): self
    {
        $this->logoUrl = $logoUrl;
        return $this;
    }

    public function getCountryOfOrigin(): ?string
    {
        return $this->countryOfOrigin;
    }

    public function setCountryOfOrigin(?string $countryOfOrigin): self
    {
        $this->countryOfOrigin = $countryOfOrigin;
        return $this;
    }

    public function getFoundedYear(): ?int
    {
        return $this->foundedYear;
    }

    public function setFoundedYear(?int $foundedYear): self
    {
        $this->foundedYear = $foundedYear;
        return $this;
    }

    public function getHeadquarters(): ?string
    {
        return $this->headquarters;
    }

    public function setHeadquarters(?string $headquarters): self
    {
        $this->headquarters = $headquarters;
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

    public function getPopularity(): int
    {
        return $this->popularity;
    }

    public function setPopularity(int $popularity): self
    {
        $this->popularity = $popularity;
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
