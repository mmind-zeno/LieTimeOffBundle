<?php
declare(strict_types=1);

namespace KimaiPlugin\LieTimeOffBundle\Service;

use App\Entity\User;
use App\Entity\Timesheet;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\LieTimeOffBundle\Entity\LeaveBalance;
use KimaiPlugin\LieTimeOffBundle\Entity\LeavePolicy;
use KimaiPlugin\LieTimeOffBundle\Entity\LeaveRequest;
use KimaiPlugin\LieTimeOffBundle\Entity\UserLeaveSettings;

class BalanceService
{
    // Konstanten für Berechnung
    private const BASELINE_HOURS_PER_YEAR = 2080.0; // 40h × 52 Wochen
    private const HOURS_PER_DAY = 8.0;
    private const DEFAULT_ANNUAL_DAYS = 25.0;
    private const DEFAULT_MAX_CARRYOVER = 5.0;

    private EntityManagerInterface $em;
    private SettingsService $settingsService;

    public function __construct(
        EntityManagerInterface $em,
        SettingsService $settingsService
    ) {
        $this->em = $em;
        $this->settingsService = $settingsService;
    }

    /**
     * Berechnet den Urlaubssaldo für einen User (mit automatischer Stunden-Berechnung)
     */
    public function calculateBalance(User $user, int $year): array
    {
        // 1. User-Settings laden (oder Defaults)
        $settings = $this->getUserSettings($user);
        
        // 2. Policy bestimmen (mit Fallback auf System-Settings)
        $policy = $settings?->getPolicy() 
            ?? $this->em->getRepository(LeavePolicy::class)->findOneBy(["isDefault" => true, "isActive" => true]);

        // Fallback-Reihenfolge: Policy → System-Settings → Hardcoded Default
        $annualDaysFT = (float) ($policy?->getAnnualDays() 
            ?? $this->settingsService->get('default_annual_days', self::DEFAULT_ANNUAL_DAYS));
        $maxCarryover = (float) ($policy?->getMaxCarryover() 
            ?? $this->settingsService->get('max_carryover_days', self::DEFAULT_MAX_CARRYOVER));

        // 3. Anspruch basierend auf Employment Type berechnen
        $employmentType = $settings?->getEmploymentType() ?? UserLeaveSettings::TYPE_FULLTIME;
        $annualEntitlement = $this->calculateEntitlement($user, $year, $employmentType, $annualDaysFT, $settings);

        // 4. Übertrag vom Vorjahr
        $carryover = $this->getCarryover($user, $year - 1, $maxCarryover);

        // 5. Genommene, genehmigte und ausstehende Tage (NUR Urlaub!)
        $taken = $this->getTakenDays($user, $year);
        $approved = $this->getApprovedFutureDays($user, $year);
        $pending = $this->getPendingDays($user, $year);
        $sicknessDays = $this->getSicknessDays($user, $year);  // NEU!

        // 6. Verfügbare Tage
        $available = ($annualEntitlement + $carryover) - ($taken + $approved);

        return [
            "annualEntitlement" => round($annualEntitlement, 2),
            "taken" => round($taken, 2),
            "approved" => round($approved, 2),
            "pending" => round($pending, 2),
            "available" => round($available, 2),
            "carryover" => round($carryover, 2),
            "sicknessDays" => round($sicknessDays, 2),  // NEU!
            "policy" => $policy,
            "employmentType" => $employmentType,
            "settings" => $settings,
        ];
    }

    /**
     * Berechnet Urlaubsanspruch basierend auf Employment Type
     */
    private function calculateEntitlement(User $user, int $year, string $employmentType, float $annualDaysFT, ?UserLeaveSettings $settings): float
    {
        switch ($employmentType) {
            case UserLeaveSettings::TYPE_HOURLY:
                // Stunden aus Kimai ziehen und anteilig berechnen
                if ($settings?->getUseKimaiTimeTracking()) {
                    return $this->calculateHourlyEntitlement($user, $year, $annualDaysFT);
                }
                // Fallback: 0 wenn keine Stunden erfasst
                return 0.0;

            case UserLeaveSettings::TYPE_PARTTIME:
                // Prozentual berechnen
                $percentage = $settings?->getWorkingTimePercentage() ?? 100.0;
                return $annualDaysFT * ($percentage / 100.0);

            case UserLeaveSettings::TYPE_FULLTIME:
            default:
                // Vollzeit: fixe Tage
                return $annualDaysFT;
        }
    }

    /**
     * Berechnet Urlaubsanspruch für Stundenlöhner aus Kimai-Timesheet
     */
    private function calculateHourlyEntitlement(User $user, int $year, float $annualDaysFT): float
    {
        $from = new \DateTimeImmutable("$year-01-01 00:00:00");
        $to = new \DateTimeImmutable("$year-12-31 23:59:59");

        // Gearbeitete Sekunden aus Kimai holen
        $workedSeconds = (int) $this->em->createQueryBuilder()
            ->select("COALESCE(SUM(t.duration), 0)")
            ->from(Timesheet::class, "t")
            ->where("t.user = :user")
            ->andWhere("t.begin >= :from AND t.begin <= :to")
            ->setParameter("user", $user)
            ->setParameter("from", $from)
            ->setParameter("to", $to)
            ->getQuery()
            ->getSingleScalarResult();

        $workedHours = $workedSeconds / 3600.0;

        // Urlaubstage = (Gearbeitete Stunden / Jahres-Vollzeit-Stunden) × Basis-Urlaubstage
        if (self::BASELINE_HOURS_PER_YEAR > 0) {
            return ($workedHours / self::BASELINE_HOURS_PER_YEAR) * $annualDaysFT;
        }

        return 0.0;
    }

    /**
     * Lädt User-Settings oder gibt null zurück
     */
    private function getUserSettings(User $user): ?UserLeaveSettings
    {
        return $this->em->getRepository(UserLeaveSettings::class)->findOneBy(["user" => $user]);
    }

    /**
     * Übertrag vom Vorjahr
     */
    private function getCarryover(User $user, int $year, float $maxCarryover): float
    {
        $balance = $this->em->getRepository(LeaveBalance::class)->findOneBy([
            "user" => $user,
            "year" => $year,
        ]);

        if (!$balance) {
            return 0.0;
        }

        $remaining = $balance->getAvailable();
        return min($remaining, $maxCarryover);
    }

    /**
     * Bereits genommene URLAUBS-Tage (approved + im Zeitraum)
     * Krankheit wird NICHT abgezogen!
     */
    private function getTakenDays(User $user, int $year): float
    {
        $from = new \DateTime("$year-01-01");
        $to = new \DateTime("$year-12-31");

        $qb = $this->em->createQueryBuilder();
        $qb->select("COALESCE(SUM(r.days), 0)")
            ->from(LeaveRequest::class, "r")
            ->where("r.user = :user")
            ->andWhere("r.status = :status")
            ->andWhere("r.type = :type")  // NEU!
            ->andWhere("r.startDate <= :to AND r.endDate >= :from")
            ->andWhere("r.startDate < :now")
            ->setParameter("user", $user)
            ->setParameter("status", "approved")
            ->setParameter("type", LeaveRequest::TYPE_VACATION)  // NEU!
            ->setParameter("from", $from)
            ->setParameter("to", $to)
            ->setParameter("now", new \DateTime());

        return (float) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Genehmigte zukünftige URLAUBS-Tage
     * Krankheit wird NICHT abgezogen!
     */
    private function getApprovedFutureDays(User $user, int $year): float
    {
        $from = new \DateTime("$year-01-01");
        $to = new \DateTime("$year-12-31");
        $now = new \DateTime();

        $qb = $this->em->createQueryBuilder();
        $qb->select("COALESCE(SUM(r.days), 0)")
            ->from(LeaveRequest::class, "r")
            ->where("r.user = :user")
            ->andWhere("r.status = :status")
            ->andWhere("r.type = :type")  // NEU!
            ->andWhere("r.startDate <= :to AND r.endDate >= :from")
            ->andWhere("r.startDate >= :now")
            ->setParameter("user", $user)
            ->setParameter("status", "approved")
            ->setParameter("type", LeaveRequest::TYPE_VACATION)  // NEU!
            ->setParameter("from", $from)
            ->setParameter("to", $to)
            ->setParameter("now", $now);

        return (float) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Ausstehende Tage (pending)
     */
    private function getPendingDays(User $user, int $year): float
    {
        $from = new \DateTime("$year-01-01");
        $to = new \DateTime("$year-12-31");

        $qb = $this->em->createQueryBuilder();
        $qb->select("COALESCE(SUM(r.days), 0)")
            ->from(LeaveRequest::class, "r")
            ->where("r.user = :user")
            ->andWhere("r.status = :status")
            ->andWhere("r.startDate <= :to AND r.endDate >= :from")
            ->setParameter("user", $user)
            ->setParameter("status", "pending")
            ->setParameter("from", $from)
            ->setParameter("to", $to);

        return (float) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Krankheitstage (approved, wird NICHT vom Urlaubssaldo abgezogen)
     */
    private function getSicknessDays(User $user, int $year): float
    {
        $from = new \DateTime("$year-01-01");
        $to = new \DateTime("$year-12-31");

        $qb = $this->em->createQueryBuilder();
        $qb->select("COALESCE(SUM(r.days), 0)")
            ->from(LeaveRequest::class, "r")
            ->where("r.user = :user")
            ->andWhere("r.status = :status")
            ->andWhere("r.type = :type")
            ->andWhere("r.startDate <= :to AND r.endDate >= :from")
            ->setParameter("user", $user)
            ->setParameter("status", "approved")
            ->setParameter("type", LeaveRequest::TYPE_SICKNESS)
            ->setParameter("from", $from)
            ->setParameter("to", $to);

        return (float) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Berechnet Salden für ALLE User (für Admin-Übersicht)
     */
    public function calculateAllBalances(int $year): array
    {
        $users = $this->em->getRepository(User::class)->findAll();
        $balances = [];

        foreach ($users as $user) {
            $balance = $this->calculateBalance($user, $year);
            $balance["user"] = $user;
            $balances[] = $balance;
        }

        return $balances;
    }

    /**
     * Berechnet Statistiken für Admin-Dashboard
     */
    public function calculateStatistics(int $year): array
    {
        $balances = $this->calculateAllBalances($year);

        $stats = [
            "totalEntitlement" => 0.0,
            "totalTaken" => 0.0,
            "totalApproved" => 0.0,
            "totalPending" => 0.0,
            "totalAvailable" => 0.0,
            "totalSicknessDays" => 0.0,  // NEU!
            "userCount" => count($balances),
        ];

        foreach ($balances as $balance) {
            $stats["totalEntitlement"] += $balance["annualEntitlement"];
            $stats["totalTaken"] += $balance["taken"];
            $stats["totalApproved"] += $balance["approved"];
            $stats["totalPending"] += $balance["pending"];
            $stats["totalAvailable"] += $balance["available"];
            $stats["totalSicknessDays"] += $balance["sicknessDays"];  // NEU!
        }

        $stats["takenPercentage"] = $stats["totalEntitlement"] > 0
            ? round(($stats["totalTaken"] / $stats["totalEntitlement"]) * 100, 1)
            : 0;

        $stats["availablePercentage"] = $stats["totalEntitlement"] > 0
            ? round(($stats["totalAvailable"] / $stats["totalEntitlement"]) * 100, 1)
            : 0;

        $stats["averagePerUser"] = $stats["userCount"] > 0
            ? round($stats["totalEntitlement"] / $stats["userCount"], 1)
            : 0;
        
        $stats["averageSicknessPerUser"] = $stats["userCount"] > 0  // NEU!
            ? round($stats["totalSicknessDays"] / $stats["userCount"], 1)
            : 0;

        return $stats;
    }
}