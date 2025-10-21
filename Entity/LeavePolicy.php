<?php
declare(strict_types=1);

namespace KimaiPlugin\LieTimeOffBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "lie_leave_policy")]
#[ORM\HasLifecycleCallbacks]
class LeavePolicy
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 100)]
    private string $name;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: "decimal", precision: 5, scale: 2)]
    private string $annualDays = "25.00";

    #[ORM\Column(type: "decimal", precision: 5, scale: 2)]
    private string $maxCarryover = "5.00";

    #[ORM\Column(type: "boolean")]
    private bool $isDefault = false;

    #[ORM\Column(type: "boolean")]
    private bool $isActive = true;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $updatedAt;

    public function __construct() {}

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    public function getAnnualDays(): float { return (float) $this->annualDays; }
    public function setAnnualDays(float $annualDays): self { $this->annualDays = number_format($annualDays, 2, ".", ""); return $this; }
    public function getMaxCarryover(): float { return (float) $this->maxCarryover; }
    public function setMaxCarryover(float $maxCarryover): self { $this->maxCarryover = number_format($maxCarryover, 2, ".", ""); return $this; }
    public function isDefault(): bool { return $this->isDefault; }
    public function setIsDefault(bool $isDefault): self { $this->isDefault = $isDefault; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
}