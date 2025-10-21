<?php
declare(strict_types=1);

namespace KimaiPlugin\LieTimeOffBundle\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\LieTimeOffBundle\Entity\Holiday;
use KimaiPlugin\LieTimeOffBundle\Entity\LeavePolicy;
use KimaiPlugin\LieTimeOffBundle\Entity\LeaveRequest;
use KimaiPlugin\LieTimeOffBundle\Entity\UserLeaveSettings;
use KimaiPlugin\LieTimeOffBundle\Service\BalanceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: "/timeoff")]
#[IsGranted("IS_AUTHENTICATED_FULLY")]
final class TimeOffController extends AbstractController
{
    private const TIMEZONE = "Europe/Vaduz";
    private const TOKEN_ID = "timeoff_request";
    private const TOKEN_APPROVE = "timeoff_approve";
    private const TOKEN_CANCEL = "cancel_request";

    public function __construct(
        private EntityManagerInterface $entityManager,
        private BalanceService $balanceService,
        private \KimaiPlugin\LieTimeOffBundle\Service\SettingsService $settingsService
    ) {
    }

    private function getCurrentYear(): int
    {
        $tz = new \DateTimeZone(self::TIMEZONE);
        return (int) (new \DateTimeImmutable("now", $tz))->format("Y");
    }

    #[Route(path: "", name: "timeoff_overview", methods: ["GET"])]
    public function overview(): Response
    {
        $year = $this->getCurrentYear();
        $from = new \DateTimeImmutable("$year-01-01");
		$to = new \DateTimeImmutable(($year + 2) . "-01-01");  // +2 für Jahreswechsel-Anträge


        /** @var User $user */
        $user = $this->getUser();

        // Feiertage laden
        $holidays = $this->entityManager->createQueryBuilder()
            ->select("h")
            ->from(Holiday::class, "h")
            ->where("h.date >= :from AND h.date < :to")
            ->andWhere("h.isActive = true")
            ->setParameter("from", $from)
            ->setParameter("to", $to)
            ->orderBy("h.date", "ASC")
            ->getQuery()
            ->getResult();

        // Standard-Policy laden
        $policy = $this->entityManager->getRepository(LeavePolicy::class)
            ->findOneBy(["isDefault" => true, "isActive" => true]);

        // Eigene Anträge des Users laden
        $myRequests = $this->entityManager->createQueryBuilder()
            ->select("r")
            ->from(LeaveRequest::class, "r")
            ->where("r.user = :user")
            ->setParameter("user", $user)
            ->orderBy("r.createdAt", "DESC")
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        // Saldo berechnen mit BalanceService
        $balance = $this->balanceService->calculateBalance($user, $year);

        return $this->render("@LieTimeOffBundle/overview.html.twig", [
            "holidays" => $holidays,
            "policy"   => $policy,
            "currentYear" => $year,
            "myRequests" => $myRequests,
            "balance" => $balance,
        ]);
    }

    #[Route(path: "/request", name: "timeoff_request", methods: ["GET", "POST"])]
    public function request(Request $request): Response
    {
        $year = $this->getCurrentYear();
        $from = new \DateTimeImmutable("$year-01-01");
        $to = new \DateTimeImmutable(($year + 2) . "-01-01");  // +2 für Jahreswechsel-Anträge

        $holidays = $this->entityManager->createQueryBuilder()
            ->select("h")
            ->from(Holiday::class, "h")
            ->where("h.date >= :from AND h.date < :to")
            ->andWhere("h.isActive = true")
            ->setParameter("from", $from)
            ->setParameter("to", $to)
            ->orderBy("h.date", "ASC")
            ->getQuery()
            ->getResult();

        if ($request->isMethod("POST")) {
            return $this->handleLeaveRequest($request);
        }

        return $this->render("@LieTimeOffBundle/request.html.twig", [
            "holidays" => $holidays,
            "currentYear" => $year,
        ]);
    }

    private function handleLeaveRequest(Request $request): Response
    {
        try {
            $token = (string) $request->request->get("_token", "");
            if (!$this->isCsrfTokenValid(self::TOKEN_ID, $token)) {
                $this->addFlash("error", "Sicherheits-Token ungültig. Bitte Formular erneut absenden.");
                return $this->redirectToRoute("timeoff_request");
            }

            $type      = (string) $request->request->get("type");
            $startDate = (string) $request->request->get("start_date");
            $endDate   = (string) $request->request->get("end_date");
            $comment   = (string) $request->request->get("comment", "");

            if (!$type || !$startDate || !$endDate) {
                $this->addFlash("error", "Bitte füllen Sie alle Pflichtfelder aus.");
                return $this->redirectToRoute("timeoff_request");
            }

            $allowed = [LeaveRequest::TYPE_VACATION, LeaveRequest::TYPE_SICKNESS];
            if (!in_array($type, $allowed, true)) {
                $this->addFlash("error", "Ungültige Antragsart.");
                return $this->redirectToRoute("timeoff_request");
            }

            $start = new \DateTimeImmutable($startDate);
            $end   = new \DateTimeImmutable($endDate);
            
            if ($start > $end) {
                $this->addFlash("error", "Das Enddatum muss nach dem Startdatum liegen.");
                return $this->redirectToRoute("timeoff_request");
            }

            // Überlappung prüfen
            $hasOverlap = $this->hasOverlappingRequests($start, $end);
            if ($hasOverlap) {
                $this->addFlash("error", "Sie haben bereits einen Antrag in diesem Zeitraum.");
                return $this->redirectToRoute("timeoff_request");
            }

            $workingDays = $this->calculateWorkingDays($start, $end);
            
            if ($workingDays <= 0) {
                $this->addFlash("error", "Der Antrag umfasst keine Arbeitstage.");
                return $this->redirectToRoute("timeoff_request");
            }

            /** @var User $user */
            $user = $this->getUser();
            if (!$user instanceof User) {
                $this->addFlash("error", "Benutzerkontext ungültig.");
                return $this->redirectToRoute("timeoff_request");
            }

            $leaveRequest = new LeaveRequest();
            $leaveRequest->setUser($user);
            $leaveRequest->setType($type);
            $leaveRequest->setStartDate($start);
            $leaveRequest->setEndDate($end);
            $leaveRequest->setDays($workingDays);
            $leaveRequest->setComment($comment);
            $leaveRequest->setStatus(LeaveRequest::STATUS_PENDING);

            $this->entityManager->persist($leaveRequest);
            $this->entityManager->flush();

            $this->addFlash("success", sprintf(
                "Ihr Urlaubsantrag wurde erfolgreich eingereicht! (%.1f Tage)",
                $workingDays
            ));
            return $this->redirectToRoute("timeoff_overview");

        } catch (\Throwable $e) {
            $this->addFlash("error", "Fehler beim Erstellen des Antrags: " . $e->getMessage());
            return $this->redirectToRoute("timeoff_request");
        }
    }

    #[Route(path: "/cancel/{id}", name: "timeoff_cancel", methods: ["POST"])]
    public function cancelRequest(int $id, Request $request): Response
    {
        try {
            $token = (string) $request->request->get("_token", "");
            if (!$this->isCsrfTokenValid(self::TOKEN_CANCEL, $token)) {
                $this->addFlash("error", "Sicherheits-Token ungültig.");
                return $this->redirectToRoute("timeoff_overview");
            }

            /** @var User $user */
            $user = $this->getUser();
            $leaveRequest = $this->entityManager->find(LeaveRequest::class, $id);
            
            if (!$leaveRequest || $leaveRequest->getUser() !== $user) {
                $this->addFlash("error", "Antrag nicht gefunden.");
                return $this->redirectToRoute("timeoff_overview");
            }

            if ($leaveRequest->getStatus() !== LeaveRequest::STATUS_PENDING) {
                $this->addFlash("error", "Nur ausstehende Anträge können storniert werden.");
                return $this->redirectToRoute("timeoff_overview");
            }

            $leaveRequest->setStatus(LeaveRequest::STATUS_CANCELLED);
            $this->entityManager->flush();

            $this->addFlash("success", "Urlaubsantrag wurde storniert.");
            return $this->redirectToRoute("timeoff_overview");

        } catch (\Throwable $e) {
            $this->addFlash("error", "Fehler: " . $e->getMessage());
            return $this->redirectToRoute("timeoff_overview");
        }
    }

    private function hasOverlappingRequests(
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        ?int $excludeId = null
    ): bool {
        /** @var User $user */
        $user = $this->getUser();

        $qb = $this->entityManager->createQueryBuilder()
            ->select("COUNT(r.id)")
            ->from(LeaveRequest::class, "r")
            ->where("r.user = :user")
            ->andWhere("r.status != :rejected")
            ->andWhere("r.status != :cancelled")
            ->andWhere(
                "(r.startDate BETWEEN :start AND :end) OR " .
                "(r.endDate BETWEEN :start AND :end) OR " .
                "(r.startDate <= :start AND r.endDate >= :end)"
            )
            ->setParameter("user", $user)
            ->setParameter("rejected", LeaveRequest::STATUS_REJECTED)
            ->setParameter("cancelled", LeaveRequest::STATUS_CANCELLED)
            ->setParameter("start", $start)
            ->setParameter("end", $end);

        if ($excludeId !== null) {
            $qb->andWhere("r.id != :excludeId")
                ->setParameter("excludeId", $excludeId);
        }

        return ((int) $qb->getQuery()->getSingleScalarResult()) > 0;
    }

    private function calculateWorkingDays(\DateTimeImmutable $start, \DateTimeImmutable $end): float
    {
        $workingDays = 0;
        $current = $start;

        $holidays = $this->entityManager->createQueryBuilder()
            ->select("h.date")
            ->from(Holiday::class, "h")
            ->where("h.date >= :start AND h.date <= :end")
            ->andWhere("h.isActive = true")
            ->setParameter("start", $start)
            ->setParameter("end", $end)
            ->getQuery()
            ->getResult();

        $holidayDates = array_map(
            fn(array $h) => $h["date"]->format("Y-m-d"),
            $holidays
        );

        while ($current <= $end) {
            $dow = (int) $current->format("N");
            $dateStr = $current->format("Y-m-d");
            if ($dow <= 5 && !in_array($dateStr, $holidayDates, true)) {
                $workingDays++;
            }
            $current = $current->modify("+1 day");
        }

        return (float) $workingDays;
    }

    #[Route(path: "/approve", name: "timeoff_approve", methods: ["GET", "POST"])]
    #[IsGranted("ROLE_TEAMLEAD")]
    public function approve(Request $request): Response
    {
        if ($request->isMethod("POST")) {
            return $this->handleApproval($request);
        }

        $pendingRequests = $this->entityManager->createQueryBuilder()
            ->select("r", "u")
            ->from(LeaveRequest::class, "r")
            ->leftJoin("r.user", "u")
            ->where("r.status = :status")
            ->setParameter("status", LeaveRequest::STATUS_PENDING)
            ->orderBy("r.createdAt", "DESC")
            ->getQuery()
            ->getResult();

        return $this->render("@LieTimeOffBundle/approve.html.twig", [
            "requests" => $pendingRequests,
        ]);
    }

    private function handleApproval(Request $request): Response
    {
        try {
            $token = (string) $request->request->get("_token", "");
            if (!$this->isCsrfTokenValid(self::TOKEN_APPROVE, $token)) {
                $this->addFlash("error", "Sicherheits-Token ungültig.");
                return $this->redirectToRoute("timeoff_approve");
            }

            $requestId = (int) $request->request->get("request_id");
            $action = (string) $request->request->get("action");
            $reason = (string) $request->request->get("reason", "");

            $leaveRequest = $this->entityManager->find(LeaveRequest::class, $requestId);
            
            if (!$leaveRequest) {
                $this->addFlash("error", "Antrag nicht gefunden.");
                return $this->redirectToRoute("timeoff_approve");
            }

            if (!$leaveRequest->isPending()) {
                $this->addFlash("error", "Dieser Antrag wurde bereits bearbeitet.");
                return $this->redirectToRoute("timeoff_approve");
            }

            /** @var User $approver */
            $approver = $this->getUser();
            if (!$approver instanceof User) {
                $this->addFlash("error", "Benutzerkontext ungültig.");
                return $this->redirectToRoute("timeoff_approve");
            }

            if ($action === "approve") {
                $leaveRequest->approve($approver);
                $this->addFlash("success", "Antrag wurde genehmigt.");
            } elseif ($action === "reject") {
                $leaveRequest->reject($approver, $reason);
                $this->addFlash("warning", "Antrag wurde abgelehnt.");
            } else {
                $this->addFlash("error", "Ungültige Aktion.");
                return $this->redirectToRoute("timeoff_approve");
            }

            $this->entityManager->flush();
            return $this->redirectToRoute("timeoff_approve");

        } catch (\Throwable $e) {
            $this->addFlash("error", "Fehler: " . $e->getMessage());
            return $this->redirectToRoute("timeoff_approve");
        }
    }

    #[Route(path: "/admin", name: "timeoff_admin", methods: ["GET"])]
    #[IsGranted("ROLE_ADMIN")]
    public function admin(): Response
    {
        $year = $this->getCurrentYear();
        
        $policies = $this->entityManager->getRepository(LeavePolicy::class)
            ->findBy(["isActive" => true], ["name" => "ASC"]);

        $from = new \DateTimeImmutable("$year-01-01");
        $to   = new \DateTimeImmutable(($year + 1) . "-01-01");

        $holidays = $this->entityManager->createQueryBuilder()
            ->select("h")
            ->from(Holiday::class, "h")
            ->where("h.date >= :from AND h.date < :to")
            ->setParameter("from", $from)
            ->setParameter("to", $to)
            ->orderBy("h.date", "ASC")
            ->getQuery()
            ->getResult();

        // BalanceService für Stats und Balances nutzen
        $stats = $this->balanceService->calculateStatistics($year);
        $balances = $this->balanceService->calculateAllBalances($year);

        // NEU: User-Settings für Tab "Mitarbeiter-Einstellungen"
        $users = $this->entityManager->getRepository(User::class)
            ->findBy(["enabled" => true], ["alias" => "ASC"]);
        
        $allSettings = $this->entityManager->getRepository(UserLeaveSettings::class)
            ->findAll();
        
        $settingsByUser = [];
        foreach ($allSettings as $setting) {
            $settingsByUser[$setting->getUser()->getId()] = $setting;
        }

        // System-Settings laden
        $settings = $this->settingsService->all();

        return $this->render("@LieTimeOffBundle/admin.html.twig", [
            "policies" => $policies,
            "holidays" => $holidays,
            "currentYear" => $year,
            "stats" => $stats,
            "balances" => $balances,
            "users" => $users,
            "settingsByUser" => $settingsByUser,
            "settings" => $settings,
        ]);
    }
}