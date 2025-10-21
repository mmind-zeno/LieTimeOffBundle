<?php
declare(strict_types=1);

namespace KimaiPlugin\LieTimeOffBundle\Command;

use KimaiPlugin\LieTimeOffBundle\Service\HolidayProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'timeoff:init-year', description: 'Listet LI-Feiertage für ein Jahr')]
final class InitializeYearCommand extends Command
{
    public function __construct(private HolidayProvider $holidays) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addArgument('year', InputArgument::REQUIRED, 'Jahr, z.B. 2026');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $year = (int)$input->getArgument('year');
        $list = $this->holidays->getHolidayList($year);
        $io->title('Liechtenstein-Feiertage '.$year);
        foreach ($list as $h) {
            $io->writeln(sprintf('%s  %s  (%s%s)',
                $h['formatted'], $h['name'], $h['type'], $h['is_paid'] ? ', paid' : ''
            ));
        }
        $io->success('Fertig.');
        return Command::SUCCESS;
    }
}
