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


    public function fetchListRowsSearched(int $limit, ?string $searchTerm): array
    {
        $queryBuilder = $this->createQueryBuilder('client')
            ->leftJoin('client.projects', 'project')
            ->addSelect('client.id, client.name, client.city, client.country, client.contactPerson, client.contactEmail, client.contactPhone, client.adress, client.postalCode')
            ->addSelect('COUNT(DISTINCT project.id) AS projectsCount')
            ->groupBy('client.id')
            ->orderBy('client.name', 'DESC')
            ->setMaxResults($limit);

        if ($searchTerm) {
            $queryBuilder->andWhere('
            client.name LIKE :searchTerm OR
            client.city LIKE :searchTerm OR
            client.country LIKE :searchTerm OR
            client.contactPerson LIKE :searchTerm OR
            client.contactEmail LIKE :searchTerm
        ')->setParameter('searchTerm', '%'.$searchTerm.'%');
        }

        $rows = $queryBuilder->getQuery()->getArrayResult();
        if (!$rows) {
            return [];
        }

        // Fetch projects for the selected clients together, without limiting projects per client.
        $projects = $this->createQueryBuilder('client')
            ->select('client.id AS clientId, project.id AS id, project.name AS name, project.isArchived AS archived')
            ->innerJoin('client.projects', 'project')
            ->where('client.id IN (:ids)')
            ->setParameter('ids', array_column($rows, 'id'))
            ->orderBy('project.name', 'ASC')
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
        $queryBuilder = $this->createQueryBuilder('client')
            ->where('LOWER(client.contactEmail) = :lookupValue OR LOWER(client.name) = :lookupValue')
            ->setMaxResults(1)
            ->setParameter('lookupValue', mb_strtolower($key));

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    /**
     * Last updated / created clients for terminal previews.
     *
     * @return Client[]
     */
    public function findRecentForTerminal(int $limit = 5): array
    {
        return $this->createQueryBuilder('client')
            ->orderBy('client.id', 'DESC')
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
