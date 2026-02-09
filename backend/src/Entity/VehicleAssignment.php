<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'vehicle_assignments')]
#[ORM\UniqueConstraint(name: 'uq_vehicle_user', columns: ['vehicle_id', 'assigned_to_id'])]
class VehicleAssignment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Vehicle::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Vehicle $vehicle = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'assigned_to_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $assignedTo = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'assigned_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $assignedBy = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $canView = true;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $canEdit = true;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $canAddRecords = true;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $canDelete = false;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

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

    public function setVehicle(Vehicle $vehicle): self
    {
        $this->vehicle = $vehicle;
        return $this;
    }

    public function getAssignedTo(): ?User
    {
        return $this->assignedTo;
    }

    public function setAssignedTo(User $assignedTo): self
    {
        $this->assignedTo = $assignedTo;
        return $this;
    }

    public function getAssignedBy(): ?User
    {
        return $this->assignedBy;
    }

    public function setAssignedBy(?User $assignedBy): self
    {
        $this->assignedBy = $assignedBy;
        return $this;
    }

    public function canView(): bool
    {
        return $this->canView;
    }

    public function setCanView(bool $canView): self
    {
        $this->canView = $canView;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function canEdit(): bool
    {
        return $this->canEdit;
    }

    public function setCanEdit(bool $canEdit): self
    {
        $this->canEdit = $canEdit;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function canAddRecords(): bool
    {
        return $this->canAddRecords;
    }

    public function setCanAddRecords(bool $canAddRecords): self
    {
        $this->canAddRecords = $canAddRecords;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function canDelete(): bool
    {
        return $this->canDelete;
    }

    public function setCanDelete(bool $canDelete): self
    {
        $this->canDelete = $canDelete;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }
}
