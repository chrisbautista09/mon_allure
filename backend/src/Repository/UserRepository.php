<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
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

    /** @return Paginator<User> */
    public function findAllPaginated(int $page = 1, int $perPage = 20): Paginator
    {
        return $this->paginate($this->administrationQuery(), $page, $perPage);
    }

    /** @return list<User> */
    public function findActiveUsers(): array
    {
        return $this->administrationQuery()
            ->andWhere('user.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    /** @return list<User> */
    public function findInactiveUsers(): array
    {
        return $this->administrationQuery()
            ->andWhere('user.isActive = :active')
            ->setParameter('active', false)
            ->getQuery()
            ->getResult();
    }

    /** @return Paginator<User> */
    public function searchUsers(
        string $search,
        int $page = 1,
        int $perPage = 20,
        string $status = 'all',
        string $role = 'all',
    ): Paginator
    {
        if (!in_array($status, ['all', 'active', 'inactive'], true)) {
            throw new \InvalidArgumentException('Le filtre de statut est invalide.');
        }

        if (!in_array($role, ['all', 'admin', 'user'], true)) {
            throw new \InvalidArgumentException('Le filtre de rôle est invalide.');
        }

        $search = trim($search);
        $query = $this->administrationQuery();

        if ($search !== '') {
            $query->andWhere('user.email LIKE :search OR user.pseudo LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        if ($status !== 'all') {
            $query->andWhere('user.isActive = :active')
                ->setParameter('active', $status === 'active');
        }

        if ($role === 'admin') {
            $query->andWhere('user.roles LIKE :adminRole')
                ->setParameter('adminRole', '%ROLE_ADMIN%');
        } elseif ($role === 'user') {
            $query->andWhere('user.roles NOT LIKE :adminRole')
                ->setParameter('adminRole', '%ROLE_ADMIN%');
        }

        return $this->paginate($query, $page, $perPage);
    }

    public function countAdministrators(bool $activeOnly = false): int
    {
        $query = $this->createQueryBuilder('user')
            ->select('COUNT(user.id)')
            ->andWhere('user.roles LIKE :adminRole')
            ->setParameter('adminRole', '%ROLE_ADMIN%');

        if ($activeOnly) {
            $query->andWhere('user.isActive = :active')->setParameter('active', true);
        }

        return (int) $query->getQuery()->getSingleScalarResult();
    }

    private function administrationQuery(): QueryBuilder
    {
        return $this->createQueryBuilder('user')
            ->orderBy('user.createdAt', 'DESC')
            ->addOrderBy('user.id', 'DESC');
    }

    /** @return Paginator<User> */
    private function paginate(QueryBuilder $query, int $page, int $perPage): Paginator
    {
        if ($page < 1) {
            throw new \InvalidArgumentException('Le numéro de page doit être supérieur ou égal à 1.');
        }

        if ($perPage < 1 || $perPage > 100) {
            throw new \InvalidArgumentException('Le nombre d’utilisateurs par page doit être compris entre 1 et 100.');
        }

        $query->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        return new Paginator($query->getQuery(), fetchJoinCollection: false);
    }
}
