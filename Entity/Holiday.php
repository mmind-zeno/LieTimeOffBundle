<?php
declare(strict_types=1);

namespace KimaiPlugin\LieTimeOffBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(
    name: "lie_holiday",
    uniqueConstraints: [new ORM\UniqueConstraint(name: "date_unique", columns: ["date"])]
)]
class Holiday
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "date")]
    private \DateTimeInterface $date;

    #[ORM\Column(type: "string", length: 100)]
    private string $name;

    #[ORM\Column(type: "string", length: 50)]
    private string $type = "public";

    #[ORM\Column(type: "boolean")]
    private bool $isActive = true;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getDate(): \DateTimeInterface { return $this->date; }
    public function setDate(\DateTimeInterface $d): self { $this->date = $d; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $n): self { $this->name = $n; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $t): self { $this->type = $t; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $a): self { $this->isActive = $a; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}