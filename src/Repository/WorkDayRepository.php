<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\WorkDay;
use App\Time\WeekReference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkDay>
 */
class WorkDayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkDay::class);
    }

    /**
     * The five working days of a week for a user. Days without a row in the
     * database are returned as fresh, unmanaged WorkDay instances.
     *
     * @return list<WorkDay> Monday … Friday
     */
    public function findWeek(User $user, WeekReference $week): array
    {
        /** @var list<WorkDay> $rows */
        $rows = $this->createQueryBuilder('d')
            ->andWhere('d.user = :user')
            ->andWhere('d.date BETWEEN :from AND :to')
            ->setParameter('user', $user)
            ->setParameter('from', $week->monday(), Types::DATE_IMMUTABLE)
            ->setParameter('to', $week->friday(), Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getResult();

        $byDate = [];
        foreach ($rows as $row) {
            $byDate[$row->getDate()->format('Y-m-d')] = $row;
        }

        $days = [];
        foreach ($week->workingDays() as $date) {
            $days[] = $byDate[$date->format('Y-m-d')] ?? new WorkDay($user, $date);
        }

        return $days;
    }

    public function deleteAllForUser(User $user): int
    {
        return $this->createQueryBuilder('d')
            ->delete()
            ->andWhere('d.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
