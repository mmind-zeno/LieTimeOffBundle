<?php
declare(strict_types=1);

namespace KimaiPlugin\LieTimeOffBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\LieTimeOffBundle\Entity\SystemSetting;

class SettingsService
{
    private array $cache = [];

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Holt einen Setting-Wert (mit Default-Fallback)
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // Cache prüfen
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $setting = $this->entityManager
            ->getRepository(SystemSetting::class)
            ->find($key);

        if (!$setting) {
            return $default;
        }

        $value = $this->parseValue($setting->getSettingValue());
        $this->cache[$key] = $value;

        return $value;
    }

    /**
     * Speichert einen Setting-Wert
     */
    public function set(string $key, mixed $value): void
    {
        $setting = $this->entityManager
            ->getRepository(SystemSetting::class)
            ->find($key);

        if (!$setting) {
            $setting = new SystemSetting();
            $setting->setSettingKey($key);
        }

        $setting->setSettingValue($this->encodeValue($value));
        $setting->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($setting);
        $this->entityManager->flush();

        // Cache aktualisieren
        $this->cache[$key] = $value;
    }

    /**
     * Holt mehrere Settings auf einmal
     */
    public function getMultiple(array $keys): array
    {
        $result = [];
        foreach ($keys as $key => $default) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    /**
     * Speichert mehrere Settings auf einmal
     */
    public function setMultiple(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Löscht ein Setting
     */
    public function delete(string $key): void
    {
        $setting = $this->entityManager
            ->getRepository(SystemSetting::class)
            ->find($key);

        if ($setting) {
            $this->entityManager->remove($setting);
            $this->entityManager->flush();
        }

        unset($this->cache[$key]);
    }

    /**
     * Konvertiert Wert für Speicherung
     */
    private function encodeValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? "1" : "0";
        }
        if (is_array($value)) {
            return json_encode($value);
        }
        return (string) $value;
    }

    /**
     * Parst Wert aus Datenbank
     */
    private function parseValue(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        // Boolean
        if ($value === "1" || $value === "0") {
            return $value === "1";
        }

        // JSON Array/Object
        if (str_starts_with($value, "{") || str_starts_with($value, "[")) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        // Numeric
        if (is_numeric($value)) {
            return str_contains($value, ".") ? (float) $value : (int) $value;
        }

        return $value;
    }

    /**
     * Gibt alle Settings zurück
     */
    public function all(): array
    {
        $settings = $this->entityManager
            ->getRepository(SystemSetting::class)
            ->findAll();

        $result = [];
        foreach ($settings as $setting) {
            $result[$setting->getSettingKey()] = $this->parseValue($setting->getSettingValue());
        }

        return $result;
    }
}