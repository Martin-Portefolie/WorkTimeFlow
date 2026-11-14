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
    /** @return Project[] */
    public function findForTerminalList(?string $searchTerm, int $limit, string $archivedFlag, ?\App\Entity\Client $client = null): array
    {
        $queryBuilder = $this->createQueryBuilder('project')
            ->leftJoin('project.client', 'client')->addSelect('client')
            ->leftJoin('project.teams', 'team')->addSelect('team')
            ->leftJoin('project.rate', 'rate')->addSelect('rate')
            ->orderBy('project.lastUpdated', 'DESC')
            ->setMaxResults($limit);

        if ($archivedFlag === 'active') {
            $queryBuilder->andWhere('project.isArchived = :archived')->setParameter('archived', false);
        }

        if ($archivedFlag === 'archived') {
            $queryBuilder->andWhere('project.isArchived = :archived')->setParameter('archived', true);
        }

        if ($searchTerm) {
            if (ctype_digit($searchTerm)) {
                $queryBuilder->andWhere('project.id = :id OR LOWER(project.name) LIKE :searchTerm OR LOWER(client.name) LIKE :searchTerm')
                    ->setParameter('id', (int) $searchTerm)
                    ->setParameter('searchTerm', '%' . mb_strtolower($searchTerm) . '%');
            } else {
                $queryBuilder->andWhere('LOWER(project.name) LIKE :searchTerm OR LOWER(client.name) LIKE :searchTerm')
                    ->setParameter('searchTerm', '%' . mb_strtolower($searchTerm) . '%');
            }
        }

        if ($client !== null) {
            $queryBuilder->andWhere('project.client = :client')->setParameter('client', $client);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * Get projects where the user is assigned to a team.
     */
    public function findProjectsByUser(User $user): array
    {
        return $this->createQueryBuilder('project')
            ->join('project.teams', 'team')
            ->join('team.users', 'user') // Ensure the user is part of the team
            ->where('team IN (:teams)')
            ->andWhere('user = :user')
            ->setParameter('teams', $user->getTeams()->toArray()) // Convert collection to array
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }
}
