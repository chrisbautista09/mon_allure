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

    public function findOneBySessionAndUser(Session $session, User $user): ?Performance
    {
        return $this->findOneBy(['session' => $session, 'user' => $user]);
    }
}
