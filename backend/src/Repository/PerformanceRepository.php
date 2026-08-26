<?php

namespace App\Repository;

use App\Entity\Performance;
use App\Entity\Session;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Performance>
 */
class PerformanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Performance::class);
    }

    /** @return list<Performance> */
    public function findBySession(Session $session): array
    {
        return $this->findBy(['session' => $session], ['createdAt' => 'DESC']);
    }

    /** @return list<Performance> */
    public function findUserPerformances(User $user): array
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'DESC']);
    }

    /** @return list<Performance> */
    public function findRecentPerformances(User $user, int $limit = 10): array
    {
        if ($limit <= 0) {
            throw new \InvalidArgumentException('La limite doit être strictement positive.');
        }

        return $this->createQueryBuilder('performance')
            ->innerJoin('performance.session', 'session')
            ->addSelect('session')
            ->innerJoin('session.trainingPlan', 'plan')
            ->addSelect('plan')
            ->andWhere('performance.user = :user')
            ->setParameter('user', $user)
            ->orderBy('performance.createdAt', 'DESC')
            ->addOrderBy('performance.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findOneBySessionAndUser(Session $session, User $user): ?Performance
    {
        return $this->findOneBy(['session' => $session, 'user' => $user]);
    }
}
