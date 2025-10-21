<?php
declare(strict_types=1);

namespace KimaiPlugin\LieTimeOffBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\LieTimeOffBundle\Entity\Holiday;
use KimaiPlugin\LieTimeOffBundle\Entity\LeavePolicy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: "timeoff:init",
    description: "Initialize LieTimeOffBundle with default data"
)]
class InitializeCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title("LieTimeOffBundle Initialization");

        // 1. Create default leave policy
        $io->section("Creating default leave policies...");
        $this->createPolicies($io);

        // 2. Create Liechtenstein holidays 2025
        $io->section("Creating Liechtenstein holidays for 2025...");
        $this->createHolidays2025($io);

        $io->success("Initialization completed successfully!");
        return Command::SUCCESS;
    }

    private function createPolicies(SymfonyStyle $io): void
    {
        $repoPolicy = $this->entityManager->getRepository(LeavePolicy::class);
        
        $policies = [
            [
                "name" => "Standard LI (Vollzeit)",
                "description" => "Gesetzlicher Mindestanspruch nach LI-Recht",
                "annual_days" => 25.0,
                "max_carryover" => 5.0,
                "is_default" => true,
            ],
            [
                "name" => "Jugendliche (bis 20 Jahre)",
                "description" => "Erhöhter Anspruch gemäß LI-Recht",
                "annual_days" => 30.0,
                "max_carryover" => 5.0,
                "is_default" => false,
            ],
            [
                "name" => "Teilzeit (50%)",
                "description" => "Anteiliger Urlaubsanspruch",
                "annual_days" => 12.5,
                "max_carryover" => 2.5,
                "is_default" => false,
            ],
        ];

        foreach ($policies as $policyData) {
            $existing = $repoPolicy->findOneBy(["name" => $policyData["name"]]);
            
            if ($existing) {
                $io->writeln("  • Policy existiert bereits: " . $policyData["name"]);
            } else {
                $policy = new LeavePolicy();
                $policy->setName($policyData["name"]);
                $policy->setDescription($policyData["description"]);
                $policy->setAnnualDays($policyData["annual_days"]);
                $policy->setMaxCarryover($policyData["max_carryover"]);
                $policy->setIsDefault($policyData["is_default"]);

                $this->entityManager->persist($policy);
                $io->writeln("  ✓ Created policy: " . $policyData["name"]);
            }
        }

        $this->entityManager->flush();
    }

    private function createHolidays2025(SymfonyStyle $io): void
    {
        $repoHoliday = $this->entityManager->getRepository(Holiday::class);
        
        $holidays = [
            ["2025-01-01", "Neujahr"],
            ["2025-01-06", "Heilige Drei Könige"],
            ["2025-02-02", "Mariä Lichtmess"],
            ["2025-03-19", "Josefstag"],
            ["2025-04-18", "Karfreitag"],
            ["2025-04-20", "Ostersonntag"],
            ["2025-04-21", "Ostermontag"],
            ["2025-05-01", "Tag der Arbeit"],
            ["2025-05-29", "Christi Himmelfahrt"],
            ["2025-06-08", "Pfingstsonntag"],
            ["2025-06-09", "Pfingstmontag"],
            ["2025-06-19", "Fronleichnam"],
            ["2025-08-15", "Mariä Himmelfahrt"],
            ["2025-09-08", "Mariä Geburt"],
            ["2025-11-01", "Allerheiligen"],
            ["2025-12-08", "Mariä Empfängnis"],
            ["2025-12-25", "Weihnachten"],
            ["2025-12-26", "Stephanstag"],
        ];

        foreach ($holidays as [$date, $name]) {
            $dateObj = new \DateTimeImmutable($date);
            $existing = $repoHoliday->findOneBy(["date" => $dateObj]);
            
            if ($existing) {
                $io->writeln("  • Holiday existiert bereits: $date - $name");
            } else {
                $holiday = new Holiday();
                $holiday->setDate($dateObj);
                $holiday->setName($name);
                $holiday->setType("public");

                $this->entityManager->persist($holiday);
                $io->writeln("  ✓ Created holiday: $date - $name");
            }
        }

        $this->entityManager->flush();
    }
}