<?php

namespace App\Repository;

use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Client>
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }


    public function fetchListRowsSearched(int $limit, ?string $q): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.projects', 'p')
            ->addSelect('c.id, c.name, c.city, c.country, c.contactPerson, c.contactEmail, c.contactPhone, c.adress, c.postalCode')
            ->addSelect('COUNT(DISTINCT p.id) AS projectsCount')
            ->groupBy('c.id')
            ->orderBy('c.name', 'DESC')
            ->setMaxResults($limit);

        if ($q) {
            $qb->andWhere('
            c.name LIKE :q OR
            c.city LIKE :q OR
            c.country LIKE :q OR
            c.contactPerson LIKE :q OR
            c.contactEmail LIKE :q
        ')->setParameter('q', '%'.$q.'%');
        }

        $rows = $qb->getQuery()->getArrayResult();
        if (!$rows) {
            return [];
        }

        // Fetch projects for the selected clients together, without limiting projects per client.
        $projects = $this->createQueryBuilder('c')
            ->select('c.id AS clientId, p.id AS id, p.name AS name, p.isArchived AS archived')
            ->innerJoin('c.projects', 'p')
            ->where('c.id IN (:ids)')
            ->setParameter('ids', array_column($rows, 'id'))
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getArrayResult();
        $byClient = [];
        foreach ($projects as $project) {
            $byClient[$project['clientId']][] = ['id' => $project['id'], 'name' => $project['name'], 'archived' => (bool) $project['archived']];
        }
        foreach ($rows as &$row) {
            $row['projects'] = $byClient[$row['id']] ?? [];
        }
        unset($row);

        return $rows;
    }

    /** @param string $key id (digits) OR exact email OR exact name (case-insensitive) */
    public function findOneByIdNameOrEmail(string $key): ?Client
    {
        // id?
        if (ctype_digit($key)) {
            $found = $this->find((int)$key);
            if ($found) return $found;
        }

        // Try exact (case-insensitive) email or name
        $qb = $this->createQueryBuilder('c')
            ->where('LOWER(c.contactEmail) = :k OR LOWER(c.name) = :k')
            ->setMaxResults(1)
            ->setParameter('k', mb_strtolower($key));

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Last updated / created clients for terminal previews.
     *
     * @return Client[]
     */
    public function findRecentForTerminal(int $limit = 5): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }


    //    /**
    //     * @return ClientFixtures[] Returns an array of ClientFixtures objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?ClientFixtures
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
