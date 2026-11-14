<?php

namespace App\Repository;

use App\Entity\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Team>
 */
class TeamRepository extends ServiceEntityRepository
{
    /** @return Team[] */
    public function findForTerminalList(?string $searchTerm, int $limit): array
    {
        $queryBuilder = $this->createQueryBuilder('team')
            ->leftJoin('team.users', 'user')->addSelect('user')
            ->leftJoin('team.projects', 'project')->addSelect('project')
            ->orderBy('team.name', 'ASC')
            ->setMaxResults($limit);

        if ($searchTerm) {
            if (ctype_digit($searchTerm)) {
                $queryBuilder->andWhere('team.id = :id OR LOWER(team.name) LIKE :searchTerm OR LOWER(project.name) LIKE :searchTerm')
                    ->setParameter('id', (int) $searchTerm)
                    ->setParameter('searchTerm', '%' . mb_strtolower($searchTerm) . '%');
            } else {
                $queryBuilder->andWhere('LOWER(team.name) LIKE :searchTerm OR LOWER(project.name) LIKE :searchTerm')
                    ->setParameter('searchTerm', '%' . mb_strtolower($searchTerm) . '%');
            }
        }

        return $queryBuilder->getQuery()->getResult();
    }

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Team::class);
    }


    /**
     * Last created/updated teams for terminal previews.
     *
     * @return Team[]
     */
    public function findRecentForTerminal(int $limit = 5): array
    {
        return $this->createQueryBuilder('team')
            ->orderBy('team.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Team[] Returns an array of Team objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Team
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
