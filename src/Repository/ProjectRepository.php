<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * Get projects where the user is assigned to a team.
     */
    public function findProjectsByUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.teams', 't')
            ->join('t.users', 'u') // Ensure the user is part of the team
            ->where('t IN (:teams)')
            ->andWhere('u = :user')
            ->setParameter('teams', $user->getTeams()->toArray()) // Convert collection to array
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }
}
