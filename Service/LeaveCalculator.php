<?php
declare(strict_types=1);

namespace KimaiPlugin\LieTimeOffBundle\Service;

final class LeaveCalculator
{
    public function __construct(private HolidayProvider $holidays) {}

    public function countVacationDays(\DateTimeInterface $from, \DateTimeInterface $to, int $workweek): float
    {
        $days = 0.0;
        $cur = \DateTimeImmutable::createFromInterface($from)->setTime(0,0);
        $end = \DateTimeImmutable::createFromInterface($to)->setTime(0,0);

        while ($cur <= $end) {
            $dow = (int)$cur->format('N'); // 1..7
            $isWorkday = $workweek === 5 ? ($dow >=1 && $dow <=5) : ($dow >=1 && $dow <=6);
            if ($isWorkday && !$this->holidays->isHoliday($cur)) {
                $days += 1.0;
            }
            $cur = $cur->modify('+1 day');
        }
        return $days;
    }
}
