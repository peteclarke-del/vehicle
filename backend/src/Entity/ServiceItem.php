<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Part;

#[ORM\Entity]
#[ORM\Table(name: 'service_items')]
class ServiceItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ServiceRecord::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ServiceRecord $serviceRecord = null;

    #[ORM\ManyToOne(targetEntity: Consumable::class)]
    #[ORM\JoinColumn(name: 'consumable_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Consumable $consumable = null;

    #[ORM\ManyToOne(targetEntity: Part::class)]
    #[ORM\JoinColumn(name: 'part_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Part $part = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $type = 'part'; // 'part' or 'labour' or 'consumable'

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $cost = null;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $quantity = 1;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getServiceRecord(): ?ServiceRecord
    {
        return $this->serviceRecord;
    }

    public function setServiceRecord(?ServiceRecord $serviceRecord): self
    {
        $this->serviceRecord = $serviceRecord;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
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

    public function getCost(): ?string
    {
        return $this->cost;
    }

    public function setCost($cost): self
    {
        $this->cost = $cost !== null ? (string) $cost : null;
        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getTotal(): string
    {
        return bcmul($this->cost ?? '0.00', (string) $this->quantity, 2);
    }

    public function getConsumable(): ?Consumable
    {
        return $this->consumable;
    }

    public function setConsumable(?Consumable $consumable): self
    {
        $this->consumable = $consumable;
        return $this;
    }

    public function getPart(): ?Part
    {
        return $this->part;
    }

    public function setPart(?Part $part): self
    {
        $this->part = $part;
        return $this;
    }
}
