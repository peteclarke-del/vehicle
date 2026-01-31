<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Attachment;

#[ORM\Entity]
#[ORM\Table(name: 'fuel_records')]
class FuelRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Vehicle::class, inversedBy: 'fuelRecords')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Vehicle $vehicle = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2)]
    private ?string $litres = null;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2)]
    private ?string $cost = null;

    #[ORM\Column(type: 'integer')]
    private ?int $mileage = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $fuelType = null;

    #[ORM\Column(type: 'string', length: 200, nullable: true)]
    private ?string $station = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: Attachment::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Attachment $receiptAttachment = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

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

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;
        return $this;
    }

    public function getLitres(): ?string
    {
        return $this->litres;
    }

    public function setLitres($litres): self
    {
        if ($litres !== null) {
            $this->litres = (string) $litres;
        } else {
            $this->litres = null;
        }
        return $this;
    }

    public function getCost(): ?string
    {
        return $this->cost;
    }

    public function setCost($cost): self
    {
        if ($cost !== null) {
            $this->cost = (string) $cost;
        } else {
            $this->cost = null;
        }
        return $this;
    }

    public function getMileage(): ?int
    {
        return $this->mileage;
    }

    public function setMileage(int $mileage): self
    {
        $this->mileage = $mileage;
        return $this;
    }

    public function getFuelType(): ?string
    {
        return $this->fuelType;
    }

    public function setFuelType(?string $fuelType): self
    {
        $this->fuelType = $fuelType;
        return $this;
    }

    public function getStation(): ?string
    {
        return $this->station;
    }

    public function setStation(?string $station): self
    {
        $this->station = $station;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
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

    public function getReceiptAttachment(): ?Attachment
    {
        return $this->receiptAttachment;
    }

    public function setReceiptAttachment(?Attachment $receiptAttachment): self
    {
        $this->receiptAttachment = $receiptAttachment;
        return $this;
    }


    public function calculateMpg(?FuelRecord $previousRecord = null): ?float
    {
        if (!$this->litres || $this->litres <= 0) {
            return null;
        }

        // Calculate miles travelled
        $milesTravelled = null;
        if ($previousRecord && $this->mileage && $previousRecord->getMileage()) {
            $milesTravelled = $this->mileage - $previousRecord->getMileage();
        }

        if (!$milesTravelled || $milesTravelled <= 0) {
            return null;
        }

        // Convert litres to gallons (UK gallon = 4.54609 litres)
        $gallons = (float)$this->litres / 4.54609;
        return (float)$milesTravelled / $gallons;
    }

    public function getPricePerLitre(): ?float
    {
        if (!$this->litres || $this->litres <= 0 || !$this->cost) {
            return null;
        }
        return (float)$this->cost / (float)$this->litres;
    }

    public function calculateCostPerMile(?FuelRecord $previousRecord = null): ?float
    {
        if (!$this->cost) {
            return null;
        }

        // Calculate miles travelled
        $milesTravelled = null;
        if ($previousRecord && $this->mileage && $previousRecord->getMileage()) {
            $milesTravelled = $this->mileage - $previousRecord->getMileage();
        }

        if (!$milesTravelled || $milesTravelled <= 0) {
            return null;
        }

        return (float)$this->cost / (float)$milesTravelled;
    }

    /**
     * Convert MPG to Litres per 100km
     */
    public function convertMpgToLitres100km(float $mpg): float
    {
        if ($mpg <= 0) {
            return 0;
        }
        return 282.481 / $mpg;
    }
}
