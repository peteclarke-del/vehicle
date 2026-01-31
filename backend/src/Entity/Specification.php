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

/**
 * class Specification
 */
class Specification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]

    /**
     * @var int
     */
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Vehicle::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]

    /**
     * @var Vehicle
     */
    private ?Vehicle $vehicle = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]

    /**
     * @var string
     */
    private ?string $engineType = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]

    /**
     * @var string
     */
    private ?string $displacement = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]

    /**
     * @var string
     */
    private ?string $power = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]

    /**
     * @var string
     */
    private ?string $torque = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]

    /**
     * @var string
     */
    private ?string $compression = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]

    /**
     * @var string
     */
    private ?string $bore = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]

    /**
     * @var string
     */
    private ?string $stroke = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]

    /**
     * @var string
     */
    private ?string $fuelSystem = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]

    /**
     * @var string
     */
    private ?string $cooling = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]

    /**
     * @var string
     */
    private ?string $sparkplugType = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]

    /**
     * @var string
     */
    private ?string $coolantType = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]

    /**
     * @var string
     */
    private ?string $coolantCapacity = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]

    /**
     * @var string
     */
    private ?string $gearbox = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]

    /**
     * @var string
     */
    private ?string $transmission = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]

    /**
     * @var string
     */
    private ?string $finalDrive = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]

    /**
     * @var string
     */
    private ?string $clutch = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]

    /**
     * @var string
     */
    private ?string $engineOilType = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]

    /**
     * @var string
     */
    private ?string $engineOilCapacity = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]

    /**
     * @var string
     */
    private ?string $transmissionOilType = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]

    /**
     * @var string
     */
    private ?string $transmissionOilCapacity = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]

    /**
     * @var string
     */
    private ?string $middleDriveOilType = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]

    /**
     * @var string
     */
    private ?string $middleDriveOilCapacity = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]

    /**
     * @var string
     */
    private ?string $frame = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]

    /**
     * @var string
     */
    private ?string $frontSuspension = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]

    /**
     * @var string
     */
    private ?string $rearSuspension = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]

    /**
     * @var string
     */
    private ?string $staticSagFront = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]

    /**
     * @var string
     */
    private ?string $staticSagRear = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]

    /**
     * @var string
     */
    private ?string $frontBrakes = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]

    /**
     * @var string
     */
    private ?string $rearBrakes = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]

    /**
     * @var string
     */
    private ?string $frontTyre = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]

    /**
     * @var string
     */
    private ?string $rearTyre = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]

    /**
     * @var string
     */
    private ?string $frontTyrePressure = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]

    /**
     * @var string
     */
    private ?string $rearTyrePressure = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]

    /**
     * @var string
     */
    private ?string $frontWheelTravel = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]

    /**
     * @var string
     */
    private ?string $rearWheelTravel = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]

    /**
     * @var string
     */
    private ?string $wheelbase = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]

    /**
     * @var string
     */
    private ?string $seatHeight = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]

    /**
     * @var string
     */
    private ?string $groundClearance = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]

    /**
     * @var string
     */
    private ?string $dryWeight = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]

    /**
     * @var string
     */
    private ?string $wetWeight = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]

    /**
     * @var string
     */
    private ?string $fuelCapacity = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]

    /**
     * @var string
     */
    private ?string $topSpeed = null;

    #[ORM\Column(type: 'text', nullable: true)]

    /**
     * @var string
     */
    private ?string $additionalInfo = null;

    #[ORM\Column(type: 'datetime', nullable: true)]

    /**
     * @var \DateTimeInterface
     */
    private ?\DateTimeInterface $scrapedAt = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]

    /**
     * @var string
     */
    private ?string $sourceUrl = null;

    // Getters and Setters

    /**
     * function getId
     *
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * function getVehicle
     *
     * @return Vehicle
     */
    public function getVehicle(): ?Vehicle
    {
        return $this->vehicle;
    }

    /**
     * function setVehicle
     *
     * @param Vehicle $vehicle
     *
     * @return self
     */
    public function setVehicle(?Vehicle $vehicle): self
    {
        $this->vehicle = $vehicle;
        return $this;
    }

    /**
     * function getEngineType
     *
     * @return string
     */
    public function getEngineType(): ?string
    {
        return $this->engineType;
    }

    /**
     * function setEngineType
     *
     * @param string $engineType
     *
     * @return self
     */
    public function setEngineType(?string $engineType): self
    {
        $this->engineType = $engineType;
        return $this;
    }

    /**
     * function getDisplacement
     *
     * @return string
     */
    public function getDisplacement(): ?string
    {
        return $this->displacement;
    }

    /**
     * function setDisplacement
     *
     * @param string $displacement
     *
     * @return self
     */
    public function setDisplacement(?string $displacement): self
    {
        $this->displacement = $displacement;
        return $this;
    }

    /**
     * function getPower
     *
     * @return string
     */
    public function getPower(): ?string
    {
        return $this->power;
    }

    /**
     * function setPower
     *
     * @param string $power
     *
     * @return self
     */
    public function setPower(?string $power): self
    {
        $this->power = $power;
        return $this;
    }

    /**
     * function getTorque
     *
     * @return string
     */
    public function getTorque(): ?string
    {
        return $this->torque;
    }

    /**
     * function setTorque
     *
     * @param string $torque
     *
     * @return self
     */
    public function setTorque(?string $torque): self
    {
        $this->torque = $torque;
        return $this;
    }

    /**
     * function getCompression
     *
     * @return string
     */
    public function getCompression(): ?string
    {
        return $this->compression;
    }

    /**
     * function setCompression
     *
     * @param string $compression
     *
     * @return self
     */
    public function setCompression(?string $compression): self
    {
        $this->compression = $compression;
        return $this;
    }

    /**
     * function getBore
     *
     * @return string
     */
    public function getBore(): ?string
    {
        return $this->bore;
    }

    /**
     * function setBore
     *
     * @param string $bore
     *
     * @return self
     */
    public function setBore(?string $bore): self
    {
        $this->bore = $bore;
        return $this;
    }

    /**
     * function getStroke
     *
     * @return string
     */
    public function getStroke(): ?string
    {
        return $this->stroke;
    }

    /**
     * function setStroke
     *
     * @param string $stroke
     *
     * @return self
     */
    public function setStroke(?string $stroke): self
    {
        $this->stroke = $stroke;
        return $this;
    }

    /**
     * function getFuelSystem
     *
     * @return string
     */
    public function getFuelSystem(): ?string
    {
        return $this->fuelSystem;
    }

    /**
     * function setFuelSystem
     *
     * @param string $fuelSystem
     *
     * @return self
     */
    public function setFuelSystem(?string $fuelSystem): self
    {
        $this->fuelSystem = $fuelSystem;
        return $this;
    }

    /**
     * function getCooling
     *
     * @return string
     */
    public function getCooling(): ?string
    {
        return $this->cooling;
    }

    /**
     * function setCooling
     *
     * @param string $cooling
     *
     * @return self
     */
    public function setCooling(?string $cooling): self
    {
        $this->cooling = $cooling;
        return $this;
    }

    /**
     * function getSparkplugType
     *
     * @return string
     */
    public function getSparkplugType(): ?string
    {
        return $this->sparkplugType;
    }

    /**
     * function setSparkplugType
     *
     * @param string $sparkplugType
     *
     * @return self
     */
    public function setSparkplugType(?string $sparkplugType): self
    {
        $this->sparkplugType = $sparkplugType;
        return $this;
    }

    /**
     * function getCoolantType
     *
     * @return string
     */
    public function getCoolantType(): ?string
    {
        return $this->coolantType;
    }

    /**
     * function setCoolantType
     *
     * @param string $coolantType
     *
     * @return self
     */
    public function setCoolantType(?string $coolantType): self
    {
        $this->coolantType = $coolantType;
        return $this;
    }

    /**
     * function getCoolantCapacity
     *
     * @return string
     */
    public function getCoolantCapacity(): ?string
    {
        return $this->coolantCapacity;
    }

    /**
     * function setCoolantCapacity
     *
     * @param string $coolantCapacity
     *
     * @return self
     */
    public function setCoolantCapacity(?string $coolantCapacity): self
    {
        $this->coolantCapacity = $coolantCapacity;
        return $this;
    }

    /**
     * function getGearbox
     *
     * @return string
     */
    public function getGearbox(): ?string
    {
        return $this->gearbox;
    }

    /**
     * function setGearbox
     *
     * @param string $gearbox
     *
     * @return self
     */
    public function setGearbox(?string $gearbox): self
    {
        $this->gearbox = $gearbox;
        return $this;
    }

    /**
     * function getTransmission
     *
     * @return string
     */
    public function getTransmission(): ?string
    {
        return $this->transmission;
    }

    /**
     * function setTransmission
     *
     * @param string $transmission
     *
     * @return self
     */
    public function setTransmission(?string $transmission): self
    {
        $this->transmission = $transmission;
        return $this;
    }

    /**
     * function getFinalDrive
     *
     * @return string
     */
    public function getFinalDrive(): ?string
    {
        return $this->finalDrive;
    }

    /**
     * function setFinalDrive
     *
     * @param string $finalDrive
     *
     * @return self
     */
    public function setFinalDrive(?string $finalDrive): self
    {
        $this->finalDrive = $finalDrive;
        return $this;
    }

    /**
     * function getClutch
     *
     * @return string
     */
    public function getClutch(): ?string
    {
        return $this->clutch;
    }

    /**
     * function setClutch
     *
     * @param string $clutch
     *
     * @return self
     */
    public function setClutch(?string $clutch): self
    {
        $this->clutch = $clutch;
        return $this;
    }

    /**
     * function getEngineOilType
     *
     * @return string
     */
    public function getEngineOilType(): ?string
    {
        return $this->engineOilType;
    }

    /**
     * function setEngineOilType
     *
     * @param string $engineOilType
     *
     * @return self
     */
    public function setEngineOilType(?string $engineOilType): self
    {
        $this->engineOilType = $engineOilType;
        return $this;
    }

    /**
     * function getEngineOilCapacity
     *
     * @return string
     */
    public function getEngineOilCapacity(): ?string
    {
        return $this->engineOilCapacity;
    }

    /**
     * function setEngineOilCapacity
     *
     * @param string $engineOilCapacity
     *
     * @return self
     */
    public function setEngineOilCapacity(?string $engineOilCapacity): self
    {
        $this->engineOilCapacity = $engineOilCapacity;
        return $this;
    }

    /**
     * function getTransmissionOilType
     *
     * @return string
     */
    public function getTransmissionOilType(): ?string
    {
        return $this->transmissionOilType;
    }

    /**
     * function setTransmissionOilType
     *
     * @param string $transmissionOilType
     *
     * @return self
     */
    public function setTransmissionOilType(?string $transmissionOilType): self
    {
        $this->transmissionOilType = $transmissionOilType;
        return $this;
    }

    /**
     * function getTransmissionOilCapacity
     *
     * @return string
     */
    public function getTransmissionOilCapacity(): ?string
    {
        return $this->transmissionOilCapacity;
    }

    /**
     * function setTransmissionOilCapacity
     *
     * @param string $transmissionOilCapacity
     *
     * @return self
     */
    public function setTransmissionOilCapacity(?string $transmissionOilCapacity): self
    {
        $this->transmissionOilCapacity = $transmissionOilCapacity;
        return $this;
    }

    /**
     * function getMiddleDriveOilType
     *
     * @return string
     */
    public function getMiddleDriveOilType(): ?string
    {
        return $this->middleDriveOilType;
    }

    /**
     * function setMiddleDriveOilType
     *
     * @param string $middleDriveOilType
     *
     * @return self
     */
    public function setMiddleDriveOilType(?string $middleDriveOilType): self
    {
        $this->middleDriveOilType = $middleDriveOilType;
        return $this;
    }

    /**
     * function getMiddleDriveOilCapacity
     *
     * @return string
     */
    public function getMiddleDriveOilCapacity(): ?string
    {
        return $this->middleDriveOilCapacity;
    }

    /**
     * function setMiddleDriveOilCapacity
     *
     * @param string $middleDriveOilCapacity
     *
     * @return self
     */
    public function setMiddleDriveOilCapacity(?string $middleDriveOilCapacity): self
    {
        $this->middleDriveOilCapacity = $middleDriveOilCapacity;
        return $this;
    }

    /**
     * function getFrame
     *
     * @return string
     */
    public function getFrame(): ?string
    {
        return $this->frame;
    }

    /**
     * function setFrame
     *
     * @param string $frame
     *
     * @return self
     */
    public function setFrame(?string $frame): self
    {
        $this->frame = $frame;
        return $this;
    }

    /**
     * function getFrontSuspension
     *
     * @return string
     */
    public function getFrontSuspension(): ?string
    {
        return $this->frontSuspension;
    }

    /**
     * function setFrontSuspension
     *
     * @param string $frontSuspension
     *
     * @return self
     */
    public function setFrontSuspension(?string $frontSuspension): self
    {
        $this->frontSuspension = $frontSuspension;
        return $this;
    }

    /**
     * function getRearSuspension
     *
     * @return string
     */
    public function getRearSuspension(): ?string
    {
        return $this->rearSuspension;
    }

    /**
     * function setRearSuspension
     *
     * @param string $rearSuspension
     *
     * @return self
     */
    public function setRearSuspension(?string $rearSuspension): self
    {
        $this->rearSuspension = $rearSuspension;
        return $this;
    }

    /**
     * function getStaticSagFront
     *
     * @return string
     */
    public function getStaticSagFront(): ?string
    {
        return $this->staticSagFront;
    }

    /**
     * function setStaticSagFront
     *
     * @param string $staticSagFront
     *
     * @return self
     */
    public function setStaticSagFront(?string $staticSagFront): self
    {
        $this->staticSagFront = $staticSagFront;
        return $this;
    }

    /**
     * function getStaticSagRear
     *
     * @return string
     */
    public function getStaticSagRear(): ?string
    {
        return $this->staticSagRear;
    }

    /**
     * function setStaticSagRear
     *
     * @param string $staticSagRear
     *
     * @return self
     */
    public function setStaticSagRear(?string $staticSagRear): self
    {
        $this->staticSagRear = $staticSagRear;
        return $this;
    }

    /**
     * function getFrontBrakes
     *
     * @return string
     */
    public function getFrontBrakes(): ?string
    {
        return $this->frontBrakes;
    }

    /**
     * function setFrontBrakes
     *
     * @param string $frontBrakes
     *
     * @return self
     */
    public function setFrontBrakes(?string $frontBrakes): self
    {
        $this->frontBrakes = $frontBrakes;
        return $this;
    }

    /**
     * function getRearBrakes
     *
     * @return string
     */
    public function getRearBrakes(): ?string
    {
        return $this->rearBrakes;
    }

    /**
     * function setRearBrakes
     *
     * @param string $rearBrakes
     *
     * @return self
     */
    public function setRearBrakes(?string $rearBrakes): self
    {
        $this->rearBrakes = $rearBrakes;
        return $this;
    }

    /**
     * function getFrontTyre
     *
     * @return string
     */
    public function getFrontTyre(): ?string
    {
        return $this->frontTyre;
    }

    /**
     * function setFrontTyre
     *
     * @param string $frontTyre
     *
     * @return self
     */
    public function setFrontTyre(?string $frontTyre): self
    {
        $this->frontTyre = $frontTyre;
        return $this;
    }

    /**
     * function getRearTyre
     *
     * @return string
     */
    public function getRearTyre(): ?string
    {
        return $this->rearTyre;
    }

    /**
     * function setRearTyre
     *
     * @param string $rearTyre
     *
     * @return self
     */
    public function setRearTyre(?string $rearTyre): self
    {
        $this->rearTyre = $rearTyre;
        return $this;
    }

    /**
     * function getFrontTyrePressure
     *
     * @return string
     */
    public function getFrontTyrePressure(): ?string
    {
        return $this->frontTyrePressure;
    }

    /**
     * function setFrontTyrePressure
     *
     * @param string $frontTyrePressure
     *
     * @return self
     */
    public function setFrontTyrePressure(?string $frontTyrePressure): self
    {
        $this->frontTyrePressure = $frontTyrePressure;
        return $this;
    }

    /**
     * function getRearTyrePressure
     *
     * @return string
     */
    public function getRearTyrePressure(): ?string
    {
        return $this->rearTyrePressure;
    }

    /**
     * function setRearTyrePressure
     *
     * @param string $rearTyrePressure
     *
     * @return self
     */
    public function setRearTyrePressure(?string $rearTyrePressure): self
    {
        $this->rearTyrePressure = $rearTyrePressure;
        return $this;
    }

    /**
     * function getFrontWheelTravel
     *
     * @return string
     */
    public function getFrontWheelTravel(): ?string
    {
        return $this->frontWheelTravel;
    }

    /**
     * function setFrontWheelTravel
     *
     * @param string $frontWheelTravel
     *
     * @return self
     */
    public function setFrontWheelTravel(?string $frontWheelTravel): self
    {
        $this->frontWheelTravel = $frontWheelTravel;
        return $this;
    }

    /**
     * function getRearWheelTravel
     *
     * @return string
     */
    public function getRearWheelTravel(): ?string
    {
        return $this->rearWheelTravel;
    }

    /**
     * function setRearWheelTravel
     *
     * @param string $rearWheelTravel
     *
     * @return self
     */
    public function setRearWheelTravel(?string $rearWheelTravel): self
    {
        $this->rearWheelTravel = $rearWheelTravel;
        return $this;
    }

    /**
     * function getWheelbase
     *
     * @return string
     */
    public function getWheelbase(): ?string
    {
        return $this->wheelbase;
    }

    /**
     * function setWheelbase
     *
     * @param string $wheelbase
     *
     * @return self
     */
    public function setWheelbase(?string $wheelbase): self
    {
        $this->wheelbase = $wheelbase;
        return $this;
    }

    /**
     * function getSeatHeight
     *
     * @return string
     */
    public function getSeatHeight(): ?string
    {
        return $this->seatHeight;
    }

    /**
     * function setSeatHeight
     *
     * @param string $seatHeight
     *
     * @return self
     */
    public function setSeatHeight(?string $seatHeight): self
    {
        $this->seatHeight = $seatHeight;
        return $this;
    }

    /**
     * function getGroundClearance
     *
     * @return string
     */
    public function getGroundClearance(): ?string
    {
        return $this->groundClearance;
    }

    /**
     * function setGroundClearance
     *
     * @param string $groundClearance
     *
     * @return self
     */
    public function setGroundClearance(?string $groundClearance): self
    {
        $this->groundClearance = $groundClearance;
        return $this;
    }

    /**
     * function getDryWeight
     *
     * @return string
     */
    public function getDryWeight(): ?string
    {
        return $this->dryWeight;
    }

    /**
     * function setDryWeight
     *
     * @param string $dryWeight
     *
     * @return self
     */
    public function setDryWeight(?string $dryWeight): self
    {
        $this->dryWeight = $dryWeight;
        return $this;
    }

    /**
     * function getWetWeight
     *
     * @return string
     */
    public function getWetWeight(): ?string
    {
        return $this->wetWeight;
    }

    /**
     * function setWetWeight
     *
     * @param string $wetWeight
     *
     * @return self
     */
    public function setWetWeight(?string $wetWeight): self
    {
        $this->wetWeight = $wetWeight;
        return $this;
    }

    /**
     * function getFuelCapacity
     *
     * @return string
     */
    public function getFuelCapacity(): ?string
    {
        return $this->fuelCapacity;
    }

    /**
     * function setFuelCapacity
     *
     * @param string $fuelCapacity
     *
     * @return self
     */
    public function setFuelCapacity(?string $fuelCapacity): self
    {
        $this->fuelCapacity = $fuelCapacity;
        return $this;
    }

    /**
     * function getTopSpeed
     *
     * @return string
     */
    public function getTopSpeed(): ?string
    {
        return $this->topSpeed;
    }

    /**
     * function setTopSpeed
     *
     * @param string $topSpeed
     *
     * @return self
     */
    public function setTopSpeed(?string $topSpeed): self
    {
        $this->topSpeed = $topSpeed;
        return $this;
    }

    /**
     * function getAdditionalInfo
     *
     * @return string
     */
    public function getAdditionalInfo(): ?string
    {
        return $this->additionalInfo;
    }

    /**
     * function setAdditionalInfo
     *
     * @param string $additionalInfo
     *
     * @return self
     */
    public function setAdditionalInfo(?string $additionalInfo): self
    {
        $this->additionalInfo = $additionalInfo;
        return $this;
    }

    /**
     * function getScrapedAt
     *
     * @return \DateTimeInterface
     */
    public function getScrapedAt(): ?\DateTimeInterface
    {
        return $this->scrapedAt;
    }

    /**
     * function setScrapedAt
     *
     * @param \DateTimeInterface $scrapedAt
     *
     * @return self
     */
    public function setScrapedAt(?\DateTimeInterface $scrapedAt): self
    {
        $this->scrapedAt = $scrapedAt;
        return $this;
    }

    /**
     * function getSourceUrl
     *
     * @return string
     */
    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    /**
     * function setSourceUrl
     *
     * @param string $sourceUrl
     *
     * @return self
     */
    public function setSourceUrl(?string $sourceUrl): self
    {
        $this->sourceUrl = $sourceUrl;
        return $this;
    }
}
