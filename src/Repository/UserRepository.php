<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Lightweight listing for terminal output (id, emails, username, roles),
     * with optional case-insensitive search on emails/username.
     *
     * @return array<int,array{id:int,emails:string,username:string,roles:array<int,string>}>
     */
    public function fetchListRowsSearched(int $limit = 50, ?string $q = null): array
    {
        $qb = $this->createQueryBuilder('u')
            ->select(
                'u.id AS id',
                'u.emails AS emails',
                // Normalize NULL -> '' at the DB layer
                "COALESCE(u.username, '') AS username",
                'u.roles AS roles'
            )
            ->orderBy('u.id', 'ASC')
            ->setMaxResults($limit);

        if ($q !== null && $q !== '') {
            $qb->andWhere('LOWER(u.emails) LIKE :q OR LOWER(u.username) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower($q).'%');
        }

        $rows = $qb->getQuery()->getArrayResult();

        return $this->normalizeListRows($rows);
    }

    /**
     * Find by id OR exact emails OR exact username (emails/username are case-insensitive).
     */
    public function findOneByIdEmailOrUsername(string $idEmailOrUsername): ?User
    {
        // Numeric → treat as id
        if (ctype_digit($idEmailOrUsername)) {
            $u = $this->find((int) $idEmailOrUsername);
            if ($u instanceof User) {
                return $u;
            }
        }

        $key = mb_strtolower($idEmailOrUsername);

        // Exact emails (case-insensitive)
        $qb = $this->createQueryBuilder('u')
            ->where('LOWER(u.emails) = :k')
            ->setParameter('k', $key)
            ->setMaxResults(1);

        $u = $qb->getQuery()->getOneOrNullResult();
        if ($u instanceof User) {
            return $u;
        }

        // Exact username (case-insensitive)
        $qb2 = $this->createQueryBuilder('u')
            ->where('LOWER(u.username) = :k')
            ->setParameter('k', $key)
            ->setMaxResults(1);

        return $qb2->getQuery()->getOneOrNullResult();
    }


    /**
     * Resolve a user by ID (numeric string ok) OR emails (case-insensitive).
     */
    public function findOneByIdOrEmailInsensitive(string $idOrEmail): ?User
    {
        // Treat digits-only as ID
        if (ctype_digit($idOrEmail)) {
            $u = $this->find((int) $idOrEmail);
            if ($u instanceof User) return $u;
            // fall-through to emails check just in case
        }

        $key = mb_strtolower($idOrEmail);

        return $this->createQueryBuilder('u')
            ->where('LOWER(u.emails) = :k')
            ->setParameter('k', $key)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }


    /**
     * Normalize list-row shapes so callers always get strict scalars:
     * - id: int
     * - emails: string
     * - username: string (never null)
     * - roles: string[]
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array{id:int,emails:string,username:string,roles:array<int,string>}>
     */
    private function normalizeListRows(array $rows): array
    {
        foreach ($rows as &$r) {
            // id
            $r['id'] = (int) $r['id'];

            // emails
            $r['emails'] = trim((string) $r['emails']);

            // username (COALESCE already ensured '', but normalize anyway)
            $r['username'] = trim((string) ($r['username'] ?? ''));

            // roles: ensure array<string>
            if (!is_array($r['roles'])) {
                $decoded = json_decode((string) $r['roles'], true);
                $r['roles'] = is_array($decoded) ? $decoded : [];
            }
            $r['roles'] = array_values(array_unique(array_map('strval', $r['roles'])));
        }
        unset($r);

        /** @var array<int,array{id:int,emails:string,username:string,roles:array<int,string>}> $rows */
        return $rows;
    }



    //    /**
    //     * @return User[] Returns an array of User objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?User
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
