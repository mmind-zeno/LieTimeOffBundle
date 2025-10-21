<?php
declare(strict_types=1);

namespace KimaiPlugin\LieTimeOffBundle\Entity;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\LieTimeOffBundle\Repository\LeaveRequestRepository;

#[ORM\Entity(repositoryClass: LeaveRequestRepository::class)]
#[ORM\Table(name: "lie_leave_request")]
#[ORM\HasLifecycleCallbacks]
class LeaveRequest
{
    public const STATUS_PENDING = "pending";
    public const STATUS_APPROVED = "approved";
    public const STATUS_REJECTED = "rejected";
    public const STATUS_CANCELLED = "cancelled";

    public const TYPE_VACATION = "vacation";
    public const TYPE_SICKNESS = "sickness";

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private User $user;

    #[ORM\Column(type: "string", length: 20)]
    private string $type = self::TYPE_VACATION;

    #[ORM\Column(type: "date")]
    private \DateTimeInterface $startDate;

    #[ORM\Column(type: "date")]
    private \DateTimeInterface $endDate;

    #[ORM\Column(type: "decimal", precision: 5, scale: 2)]
    private string $days;

    #[ORM\Column(type: "string", length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: "SET NULL")]
    private ?User $approvedBy = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTimeInterface $approvedAt = null;

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
    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }
    public function getStartDate(): \DateTimeInterface { return $this->startDate; }
    public function setStartDate(\DateTimeInterface $startDate): self { $this->startDate = $startDate; return $this; }
    public function getEndDate(): \DateTimeInterface { return $this->endDate; }
    public function setEndDate(\DateTimeInterface $endDate): self { $this->endDate = $endDate; return $this; }
    public function getDays(): float { return (float) $this->days; }
    public function setDays(float $days): self { $this->days = number_format($days, 2, ".", ""); return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function getComment(): ?string { return $this->comment; }
    public function setComment(?string $comment): self { $this->comment = $comment; return $this; }
    public function getRejectionReason(): ?string { return $this->rejectionReason; }
    public function setRejectionReason(?string $rejectionReason): self { $this->rejectionReason = $rejectionReason; return $this; }
    public function getApprovedBy(): ?User { return $this->approvedBy; }
    public function setApprovedBy(?User $approvedBy): self { $this->approvedBy = $approvedBy; return $this; }
    public function getApprovedAt(): ?\DateTimeInterface { return $this->approvedAt; }
    public function setApprovedAt(?\DateTimeInterface $approvedAt): self { $this->approvedAt = $approvedAt; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }

    public function isPending(): bool { return $this->status === self::STATUS_PENDING; }
    public function isApproved(): bool { return $this->status === self::STATUS_APPROVED; }
    public function isRejected(): bool { return $this->status === self::STATUS_REJECTED; }

    public function approve(User $approver): self
    {
        $this->status = self::STATUS_APPROVED;
        $this->approvedBy = $approver;
        $this->approvedAt = new \DateTimeImmutable();
        return $this;
    }

    public function reject(User $approver, ?string $reason = null): self
    {
        $this->status = self::STATUS_REJECTED;
        $this->approvedBy = $approver;
        $this->approvedAt = new \DateTimeImmutable();
        $this->rejectionReason = $reason;
        return $this;
    }
}