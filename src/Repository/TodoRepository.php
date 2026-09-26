<?php

namespace App\Repository;

use App\Entity\Todo;
use App\Entity\Project;
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

    /** @return Todo[] */
    public function findForTerminalList(?string $search = null, ?Project $project = null, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('todo')
            ->leftJoin('todo.project', 'p')->addSelect('p')
            ->orderBy('todo.name', 'ASC')
            ->addOrderBy('todo.id', 'ASC')
            ->setMaxResults($limit);

        if ($search !== null && $search !== '') {
            $condition = 'LOWER(todo.name) LIKE :q OR LOWER(p.name) LIKE :q';
            if (ctype_digit($search)) {
                $condition = 'todo.id = :id OR ' . $condition;
                $qb->setParameter('id', (int) $search);
            }
            $qb->andWhere($condition)->setParameter('q', '%' . mb_strtolower($search) . '%');
        }
        if ($project) {
            $qb->andWhere('todo.project = :project')->setParameter('project', $project);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return Todo[] */
    public function findAccessibleToUser(User $user): array
    {
        return $this->createQueryBuilder('todo')
            ->join('todo.project', 'p')
            ->join('p.teams', 'team')
            ->where('team IN (:teams)')
            ->setParameter('teams', $user->getTeams())
            ->getQuery()
            ->getResult();
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
