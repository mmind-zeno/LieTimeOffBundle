<?php
declare(strict_types=1);

namespace KimaiPlugin\LieTimeOffBundle\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\LieTimeOffBundle\Entity\LeaveBalance;

final class LeaveBalanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LeaveBalance::class);
    }

    public function findByUserAndYear(User $user, int $year): ?LeaveBalance
    {
        return $this->findOneBy(["user" => $user, "year" => $year]);
    }

    public function findByYear(int $year): array
    {
        return $this->createQueryBuilder("b")
            ->leftJoin("b.user", "u")
            ->andWhere("b.year = :year")
            ->setParameter("year", $year)
            ->orderBy("u.username", "ASC")
            ->getQuery()->getResult();
    }
}