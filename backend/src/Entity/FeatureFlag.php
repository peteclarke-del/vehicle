<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'feature_flags')]

/**
 * class FeatureFlag
 */
class FeatureFlag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]

    /**
     * @var int
     */
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100, unique: true)]

    /**
     * @var string
     */
    private string $featureKey = '';

    #[ORM\Column(type: 'string', length: 150)]

    /**
     * @var string
     */
    private string $label = '';

    #[ORM\Column(type: 'text', nullable: true)]

    /**
     * @var string
     */
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 50)]

    /**
     * @var string
     */
    private string $category = '';

    #[ORM\Column(type: 'boolean', options: ['default' => true])]

    /**
     * @var bool
     */
    private bool $defaultEnabled = true;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]

    /**
     * @var int
     */
    private int $sortOrder = 0;

    #[ORM\Column(type: 'datetime')]

    /**
     * @var \DateTimeInterface
     */
    private \DateTimeInterface $createdAt;

    /**
     * function __construct
     *
     * @return void
     */
    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

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
     * function getFeatureKey
     *
     * @return string
     */
    public function getFeatureKey(): string
    {
        return $this->featureKey;
    }

    /**
     * function setFeatureKey
     *
     * @param string $featureKey
     *
     * @return self
     */
    public function setFeatureKey(string $featureKey): self
    {
        $this->featureKey = $featureKey;
        return $this;
    }

    /**
     * function getLabel
     *
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * function setLabel
     *
     * @param string $label
     *
     * @return self
     */
    public function setLabel(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    /**
     * function getDescription
     *
     * @return string
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * function setDescription
     *
     * @param string $description
     *
     * @return self
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * function getCategory
     *
     * @return string
     */
    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * function setCategory
     *
     * @param string $category
     *
     * @return self
     */
    public function setCategory(string $category): self
    {
        $this->category = $category;
        return $this;
    }

    /**
     * function isDefaultEnabled
     *
     * @return bool
     */
    public function isDefaultEnabled(): bool
    {
        return $this->defaultEnabled;
    }

    /**
     * function setDefaultEnabled
     *
     * @param bool $defaultEnabled
     *
     * @return self
     */
    public function setDefaultEnabled(bool $defaultEnabled): self
    {
        $this->defaultEnabled = $defaultEnabled;
        return $this;
    }

    /**
     * function getSortOrder
     *
     * @return int
     */
    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    /**
     * function setSortOrder
     *
     * @param int $sortOrder
     *
     * @return self
     */
    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    /**
     * function getCreatedAt
     *
     * @return \DateTimeInterface
     */
    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
}
