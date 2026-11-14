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
    public function fetchListRowsSearched(int $limit = 50, ?string $searchTerm = null, ?bool $isActive = true): array
    {
        $queryBuilder = $this->createQueryBuilder('user')
            ->select(
                'user.id AS id',
                'user.email AS email',
                "COALESCE(user.username, '') AS username",
                'user.roles AS roles',
                'user.isActive AS active',
            )
            ->orderBy('user.id', 'ASC')
            ->setMaxResults($limit);

        if ($searchTerm !== null && $searchTerm !== '') {
            $queryBuilder->andWhere('LOWER(user.email) LIKE :searchTerm OR LOWER(user.username) LIKE :searchTerm')
                ->setParameter('searchTerm', '%'.mb_strtolower($searchTerm).'%');
        }

        if ($isActive !== null) {
            $queryBuilder->andWhere('user.isActive = :isActive')->setParameter('isActive', $isActive);
        }

        $rows = $queryBuilder->getQuery()->getArrayResult();

        return $this->normalizeListRows($rows);
    }

    /**
     * Find by id OR exact email OR exact username (email/username are case-insensitive).
     */
    public function findOneByIdEmailOrUsername(string $idEmailOrUsername): ?User
    {
        if (ctype_digit($idEmailOrUsername)) {
            $user = $this->find((int) $idEmailOrUsername);
            if ($user instanceof User) {
                return $user;
            }
        }

        $key = mb_strtolower($idEmailOrUsername);

        // Exact email (case-insensitive)
        $queryBuilder = $this->createQueryBuilder('user')
            ->where('LOWER(user.email) = :lookupValue')
            ->setParameter('lookupValue', $key)
            ->setMaxResults(1);

        $user = $queryBuilder->getQuery()->getOneOrNullResult();
        if ($user instanceof User) {
            return $user;
        }

        // Exact username (case-insensitive)
        return $this->createQueryBuilder('user')
            ->where('LOWER(user.username) = :lookupValue')
            ->setParameter('lookupValue', $key)
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
            $user = $this->find((int) $idOrEmail);
            if ($user instanceof User) return $user;
        }

        $key = mb_strtolower($idOrEmail);

        return $this->createQueryBuilder('user')
            ->where('LOWER(user.email) = :lookupValue')
            ->setParameter('lookupValue', $key)
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
        foreach ($rows as &$userRow) {
            $userRow['id'] = (int) $userRow['id'];
            $userRow['email'] = trim((string) ($userRow['email'] ?? ''));
            $userRow['username'] = trim((string) ($userRow['username'] ?? ''));
            if (!is_array($userRow['roles'])) {
                $decoded = json_decode((string) $userRow['roles'], true);
                $userRow['roles'] = is_array($decoded) ? $decoded : [];
            }
            $userRow['roles'] = array_values(array_unique(array_map('strval', $userRow['roles'])));
            $userRow['active'] = (bool) ($userRow['active'] ?? false);
        }
        unset($userRow);

        /** @var array<int,array{id:int,email:string,username:string,roles:array<int,string>}> $rows */
        return $rows;
    }
}
