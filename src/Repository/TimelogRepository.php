<?php

namespace App\Repository;

use App\Entity\Timelog;
use App\Entity\User;
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
//    public function findTimelogsByUserAndWeek(User $user, int $week, int $year): array
//    {
//        return $this->createQueryBuilder('timelog')
//            ->join('timelog.todo', 'todo')
//            ->join('todo.project', 'project')
//            ->join('project.teams', 'team')
//            ->where('team IN (:teams)')
//            ->andWhere('WEEK(timelog.date) = :week')
//            ->andWhere('YEAR(timelog.date) = :year')
//            ->setParameter('teams', $user->getTeams())
//            ->setParameter('week', $week)
//            ->setParameter('year', $year)
//            ->getQuery()
//            ->getResult();
//    }

    public function findTimelogsByUserAndWeek(User $user, int $week, int $year): array
    {
        $startOfWeek = new \DateTime();
        $startOfWeek->setISODate($year, $week)->setTime(0, 0, 0); // ✅ First day of the ISO week

        $endOfWeek = clone $startOfWeek;
        $endOfWeek->modify('+6 days')->setTime(23, 59, 59); // ✅ Last day of the week

        return $this->createQueryBuilder('timelog')
            ->join('timelog.todo', 'todo')
            ->join('todo.project', 'project')
            ->join('project.teams', 'team')
            ->where('team IN (:teams)')
            ->andWhere('timelog.date BETWEEN :startOfWeek AND :endOfWeek') // ✅ Works on all databases
            ->setParameter('teams', $user->getTeams())
            ->setParameter('startOfWeek', $startOfWeek)
            ->setParameter('endOfWeek', $endOfWeek)
            ->getQuery()
            ->getResult();
    }


}
