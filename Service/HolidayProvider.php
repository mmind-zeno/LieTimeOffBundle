<?php
declare(strict_types=1);

namespace KimaiPlugin\LieTimeOffBundle\Service;

final class HolidayProvider
{
    public function isHoliday(\DateTimeInterface $date): bool
    {
        $list = $this->getLiechtensteinHolidays((int)$date->format('Y'));
        return isset($list[$date->format('Y-m-d')]);
    }

    /** @return array<string, array{name:string,type:string,is_paid:bool}> */
    public function getLiechtensteinHolidays(int $year): array
    {
        $holidays = [];
        $fixed = [
            '01-01' => ['name'=>'Neujahr','type'=>'national','is_paid'=>true],
            '01-06' => ['name'=>'Heilige Drei Könige','type'=>'national','is_paid'=>true],
            '02-02' => ['name'=>'Mariä Lichtmess','type'=>'national','is_paid'=>true],
            '03-19' => ['name'=>'Josefstag','type'=>'national','is_paid'=>true],
            '05-01' => ['name'=>'Tag der Arbeit','type'=>'national','is_paid'=>true],
            '08-15' => ['name'=>'Staatsfeiertag (Maria Himmelfahrt)','type'=>'national','is_paid'=>true],
            '09-08' => ['name'=>'Mariä Geburt','type'=>'national','is_paid'=>true],
            '11-01' => ['name'=>'Allerheiligen','type'=>'national','is_paid'=>true],
            '12-08' => ['name'=>'Mariä Empfängnis','type'=>'national','is_paid'=>true],
            '12-24' => ['name'=>'Heiligabend','type'=>'optional','is_paid'=>false],
            '12-25' => ['name'=>'Weihnachten','type'=>'national','is_paid'=>true],
            '12-26' => ['name'=>'Stephanstag','type'=>'national','is_paid'=>true],
            '12-31' => ['name'=>'Silvester','type'=>'optional','is_paid'=>false],
        ];
        foreach ($fixed as $md => $info) {
            $holidays[sprintf('%d-%s',$year,$md)] = $info;
        }

        $easter = $this->getEasterDate($year);
        $mov = [
            -2 => ['name'=>'Karfreitag','type'=>'national','is_paid'=>true],
            0  => ['name'=>'Ostersonntag','type'=>'national','is_paid'=>true],
            1  => ['name'=>'Ostermontag','type'=>'national','is_paid'=>true],
            39 => ['name'=>'Christi Himmelfahrt','type'=>'national','is_paid'=>true],
            49 => ['name'=>'Pfingstsonntag','type'=>'national','is_paid'=>true],
            50 => ['name'=>'Pfingstmontag','type'=>'national','is_paid'=>true],
            60 => ['name'=>'Fronleichnam','type'=>'national','is_paid'=>true],
        ];
        foreach ($mov as $off => $info) {
            $date = $easter->modify(($off >= 0 ? '+' : '') . $off . ' days');
            $holidays[$date->format('Y-m-d')] = $info;
        }
        return $holidays;
    }

    public function getHolidayList(int $year): array
    {
        $h = $this->getLiechtensteinHolidays($year);
        ksort($h);
        $out=[];
        foreach ($h as $d=>$i) {
            $out[] = ['date'=>$d,'name'=>$i['name'],'type'=>$i['type'],'is_paid'=>$i['is_paid'],'formatted'=>(new \DateTimeImmutable($d))->format('d.m.Y')];
        }
        return $out;
    }

    private function getEasterDate(int $year): \DateTimeImmutable
    {
        if (function_exists('easter_days')) {
            $base = new \DateTimeImmutable("$year-03-21");
            return $base->modify('+' . easter_days($year) . ' days');
        }
        // Fallback (Butcher)
        $a=$year%19; $b=intdiv($year,100); $c=$year%100;
        $d=intdiv($b,4); $e=$b%4; $f=intdiv($b+8,25);
        $g=intdiv($b-$f+1,3); $h=(19*$a+$b-$d-$g+15)%30;
        $i=intdiv($c,4); $k=$c%4; $l=(32+2*$e+2*$i-$h-$k)%7;
        $m=intdiv($a+11*$h+22*$l,451);
        $month=intdiv($h+$l-7*$m+114,31); $day=(($h+$l-7*$m+114)%31)+1;
        return new \DateTimeImmutable(sprintf('%d-%02d-%02d', $year, $month, $day));
    }
}
