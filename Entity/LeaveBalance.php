<?php
declare(strict_types=1);

namespace KimaiPlugin\LieTimeOffBundle\Entity;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(
    name: "lie_leave_balance",
    uniqueConstraints: [new ORM\UniqueConstraint(name: "user_year_unique", columns: ["user_id", "year"])]
)]
#[ORM\HasLifecycleCallbacks]
class LeaveBalance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private User $user;

    #[ORM\ManyToOne(targetEntity: LeavePolicy::class)]
    #[ORM\JoinColumn(nullable: false)]
    private LeavePolicy $policy;

    #[ORM\Column(type: "integer")]
    private int $year;

    #[ORM\Column(type: "decimal", precision: 5, scale: 2)]
    private string $annualEntitlement;

    #[ORM\Column(type: "decimal", precision: 5, scale: 2)]
    private string $carryoverFromPreviousYear = "0.00";

    #[ORM\Column(type: "decimal", precision: 5, scale: 2)]
    private string $taken = "0.00";

    #[ORM\Column(type: "decimal", precision: 5, scale: 2)]
    private string $approved = "0.00";

    #[ORM\Column(type: "decimal", precision: 6, scale: 2)]
    private string $manualAdjustment = "0.00";

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $adjustmentNote = null;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $updatedAt;

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
    public function getUser(): User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }
    public function getPolicy(): LeavePolicy { return $this->policy; }
    public function setPolicy(LeavePolicy $policy): self { $this->policy = $policy; return $this; }
    public function getYear(): int { return $this->year; }
    public function setYear(int $year): self { $this->year = $year; return $this; }

    public function getAnnualEntitlement(): float { return (float) $this->annualEntitlement; }
    public function setAnnualEntitlement(float $v): self { $this->annualEntitlement = number_format($v, 2, ".", ""); return $this; }
    public function getCarryoverFromPreviousYear(): float { return (float) $this->carryoverFromPreviousYear; }
    public function setCarryoverFromPreviousYear(float $v): self { $this->carryoverFromPreviousYear = number_format($v, 2, ".", ""); return $this; }
    public function getTaken(): float { return (float) $this->taken; }
    public function setTaken(float $v): self { $this->taken = number_format($v, 2, ".", ""); return $this; }
    public function getApproved(): float { return (float) $this->approved; }
    public function setApproved(float $v): self { $this->approved = number_format($v, 2, ".", ""); return $this; }
    public function getManualAdjustment(): float { return (float) $this->manualAdjustment; }
    public function setManualAdjustment(float $v): self { $this->manualAdjustment = number_format($v, 2, ".", ""); return $this; }
    public function getAdjustmentNote(): ?string { return $this->adjustmentNote; }
    public function setAdjustmentNote(?string $note): self { $this->adjustmentNote = $note; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }

    public function getAvailable(): float
    {
        return $this->getTotal() - $this->getTaken() - $this->getApproved();
    }

    public function getTotal(): float
    {
        return $this->getAnnualEntitlement()
            + $this->getCarryoverFromPreviousYear()
            + $this->getManualAdjustment();
    }
}
