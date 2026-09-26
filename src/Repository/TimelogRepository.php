<?php

namespace App\Repository;

use App\Entity\Timelog;
use App\Entity\Todo;
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
     * Return admin list results with optional relation/date filters applied in one query.
     *
     * @return Timelog[]
     */
    public function findForTerminalList(
        ?User $user = null,
        ?Todo $todo = null,
        ?int $projectId = null,
        ?\DateTimeInterface $date = null,
        int $limit = 50,
    ): array {
        $qb = $this->createQueryBuilder('log')
            ->leftJoin('log.user', 'u')->addSelect('u')
            ->leftJoin('log.todo', 'todo')->addSelect('todo')
            ->leftJoin('todo.project', 'p')->addSelect('p')
            ->orderBy('log.date', 'DESC')
            ->addOrderBy('log.id', 'DESC')
            ->setMaxResults($limit);

        if ($user) {
            $qb->andWhere('log.user = :user')->setParameter('user', $user);
        }
        if ($todo) {
            $qb->andWhere('log.todo = :todo')->setParameter('todo', $todo);
        }
        if ($projectId !== null) {
            $qb->andWhere('p.id = :project')->setParameter('project', $projectId);
        }
        if ($date) {
            $end = (clone $date)->modify('+1 day');
            $qb->andWhere('log.date >= :start AND log.date < :end')
                ->setParameter('start', $date)
                ->setParameter('end', $end);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return Timelog[] */
    public function findForUserTodoOnDate(User $user, Todo $todo, \DateTimeInterface $date): array
    {
        $start = (clone $date)->setTime(0, 0, 0);
        $end = (clone $start)->modify('+1 day');

        return $this->createQueryBuilder('log')
            ->where('log.user = :user')
            ->andWhere('log.todo = :todo')
            ->andWhere('log.date >= :start AND log.date < :end')
            ->setParameter('user', $user)
            ->setParameter('todo', $todo)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();
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
        $startOfWeek->setISODate($year, $week)->setTime(0, 0, 0);

        $endOfWeek = clone $startOfWeek;
        $endOfWeek->modify('+6 days')->setTime(23, 59, 59);

        return $this->createQueryBuilder('timelog')
            ->join('timelog.todo', 'todo')
            ->join('todo.project', 'project')
            ->join('project.teams', 'team')
            ->where('team IN (:teams)')
            ->andWhere('timelog.user = :user')
            ->andWhere('timelog.date BETWEEN :startOfWeek AND :endOfWeek')
            ->setParameter('teams', $user->getTeams())
            ->setParameter('user', $user)
            ->setParameter('startOfWeek', $startOfWeek)
            ->setParameter('endOfWeek', $endOfWeek)
            ->getQuery()
            ->getResult();
    }
}
