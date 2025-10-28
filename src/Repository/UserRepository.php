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
     * Lightweight listing for terminal output (id, email, username, roles),
     * with optional case-insensitive search on email/username.
     *
     * @return array<int,array{id:int,email:string,username:string,roles:array<int,string>}>
     */
    public function fetchListRowsSearched(int $limit = 50, ?string $q = null, ?bool $isActive = true): array
    {
        $qb = $this->createQueryBuilder('u')
            ->select(
                'u.id AS id',
                'u.email AS email',
                "COALESCE(u.username, '') AS username",
                'u.roles AS roles',
                'u.isActive AS active',
            )
            ->orderBy('u.id', 'ASC')
            ->setMaxResults($limit);

        if ($q !== null && $q !== '') {
            $qb->andWhere('LOWER(u.email) LIKE :q OR LOWER(u.username) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower($q).'%');
        }

        if ($isActive !== null) {
            $qb->andWhere('u.isActive = :act')->setParameter('act', $isActive);
        }

        $rows = $qb->getQuery()->getArrayResult();

        return $this->normalizeListRows($rows);
    }

    /**
     * Find by id OR exact email OR exact username (email/username are case-insensitive).
     */
    public function findOneByIdEmailOrUsername(string $idEmailOrUsername): ?User
    {
        if (ctype_digit($idEmailOrUsername)) {
            $u = $this->find((int) $idEmailOrUsername);
            if ($u instanceof User) {
                return $u;
            }
        }

        $key = mb_strtolower($idEmailOrUsername);

        // Exact email (case-insensitive)
        $qb = $this->createQueryBuilder('u')
            ->where('LOWER(u.email) = :k')
            ->setParameter('k', $key)
            ->setMaxResults(1);

        $u = $qb->getQuery()->getOneOrNullResult();
        if ($u instanceof User) {
            return $u;
        }

        // Exact username (case-insensitive)
        return $this->createQueryBuilder('u')
            ->where('LOWER(u.username) = :k')
            ->setParameter('k', $key)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Resolve a user by ID (numeric string ok) OR email (case-insensitive).
     */
    public function findOneByIdOrEmailInsensitive(string $idOrEmail): ?User
    {
        if (ctype_digit($idOrEmail)) {
            $u = $this->find((int) $idOrEmail);
            if ($u instanceof User) return $u;
        }

        $key = mb_strtolower($idOrEmail);

        return $this->createQueryBuilder('u')
            ->where('LOWER(u.email) = :k')
            ->setParameter('k', $key)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Normalize list-row shapes so callers always get strict scalars:
     * - id: int
     * - email: string
     * - username: string (never null)
     * - roles: string[]
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array{id:int,email:string,username:string,roles:array<int,string>}>
     */
    private function normalizeListRows(array $rows): array
    {
        foreach ($rows as &$r) {
            $r['id'] = (int) $r['id'];
            $r['email'] = trim((string) ($r['email'] ?? ''));
            $r['username'] = trim((string) ($r['username'] ?? ''));
            if (!is_array($r['roles'])) {
                $decoded = json_decode((string) $r['roles'], true);
                $r['roles'] = is_array($decoded) ? $decoded : [];
            }
            $r['roles'] = array_values(array_unique(array_map('strval', $r['roles'])));
            $r['active'] = (bool) ($r['active'] ?? false);
        }
        unset($r);

        /** @var array<int,array{id:int,email:string,username:string,roles:array<int,string>}> $rows */
        return $rows;
    }
}
