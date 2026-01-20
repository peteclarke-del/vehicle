<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'vehicle_models')]
class VehicleModel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name;

    #[ORM\ManyToOne(targetEntity: VehicleMake::class, inversedBy: 'models')]
    #[ORM\JoinColumn(nullable: false)]
    private VehicleMake $make;

    #[ORM\ManyToOne(targetEntity: VehicleType::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?VehicleType $vehicleType = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $startYear = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $endYear = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $productionStartYear = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $productionEndYear = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $engineOptions = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $transmissionOptions = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $generationCount = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    private Collection $vehicles;

    public function __construct()
    {
        $this->vehicles = new ArrayCollection();
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

    public function getMake(): VehicleMake
    {
        return $this->make;
    }

    public function setMake(VehicleMake $make): self
    {
        $this->make = $make;
        return $this;
    }

    public function getStartYear(): ?int
    {
        return $this->startYear;
    }

    public function setStartYear(?int $startYear): self
    {
        $this->startYear = $startYear;
        return $this;
    }

    public function getEndYear(): ?int
    {
        return $this->endYear;
    }

    public function setEndYear(?int $endYear): self
    {
        $this->endYear = $endYear;
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

    public function getProductionStartYear(): ?int
    {
        return $this->productionStartYear ?? $this->startYear;
    }

    public function setProductionStartYear(?int $productionStartYear): self
    {
        $this->productionStartYear = $productionStartYear;
        return $this;
    }

    public function getProductionEndYear(): ?int
    {
        return $this->productionEndYear ?? $this->endYear;
    }

    public function setProductionEndYear(?int $productionEndYear): self
    {
        $this->productionEndYear = $productionEndYear;
        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): self
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    public function getEngineOptions(): ?array
    {
        return $this->engineOptions;
    }

    public function setEngineOptions(?array $engineOptions): self
    {
        $this->engineOptions = $engineOptions;
        return $this;
    }

    public function getTransmissionOptions(): ?array
    {
        return $this->transmissionOptions;
    }

    public function setTransmissionOptions(?array $transmissionOptions): self
    {
        $this->transmissionOptions = $transmissionOptions;
        return $this;
    }

    public function getGenerationCount(): ?int
    {
        return $this->generationCount;
    }

    public function setGenerationCount(?int $generationCount): self
    {
        $this->generationCount = $generationCount;
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

    public function getVehicles(): Collection
    {
        return $this->vehicles;
    }

    public function addVehicle(Vehicle $vehicle): self
    {
        if (!$this->vehicles->contains($vehicle)) {
            $this->vehicles[] = $vehicle;
        }
        return $this;
    }

    public function removeVehicle(Vehicle $vehicle): self
    {
        $this->vehicles->removeElement($vehicle);
        return $this;
    }

    /**
     * Check if model is still in production
     */
    public function isStillInProduction(): bool
    {
        $endYear = $this->productionEndYear ?? $this->endYear;
        return $endYear === null;
    }

    /**
     * String representation
     */
    public function __toString(): string
    {
        return $this->make->getName() . ' ' . $this->name;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }
}
