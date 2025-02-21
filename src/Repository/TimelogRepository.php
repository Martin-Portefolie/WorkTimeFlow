<?php

namespace App\Repository;

use App\Entity\Timelog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Timelog>
 */
class TimelogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Timelog::class);
    }

    /**
     *  Get all time logs for a given user within a specific week and year.
     */
    public function findTimelogsByUserAndWeek(User $user, int $week, int $year): array
    {
        return $this->createQueryBuilder('timelog')
            ->join('timelog.todo', 'todo')
            ->join('todo.project', 'project')
            ->join('project.teams', 'team')
            ->where('team IN (:teams)')
            ->andWhere('WEEK(timelog.date) = :week')
            ->andWhere('YEAR(timelog.date) = :year')
            ->setParameter('teams', $user->getTeams())
            ->setParameter('week', $week)
            ->setParameter('year', $year)
            ->getQuery()
            ->getResult();
    }
}
