<?php

namespace App\Repository;

use App\Entity\Todo;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Todo>
 */
class TodoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Todo::class);
    }

    /**
     * Get todos for projects where the user is assigned to a team.
     */
    public function findTodosByUserProjects(User $user, array $projects): array
    {
        return $this->createQueryBuilder('todo')
            ->leftJoin('todo.timelogs', 'timelog')
            ->select('todo, COALESCE(SUM(timelog.totalMinutes), 0) AS totalMinutesLogged')
            ->where('todo.project IN (:projects)')
            ->setParameter('projects', $projects)
            ->groupBy('todo.id')
            ->getQuery()
            ->getResult(\Doctrine\ORM\Query::HYDRATE_OBJECT);
    }
}
