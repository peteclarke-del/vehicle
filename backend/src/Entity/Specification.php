<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Vehicle Specification Entity
 * 
 * This entity stores technical specifications for all vehicle types including:
 * - Motorcycles (engine, suspension, brakes, etc.)
 * - Cars (engine, transmission, dimensions, etc.)
 * - Trucks, Vans, and other vehicles
 * 
 * Fields are kept generic to accommodate different vehicle types.
 * Not all fields will be applicable to every vehicle type.
 */
#[ORM\Entity]
#[ORM\Table(name: 'specifications')]
class Specification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Vehicle::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Vehicle $vehicle = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $engineType = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $displacement = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $power = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $torque = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $compression = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $bore = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $stroke = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $fuelSystem = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $cooling = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $gearbox = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $transmission = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $clutch = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $frame = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $frontSuspension = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $rearSuspension = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $frontBrakes = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $rearBrakes = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $frontTyre = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $rearTyre = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $frontWheelTravel = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $rearWheelTravel = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $wheelbase = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $seatHeight = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $groundClearance = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $dryWeight = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $wetWeight = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $fuelCapacity = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $topSpeed = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $additionalInfo = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $scrapedAt = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $sourceUrl = null;

    // Getters and Setters

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

    public function getEngineType(): ?string
    {
        return $this->engineType;
    }

    public function setEngineType(?string $engineType): self
    {
        $this->engineType = $engineType;
        return $this;
    }

    public function getDisplacement(): ?string
    {
        return $this->displacement;
    }

    public function setDisplacement(?string $displacement): self
    {
        $this->displacement = $displacement;
        return $this;
    }

    public function getPower(): ?string
    {
        return $this->power;
    }

    public function setPower(?string $power): self
    {
        $this->power = $power;
        return $this;
    }

    public function getTorque(): ?string
    {
        return $this->torque;
    }

    public function setTorque(?string $torque): self
    {
        $this->torque = $torque;
        return $this;
    }

    public function getCompression(): ?string
    {
        return $this->compression;
    }

    public function setCompression(?string $compression): self
    {
        $this->compression = $compression;
        return $this;
    }

    public function getBore(): ?string
    {
        return $this->bore;
    }

    public function setBore(?string $bore): self
    {
        $this->bore = $bore;
        return $this;
    }

    public function getStroke(): ?string
    {
        return $this->stroke;
    }

    public function setStroke(?string $stroke): self
    {
        $this->stroke = $stroke;
        return $this;
    }

    public function getFuelSystem(): ?string
    {
        return $this->fuelSystem;
    }

    public function setFuelSystem(?string $fuelSystem): self
    {
        $this->fuelSystem = $fuelSystem;
        return $this;
    }

    public function getCooling(): ?string
    {
        return $this->cooling;
    }

    public function setCooling(?string $cooling): self
    {
        $this->cooling = $cooling;
        return $this;
    }

    public function getGearbox(): ?string
    {
        return $this->gearbox;
    }

    public function setGearbox(?string $gearbox): self
    {
        $this->gearbox = $gearbox;
        return $this;
    }

    public function getTransmission(): ?string
    {
        return $this->transmission;
    }

    public function setTransmission(?string $transmission): self
    {
        $this->transmission = $transmission;
        return $this;
    }

    public function getClutch(): ?string
    {
        return $this->clutch;
    }

    public function setClutch(?string $clutch): self
    {
        $this->clutch = $clutch;
        return $this;
    }

    public function getFrame(): ?string
    {
        return $this->frame;
    }

    public function setFrame(?string $frame): self
    {
        $this->frame = $frame;
        return $this;
    }

    public function getFrontSuspension(): ?string
    {
        return $this->frontSuspension;
    }

    public function setFrontSuspension(?string $frontSuspension): self
    {
        $this->frontSuspension = $frontSuspension;
        return $this;
    }

    public function getRearSuspension(): ?string
    {
        return $this->rearSuspension;
    }

    public function setRearSuspension(?string $rearSuspension): self
    {
        $this->rearSuspension = $rearSuspension;
        return $this;
    }

    public function getFrontBrakes(): ?string
    {
        return $this->frontBrakes;
    }

    public function setFrontBrakes(?string $frontBrakes): self
    {
        $this->frontBrakes = $frontBrakes;
        return $this;
    }

    public function getRearBrakes(): ?string
    {
        return $this->rearBrakes;
    }

    public function setRearBrakes(?string $rearBrakes): self
    {
        $this->rearBrakes = $rearBrakes;
        return $this;
    }

    public function getFrontTyre(): ?string
    {
        return $this->frontTyre;
    }

    public function setFrontTyre(?string $frontTyre): self
    {
        $this->frontTyre = $frontTyre;
        return $this;
    }

    public function getRearTyre(): ?string
    {
        return $this->rearTyre;
    }

    public function setRearTyre(?string $rearTyre): self
    {
        $this->rearTyre = $rearTyre;
        return $this;
    }

    public function getFrontWheelTravel(): ?string
    {
        return $this->frontWheelTravel;
    }

    public function setFrontWheelTravel(?string $frontWheelTravel): self
    {
        $this->frontWheelTravel = $frontWheelTravel;
        return $this;
    }

    public function getRearWheelTravel(): ?string
    {
        return $this->rearWheelTravel;
    }

    public function setRearWheelTravel(?string $rearWheelTravel): self
    {
        $this->rearWheelTravel = $rearWheelTravel;
        return $this;
    }

    public function getWheelbase(): ?string
    {
        return $this->wheelbase;
    }

    public function setWheelbase(?string $wheelbase): self
    {
        $this->wheelbase = $wheelbase;
        return $this;
    }

    public function getSeatHeight(): ?string
    {
        return $this->seatHeight;
    }

    public function setSeatHeight(?string $seatHeight): self
    {
        $this->seatHeight = $seatHeight;
        return $this;
    }

    public function getGroundClearance(): ?string
    {
        return $this->groundClearance;
    }

    public function setGroundClearance(?string $groundClearance): self
    {
        $this->groundClearance = $groundClearance;
        return $this;
    }

    public function getDryWeight(): ?string
    {
        return $this->dryWeight;
    }

    public function setDryWeight(?string $dryWeight): self
    {
        $this->dryWeight = $dryWeight;
        return $this;
    }

    public function getWetWeight(): ?string
    {
        return $this->wetWeight;
    }

    public function setWetWeight(?string $wetWeight): self
    {
        $this->wetWeight = $wetWeight;
        return $this;
    }

    public function getFuelCapacity(): ?string
    {
        return $this->fuelCapacity;
    }

    public function setFuelCapacity(?string $fuelCapacity): self
    {
        $this->fuelCapacity = $fuelCapacity;
        return $this;
    }

    public function getTopSpeed(): ?string
    {
        return $this->topSpeed;
    }

    public function setTopSpeed(?string $topSpeed): self
    {
        $this->topSpeed = $topSpeed;
        return $this;
    }

    public function getAdditionalInfo(): ?string
    {
        return $this->additionalInfo;
    }

    public function setAdditionalInfo(?string $additionalInfo): self
    {
        $this->additionalInfo = $additionalInfo;
        return $this;
    }

    public function getScrapedAt(): ?\DateTimeInterface
    {
        return $this->scrapedAt;
    }

    public function setScrapedAt(?\DateTimeInterface $scrapedAt): self
    {
        $this->scrapedAt = $scrapedAt;
        return $this;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(?string $sourceUrl): self
    {
        $this->sourceUrl = $sourceUrl;
        return $this;
    }
}
