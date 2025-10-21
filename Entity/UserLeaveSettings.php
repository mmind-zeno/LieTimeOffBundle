<?php
declare(strict_types=1);

namespace KimaiPlugin\LieTimeOffBundle\Entity;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "lie_user_leave_settings")]
class UserLeaveSettings
{
    public const TYPE_FULLTIME = "fulltime";
    public const TYPE_PARTTIME = "parttime";
    public const TYPE_HOURLY = "hourly";

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
    private User $user;

    #[ORM\ManyToOne(targetEntity: LeavePolicy::class)]
    #[ORM\JoinColumn(name: "policy_id", referencedColumnName: "id", nullable: true, onDelete: "SET NULL")]
    private ?LeavePolicy $policy = null;

    #[ORM\Column(type: "string", length: 20, options: ["default" => "fulltime"])]
    private string $employmentType = self::TYPE_FULLTIME;

    #[ORM\Column(type: "float", nullable: true)]
    private ?float $contractedHoursPerWeek = 40.0;

    #[ORM\Column(type: "float", nullable: false, options: ["default" => 100.0])]
    private float $workingTimePercentage = 100.0;

    #[ORM\Column(type: "boolean", options: ["default" => false])]
    private bool $useKimaiTimeTracking = false;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getPolicy(): ?LeavePolicy
    {
        return $this->policy;
    }

    public function setPolicy(?LeavePolicy $policy): self
    {
        $this->policy = $policy;
        return $this;
    }

    public function getEmploymentType(): string
    {
        return $this->employmentType;
    }

    public function setEmploymentType(string $type): self
    {
        $this->employmentType = $type;
        return $this;
    }

    public function isFulltime(): bool
    {
        return $this->employmentType === self::TYPE_FULLTIME;
    }

    public function isParttime(): bool
    {
        return $this->employmentType === self::TYPE_PARTTIME;
    }

    public function isHourly(): bool
    {
        return $this->employmentType === self::TYPE_HOURLY;
    }

    public function getContractedHoursPerWeek(): ?float
    {
        return $this->contractedHoursPerWeek;
    }

    public function setContractedHoursPerWeek(?float $hours): self
    {
        $this->contractedHoursPerWeek = $hours;
        return $this;
    }

    public function getWorkingTimePercentage(): float
    {
        return $this->workingTimePercentage;
    }

    public function setWorkingTimePercentage(float $percentage): self
    {
        $this->workingTimePercentage = $percentage;
        return $this;
    }

    public function getUseKimaiTimeTracking(): bool
    {
        return $this->useKimaiTimeTracking;
    }

    public function setUseKimaiTimeTracking(bool $use): self
    {
        $this->useKimaiTimeTracking = $use;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}