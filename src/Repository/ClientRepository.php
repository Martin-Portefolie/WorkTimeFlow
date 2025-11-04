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
            ->select('c.id','c.name','c.contactEmail','c.city','c.country')
            ->orderBy('c.id','ASC')
            ->setMaxResults($limit);

        if ($q) {
            $qb->andWhere('LOWER(c.name) LIKE :q OR LOWER(c.contactEmail) LIKE :q OR LOWER(c.city) LIKE :q OR LOWER(c.country) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower($q).'%');
        }

        return array_map(
            static fn ($r) => [
                'id' => (int)$r['id'],
                'name' => (string)$r['name'],
                'contactEmail' => $r['contactEmail'] ?? null,
                'city' => $r['city'] ?? null,
                'country' => $r['country'] ?? null,
            ],
            $qb->getQuery()->getArrayResult()
        );
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
