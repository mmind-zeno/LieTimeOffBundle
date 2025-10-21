<?php
declare(strict_types=1);

namespace KimaiPlugin\LieTimeOffBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "lie_timeoff_settings")]
class SystemSetting
{
    #[ORM\Id]
    #[ORM\Column(type: "string", length: 255)]
    private string $settingKey;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $settingValue = null;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getSettingKey(): string
    {
        return $this->settingKey;
    }

    public function setSettingKey(string $settingKey): self
    {
        $this->settingKey = $settingKey;
        return $this;
    }

    public function getSettingValue(): ?string
    {
        return $this->settingValue;
    }

    public function setSettingValue(?string $settingValue): self
    {
        $this->settingValue = $settingValue;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}